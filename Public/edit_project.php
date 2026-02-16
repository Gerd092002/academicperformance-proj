<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
check_login();

// Only admin and teachers can edit projects
if (!in_array($_SESSION['user']['role_id'], [1, 2])) {
    header('Location: login.php');
    exit;
}

$current_user_id = $_SESSION['user']['id'];
$current_user_role = $_SESSION['user']['role_id'];
$error = $success = '';
$project = [];
$subjects = [];

// Get project ID from query string
$project_id = $_GET['id'] ?? null;

if (!$project_id) {
    header('Location: ' . ($current_user_role == 1 ? 'admin_dashboard.php' : 'teacher_dashboard.php'));
    exit;
}

// Fetch project information - Note: Your table has both 'title' and 'name' columns
$stmt = $pdo->prepare("
    SELECT p.*, 
           s.code as subject_code, s.name as subject_name, s.teacher_id,
           u.username as teacher_name, u.full_name as teacher_full_name,
           uc.username as created_by_name, uc.full_name as created_by_full_name
    FROM projects p
    JOIN subjects s ON p.subject_id = s.id
    LEFT JOIN users u ON s.teacher_id = u.id
    LEFT JOIN users uc ON p.created_by = uc.id
    WHERE p.id = ?
");
$stmt->execute([$project_id]);
$project = $stmt->fetch();

if (!$project) {
    header('Location: ' . ($current_user_role == 1 ? 'admin_dashboard.php' : 'teacher_dashboard.php'));
    exit;
}

// Check permissions
if ($current_user_role == 2 && $project['teacher_id'] != $current_user_id) {
    // Teacher can only edit projects in their own subjects
    header('Location: teacher_dashboard.php');
    exit;
}

// Fetch all subjects for dropdown
if ($current_user_role == 1) {
    // Admin can assign to any subject
    $stmt = $pdo->prepare("
        SELECT s.*, u.username as teacher_name, u.full_name as teacher_full_name
        FROM subjects s
        LEFT JOIN users u ON s.teacher_id = u.id
        ORDER BY s.code
    ");
    $stmt->execute();
    $subjects = $stmt->fetchAll();
} else {
    // Teacher can only assign to their own subjects
    $stmt = $pdo->prepare("
        SELECT s.*, u.username as teacher_name, u.full_name as teacher_full_name
        FROM subjects s
        LEFT JOIN users u ON s.teacher_id = u.id
        WHERE s.teacher_id = ?
        ORDER BY s.code
    ");
    $stmt->execute([$current_user_id]);
    $subjects = $stmt->fetchAll();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_project'])) {
        $title = trim($_POST['title']);
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $due_date = $_POST['due_date'];
        $subject_id = $_POST['subject_id'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $total_points = (int)$_POST['total_points'];
        
        // Validation
        if (empty($title)) {
            $error = "Project title is required.";
        } elseif (empty($name)) {
            $error = "Project name is required.";
        } elseif (empty($due_date)) {
            $error = "Due date is required.";
        } elseif (strtotime($due_date) < strtotime('today')) {
            $error = "Due date cannot be in the past.";
        } elseif (empty($subject_id)) {
            $error = "Subject is required.";
        } elseif ($total_points <= 0) {
            $error = "Total points must be greater than 0.";
        }
        
        // If no errors, update project
        if (empty($error)) {
            try {
                $pdo->beginTransaction();
                
                // Check if created_by is already set, if not set it to current user
                $created_by = $project['created_by'] ?: $current_user_id;
                
                // Update project - include both title and name
                $stmt = $pdo->prepare("
                    UPDATE projects 
                    SET title = ?, name = ?, description = ?, due_date = ?, 
                        subject_id = ?, total_points = ?, is_active = ?, 
                        created_by = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $title, $name, $description, $due_date,
                    $subject_id, $total_points, $is_active,
                    $created_by, $project_id
                ]);
                
                $pdo->commit();
                
                $success = "Project updated successfully!";
                
                // Refresh project data
                $stmt = $pdo->prepare("
                    SELECT p.*, 
                           s.code as subject_code, s.name as subject_name, s.teacher_id,
                           u.username as teacher_name, u.full_name as teacher_full_name,
                           uc.username as created_by_name, uc.full_name as created_by_full_name
                    FROM projects p
                    JOIN subjects s ON p.subject_id = s.id
                    LEFT JOIN users u ON s.teacher_id = u.id
                    LEFT JOIN users uc ON p.created_by = uc.id
                    WHERE p.id = ?
                ");
                $stmt->execute([$project_id]);
                $project = $stmt->fetch();
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Database error: " . $e->getMessage();
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
    <title>Edit Project - <?= $current_user_role == 1 ? 'Admin' : 'Teacher' ?> Panel</title>
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
        
        .project-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .badge-active {
            background: linear-gradient(to right, #10b981, #059669);
            color: white;
        }
        
        .badge-inactive {
            background: linear-gradient(to right, #6b7280, #4b5563);
            color: white;
        }
        
        .badge-overdue {
            background: linear-gradient(to right, #ef4444, #dc2626);
            color: white;
        }
        
        .badge-upcoming {
            background: linear-gradient(to right, #f59e0b, #d97706);
            color: white;
        }
        
        .project-header {
            background: linear-gradient(to right, #4f46e5, #7c3aed);
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
        
        .info-card {
            background: linear-gradient(to bottom right, #f8fafc, #f1f5f9);
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 1rem;
        }
        
        .info-label {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            font-size: 1rem;
            color: #1f2937;
            font-weight: 600;
        }
    </style>
</head>
<body class="p-4 md:p-6">
    <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div class="flex items-center space-x-3">
                <a href="<?= $current_user_role == 1 ? 'admin_dashboard.php' : 'teacher_dashboard.php' ?>" 
                   class="text-gray-700 hover:text-indigo-700 transition">
                    <div class="bg-white p-2 rounded-lg shadow-sm hover-lift">
                        <i class="fas fa-arrow-left text-lg"></i>
                    </div>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-white">Edit Project</h1>
                    <p class="text-gray-200">
                        <?= $current_user_role == 1 ? 'Administrator' : 'Teacher' ?> Panel - Project Management
                    </p>
                </div>
            </div>
            
            <div class="flex items-center space-x-4">
                <div class="hidden md:block text-right">
                    <p class="font-medium text-white"><?= htmlspecialchars($_SESSION['user']['username']) ?></p>
                    <p class="text-gray-200 text-sm">
                        <?= $current_user_role == 1 ? 'Administrator' : ($current_user_role == 2 ? 'Teacher' : 'Student') ?>
                    </p>
                </div>
                <div class="w-10 h-10 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-full flex items-center justify-center text-white font-bold shadow-lg">
                    <?= strtoupper(substr(htmlspecialchars($_SESSION['user']['username']), 0, 1)) ?>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="glass-card rounded-2xl shadow-2xl overflow-hidden mb-6">
            <!-- Project Header -->
            <div class="project-header px-6 py-5">
                <div class="flex flex-col md:flex-row md:items-center justify-between">
                    <div class="flex items-center space-x-3 mb-4 md:mb-0">
                        <div class="bg-white p-3 rounded-xl">
                            <i class="fas fa-project-diagram text-indigo-600 text-xl"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-white"><?= htmlspecialchars($project['name']) ?></h2>
                            <p class="text-indigo-100">
                                <?= htmlspecialchars($project['subject_code']) ?>: <?= htmlspecialchars($project['subject_name']) ?>
                                <?php if (!empty($project['title'])): ?>
                                    <br><span class="text-indigo-200"><?= htmlspecialchars($project['title']) ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="bg-white/20 px-4 py-2 rounded-full">
                            <span class="text-white font-semibold">Project ID: <?= $project['id'] ?></span>
                        </div>
                        <?php
                        $due_date = strtotime($project['due_date']);
                        $today = strtotime('today');
                        $status_class = '';
                        $status_text = '';
                        
                        if ($project['is_active'] == 0) {
                            $status_class = 'badge-inactive';
                            $status_text = 'Inactive';
                        } elseif ($due_date < $today) {
                            $status_class = 'badge-overdue';
                            $status_text = 'Overdue';
                        } elseif ($due_date == $today) {
                            $status_class = 'badge-upcoming';
                            $status_text = 'Due Today';
                        } else {
                            $status_class = 'badge-active';
                            $status_text = 'Active';
                        }
                        ?>
                        <span class="project-badge <?= $status_class ?>">
                            <i class="fas fa-<?= $status_class == 'badge-overdue' ? 'exclamation-triangle' : ($status_class == 'badge-inactive' ? 'times-circle' : 'check-circle') ?> mr-2"></i>
                            <?= $status_text ?>
                        </span>
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
                <!-- Project Information Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="info-card">
                        <div class="info-label">Created By</div>
                        <div class="info-value">
                            <?= htmlspecialchars($project['created_by_full_name'] ?: $project['created_by_name'] ?: 'Not assigned') ?>
                        </div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Created Date</div>
                        <div class="info-value">
                            <?= date('F j, Y', strtotime($project['created_at'])) ?>
                        </div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Last Updated</div>
                        <div class="info-value">
                            <?= date('F j, Y g:i A', strtotime($project['updated_at'])) ?>
                        </div>
                    </div>
                </div>

                <form method="POST" class="space-y-6">
                    <input type="hidden" name="update_project" value="1">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Project Title (Short Title) -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-heading mr-2"></i>
                                Project Title *
                            </label>
                            <input type="text" 
                                   name="title" 
                                   value="<?= htmlspecialchars($project['title'] ?? '') ?>" 
                                   required
                                   class="form-input"
                                   placeholder="e.g., Analysis, Final Project">
                            <p class="text-gray-500 text-sm mt-1">Short title or code for the project</p>
                        </div>
                        
                        <!-- Project Name (Full Name) -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-file-signature mr-2"></i>
                                Project Name *
                            </label>
                            <input type="text" 
                                   name="name" 
                                   value="<?= htmlspecialchars($project['name']) ?>" 
                                   required
                                   class="form-input"
                                   placeholder="e.g., Capstone Project, E-commerce Website">
                            <p class="text-gray-500 text-sm mt-1">Full descriptive name of the project</p>
                        </div>
                        
                        <!-- Subject -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-book mr-2"></i>
                                Subject *
                            </label>
                            <select name="subject_id" required class="form-input" id="subjectSelect">
                                <option value="">Select Subject</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?= $subject['id'] ?>" 
                                            <?= $project['subject_id'] == $subject['id'] ? 'selected' : '' ?>
                                            data-teacher="<?= htmlspecialchars($subject['teacher_full_name'] ?: $subject['teacher_name'] ?: 'No teacher assigned') ?>">
                                        <?= htmlspecialchars($subject['code']) ?>: <?= htmlspecialchars($subject['name']) ?>
                                        <?php if ($subject['is_active'] != 1): ?>
                                            (Inactive)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Total Points -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-star mr-2"></i>
                                Total Points *
                            </label>
                            <input type="number" 
                                   name="total_points" 
                                   value="<?= $project['total_points'] ?>" 
                                   required
                                   min="1"
                                   max="1000"
                                   class="form-input"
                                   placeholder="e.g., 100">
                        </div>
                        
                        <!-- Due Date -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-calendar-times mr-2"></i>
                                Due Date *
                            </label>
                            <input type="date" 
                                   name="due_date" 
                                   value="<?= htmlspecialchars($project['due_date']) ?>" 
                                   required
                                   class="form-input">
                            <p class="text-gray-500 text-sm mt-1">Format: YYYY-MM-DD</p>
                        </div>
                        
                        <!-- Project Status -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-power-off mr-2"></i>
                                Project Status
                            </label>
                            <div class="mt-2">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" 
                                           name="is_active" 
                                           value="1" 
                                           <?= $project['is_active'] ? 'checked' : '' ?>
                                           class="sr-only peer">
                                    <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                                    <span class="ml-3 text-gray-700 font-medium">
                                        <?= $project['is_active'] ? 'Project is Active' : 'Project is Inactive' ?>
                                    </span>
                                </label>
                                <p class="text-gray-500 text-sm mt-2">
                                    When inactive, students cannot access this project.
                                </p>
                            </div>
                        </div>
                        
                        <!-- Teacher Info -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-user-tie mr-2"></i>
                                Teacher
                            </label>
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <p class="font-semibold text-gray-800" id="teacher-display">
                                    <?= htmlspecialchars($project['teacher_full_name'] ?: $project['teacher_name'] ?: 'No teacher assigned') ?>
                                </p>
                                <p class="text-gray-600 text-sm">
                                    <?= $current_user_role == 1 ? 'Will update based on selected subject' : 'You are the teacher for this project' ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Description -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-align-left mr-2"></i>
                            Project Description
                        </label>
                        <textarea name="description" 
                                  rows="4"
                                  class="form-input"
                                  placeholder="Brief description of the project..."><?= htmlspecialchars($project['description'] ?? '') ?></textarea>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="flex justify-between pt-6 border-t border-gray-200">
                        <a href="<?= $current_user_role == 1 ? 'admin_dashboard.php' : 'teacher_dashboard.php' ?>" 
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
        </div>
    </div>

    <script>
        // Update teacher display when subject changes
        document.getElementById('subjectSelect').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const teacherName = selectedOption.getAttribute('data-teacher');
            if (teacherName) {
                document.getElementById('teacher-display').textContent = teacherName;
            }
        });
        
        // Set min date for due date to today
        document.addEventListener('DOMContentLoaded', function() {
            const dueDateInput = document.querySelector('input[name="due_date"]');
            if (dueDateInput) {
                const today = new Date().toISOString().split('T')[0];
                dueDateInput.min = today;
            }
        });
    </script>
</body>
</html>