<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
check_login();

// Ensure only admin can access
if ($_SESSION['user']['role_id'] != 1) {
    header('Location: login.php');
    exit;
}

// Handle subject deletion
// Handle subject deletion
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    
    // Check if subject exists
    $stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ?");
    $stmt->execute([$delete_id]);
    
    if ($stmt->fetch()) {
        try {
            // First, disable foreign key checks
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            // Delete the subject - cascades should handle related records
            $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
            $stmt->execute([$delete_id]);
            
            // Re-enable foreign key checks
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            $success_message = "Subject deleted successfully!";
        } catch (Exception $e) {
            // Re-enable foreign key checks in case of error
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            $error_message = "Error deleting subject: " . $e->getMessage();
        }
    } else {
        $error_message = "Subject not found!";
    }
}


// Fetch all subjects with teacher information
$subjects = $pdo->query("
    SELECT s.*, u.username as teacher_name, u.email as teacher_email,
           (SELECT COUNT(*) FROM student_subject WHERE subject_id = s.id AND status = 'approved') as enrolled_students,
           (SELECT COUNT(*) FROM assignments WHERE subject_id = s.id) as assignment_count,
           (SELECT COUNT(*) FROM projects WHERE subject_id = s.id) as project_count,
           (SELECT COUNT(*) FROM activities WHERE subject_id = s.id) as activity_count
    FROM subjects s
    LEFT JOIN users u ON s.teacher_id = u.id
    ORDER BY s.created_at DESC
")->fetchAll();

// Fetch all teachers for assignment
$teachers = $pdo->query("SELECT id, username, email FROM users WHERE role_id = 2 ORDER BY username")->fetchAll();

$subject_count = count($subjects);
$teacher_count = count($teachers);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subjects - Admin Panel</title>
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
        
        .dashboard-container {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .hover-lift {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .hover-lift:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
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
        
        .input-focus-effect {
            transition: all 0.3s ease;
        }
        
        .input-focus-effect:focus {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .tab-button {
            padding: 0.75rem 1.5rem;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
            background: transparent;
        }
        
        .tab-button.active {
            border-bottom-color: #4F46E5;
            color: #4F46E5;
            font-weight: 600;
        }
        
        .slide-in {
            animation: slideIn 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55) forwards;
        }
        
        @keyframes slideIn {
            0% { opacity: 0; transform: translateY(30px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 9999px;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .floating-element {
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body class="p-4">
    <!-- Background decorative elements -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="absolute -top-20 -left-20 w-64 h-64 bg-white rounded-full opacity-10 floating-element"></div>
        <div class="absolute top-1/3 -right-16 w-48 h-48 bg-purple-300 rounded-full opacity-10 floating-element" style="animation-delay: 1s;"></div>
        <div class="absolute bottom-1/4 left-1/4 w-32 h-32 bg-blue-300 rounded-full opacity-10 floating-element" style="animation-delay: 2s;"></div>
    </div>

    <!-- Header -->
    <header class="dashboard-container rounded-2xl shadow-2xl mb-6 z-10 relative">
        <div class="container mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <a href="admin_dashboard.php" class="mr-2 text-gray-600 hover:text-red-700 transition">
                        <div class="bg-gray-100 p-2 rounded-full hover:bg-red-100 transition">
                            <i class="fas fa-arrow-left"></i>
                        </div>
                    </a>
                    <div class="bg-gradient-to-r from-red-600 to-pink-600 p-2 rounded-lg">
                        <i class="fas fa-book text-white text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Manage Subjects</h1>
                        <p class="text-gray-600 text-sm">Admin Panel - System Subjects Management</p>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <div class="text-right hidden md:block">
                        <p class="font-medium text-gray-700"><?= htmlspecialchars($_SESSION['user']['username']) ?></p>
                        <p class="text-sm text-gray-500">Administrator</p>
                    </div>
                    <button onclick="openCreateModal()" class="btn-glow bg-gradient-to-r from-yellow-600 to-amber-600 text-white px-6 py-3 rounded-xl font-semibold hover:from-yellow-700 hover:to-amber-700 transition">
                        <i class="fas fa-plus mr-2"></i>
                        Create Subject
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-2 relative z-10">
        <!-- Success/Error Messages -->
        <?php if(isset($success_message)): ?>
        <div id="successMessage" class="mb-6 p-4 rounded-xl border-l-4 border-green-500 bg-gradient-to-r from-green-50 to-green-100 text-green-800 flex justify-between items-center slide-in shadow-lg">
            <div class="flex items-center space-x-3">
                <div class="bg-green-500 p-2 rounded-full">
                    <i class="fas fa-check-circle text-white"></i>
                </div>
                <div>
                    <p class="font-semibold">Success!</p>
                    <p><?= htmlspecialchars($success_message) ?></p>
                </div>
            </div>
            <button onclick="document.getElementById('successMessage').remove()" class="text-green-600 hover:text-green-800 transition">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        <?php endif; ?>
        
        <?php if(isset($error_message)): ?>
        <div id="errorMessage" class="mb-6 p-4 rounded-xl border-l-4 border-red-500 bg-gradient-to-r from-red-50 to-red-100 text-red-800 flex justify-between items-center slide-in shadow-lg">
            <div class="flex items-center space-x-3">
                <div class="bg-red-500 p-2 rounded-full">
                    <i class="fas fa-exclamation-circle text-white"></i>
                </div>
                <div>
                    <p class="font-semibold">Error!</p>
                    <p><?= htmlspecialchars($error_message) ?></p>
                </div>
            </div>
            <button onclick="document.getElementById('errorMessage').remove()" class="text-red-600 hover:text-red-800 transition">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            <div class="dashboard-container rounded-2xl shadow-lg p-6 hover-lift">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-gradient-to-r from-yellow-100 to-amber-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-book text-yellow-600 text-2xl"></i>
                    </div>
                    <span class="text-3xl font-bold text-yellow-600"><?= $subject_count ?></span>
                </div>
                <h4 class="text-lg font-semibold text-gray-800 mb-2">Total Subjects</h4>
                <p class="text-gray-600 text-sm">Active courses in system</p>
            </div>
            
            <div class="dashboard-container rounded-2xl shadow-lg p-6 hover-lift">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-gradient-to-r from-blue-100 to-cyan-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-chalkboard-teacher text-blue-600 text-2xl"></i>
                    </div>
                    <span class="text-3xl font-bold text-blue-600"><?= $teacher_count ?></span>
                </div>
                <h4 class="text-lg font-semibold text-gray-800 mb-2">Available Teachers</h4>
                <p class="text-gray-600 text-sm">Can be assigned to subjects</p>
            </div>
            
            <div class="dashboard-container rounded-2xl shadow-lg p-6 hover-lift">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-gradient-to-r from-green-100 to-emerald-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-user-graduate text-green-600 text-2xl"></i>
                    </div>
                    <span class="text-3xl font-bold text-green-600">
                        <?= array_sum(array_column($subjects, 'enrolled_students')) ?>
                    </span>
                </div>
                <h4 class="text-lg font-semibold text-gray-800 mb-2">Total Enrollments</h4>
                <p class="text-gray-600 text-sm">Students across all subjects</p>
            </div>
        </div>

        <!-- Subjects Table -->
        <div class="dashboard-container rounded-2xl shadow-lg overflow-hidden slide-in">
            <div class="bg-gradient-to-r from-yellow-50 to-amber-50 px-6 py-5 border-b border-yellow-100">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="text-2xl font-bold text-gray-800">All Subjects</h3>
                        <p class="text-gray-600">Manage system subjects and assignments</p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="relative">
                            <input type="text" id="searchInput" placeholder="Search subjects..." 
                                   class="input-focus-effect px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-yellow-300 focus:border-yellow-500">
                            <div class="absolute right-3 top-2.5 text-gray-400">
                                <i class="fas fa-search"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="p-6">
                <?php if($subject_count > 0): ?>
                    <div class="overflow-x-auto rounded-xl border border-gray-200 shadow-sm">
                        <table class="min-w-full divide-y divide-gray-200" id="subjectsTable">
                            <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Subject Details</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Teacher</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Statistics</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Created</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <?php foreach($subjects as $subject): ?>
                                <tr class="hover:bg-gradient-to-r hover:from-gray-50 hover:to-gray-100 transition-all duration-200">
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-gray-900 text-lg"><?= htmlspecialchars($subject['name']) ?></div>
                                        <?php if($subject['code']): ?>
                                            <div class="text-sm text-gray-600 mt-1">
                                                <span class="badge bg-gradient-to-r from-gray-100 to-gray-200 text-gray-800 border border-gray-300">
                                                    <?= htmlspecialchars($subject['code']) ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if($subject['description']): ?>
                                            <div class="text-sm text-gray-500 mt-2 max-w-md">
                                                <?= htmlspecialchars(substr($subject['description'], 0, 100)) ?>
                                                <?= strlen($subject['description']) > 100 ? '...' : '' ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if($subject['teacher_name']): ?>
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-cyan-500 rounded-full flex items-center justify-center text-white font-bold text-sm mr-3">
                                                    <?= strtoupper(substr(htmlspecialchars($subject['teacher_name']), 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($subject['teacher_name']) ?></div>
                                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($subject['teacher_email']) ?></div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge bg-gradient-to-r from-red-100 to-pink-200 text-red-800 border border-red-300">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                                No Teacher Assigned
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="grid grid-cols-2 gap-2">
                                            <div class="text-center">
                                                <div class="text-lg font-bold text-blue-600"><?= $subject['enrolled_students'] ?></div>
                                                <div class="text-xs text-gray-500">Students</div>
                                            </div>
                                            <div class="text-center">
                                                <div class="text-lg font-bold text-green-600">
                                                    <?= $subject['assignment_count'] + $subject['project_count'] + $subject['activity_count'] ?>
                                                </div>
                                                <div class="text-xs text-gray-500">Items</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-700">
                                        <?= date('M d, Y', strtotime($subject['created_at'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex space-x-2">
                                            <a href="edit_subject.php?id=<?= $subject['id'] ?>" 
                                               class="btn-glow inline-flex items-center px-4 py-2 rounded-xl text-sm font-medium bg-gradient-to-r from-yellow-500 to-amber-500 text-white hover:from-yellow-600 hover:to-amber-600 transition">
                                                <i class="fas fa-edit mr-2"></i>
                                                Edit
                                            </a>
                                            <button onclick="openDeleteModal(<?= $subject['id'] ?>, '<?= htmlspecialchars(addslashes($subject['name'])) ?>')"
                                                    class="btn-glow inline-flex items-center px-4 py-2 rounded-xl text-sm font-medium bg-gradient-to-r from-red-500 to-pink-500 text-white hover:from-red-600 hover:to-pink-600 transition">
                                                <i class="fas fa-trash-alt mr-2"></i>
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Table Summary -->
                    <div class="mt-6 p-4 bg-gradient-to-r from-gray-50 to-gray-100 rounded-xl border border-gray-200">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-center">
                            <div>
                                <div class="text-2xl font-bold text-blue-600"><?= array_sum(array_column($subjects, 'assignment_count')) ?></div>
                                <div class="text-sm text-gray-600">Total Assignments</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-green-600"><?= array_sum(array_column($subjects, 'project_count')) ?></div>
                                <div class="text-sm text-gray-600">Total Projects</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-yellow-600"><?= array_sum(array_column($subjects, 'activity_count')) ?></div>
                                <div class="text-sm text-gray-600">Total Activities</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-purple-600"><?= $subject_count ?></div>
                                <div class="text-sm text-gray-600">Active Subjects</div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <div class="w-24 h-24 bg-gradient-to-r from-gray-100 to-gray-200 rounded-full flex items-center justify-center mx-auto mb-6">
                            <i class="fas fa-book text-gray-400 text-4xl"></i>
                        </div>
                        <h4 class="text-2xl font-bold text-gray-800 mb-3">No Subjects Found</h4>
                        <p class="text-gray-600 mb-6 max-w-md mx-auto">There are no subjects created in the system yet. Create your first subject to get started.</p>
                        <button onclick="openCreateModal()" class="btn-glow inline-flex items-center space-x-2 bg-gradient-to-r from-yellow-600 to-amber-600 text-white px-6 py-3 rounded-xl font-semibold">
                            <i class="fas fa-plus mr-2"></i>
                            <span>Create First Subject</span>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Create Subject Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4">
            <div class="bg-gradient-to-r from-yellow-600 to-amber-600 p-6 text-white">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-2xl font-bold">Create New Subject</h2>
                        <p class="text-yellow-100">Add a new course to the system</p>
                    </div>
                    <button onclick="closeCreateModal()" class="text-white hover:text-yellow-200">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>
            
            <div class="p-6">
                <form id="createSubjectForm" action="add_subject.php" method="POST" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Subject Name *</label>
                            <input type="text" name="name" required 
                                   placeholder="e.g., Mathematics, Computer Science"
                                   class="input-focus-effect w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-yellow-300 focus:border-yellow-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Subject Code</label>
                            <input type="text" name="code" 
                                   placeholder="e.g., MATH101, CS101"
                                   class="input-focus-effect w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-yellow-300 focus:border-yellow-500">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Assign Teacher *</label>
                        <select name="teacher_id" required 
                                class="input-focus-effect w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-yellow-300 focus:border-yellow-500">
                            <option value="">Select a teacher...</option>
                            <?php foreach($teachers as $teacher): ?>
                                <option value="<?= $teacher['id'] ?>">
                                    <?= htmlspecialchars($teacher['username']) ?> (<?= htmlspecialchars($teacher['email']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" rows="3"
                                  placeholder="Enter subject description..."
                                  class="input-focus-effect w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-yellow-300 focus:border-yellow-500"></textarea>
                    </div>
                    
                    <div class="pt-4 border-t border-gray-200 flex justify-end space-x-3">
                        <button type="button" onclick="closeCreateModal()" 
                                class="px-6 py-3 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="btn-glow bg-gradient-to-r from-yellow-600 to-amber-600 text-white px-6 py-3 rounded-xl font-semibold hover:from-yellow-700 hover:to-amber-700 transition">
                            <i class="fas fa-plus mr-2"></i>
                            Create Subject
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4">
            <div class="bg-gradient-to-r from-red-600 to-pink-600 p-6 text-white">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-2xl font-bold">Confirm Deletion</h2>
                        <p class="text-red-100">This action cannot be undone</p>
                    </div>
                    <button onclick="closeDeleteModal()" class="text-white hover:text-red-200">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
            </div>
            
            <div class="p-6 text-center">
                <div class="w-16 h-16 bg-gradient-to-r from-red-100 to-pink-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                </div>
                
                <h3 class="text-xl font-bold text-gray-800 mb-2" id="deleteSubjectName"></h3>
                <p class="text-gray-600 mb-6">Are you sure you want to delete this subject? All related assignments, projects, activities, and student enrollments will also be deleted.</p>
                
                <div class="flex justify-center space-x-3">
                    <button onclick="closeDeleteModal()" 
                            class="px-6 py-3 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <a href="#" id="confirmDeleteBtn"
                       class="btn-glow bg-gradient-to-r from-red-600 to-pink-600 text-white px-6 py-3 rounded-xl font-semibold hover:from-red-700 hover:to-pink-700 transition">
                        <i class="fas fa-trash-alt mr-2"></i>
                        Delete Subject
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Modal Functions
        function openCreateModal() {
            document.getElementById('createModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeCreateModal() {
            document.getElementById('createModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }
        
        function openDeleteModal(subjectId, subjectName) {
            document.getElementById('deleteSubjectName').textContent = subjectName;
            document.getElementById('confirmDeleteBtn').href = `?delete_id=${subjectId}`;
            document.getElementById('deleteModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }
        
        // Close modals on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeCreateModal();
                closeDeleteModal();
            }
        });
        
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#subjectsTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Close modals when clicking outside
            document.querySelectorAll('.modal').forEach(modal => {
                modal.addEventListener('click', function(event) {
                    if (event.target === this) {
                        closeCreateModal();
                        closeDeleteModal();
                    }
                });
            });
            
            // Auto-focus search input
            document.getElementById('searchInput').focus();
        });
        
        // Form validation for create form
        document.getElementById('createSubjectForm').addEventListener('submit', function(event) {
            const name = this.elements['name'].value.trim();
            const teacher = this.elements['teacher_id'].value;
            
            if (!name) {
                event.preventDefault();
                alert('Subject name is required!');
                this.elements['name'].focus();
                return;
            }
            
            if (!teacher) {
                event.preventDefault();
                alert('Please select a teacher!');
                this.elements['teacher_id'].focus();
                return;
            }
        });
    </script>
</body>
</html>