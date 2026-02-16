<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
check_login();

// Only admin can delete users
if ($_SESSION['user']['role_id'] != 1) {
    header('Location: login.php');
    exit;
}

$admin_id = $_SESSION['user']['id'];
$error = $success = '';
$deletion_notes = [];

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

// Prevent admin from deleting themselves
if ($user_id == $admin_id) {
    $error = "You cannot delete your own account.";
}

// Check if user is the last admin
if ($user['role_id'] == 1) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as admin_count FROM users WHERE role_id = 1 AND id != ?");
    $stmt->execute([$user_id]);
    $admin_count = $stmt->fetch()['admin_count'];
    
    if ($admin_count == 0) {
        $error = "Cannot delete the last administrator. There must be at least one admin account.";
    }
}

// Check for related data before deletion
$related_data_exists = false;
$related_data_info = [];

// Check submissions
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM submissions WHERE student_id = ?");
$stmt->execute([$user_id]);
$submission_count = $stmt->fetch()['count'];
if ($submission_count > 0) {
    $related_data_exists = true;
    $related_data_info[] = [
        'type' => 'submissions',
        'count' => $submission_count,
        'message' => "User has $submission_count submission(s)"
    ];
}

// Check student_subject enrollments
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM student_subject WHERE student_id = ?");
$stmt->execute([$user_id]);
$enrollment_count = $stmt->fetch()['count'];
if ($enrollment_count > 0) {
    $related_data_exists = true;
    $related_data_info[] = [
        'type' => 'enrollments',
        'count' => $enrollment_count,
        'message' => "User is enrolled in $enrollment_count subject(s)"
    ];
}

// Check teacher subjects
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM subjects WHERE teacher_id = ?");
$stmt->execute([$user_id]);
$subject_count = $stmt->fetch()['count'];
if ($subject_count > 0) {
    $related_data_exists = true;
    $related_data_info[] = [
        'type' => 'subjects',
        'count' => $subject_count,
        'message' => "User teaches $subject_count subject(s)"
    ];
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    if (!empty($error)) {
        // Don't proceed if there's an error
    } else {
        try {
            $pdo->beginTransaction();
            
            // Delete related data first (in correct order)
            
            // 1. Delete submissions (if user is a student)
            if ($user['role_id'] == 3) {
                $stmt = $pdo->prepare("DELETE FROM submissions WHERE student_id = ?");
                $stmt->execute([$user_id]);
                $deletion_notes[] = "Deleted $submission_count submission(s)";
            }
            
            // 2. Delete student_subject enrollments
            if ($user['role_id'] == 3) {
                $stmt = $pdo->prepare("DELETE FROM student_subject WHERE student_id = ?");
                $stmt->execute([$user_id]);
                $deletion_notes[] = "Removed $enrollment_count enrollment(s)";
            }
            
            // 3. Handle teacher subjects
            if ($user['role_id'] == 2) {
                // Get assignments, projects, activities created by this teacher
                $tables = ['assignments', 'projects', 'activities'];
                foreach ($tables as $table) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM $table WHERE subject_id IN (SELECT id FROM subjects WHERE teacher_id = ?)");
                    $stmt->execute([$user_id]);
                    $item_count = $stmt->fetch()['count'];
                    
                    if ($item_count > 0) {
                        // Delete submissions for these items first
                        $stmt = $pdo->prepare("
                            DELETE s FROM submissions s 
                            JOIN $table i ON s.{$table}_id = i.id 
                            WHERE i.subject_id IN (SELECT id FROM subjects WHERE teacher_id = ?)
                        ");
                        $stmt->execute([$user_id]);
                        
                        // Then delete the items
                        $stmt = $pdo->prepare("DELETE FROM $table WHERE subject_id IN (SELECT id FROM subjects WHERE teacher_id = ?)");
                        $stmt->execute([$user_id]);
                        
                        $deletion_notes[] = "Deleted $item_count $table";
                    }
                }
                
                // Reassign subjects to admin
                if ($subject_count > 0) {
                    $stmt = $pdo->prepare("UPDATE subjects SET teacher_id = ? WHERE teacher_id = ?");
                    $stmt->execute([$admin_id, $user_id]);
                    $deletion_notes[] = "Reassigned $subject_count subject(s) to admin";
                }
            }
            
            // 4. Finally, delete the user
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $deletion_notes[] = "User account deleted successfully";
            
            $pdo->commit();
            
            $success = "User deleted successfully!";
            
            // Store deletion notes in session for display on redirect
            $_SESSION['deletion_notes'] = $deletion_notes;
            
            // Redirect after 2 seconds
            header("refresh:2;url=admin_dashboard.php");
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Database error: " . $e->getMessage();
            
            // Add more specific error message for foreign key constraint
            if (strpos($e->getMessage(), '1451') !== false) {
                $error .= "<br><br><strong>Foreign Key Constraint Error:</strong> There are still related records that cannot be deleted. Please check if all related data has been properly handled.";
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
    <title>Delete User - Admin Panel</title>
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
        
        .warning-card {
            animation: pulse 2s infinite;
            border-left: 6px solid #f59e0b;
        }
        
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4); }
            50% { box-shadow: 0 0 0 10px rgba(245, 158, 11, 0); }
        }
        
        .danger-card {
            border-left: 6px solid #ef4444;
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
        
        .info-item {
            transition: all 0.3s ease;
        }
        
        .info-item:hover {
            transform: translateY(-2px);
            background: linear-gradient(to right, #f8fafc, #f1f5f9);
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
        
        .related-data-item {
            border-left: 4px solid #f59e0b;
            background: linear-gradient(to right, #fffbeb, #fef3c7);
        }
        
        .deletion-step {
            counter-increment: step-counter;
            position: relative;
            padding-left: 3rem;
        }
        
        .deletion-step:before {
            content: counter(step-counter);
            position: absolute;
            left: 0;
            top: 0;
            width: 2rem;
            height: 2rem;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
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
                    <h1 class="text-3xl font-bold text-white">Delete User</h1>
                    <p class="text-gray-200">Administrator Panel - User Management</p>
                </div>
            </div>
            
            <div class="flex items-center space-x-4">
                <div class="hidden md:block text-right">
                    <p class="font-medium text-white"><?= htmlspecialchars($_SESSION['user']['username']) ?></p>
                    <p class="text-gray-200 text-sm">Administrator</p>
                </div>
                <div class="w-10 h-10 bg-gradient-to-r from-red-600 to-pink-600 rounded-full flex items-center justify-center text-white font-bold shadow-lg">
                    <?= strtoupper(substr(htmlspecialchars($_SESSION['user']['username']), 0, 1)) ?>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="glass-card rounded-2xl shadow-2xl overflow-hidden mb-6">
            <!-- Form Header -->
            <div class="bg-gradient-to-r from-red-500 to-pink-500 px-6 py-5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="bg-white p-3 rounded-xl">
                            <i class="fas fa-user-slash text-red-600 text-xl"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-white">Delete User Account</h2>
                            <p class="text-red-100">Permanently remove user from the system</p>
                        </div>
                    </div>
                    <div class="bg-white/20 px-4 py-2 rounded-full">
                        <span class="text-white font-semibold">Warning: This action cannot be undone</span>
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
                        <div class="flex-1">
                            <p class="font-semibold">Cannot Delete User</p>
                            <p><?= $error ?></p>
                        </div>
                    </div>
                    <div class="mt-4 text-center">
                        <a href="admin_dashboard.php" 
                           class="inline-block bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700 transition">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Return to User Management
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="mx-6 mt-6 p-4 rounded-xl border-l-4 border-green-500 bg-gradient-to-r from-green-50 to-green-100 text-green-800">
                    <div class="flex items-center space-x-3">
                        <div class="bg-green-500 p-2 rounded-full">
                            <i class="fas fa-check-circle text-white"></i>
                        </div>
                        <div class="flex-1">
                            <p class="font-semibold">Success!</p>
                            <p><?= htmlspecialchars($success) ?></p>
                            <?php if (!empty($deletion_notes)): ?>
                                <div class="mt-3">
                                    <p class="font-medium text-sm">Actions performed:</p>
                                    <ul class="text-sm mt-1 space-y-1">
                                        <?php foreach ($deletion_notes as $note): ?>
                                            <li class="flex items-center">
                                                <i class="fas fa-check text-green-500 mr-2"></i>
                                                <?= htmlspecialchars($note) ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            <p class="text-sm mt-2">Redirecting to user management...</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- User Information -->
            <?php if (empty($error) && empty($success)): ?>
            <div class="p-6">
                <!-- Warning Message -->
                <div class="warning-card mb-8 p-6 bg-gradient-to-r from-yellow-50 to-amber-50 rounded-xl">
                    <div class="flex items-start space-x-3">
                        <div class="bg-yellow-500 p-3 rounded-full">
                            <i class="fas fa-exclamation-triangle text-white text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-xl font-bold text-yellow-800 mb-2">Warning: Irreversible Action</h3>
                            <p class="text-yellow-700">
                                You are about to permanently delete a user account. This action:
                            </p>
                            <ul class="mt-2 space-y-1 text-yellow-700">
                                <li class="flex items-center">
                                    <i class="fas fa-times-circle mr-2"></i>
                                    Cannot be undone
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-trash-alt mr-2"></i>
                                    Permanently removes all user data
                                </li>
                                <li class="flex items-center">
                                    <i class="fas fa-ban mr-2"></i>
                                    User will lose access immediately
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- User Details -->
                <div class="danger-card mb-8 p-6 bg-gradient-to-r from-red-50 to-pink-50 rounded-xl">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">User to be Deleted</h3>
                    
                    <div class="flex flex-col md:flex-row items-center md:items-start space-y-6 md:space-y-0 md:space-x-6 mb-6">
                        <!-- Avatar -->
                        <div class="user-avatar rounded-full flex items-center justify-center text-white text-3xl font-bold">
                            <?= strtoupper(substr(htmlspecialchars($user['username']), 0, 1)) ?>
                        </div>
                        
                        <!-- Basic Info -->
                        <div class="flex-1">
                            <div class="flex flex-col md:flex-row md:items-center justify-between mb-4">
                                <div>
                                    <h4 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($user['username']) ?></h4>
                                    <p class="text-gray-600">ID: <?= $user['id'] ?></p>
                                </div>
                                <div class="mt-2 md:mt-0">
                                    <span class="role-badge role-<?= $user['role_name'] ?>">
                                        <i class="fas fa-user-<?= $user['role_name'] == 'admin' ? 'shield' : ($user['role_name'] == 'teacher' ? 'tie' : 'graduate') ?> mr-2"></i>
                                        <?= ucfirst($user['role_name']) ?>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- User Information Grid -->
                            <div class="info-grid">
                                <div class="info-item p-4 bg-white rounded-xl border border-gray-200">
                                    <div class="flex items-center mb-2">
                                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                            <i class="fas fa-envelope text-blue-600"></i>
                                        </div>
                                        <h5 class="font-semibold text-gray-800">Email</h5>
                                    </div>
                                    <p class="text-gray-600"><?= htmlspecialchars($user['email']) ?></p>
                                </div>
                                
                                <?php if($user['full_name']): ?>
                                <div class="info-item p-4 bg-white rounded-xl border border-gray-200">
                                    <div class="flex items-center mb-2">
                                        <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                            <i class="fas fa-id-card text-green-600"></i>
                                        </div>
                                        <h5 class="font-semibold text-gray-800">Full Name</h5>
                                    </div>
                                    <p class="text-gray-600"><?= htmlspecialchars($user['full_name']) ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if($user['phone']): ?>
                                <div class="info-item p-4 bg-white rounded-xl border border-gray-200">
                                    <div class="flex items-center mb-2">
                                        <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center mr-3">
                                            <i class="fas fa-phone text-yellow-600"></i>
                                        </div>
                                        <h5 class="font-semibold text-gray-800">Phone</h5>
                                    </div>
                                    <p class="text-gray-600"><?= htmlspecialchars($user['phone']) ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if($user['address']): ?>
                                <div class="info-item p-4 bg-white rounded-xl border border-gray-200">
                                    <div class="flex items-center mb-2">
                                        <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                                            <i class="fas fa-map-marker-alt text-purple-600"></i>
                                        </div>
                                        <h5 class="font-semibold text-gray-800">Address</h5>
                                    </div>
                                    <p class="text-gray-600"><?= htmlspecialchars($user['address']) ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <div class="info-item p-4 bg-white rounded-xl border border-gray-200">
                                    <div class="flex items-center mb-2">
                                        <div class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center mr-3">
                                            <i class="fas fa-calendar-alt text-gray-600"></i>
                                        </div>
                                        <h5 class="font-semibold text-gray-800">Member Since</h5>
                                    </div>
                                    <p class="text-gray-600"><?= date('F j, Y', strtotime($user['created_at'])) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Related Data Warning -->
                <?php if ($related_data_exists): ?>
                <div class="mb-8 p-6 bg-gradient-to-r from-orange-50 to-amber-50 rounded-xl border border-orange-200">
                    <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-database mr-3 text-orange-600"></i>
                        Related Data Found
                    </h3>
                    
                    <p class="text-orange-700 mb-4">
                        This user has related data in the system. The following actions will be performed:
                    </p>
                    
                    <div class="space-y-3 mb-4">
                        <?php foreach ($related_data_info as $data): ?>
                        <div class="related-data-item p-4 rounded-lg">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas fa-<?= $data['type'] == 'submissions' ? 'file-upload' : ($data['type'] == 'enrollments' ? 'book' : 'chalkboard-teacher') ?> text-orange-600"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-semibold text-gray-800"><?= ucfirst($data['type']) ?></h4>
                                        <p class="text-gray-600 text-sm"><?= $data['message'] ?></p>
                                    </div>
                                </div>
                                <div class="bg-orange-100 text-orange-800 px-3 py-1 rounded-full text-sm font-semibold">
                                    <?= $data['count'] ?> record(s)
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="bg-white p-4 rounded-lg border border-orange-300">
                        <h4 class="font-semibold text-gray-800 mb-2">Deletion Steps:</h4>
                        <ol class="list-decimal pl-5 space-y-2 text-gray-700">
                            <?php if ($user['role_id'] == 3): ?>
                            <li class="deletion-step ml-4 mb-2">
                                Delete all submissions by this student
                            </li>
                            <li class="deletion-step ml-4 mb-2">
                                Remove student from all enrolled subjects
                            </li>
                            <?php elseif ($user['role_id'] == 2): ?>
                            <li class="deletion-step ml-4 mb-2">
                                Delete submissions for teacher's assignments/projects/activities
                            </li>
                            <li class="deletion-step ml-4 mb-2">
                                Delete teacher's assignments, projects, and activities
                            </li>
                            <li class="deletion-step ml-4 mb-2">
                                Reassign teacher's subjects to admin
                            </li>
                            <?php endif; ?>
                            <li class="deletion-step ml-4 mb-2">
                                Delete user account
                            </li>
                        </ol>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Confirmation Form -->
                <form method="POST" class="p-6 bg-gradient-to-r from-red-50 to-pink-50 rounded-xl border border-red-200">
                    <h3 class="text-xl font-bold text-gray-800 mb-4">Final Confirmation</h3>
                    
                    <div class="mb-6">
                        <label class="flex items-center p-4 bg-white rounded-xl border border-red-300 cursor-pointer hover:bg-red-50 transition">
                            <input type="checkbox" name="confirm_delete" value="1" required 
                                   class="w-5 h-5 text-red-600 rounded focus:ring-red-500 mr-3">
                            <span class="text-gray-700 font-semibold">
                                I understand that this action will delete all related data and cannot be undone.
                            </span>
                        </label>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block mb-2 text-gray-700 font-semibold">
                            Type "DELETE" to confirm:
                        </label>
                        <input type="text" name="confirmation_text" required 
                               placeholder="Type DELETE here"
                               class="w-full border-2 border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-red-300 focus:border-transparent transition"
                               pattern="DELETE"
                               oninput="validateConfirmation(this)">
                        <p class="text-gray-500 text-sm mt-2">This is case-sensitive and must match exactly: DELETE</p>
                    </div>

                    <div class="flex justify-between pt-6 border-t border-red-200">
                        <a href="admin_dashboard.php" 
                           class="px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-semibold">
                            <i class="fas fa-times mr-2"></i>
                            Cancel Deletion
                        </a>
                        
                        <button type="submit" 
                                class="btn-glow px-8 py-3 bg-gradient-to-r from-red-600 to-pink-600 text-white rounded-xl hover:from-red-700 hover:to-pink-700 transition font-semibold"
                                id="deleteButton" disabled>
                            <i class="fas fa-trash-alt mr-2"></i>
                            Permanently Delete User
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Confirmation validation
        function validateConfirmation(input) {
            const deleteButton = document.getElementById('deleteButton');
            const confirmationCheckbox = document.querySelector('input[name="confirm_delete"]');
            
            if (input.value === 'DELETE' && confirmationCheckbox.checked) {
                deleteButton.disabled = false;
                input.classList.remove('border-red-300');
                input.classList.add('border-green-300');
            } else {
                deleteButton.disabled = true;
                input.classList.remove('border-green-300');
                input.classList.add('border-red-300');
            }
        }
        
        // Checkbox validation
        document.querySelector('input[name="confirm_delete"]').addEventListener('change', function() {
            const confirmationInput = document.querySelector('input[name="confirmation_text"]');
            validateConfirmation(confirmationInput);
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const confirmationText = document.querySelector('input[name="confirmation_text"]').value;
            const confirmationCheckbox = document.querySelector('input[name="confirm_delete"]').checked;
            
            if (!confirmationCheckbox) {
                e.preventDefault();
                alert('Please confirm that you understand this action is irreversible.');
                return false;
            }
            
            if (confirmationText !== 'DELETE') {
                e.preventDefault();
                alert('Please type "DELETE" exactly as shown to confirm.');
                return false;
            }
            
            // Final confirmation
            const relatedDataExists = <?= $related_data_exists ? 'true' : 'false' ?>;
            let message = '⚠️ FINAL WARNING: This will permanently delete the user account. ';
            
            if (relatedDataExists) {
                message += 'All related data (submissions, enrollments, etc.) will also be deleted. ';
            }
            
            message += 'This action cannot be undone. Are you absolutely sure?';
            
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
            
            return true;
        });
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Focus on confirmation input
            const confirmationInput = document.querySelector('input[name="confirmation_text"]');
            if (confirmationInput) {
                confirmationInput.focus();
            }
        });
    </script>
</body>
</html>