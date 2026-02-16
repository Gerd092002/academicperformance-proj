<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
check_login();

// Only teachers can access this page
if ($_SESSION['user']['role_id'] != 2) {
    header('Location: login.php');
    exit;
}

$teacher_id = $_SESSION['user']['id'];
$error = $success = '';
$current_username = $_SESSION['user']['username'];

// Fetch current teacher information
$stmt = $pdo->prepare("SELECT username, full_name, address, phone, email, created_at FROM users WHERE id = ?");
$stmt->execute([$teacher_id]);
$teacher = $stmt->fetch();

if (!$teacher) {
    header('Location: logout.php');
    exit;
}

// Initialize variables with current data
$username = $teacher['username'];
$full_name = $teacher['full_name'];
$address = $teacher['address'];
$phone = $teacher['phone'];
$email = $teacher['email'];
$created_at = $teacher['created_at'];

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $username = trim($_POST['username']);
        $full_name = trim($_POST['full_name']);
        $address = trim($_POST['address']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        
        // Validation
        if (empty($username) || empty($email)) {
            $error = "Username and email are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } elseif (strlen($username) < 3) {
            $error = "Username must be at least 3 characters.";
        } elseif (strlen($full_name) > 200) {
            $error = "Full name must be less than 200 characters.";
        } elseif (strlen($address) > 100) {
            $error = "Address must be less than 100 characters.";
        } elseif (!empty($phone) && !preg_match('/^[0-9]{11}$/', $phone)) {
            $error = "Phone number must be 11 digits.";
        } else {
            try {
                // Check if username already exists (excluding current user)
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $stmt->execute([$username, $teacher_id]);
                if ($stmt->fetch()) {
                    $error = "Username already exists. Please choose another.";
                } else {
                    // Check if email already exists (excluding current user)
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $teacher_id]);
                    if ($stmt->fetch()) {
                        $error = "Email already exists. Please use another email.";
                    } else {
                        // Update profile
                        $stmt = $pdo->prepare("
                            UPDATE users 
                            SET username = ?, full_name = ?, address = ?, phone = ?, email = ? 
                            WHERE id = ?
                        ");
                        $stmt->execute([$username, $full_name, $address, $phone, $email, $teacher_id]);
                        
                        // Update session data
                        $_SESSION['user']['username'] = $username;
                        $_SESSION['user']['email'] = $email;
                        $_SESSION['user']['full_name'] = $full_name;
                        
                        $success = "Profile updated successfully!";
                    }
                }
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
    
    // Handle password change
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validation
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = "All password fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $error = "New password and confirmation do not match.";
        } elseif (strlen($new_password) < 6) {
            $error = "New password must be at least 6 characters.";
        } else {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$teacher_id]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($current_password, $user['password_hash'])) {
                // Update password
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$new_password_hash, $teacher_id]);
                
                $success = "Password changed successfully!";
            } else {
                $error = "Current password is incorrect.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Profile - Edit Information</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        
        * {
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .btn-glow {
            position: relative;
            overflow: hidden;
            transition: all 0.4s ease;
        }
        
        .btn-glow:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: 4px solid white;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .info-item {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        
        .info-item:hover {
            border-left-color: #667eea;
            background: linear-gradient(to right, #f8fafc, #f1f5f9);
        }
        
        .input-group {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
        }
        
        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6b7280;
        }
        
        .password-toggle:hover {
            color: #4f46e5;
        }
        
        .tab-button {
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
        }
        
        .tab-button.active {
            border-bottom-color: #4f46e5;
            color: #4f46e5;
            font-weight: 600;
        }
        
        .stats-card {
            transition: all 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="p-4 md:p-6">
    <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div class="flex items-center space-x-3">
                <a href="teacher_dashboard.php" 
                   class="text-gray-700 hover:text-indigo-700 transition">
                    <div class="bg-white p-2 rounded-lg shadow-sm hover-lift">
                        <i class="fas fa-arrow-left text-lg"></i>
                    </div>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-white">Teacher Profile</h1>
                    <p class="text-gray-200">Manage your account information and settings</p>
                </div>
            </div>
            
            <div class="flex items-center space-x-4">
                <div class="hidden md:block text-right">
                    <p class="font-medium text-white"><?= htmlspecialchars($current_username) ?></p>
                    <p class="text-gray-200 text-sm">Teacher</p>
                </div>
                <div class="w-10 h-10 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-full flex items-center justify-center text-white font-bold shadow-lg">
                    <?= strtoupper(substr(htmlspecialchars($current_username), 0, 1)) ?>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column: Profile Overview -->
            <div class="lg:col-span-1">
                <!-- Profile Card -->
                <div class="glass-card rounded-2xl shadow-2xl overflow-hidden mb-6">
                    <!-- Profile Header -->
                    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-8 text-center">
                        <div class="flex flex-col items-center">
                            <div class="profile-avatar rounded-full flex items-center justify-center text-white text-4xl font-bold mb-4">
                                <?= strtoupper(substr(htmlspecialchars($username), 0, 1)) ?>
                            </div>
                            <h2 class="text-2xl font-bold text-white"><?= htmlspecialchars($full_name ?: $username) ?></h2>
                            <p class="text-indigo-200">Teacher</p>
                            <div class="mt-2 bg-white/20 px-4 py-1 rounded-full">
                                <span class="text-white text-sm">ID: <?= $teacher_id ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Info -->
                    <div class="p-6">
                        <div class="space-y-4">
                            <div class="info-item p-4 rounded-xl">
                                <div class="flex items-center mb-2">
                                    <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-user text-indigo-600"></i>
                                    </div>
                                    <h3 class="font-semibold text-gray-800">Username</h3>
                                </div>
                                <p class="text-gray-600 pl-11"><?= htmlspecialchars($username) ?></p>
                            </div>
                            
                            <div class="info-item p-4 rounded-xl">
                                <div class="flex items-center mb-2">
                                    <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-envelope text-green-600"></i>
                                    </div>
                                    <h3 class="font-semibold text-gray-800">Email</h3>
                                </div>
                                <p class="text-gray-600 pl-11"><?= htmlspecialchars($email) ?></p>
                            </div>
                            
                            <?php if($full_name): ?>
                            <div class="info-item p-4 rounded-xl">
                                <div class="flex items-center mb-2">
                                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-id-card text-blue-600"></i>
                                    </div>
                                    <h3 class="font-semibold text-gray-800">Full Name</h3>
                                </div>
                                <p class="text-gray-600 pl-11"><?= htmlspecialchars($full_name) ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if($phone): ?>
                            <div class="info-item p-4 rounded-xl">
                                <div class="flex items-center mb-2">
                                    <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-phone text-yellow-600"></i>
                                    </div>
                                    <h3 class="font-semibold text-gray-800">Phone</h3>
                                </div>
                                <p class="text-gray-600 pl-11"><?= htmlspecialchars($phone) ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if($address): ?>
                            <div class="info-item p-4 rounded-xl">
                                <div class="flex items-center mb-2">
                                    <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-map-marker-alt text-purple-600"></i>
                                    </div>
                                    <h3 class="font-semibold text-gray-800">Address</h3>
                                </div>
                                <p class="text-gray-600 pl-11"><?= htmlspecialchars($address) ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <div class="info-item p-4 rounded-xl">
                                <div class="flex items-center mb-2">
                                    <div class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-calendar-alt text-gray-600"></i>
                                    </div>
                                    <h3 class="font-semibold text-gray-800">Member Since</h3>
                                </div>
                                <p class="text-gray-600 pl-11"><?= date('F j, Y', strtotime($created_at)) ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="glass-card rounded-2xl shadow-lg p-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">Account Status</h3>
                    <div class="space-y-4">
                        <div class="stats-card bg-gradient-to-r from-green-50 to-emerald-50 p-4 rounded-xl border border-green-100">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-gray-600 text-sm">Account Type</p>
                                    <p class="text-xl font-bold text-gray-800">Teacher</p>
                                </div>
                                <div class="bg-green-100 p-3 rounded-lg">
                                    <i class="fas fa-chalkboard-teacher text-green-600 text-xl"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="stats-card bg-gradient-to-r from-blue-50 to-cyan-50 p-4 rounded-xl border border-blue-100">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-gray-600 text-sm">Account Created</p>
                                    <p class="text-xl font-bold text-gray-800"><?= date('M Y', strtotime($created_at)) ?></p>
                                </div>
                                <div class="bg-blue-100 p-3 rounded-lg">
                                    <i class="fas fa-user-check text-blue-600 text-xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Edit Forms -->
            <div class="lg:col-span-2">
                <!-- Messages -->
                <?php if (!empty($error)): ?>
                    <div class="glass-card rounded-2xl shadow-lg mb-6">
                        <div class="p-4 rounded-xl border-l-4 border-red-500 bg-gradient-to-r from-red-50 to-red-100 text-red-800 flex justify-between items-center">
                            <div class="flex items-center space-x-3">
                                <div class="bg-red-500 p-2 rounded-full">
                                    <i class="fas fa-exclamation-circle text-white"></i>
                                </div>
                                <div>
                                    <p class="font-semibold">Error</p>
                                    <p><?= htmlspecialchars($error) ?></p>
                                </div>
                            </div>
                            <button onclick="this.parentElement.remove()" class="text-red-600 hover:text-red-800 transition">
                                <i class="fas fa-times text-lg"></i>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="glass-card rounded-2xl shadow-lg mb-6">
                        <div class="p-4 rounded-xl border-l-4 border-green-500 bg-gradient-to-r from-green-50 to-green-100 text-green-800 flex justify-between items-center">
                            <div class="flex items-center space-x-3">
                                <div class="bg-green-500 p-2 rounded-full">
                                    <i class="fas fa-check-circle text-white"></i>
                                </div>
                                <div>
                                    <p class="font-semibold">Success!</p>
                                    <p><?= htmlspecialchars($success) ?></p>
                                </div>
                            </div>
                            <button onclick="this.parentElement.remove()" class="text-green-600 hover:text-green-800 transition">
                                <i class="fas fa-times text-lg"></i>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Tabs Navigation -->
                <div class="glass-card rounded-2xl shadow-lg mb-6">
                    <div class="border-b border-gray-200">
                        <nav class="flex space-x-8 px-6">
                            <button id="profile-tab" class="tab-button py-4 text-lg font-medium active" onclick="switchTab('profile')">
                                <i class="fas fa-user-edit mr-2"></i>
                                Edit Profile
                            </button>
                            <button id="password-tab" class="tab-button py-4 text-lg font-medium" onclick="switchTab('password')">
                                <i class="fas fa-key mr-2"></i>
                                Change Password
                            </button>
                        </nav>
                    </div>
                </div>

                <!-- Profile Edit Form -->
                <div id="profile-content" class="tab-content">
                    <div class="glass-card rounded-2xl shadow-2xl overflow-hidden mb-6">
                        <!-- Form Header -->
                        <div class="bg-gradient-to-r from-blue-500 to-cyan-500 px-6 py-5">
                            <div class="flex items-center space-x-3">
                                <div class="bg-white p-3 rounded-xl">
                                    <i class="fas fa-user-edit text-blue-600 text-xl"></i>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold text-white">Edit Profile Information</h2>
                                    <p class="text-blue-100">Update your personal details</p>
                                </div>
                            </div>
                        </div>

                        <!-- Profile Form -->
                        <form method="POST" class="p-6">
                            <input type="hidden" name="update_profile" value="1">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <!-- Username -->
                                <div>
                                    <label class="block mb-2 text-gray-700 font-semibold">
                                        <i class="fas fa-user mr-2 text-blue-600"></i>
                                        Username *
                                    </label>
                                    <div class="input-group">
                                        <span class="input-icon">
                                            <i class="fas fa-user"></i>
                                        </span>
                                        <input type="text" name="username" required 
                                               value="<?= htmlspecialchars($username) ?>"
                                               class="w-full pl-10 pr-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-300 focus:border-transparent transition">
                                    </div>
                                    <p class="text-gray-500 text-sm mt-2">Your unique username for login</p>
                                </div>

                                <!-- Email -->
                                <div>
                                    <label class="block mb-2 text-gray-700 font-semibold">
                                        <i class="fas fa-envelope mr-2 text-blue-600"></i>
                                        Email Address *
                                    </label>
                                    <div class="input-group">
                                        <span class="input-icon">
                                            <i class="fas fa-envelope"></i>
                                        </span>
                                        <input type="email" name="email" required 
                                               value="<?= htmlspecialchars($email) ?>"
                                               class="w-full pl-10 pr-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-300 focus:border-transparent transition">
                                    </div>
                                    <p class="text-gray-500 text-sm mt-2">Your contact email address</p>
                                </div>
                            </div>

                            <!-- Full Name -->
                            <div class="mb-6">
                                <label class="block mb-2 text-gray-700 font-semibold">
                                    <i class="fas fa-id-card mr-2 text-blue-600"></i>
                                    Full Name
                                </label>
                                <div class="input-group">
                                    <span class="input-icon">
                                        <i class="fas fa-user-tag"></i>
                                    </span>
                                    <input type="text" name="full_name" 
                                           value="<?= htmlspecialchars($full_name) ?>"
                                           placeholder="Enter your full name"
                                           class="w-full pl-10 pr-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-300 focus:border-transparent transition">
                                </div>
                                <p class="text-gray-500 text-sm mt-2">Your complete name (optional)</p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                                <!-- Phone -->
                                <div>
                                    <label class="block mb-2 text-gray-700 font-semibold">
                                        <i class="fas fa-phone mr-2 text-blue-600"></i>
                                        Phone Number
                                    </label>
                                    <div class="input-group">
                                        <span class="input-icon">
                                            <i class="fas fa-mobile-alt"></i>
                                        </span>
                                        <input type="text" name="phone" 
                                               value="<?= htmlspecialchars($phone) ?>"
                                               placeholder="09123456789"
                                               maxlength="11"
                                               class="w-full pl-10 pr-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-300 focus:border-transparent transition">
                                    </div>
                                    <p class="text-gray-500 text-sm mt-2">11-digit phone number (optional)</p>
                                </div>

                                <!-- Address -->
                                <div>
                                    <label class="block mb-2 text-gray-700 font-semibold">
                                        <i class="fas fa-map-marker-alt mr-2 text-blue-600"></i>
                                        Address
                                    </label>
                                    <div class="input-group">
                                        <span class="input-icon">
                                            <i class="fas fa-home"></i>
                                        </span>
                                        <input type="text" name="address" 
                                               value="<?= htmlspecialchars($address) ?>"
                                               placeholder="Enter your address"
                                               maxlength="100"
                                               class="w-full pl-10 pr-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-300 focus:border-transparent transition">
                                    </div>
                                    <p class="text-gray-500 text-sm mt-2">Your residential address (optional)</p>
                                </div>
                            </div>

                            <div class="flex justify-between pt-6 border-t border-gray-200">
                                <a href="teacher_dashboard.php" 
                                   class="px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-semibold">
                                    <i class="fas fa-times mr-2"></i>
                                    Cancel
                                </a>
                                
                                <button type="submit" 
                                        class="btn-glow px-8 py-3 bg-gradient-to-r from-blue-600 to-cyan-600 text-white rounded-xl hover:from-blue-700 hover:to-cyan-700 transition font-semibold">
                                    <i class="fas fa-save mr-2"></i>
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Password Change Form -->
                <div id="password-content" class="tab-content hidden">
                    <div class="glass-card rounded-2xl shadow-2xl overflow-hidden">
                        <!-- Form Header -->
                        <div class="bg-gradient-to-r from-purple-500 to-pink-500 px-6 py-5">
                            <div class="flex items-center space-x-3">
                                <div class="bg-white p-3 rounded-xl">
                                    <i class="fas fa-key text-purple-600 text-xl"></i>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold text-white">Change Password</h2>
                                    <p class="text-purple-100">Update your account password</p>
                                </div>
                            </div>
                        </div>

                        <!-- Password Form -->
                        <form method="POST" class="p-6" id="passwordForm">
                            <input type="hidden" name="change_password" value="1">
                            
                            <!-- Current Password -->
                            <div class="mb-8">
                                <label class="block mb-2 text-gray-700 font-semibold">
                                    <i class="fas fa-lock mr-2 text-purple-600"></i>
                                    Current Password *
                                </label>
                                <div class="input-group">
                                    <span class="input-icon">
                                        <i class="fas fa-key"></i>
                                    </span>
                                    <input type="password" name="current_password" required 
                                           id="currentPassword"
                                           placeholder="Enter your current password"
                                           class="w-full pl-10 pr-12 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-300 focus:border-transparent transition">
                                    <span class="password-toggle" onclick="togglePassword('currentPassword')">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                </div>
                                <p class="text-gray-500 text-sm mt-2">Enter your current password to verify</p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                                <!-- New Password -->
                                <div>
                                    <label class="block mb-2 text-gray-700 font-semibold">
                                        <i class="fas fa-lock mr-2 text-purple-600"></i>
                                        New Password *
                                    </label>
                                    <div class="input-group">
                                        <span class="input-icon">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password" name="new_password" required 
                                               id="newPassword"
                                               placeholder="Enter new password"
                                               class="w-full pl-10 pr-12 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-300 focus:border-transparent transition">
                                        <span class="password-toggle" onclick="togglePassword('newPassword')">
                                            <i class="fas fa-eye"></i>
                                        </span>
                                    </div>
                                    <p class="text-gray-500 text-sm mt-2">Minimum 6 characters</p>
                                </div>

                                <!-- Confirm Password -->
                                <div>
                                    <label class="block mb-2 text-gray-700 font-semibold">
                                        <i class="fas fa-lock mr-2 text-purple-600"></i>
                                        Confirm Password *
                                    </label>
                                    <div class="input-group">
                                        <span class="input-icon">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password" name="confirm_password" required 
                                               id="confirmPassword"
                                               placeholder="Confirm new password"
                                               class="w-full pl-10 pr-12 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-300 focus:border-transparent transition">
                                        <span class="password-toggle" onclick="togglePassword('confirmPassword')">
                                            <i class="fas fa-eye"></i>
                                        </span>
                                    </div>
                                    <p class="text-gray-500 text-sm mt-2">Re-enter your new password</p>
                                </div>
                            </div>

                            <!-- Password Strength Indicator -->
                            <div class="mb-8 p-4 bg-gradient-to-r from-gray-50 to-gray-100 rounded-xl border border-gray-200">
                                <h4 class="font-semibold text-gray-800 mb-3">
                                    <i class="fas fa-shield-alt mr-2 text-gray-600"></i>
                                    Password Requirements
                                </h4>
                                <div class="space-y-2">
                                    <div class="flex items-center">
                                        <div class="w-5 h-5 rounded-full border-2 border-gray-300 mr-3 flex items-center justify-center" id="lengthCheck">
                                            <i class="fas fa-check text-xs hidden"></i>
                                        </div>
                                        <span class="text-gray-700">At least 6 characters</span>
                                    </div>
                                    <div class="flex items-center">
                                        <div class="w-5 h-5 rounded-full border-2 border-gray-300 mr-3 flex items-center justify-center" id="matchCheck">
                                            <i class="fas fa-check text-xs hidden"></i>
                                        </div>
                                        <span class="text-gray-700">Passwords must match</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-between pt-6 border-t border-gray-200">
                                <button type="button" onclick="switchTab('profile')" 
                                        class="px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-semibold">
                                    <i class="fas fa-arrow-left mr-2"></i>
                                    Back to Profile
                                </button>
                                
                                <button type="submit" 
                                        class="btn-glow px-8 py-3 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-xl hover:from-purple-700 hover:to-pink-700 transition font-semibold">
                                    <i class="fas fa-key mr-2"></i>
                                    Change Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Security Tips -->
                <div class="glass-card rounded-2xl shadow-lg p-6 mt-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-shield-alt mr-3 text-green-600"></i>
                        Security Tips
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex items-start space-x-3">
                            <div class="bg-green-100 p-2 rounded-lg">
                                <i class="fas fa-key text-green-600"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-800">Strong Passwords</h4>
                                <p class="text-gray-600 text-sm">Use a combination of letters, numbers, and symbols.</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3">
                            <div class="bg-blue-100 p-2 rounded-lg">
                                <i class="fas fa-sync-alt text-blue-600"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-800">Regular Updates</h4>
                                <p class="text-gray-600 text-sm">Change your password periodically for security.</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3">
                            <div class="bg-yellow-100 p-2 rounded-lg">
                                <i class="fas fa-user-secret text-yellow-600"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-800">Privacy</h4>
                                <p class="text-gray-600 text-sm">Never share your password with anyone.</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3">
                            <div class="bg-purple-100 p-2 rounded-lg">
                                <i class="fas fa-sign-out-alt text-purple-600"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-800">Logout</h4>
                                <p class="text-gray-600 text-sm">Always logout when using shared devices.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab switching functionality
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Show selected tab content and activate tab button
            const tabContent = document.getElementById(tabName + '-content');
            const tabButton = document.getElementById(tabName + '-tab');
            
            if (tabContent) tabContent.classList.remove('hidden');
            if (tabButton) tabButton.classList.add('active');
        }
        
        // Password visibility toggle
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const toggleIcon = field.nextElementSibling.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        // Password validation
        function validatePasswords() {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            // Length check
            const lengthCheck = document.getElementById('lengthCheck');
            const lengthIcon = lengthCheck.querySelector('i');
            if (newPassword.length >= 6) {
                lengthCheck.style.borderColor = '#10b981';
                lengthCheck.style.backgroundColor = '#10b981';
                lengthIcon.classList.remove('hidden');
                lengthIcon.style.color = 'white';
            } else {
                lengthCheck.style.borderColor = '#d1d5db';
                lengthCheck.style.backgroundColor = '';
                lengthIcon.classList.add('hidden');
            }
            
            // Match check
            const matchCheck = document.getElementById('matchCheck');
            const matchIcon = matchCheck.querySelector('i');
            if (newPassword && confirmPassword && newPassword === confirmPassword) {
                matchCheck.style.borderColor = '#10b981';
                matchCheck.style.backgroundColor = '#10b981';
                matchIcon.classList.remove('hidden');
                matchIcon.style.color = 'white';
            } else {
                matchCheck.style.borderColor = '#d1d5db';
                matchCheck.style.backgroundColor = '';
                matchIcon.classList.add('hidden');
            }
        }
        
        // Form validation for password change
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const currentPassword = document.getElementById('currentPassword').value;
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (!currentPassword || !newPassword || !confirmPassword) {
                e.preventDefault();
                alert('Please fill in all password fields.');
                return false;
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('New password must be at least 6 characters.');
                return false;
            }
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New password and confirmation do not match.');
                return false;
            }
            
            return true;
        });
        
        // Real-time password validation
        document.getElementById('newPassword').addEventListener('input', validatePasswords);
        document.getElementById('confirmPassword').addEventListener('input', validatePasswords);
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize first tab as active
            switchTab('profile');
            
            // Validate passwords on load (in case of form errors)
            validatePasswords();
        });
    </script>
</body>
</html>