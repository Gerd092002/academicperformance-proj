<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
check_login();

// Only admin can edit users
if ($_SESSION['user']['role_id'] != 1) {
    header('Location: login.php');
    exit;
}

$admin_id = $_SESSION['user']['id'];
$error = $success = '';

// Get user ID from query string
$user_id = $_GET['id'] ?? null;

if (!$user_id) {
    header('Location: admin_dashboard.php');
    exit;
}

// Fetch user information
$stmt = $pdo->prepare("
    SELECT u.*, role_name 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: admin_dashboard.php');
    exit;
}

// Fetch all roles for dropdown
$stmt = $pdo->prepare("SELECT * FROM roles ORDER BY id");
$stmt->execute();
$roles = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $role_id = $_POST['role_id'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validation
    if (empty($username)) {
        $error = "Username is required.";
    } elseif (empty($email)) {
        $error = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (empty($role_id)) {
        $error = "Role is required.";
    } else {
        // Check for duplicate username (excluding current user)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $user_id]);
        if ($stmt->fetch()) {
            $error = "Username already exists. Please choose a different one.";
        }
        
        // Check for duplicate email (excluding current user)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            $error = "Email already exists. Please use a different email.";
        }
    }
    
    // Prevent admin from changing their own role away from admin
    if ($user_id == $admin_id && $role_id != 1) {
        $error = "You cannot change your own role from administrator.";
    }
    
    // Prevent changing the last admin's role
    if ($user['role_id'] == 1 && $role_id != 1) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as admin_count FROM users WHERE role_id = 1 AND id != ?");
        $stmt->execute([$user_id]);
        $admin_count = $stmt->fetch()['admin_count'];
        
        if ($admin_count == 0) {
            $error = "Cannot change role of the last administrator. There must be at least one admin account.";
        }
    }
    
    // If no errors, update user
    if (empty($error)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET username = ?, email = ?, full_name = ?, phone = ?, address = ?, 
                    role_id = ?, is_active = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            
            $stmt->execute([
                $username, $email, $full_name, $phone, $address,
                $role_id, $is_active, $user_id
            ]);
            
            $success = "User information updated successfully!";
            
            // Update the $user array with new values for display
            $user['username'] = $username;
            $user['email'] = $email;
            $user['full_name'] = $full_name;
            $user['phone'] = $phone;
            $user['address'] = $address;
            $user['role_id'] = $role_id;
            $user['is_active'] = $is_active;
            
            // Update role name
            foreach ($roles as $role) {
                if ($role['id'] == $role_id) {
                    $user['role_name'] = $role['role_name'];
                    break;
                }
            }
            
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Validation
    if (empty($new_password)) {
        $error = "New password is required.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        try {
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            
            $success = "Password reset successfully!";
            
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }

}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Admin Panel</title>
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
        
        .user-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: 3px solid white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .role-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .role-admin {
            background: linear-gradient(to right, #ef4444, #dc2626);
            color: white;
        }
        
        .role-teacher {
            background: linear-gradient(to right, #3b82f6, #2563eb);
            color: white;
        }
        
        .role-student {
            background: linear-gradient(to right, #10b981, #059669);
            color: white;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .status-active {
            background: linear-gradient(to right, #10b981, #059669);
            color: white;
        }
        
        .status-inactive {
            background: linear-gradient(to right, #6b7280, #4b5563);
            color: white;
        }
        
        .tab-button {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .tab-button.active {
            background: linear-gradient(to right, #667eea, #764ba2);
            color: white;
        }
        
        .tab-button:not(.active) {
            background: #f3f4f6;
            color: #6b7280;
        }
        
        .tab-button:not(.active):hover {
            background: #e5e7eb;
        }
        
        .tab-content {
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-card {
            background: linear-gradient(to bottom right, #f8fafc, #f1f5f9);
            border: 1px solid #e2e8f0;
        }
        
        .form-group {
            position: relative;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #4b5563;
        }
        
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .password-strength {
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            margin-top: 0.5rem;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            transition: width 0.3s ease;
        }
        
        .password-strength-weak {
            background: linear-gradient(to right, #ef4444, #f87171);
        }
        
        .password-strength-medium {
            background: linear-gradient(to right, #f59e0b, #fbbf24);
        }
        
        .password-strength-strong {
            background: linear-gradient(to right, #10b981, #34d399);
        }
    </style>
</head>
<body class="p-4 md:p-6">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div class="flex items-center space-x-3">
                <a href="admin_dashboard.php" 
                   class="text-gray-700 hover:text-indigo-700 transition">
                    <div class="bg-white p-2 rounded-lg shadow-sm hover-lift">
                        <i class="fas fa-arrow-left text-lg"></i>
                    </div>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-white">Edit User</h1>
                    <p class="text-gray-200">Administrator Panel - User Management</p>
                </div>
            </div>
            
            <div class="flex items-center space-x-4">
                <div class="hidden md:block text-right">
                    <p class="font-medium text-white"><?= htmlspecialchars($_SESSION['user']['username']) ?></p>
                    <p class="text-gray-200 text-sm">Administrator</p>
                </div>
                <div class="w-10 h-10 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-full flex items-center justify-center text-white font-bold shadow-lg">
                    <?= strtoupper(substr(htmlspecialchars($_SESSION['user']['username']), 0, 1)) ?>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="glass-card rounded-2xl shadow-2xl overflow-hidden mb-6">
            <!-- Form Header -->
            <div class="bg-gradient-to-r from-indigo-500 to-purple-500 px-6 py-5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="bg-white p-3 rounded-xl">
                            <i class="fas fa-user-edit text-indigo-600 text-xl"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-white">Edit User Account</h2>
                            <p class="text-indigo-100">Update user information and permissions</p>
                        </div>
                    </div>
                    <div class="bg-white/20 px-4 py-2 rounded-full">
                        <span class="text-white font-semibold">User ID: <?= $user['id'] ?></span>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <?php if (!empty($error)): ?>
                <div class="mx-6 mt-6 p-4 rounded-xl border-l-4 border-red-500 bg-gradient-to-r from-red-50 to-red-100 text-red-800">
                    <div class="flex items-center space-x-3">
                        <div class="bg-red-500 p-2 rounded-full">
                            <i class="fas fa-exclamation-triangle text-white"></i>
                        </div>
                        <div>
                            <p class="font-semibold">Error</p>
                            <p><?= $error ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="mx-6 mt-6 p-4 rounded-xl border-l-4 border-green-500 bg-gradient-to-r from-green-50 to-green-100 text-green-800">
                    <div class="flex items-center space-x-3">
                        <div class="bg-green-500 p-2 rounded-full">
                            <i class="fas fa-check-circle text-white"></i>
                        </div>
                        <div>
                            <p class="font-semibold">Success!</p>
                            <p><?= $success ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="p-6">
                <!-- User Profile Header -->
                <div class="flex flex-col md:flex-row items-center md:items-start space-y-6 md:space-y-0 md:space-x-6 mb-8">
                    <!-- Avatar -->
                    <div class="user-avatar rounded-full flex items-center justify-center text-white text-3xl font-bold">
                        <?= strtoupper(substr(htmlspecialchars($user['username']), 0, 1)) ?>
                    </div>
                    
                    <!-- User Info -->
                    <div class="flex-1">
                        <div class="flex flex-col md:flex-row md:items-center justify-between mb-4">
                            <div>
                                <h3 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($user['username']) ?></h3>
                                <p class="text-gray-600"><?= htmlspecialchars($user['email']) ?></p>
                            </div>
                            <div class="flex items-center space-x-3 mt-2 md:mt-0">
                                <span class="role-badge role-<?= $user['role_name'] ?>">
                                    <i class="fas fa-user-<?= $user['role_name'] == 'admin' ? 'shield' : ($user['role_name'] == 'teacher' ? 'tie' : 'graduate') ?> mr-2"></i>
                                    <?= ucfirst($user['role_name']) ?>
                                </span>
                                
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-4 text-gray-600">
                            <div class="flex items-center">
                                <i class="fas fa-calendar-alt mr-2"></i>
                                <span>Joined: <?= date('F j, Y', strtotime($user['created_at'])) ?></span>
                            </div>
                            <?php if ($user['updated_at'] != $user['created_at']): ?>
                            <div class="flex items-center">
                                <i class="fas fa-history mr-2"></i>
                                <span>Updated: <?= date('F j, Y', strtotime($user['updated_at'])) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="mb-8">
                    <div class="flex space-x-2 overflow-x-auto pb-2">
                        <button type="button" 
                                class="tab-button active" 
                                onclick="showTab('profile-tab')">
                            <i class="fas fa-user-edit mr-2"></i>
                            Edit Profile
                        </button>
                        <button type="button" 
                                class="tab-button" 
                                onclick="showTab('password-tab')">
                            <i class="fas fa-key mr-2"></i>
                            Reset Password
                        </button>
                        <button type="button" 
                                class="tab-button" 
                                onclick="showTab('activity-tab')">
                            <i class="fas fa-chart-bar mr-2"></i>
                            Activity Info
                        </button>
                    </div>
                </div>

                <!-- Edit Profile Tab -->
                <div id="profile-tab" class="tab-content">
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="update_user" value="1">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Username -->
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-user mr-2"></i>
                                    Username *
                                </label>
                                <input type="text" 
                                       name="username" 
                                       value="<?= htmlspecialchars($user['username']) ?>" 
                                       required
                                       class="form-input"
                                       placeholder="Enter username">
                            </div>
                            
                            <!-- Email -->
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-envelope mr-2"></i>
                                    Email *
                                </label>
                                <input type="email" 
                                       name="email" 
                                       value="<?= htmlspecialchars($user['email']) ?>" 
                                       required
                                       class="form-input"
                                       placeholder="Enter email address">
                            </div>
                            
                            <!-- Full Name -->
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-id-card mr-2"></i>
                                    Full Name
                                </label>
                                <input type="text" 
                                       name="full_name" 
                                       value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" 
                                       class="form-input"
                                       placeholder="Enter full name">
                            </div>
                            
                            <!-- Phone -->
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-phone mr-2"></i>
                                    Phone Number
                                </label>
                                <input type="tel" 
                                       name="phone" 
                                       value="<?= htmlspecialchars($user['phone'] ?? '') ?>" 
                                       class="form-input"
                                       placeholder="Enter phone number">
                            </div>
                            
                            <!-- Role -->
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-user-tag mr-2"></i>
                                    Role *
                                </label>
                                <select name="role_id" required class="form-input">
                                    <option value="">Select Role</option>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?= $role['id'] ?>" 
                                                <?= $user['role_id'] == $role['id'] ? 'selected' : '' ?>
                                                style="color: <?= $role['role_name'] == 'admin' ? '#ef4444' : ($role['role_name'] == 'teacher' ? '#3b82f6' : '#10b981') ?>">
                                            <?= ucfirst($role['role_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Account Status -->
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-power-off mr-2"></i>
                                    Account Status
                                </label>
                                <div class="mt-2">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox" 
                                               name="is_active" 
                                               value="1" 
                                               <?= $user['is_active'] ? 'checked' : '' ?>
                                               class="sr-only peer">
                                        <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                                        <span class="ml-3 text-gray-700 font-medium">
                                            <?= $user['is_active'] ? 'Account is Active' : 'Account is Inactive' ?>
                                        </span>
                                    </label>
                                    <p class="text-gray-500 text-sm mt-2">
                                        When inactive, user cannot log in to the system.
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Address -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-map-marker-alt mr-2"></i>
                                Address
                            </label>
                            <textarea name="address" 
                                      rows="3"
                                      class="form-input"
                                      placeholder="Enter address"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="flex justify-between pt-6 border-t border-gray-200">
                            <a href="admin_dashboard.php" 
                               class="px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-semibold">
                                <i class="fas fa-times mr-2"></i>
                                Cancel
                            </a>
                            
                            <button type="submit" 
                                    class="btn-glow px-8 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-xl hover:from-indigo-700 hover:to-purple-700 transition font-semibold">
                                <i class="fas fa-save mr-2"></i>
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Reset Password Tab -->
                <div id="password-tab" class="tab-content hidden">
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="reset_password" value="1">
                        
                        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-r mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-triangle text-yellow-400 text-xl"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-yellow-700 font-semibold">Important Notice</p>
                                    <p class="text-yellow-600 text-sm mt-1">
                                        Resetting the password will immediately change the user's login credentials. 
                                        The user will need to use the new password for their next login.
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- New Password -->
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-lock mr-2"></i>
                                    New Password *
                                </label>
                                <input type="password" 
                                       name="new_password" 
                                       id="new_password"
                                       required
                                       minlength="6"
                                       class="form-input"
                                       placeholder="Enter new password"
                                       onkeyup="checkPasswordStrength(this.value)">
                                <div class="password-strength">
                                    <div id="password-strength-bar" class="password-strength-bar" style="width: 0%"></div>
                                </div>
                                <p class="text-gray-500 text-sm mt-2">
                                    Password must be at least 6 characters long.
                                </p>
                            </div>
                            
                            <!-- Confirm Password -->
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-lock mr-2"></i>
                                    Confirm Password *
                                </label>
                                <input type="password" 
                                       name="confirm_password" 
                                       id="confirm_password"
                                       required
                                       minlength="6"
                                       class="form-input"
                                       placeholder="Confirm new password"
                                       onkeyup="checkPasswordMatch()">
                                <div id="password-match" class="text-sm mt-2"></div>
                            </div>
                        </div>
                        
                        <!-- Password Requirements -->
                        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                            <h4 class="font-semibold text-blue-800 mb-2">
                                <i class="fas fa-info-circle mr-2"></i>
                                Password Requirements
                            </h4>
                            <ul class="text-blue-700 text-sm space-y-1">
                                <li><i class="fas fa-check-circle text-green-500 mr-2"></i> Minimum 6 characters</li>
                                <li><i class="fas fa-check-circle text-green-500 mr-2"></i> Use a mix of letters and numbers</li>
                                <li><i class="fas fa-check-circle text-green-500 mr-2"></i> Avoid common passwords</li>
                                <li><i class="fas fa-check-circle text-green-500 mr-2"></i> Consider using special characters for extra security</li>
                            </ul>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="flex justify-between pt-6 border-t border-gray-200">
                            <button type="button" 
                                    class="px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-semibold"
                                    onclick="showTab('profile-tab')">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to Profile
                            </button>
                            
                            <button type="submit" 
                                    id="resetPasswordBtn"
                                    class="btn-glow px-8 py-3 bg-gradient-to-r from-yellow-600 to-orange-600 text-white rounded-xl hover:from-yellow-700 hover:to-orange-700 transition font-semibold"
                                    disabled>
                                <i class="fas fa-key mr-2"></i>
                                Reset Password
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Activity Info Tab -->
                <div id="activity-tab" class="tab-content hidden">
                    <div class="space-y-6">
                        <!-- User Statistics -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <?php if ($user['role_id'] == 3): // Student ?>
                                <?php
                                // Get student statistics
                                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM submissions WHERE student_id = ?");
                                $stmt->execute([$user_id]);
                                $submission_count = $stmt->fetch()['count'];
                                
                                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM student_subject WHERE student_id = ?");
                                $stmt->execute([$user_id]);
                                $enrollment_count = $stmt->fetch()['count'];
                                
                                $stmt = $pdo->prepare("SELECT AVG(grade) as avg_grade FROM submissions WHERE student_id = ? AND grade IS NOT NULL");
                                $stmt->execute([$user_id]);
                                $avg_grade = $stmt->fetch()['avg_grade'];
                                ?>
                                
                                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-xl border border-blue-200">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm text-blue-600 font-semibold">Enrolled Subjects</p>
                                            <p class="text-2xl font-bold text-blue-800"><?= $enrollment_count ?></p>
                                        </div>
                                        <div class="bg-blue-100 p-3 rounded-full">
                                            <i class="fas fa-book text-blue-600 text-xl"></i>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="bg-gradient-to-r from-green-50 to-emerald-50 p-4 rounded-xl border border-green-200">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm text-green-600 font-semibold">Submissions</p>
                                            <p class="text-2xl font-bold text-green-800"><?= $submission_count ?></p>
                                        </div>
                                        <div class="bg-green-100 p-3 rounded-full">
                                            <i class="fas fa-file-upload text-green-600 text-xl"></i>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="bg-gradient-to-r from-purple-50 to-pink-50 p-4 rounded-xl border border-purple-200">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm text-purple-600 font-semibold">Average Grade</p>
                                            <p class="text-2xl font-bold text-purple-800">
                                                <?= $avg_grade ? number_format($avg_grade, 1) : 'N/A' ?>
                                            </p>
                                        </div>
                                        <div class="bg-purple-100 p-3 rounded-full">
                                            <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                                        </div>
                                    </div>
                                </div>
                                
                            <?php elseif ($user['role_id'] == 2): // Teacher ?>
                                <?php
                                // Get teacher statistics
                                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM subjects WHERE teacher_id = ?");
                                $stmt->execute([$user_id]);
                                $subject_count = $stmt->fetch()['count'];
                                
                                $stmt = $pdo->prepare("SELECT COUNT(DISTINCT student_id) as count FROM student_subject WHERE subject_id IN (SELECT id FROM subjects WHERE teacher_id = ?)");
                                $stmt->execute([$user_id]);
                                $student_count = $stmt->fetch()['count'];
                                
                                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM assignments WHERE subject_id IN (SELECT id FROM subjects WHERE teacher_id = ?)");
                                $stmt->execute([$user_id]);
                                $assignment_count = $stmt->fetch()['count'];
                                ?>
                                
                                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-xl border border-blue-200">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm text-blue-600 font-semibold">Teaching Subjects</p>
                                            <p class="text-2xl font-bold text-blue-800"><?= $subject_count ?></p>
                                        </div>
                                        <div class="bg-blue-100 p-3 rounded-full">
                                            <i class="fas fa-chalkboard-teacher text-blue-600 text-xl"></i>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="bg-gradient-to-r from-green-50 to-emerald-50 p-4 rounded-xl border border-green-200">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm text-green-600 font-semibold">Students</p>
                                            <p class="text-2xl font-bold text-green-800"><?= $student_count ?></p>
                                        </div>
                                        <div class="bg-green-100 p-3 rounded-full">
                                            <i class="fas fa-users text-green-600 text-xl"></i>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="bg-gradient-to-r from-purple-50 to-pink-50 p-4 rounded-xl border border-purple-200">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm text-purple-600 font-semibold">Assignments</p>
                                            <p class="text-2xl font-bold text-purple-800"><?= $assignment_count ?></p>
                                        </div>
                                        <div class="bg-purple-100 p-3 rounded-full">
                                            <i class="fas fa-tasks text-purple-600 text-xl"></i>
                                        </div>
                                    </div>
                                </div>
                                
                            <?php else: // Admin ?>
                                <div class="col-span-3 bg-gradient-to-r from-red-50 to-pink-50 p-4 rounded-xl border border-red-200">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm text-red-600 font-semibold">Administrator Account</p>
                                            <p class="text-lg font-bold text-red-800">
                                                This user has administrator privileges with full system access.
                                            </p>
                                            <p class="text-red-600 mt-2">
                                                Admins can manage users, subjects, assignments, and all system settings.
                                            </p>
                                        </div>
                                        <div class="bg-red-100 p-4 rounded-full">
                                            <i class="fas fa-shield-alt text-red-600 text-2xl"></i>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Recent Activity -->
                        <div class="bg-white rounded-xl border border-gray-200 p-6">
                            <h4 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                                <i class="fas fa-history mr-2"></i>
                                Recent Activity
                            </h4>
                            
                            <?php
                            // Get recent submissions for students, recent assignments for teachers
                            if ($user['role_id'] == 3) {
                                $stmt = $pdo->prepare("
                                    SELECT s.*, a.title as assignment_title 
                                    FROM submissions s 
                                    JOIN assignments a ON s.assignment_id = a.id 
                                    WHERE s.student_id = ? 
                                    ORDER BY s.submitted_at DESC 
                                    LIMIT 5
                                ");
                                $stmt->execute([$user_id]);
                                $activities = $stmt->fetchAll();
                                
                                if ($activities): ?>
                                    <div class="space-y-3">
                                        <?php foreach ($activities as $activity): ?>
                                            <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                                                <div class="flex items-center">
                                                    <div class="bg-blue-100 p-2 rounded-lg mr-3">
                                                        <i class="fas fa-file-upload text-blue-600"></i>
                                                    </div>
                                                    <div>
                                                        <p class="font-semibold text-gray-800">Submitted: <?= htmlspecialchars($activity['assignment_title']) ?></p>
                                                        <p class="text-sm text-gray-600">
                                                            <?= date('F j, Y g:i A', strtotime($activity['submitted_at'])) ?>
                                                            <?php if ($activity['grade']): ?>
                                                                • Grade: <span class="font-bold text-green-600"><?= $activity['grade'] ?></span>
                                                            <?php endif; ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-gray-500 text-center py-4">No recent submissions found.</p>
                                <?php endif;
                                
                            } elseif ($user['role_id'] == 2) {
                                $stmt = $pdo->prepare("
                                    SELECT * FROM assignments 
                                    WHERE subject_id IN (SELECT id FROM subjects WHERE teacher_id = ?) 
                                    ORDER BY created_at DESC 
                                    LIMIT 5
                                ");
                                $stmt->execute([$user_id]);
                                $activities = $stmt->fetchAll();
                                
                                if ($activities): ?>
                                    <div class="space-y-3">
                                        <?php foreach ($activities as $activity): ?>
                                            <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                                                <div class="flex items-center">
                                                    <div class="bg-green-100 p-2 rounded-lg mr-3">
                                                        <i class="fas fa-tasks text-green-600"></i>
                                                    </div>
                                                    <div>
                                                        <p class="font-semibold text-gray-800">Created Assignment: <?= htmlspecialchars($activity['name']) ?></p>
                                                        <p class="text-sm text-gray-600">
                                                            <?= date('F j, Y g:i A', strtotime($activity['created_at'])) ?>
                                                            • Due: <?= date('F j, Y', strtotime($activity['due_date'])) ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-gray-500 text-center py-4">No recent activity found.</p>
                                <?php endif;
                            } else {
                                echo '<p class="text-gray-500 text-center py-4">Admin activity logging is not available.</p>';
                            }
                            ?>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="flex justify-between pt-6 border-t border-gray-200">
                            <button type="button" 
                                    class="px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-semibold"
                                    onclick="showTab('profile-tab')">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to Profile
                            </button>
                            
                            <?php if ($user_id != $admin_id): ?>
                                <a href="delete_user.php?id=<?= $user_id ?>" 
                                   class="px-6 py-3 bg-gradient-to-r from-red-600 to-pink-600 text-white rounded-xl hover:from-red-700 hover:to-pink-700 transition font-semibold"
                                   onclick="return confirm('Are you sure you want to delete this user?')">
                                    <i class="fas fa-trash-alt mr-2"></i>
                                    Delete User
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab functionality
        function showTab(tabId) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.add('hidden');
            });
            
            // Show selected tab
            document.getElementById(tabId).classList.remove('hidden');
            
            // Update tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Find the button that corresponds to this tab
            const buttons = {
                'profile-tab': 'Edit Profile',
                'password-tab': 'Reset Password',
                'activity-tab': 'Activity Info'
            };
            
            // This is a simplified version - in production you'd want to store the association
            document.querySelectorAll('.tab-button').forEach(button => {
                if (button.textContent.includes(buttons[tabId])) {
                    button.classList.add('active');
                }
            });
        }
        
        // Password strength checker
        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('password-strength-bar');
            const resetButton = document.getElementById('resetPasswordBtn');
            
            if (password.length === 0) {
                strengthBar.style.width = '0%';
                strengthBar.className = 'password-strength-bar';
                checkPasswordMatch();
                return;
            }
            
            let strength = 0;
            
            // Length check
            if (password.length >= 6) strength++;
            if (password.length >= 8) strength++;
            
            // Character variety checks
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            // Update strength bar
            let width = 0;
            let className = '';
            
            if (strength <= 2) {
                width = (strength / 2) * 100;
                className = 'password-strength-weak';
            } else if (strength <= 4) {
                width = (strength / 4) * 100;
                className = 'password-strength-medium';
            } else {
                width = 100;
                className = 'password-strength-strong';
            }
            
            strengthBar.style.width = width + '%';
            strengthBar.className = 'password-strength-bar ' + className;
            
            checkPasswordMatch();
        }
        
        // Password match checker
        function checkPasswordMatch() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchDiv = document.getElementById('password-match');
            const resetButton = document.getElementById('resetPasswordBtn');
            
            if (newPassword === '' && confirmPassword === '') {
                matchDiv.innerHTML = '';
                resetButton.disabled = true;
                return;
            }
            
            if (newPassword === confirmPassword && newPassword.length >= 6) {
                matchDiv.innerHTML = '<i class="fas fa-check-circle text-green-500 mr-2"></i>Passwords match';
                matchDiv.className = 'text-green-600 font-semibold';
                resetButton.disabled = false;
            } else if (newPassword !== confirmPassword) {
                matchDiv.innerHTML = '<i class="fas fa-times-circle text-red-500 mr-2"></i>Passwords do not match';
                matchDiv.className = 'text-red-600 font-semibold';
                resetButton.disabled = true;
            } else if (newPassword.length < 6) {
                matchDiv.innerHTML = '<i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>Password must be at least 6 characters';
                matchDiv.className = 'text-yellow-600 font-semibold';
                resetButton.disabled = true;
            }
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Check if there's a hash in the URL for tab navigation
            const hash = window.location.hash.substring(1);
            if (hash && ['profile-tab', 'password-tab', 'activity-tab'].includes(hash)) {
                showTab(hash);
            }
        });
    </script>
</body>
</html>