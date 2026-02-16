<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
check_login();

// Only admin and teachers can edit subjects
if (!in_array($_SESSION['user']['role_id'], [1, 2])) {
    header('Location: login.php');
    exit;
}

$current_user_id = $_SESSION['user']['id'];
$current_user_role = $_SESSION['user']['role_id'];
$error = $success = '';

// Get subject ID from query string
$subject_id = $_GET['id'] ?? null;

if (!$subject_id) {
    header('Location: ' . ($current_user_role == 1 ? 'admin_dashboard.php' : 'teacher_dashboard.php'));
    exit;
}

// Fetch subject information
$stmt = $pdo->prepare("
    SELECT s.*, u.username as teacher_name, u.full_name as teacher_full_name
    FROM subjects s
    LEFT JOIN users u ON s.teacher_id = u.id
    WHERE s.id = ?
");
$stmt->execute([$subject_id]);
$subject = $stmt->fetch();

if (!$subject) {
    header('Location: ' . ($current_user_role == 1 ? 'admin_dashboard.php' : 'teacher_dashboard.php'));
    exit;
}

// Check permissions
if ($current_user_role == 2 && $subject['teacher_id'] != $current_user_id) {
    // Teacher can only edit their own subjects
    header('Location: teacher_dashboard.php');
    exit;
}

// Fetch all teachers for dropdown (only admin can change teacher assignment)
$teachers = [];
if ($current_user_role == 1) {
    $stmt = $pdo->prepare("SELECT id, username, full_name FROM users WHERE role_id = 2 AND is_active = 1 ORDER BY username");
    $stmt->execute();
    $teachers = $stmt->fetchAll();
}

// Fetch enrolled students
$stmt = $pdo->prepare("
    SELECT ss.*, u.username, u.full_name, u.email
    FROM student_subject ss
    JOIN users u ON ss.student_id = u.id
    WHERE ss.subject_id = ?
    ORDER BY u.username
");
$stmt->execute([$subject_id]);
$enrolled_students = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_subject'])) {
        $subject_code = trim($_POST['code']);
        $subject_name = trim($_POST['name']);
        $description = trim($_POST['description']);
       
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Admin can change teacher, teacher cannot
        if ($current_user_role == 1) {
            $teacher_id = $_POST['teacher_id'] ?: null;
        } else {
            $teacher_id = $subject['teacher_id'];
        }
        
        // Validation
        if (empty($subject_code)) {
            $error = "Subject code is required.";
        } elseif (empty($subject_name)) {
            $error = "Subject name is required.";
        } else {
            // Check for duplicate subject code (excluding current subject)
            $stmt = $pdo->prepare("SELECT id FROM subjects WHERE code = ? AND id != ?");
            $stmt->execute([$subject_code, $subject_id]);
            if ($stmt->fetch()) {
                $error = "Subject code already exists. Please choose a different one.";
            }
        }
        
        // If no errors, update subject
        if (empty($error)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE subjects 
                    SET code = ?, name = ?, description = ?, 
                        teacher_id = ?, is_active = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $subject_code, $subject_name, $description, 
                   $teacher_id, $is_active, $subject_id
                ]);
                
                $success = "Subject information updated successfully!";
                
                // Update the $subject array with new values
                $subject['code'] = $subject_code;
                $subject['name'] = $subject_name;
                $subject['description'] = $description;
                
               
                $subject['is_active'] = $is_active;
                if ($teacher_id) {
                    $subject['teacher_id'] = $teacher_id;
                }
                
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
    
    // Handle student enrollment
    elseif (isset($_POST['enroll_student'])) {
        $student_id = $_POST['student_id'] ?? null;
        
        if (!$student_id) {
            $error = "Please select a student to enroll.";
        } else {
            // Check if student is already enrolled
            $stmt = $pdo->prepare("SELECT id FROM student_subject WHERE subject_id = ? AND student_id = ?");
            $stmt->execute([$subject_id, $student_id]);
            
            if ($stmt->fetch()) {
                $error = "This student is already enrolled in the subject.";
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO student_subject (subject_id, student_id, applied_at) VALUES (?, ?, NOW())");
                    $stmt->execute([$subject_id, $student_id]);
                    
                    $success = "Student enrolled successfully!";
                    
                    // Refresh enrolled students list
                    $stmt = $pdo->prepare("
                        SELECT ss.*, u.username, u.full_name, u.email
                        FROM student_subject ss
                        JOIN users u ON ss.student_id = u.id
                        WHERE ss.subject_id = ?
                        ORDER BY u.username
                    ");
                    $stmt->execute([$subject_id]);
                    $enrolled_students = $stmt->fetchAll();
                    
                } catch (PDOException $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            }
        }
    }
    
    // Handle student removal
    elseif (isset($_POST['remove_student'])) {
        $remove_student_id = $_POST['remove_student_id'] ?? null;
        
        if ($remove_student_id) {
            try {
                $stmt = $pdo->prepare("DELETE FROM student_subject WHERE subject_id = ? AND student_id = ?");
                $stmt->execute([$subject_id, $remove_student_id]);
                
                $success = "Student removed from subject successfully!";
                
                // Refresh enrolled students list
                $stmt = $pdo->prepare("
                    SELECT ss.*, u.username, u.full_name, u.email
                    FROM student_subject ss
                    JOIN users u ON ss.student_id = u.id
                    WHERE ss.subject_id = ?
                    ORDER BY u.username
                ");
                $stmt->execute([$subject_id]);
                $enrolled_students = $stmt->fetchAll();
                
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Fetch available students for enrollment (only active students)
$stmt = $pdo->prepare("
    SELECT id, username, full_name, email 
    FROM users 
    WHERE role_id = 3 AND is_active = 1 
    AND id NOT IN (SELECT student_id FROM student_subject WHERE subject_id = ?)
    ORDER BY username
");
$stmt->execute([$subject_id]);
$available_students = $stmt->fetchAll();

// Get subject statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT ss.student_id) as total_students,
        COUNT(DISTINCT a.id) as total_assignments,
        COUNT(DISTINCT p.id) as total_projects,
        COUNT(DISTINCT act.id) as total_activities
    FROM subjects s
    LEFT JOIN student_subject ss ON s.id = ss.subject_id
    LEFT JOIN assignments a ON s.id = a.subject_id
    LEFT JOIN projects p ON s.id = p.subject_id
    LEFT JOIN activities act ON s.id = act.subject_id
    WHERE s.id = ?
");
$stmt->execute([$subject_id]);
$subject_stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Subject - <?= $current_user_role == 1 ? 'Admin' : 'Teacher' ?> Panel</title>
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
        
        .subject-badge {
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
        
        .stats-card {
            background: linear-gradient(to bottom right, #f8fafc, #f1f5f9);
            border: 1px solid #e2e8f0;
        }
        
        .student-card {
            transition: all 0.3s ease;
            border-left: 4px solid #3b82f6;
        }
        
        .student-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .subject-header {
            background: linear-gradient(to right, #4f46e5, #7c3aed);
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
                    <h1 class="text-3xl font-bold text-white">Edit Subject</h1>
                    <p class="text-gray-200">
                        <?= $current_user_role == 1 ? 'Administrator' : 'Teacher' ?> Panel - Subject Management
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
            <!-- Subject Header -->
            <div class="subject-header px-6 py-5">
                <div class="flex flex-col md:flex-row md:items-center justify-between">
                    <div class="flex items-center space-x-3 mb-4 md:mb-0">
                        <div class="bg-white p-3 rounded-xl">
                            <i class="fas fa-book-open text-indigo-600 text-xl"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-white"><?= htmlspecialchars($subject['name']) ?></h2>
                            <p class="text-indigo-100"><?= htmlspecialchars($subject['code']) ?></p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="bg-white/20 px-4 py-2 rounded-full">
                            <span class="text-white font-semibold">Subject ID: <?= $subject['id'] ?></span>
                        </div>
                        
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
                <!-- Subject Information -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="stats-card p-4 rounded-xl">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-blue-600 font-semibold">Enrolled Students</p>
                                <p class="text-2xl font-bold text-blue-800"><?= $subject_stats['total_students'] ?></p>
                            </div>
                            <div class="bg-blue-100 p-3 rounded-full">
                                <i class="fas fa-users text-blue-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-card p-4 rounded-xl">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-green-600 font-semibold">Assignments</p>
                                <p class="text-2xl font-bold text-green-800"><?= $subject_stats['total_assignments'] ?></p>
                            </div>
                            <div class="bg-green-100 p-3 rounded-full">
                                <i class="fas fa-tasks text-green-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    
                </div>

                <!-- Tabs -->
                <div class="mb-8">
                    <div class="flex space-x-2 overflow-x-auto pb-2">
                        <button type="button" 
                                class="tab-button active" 
                                onclick="showTab('details-tab')">
                            <i class="fas fa-edit mr-2"></i>
                            Edit Details
                        </button>
                        <button type="button" 
                                class="tab-button" 
                                onclick="showTab('students-tab')">
                            <i class="fas fa-user-graduate mr-2"></i>
                            Manage Students
                            <?php if (count($enrolled_students) > 0): ?>
                                <span class="ml-2 bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">
                                    <?= count($enrolled_students) ?>
                                </span>
                            <?php endif; ?>
                        </button>
                        <button type="button" 
                                class="tab-button" 
                                onclick="showTab('info-tab')">
                            <i class="fas fa-info-circle mr-2"></i>
                            Subject Info
                        </button>
                    </div>
                </div>

                <!-- Edit Details Tab -->
                <div id="details-tab" class="tab-content">
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="update_subject" value="1">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Subject Code -->
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-code mr-2"></i>
                                    Subject Code *
                                </label>
                                <input type="text" 
                                       name="code" 
                                       value="<?= htmlspecialchars($subject['code']) ?>" 
                                       required
                                       class="form-input"
                                       placeholder="e.g., CS101">
                            </div>
                            
                            <!-- Subject Name -->
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-book mr-2"></i>
                                    Subject Name *
                                </label>
                                <input type="text" 
                                       name="name" 
                                       value="<?= htmlspecialchars($subject['name']) ?>" 
                                       required
                                       class="form-input"
                                       placeholder="e.g., Introduction to Programming">
                            </div>
                            
                                                    
                           
                            
                           
                            
                            <!-- Teacher Assignment (Admin only) -->
                            <?php if ($current_user_role == 1): ?>
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-chalkboard-teacher mr-2"></i>
                                    Teacher
                                </label>
                                <select name="teacher_id" class="form-input">
                                    <option value="">No Teacher Assigned</option>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <option value="<?= $teacher['id'] ?>" 
                                                <?= $subject['teacher_id'] == $teacher['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($teacher['full_name'] ?: $teacher['username']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="text-gray-500 text-sm mt-2">
                                    Leave blank if no teacher is assigned yet.
                                </p>
                            </div>
                            <?php else: ?>
                            <!-- Show teacher info for teachers -->
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-user-tie mr-2"></i>
                                    Teacher
                                </label>
                                <div class="p-3 bg-gray-50 rounded-lg">
                                    <p class="font-semibold text-gray-800">
                                        <?= htmlspecialchars($subject['teacher_full_name'] ?: $subject['teacher_name']) ?>
                                    </p>
                                    <p class="text-gray-600 text-sm">You are assigned as the teacher for this subject.</p>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Subject Status -->
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-power-off mr-2"></i>
                                    Subject Status
                                </label>
                                <div class="mt-2">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox" 
                                               name="is_active" 
                                               value="1" 
                                               <?= $subject['is_active'] ? 'checked' : '' ?>
                                               class="sr-only peer">
                                        <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                                        <span class="ml-3 text-gray-700 font-medium">
                                            <?= $subject['is_active'] ? 'Subject is Active' : 'Subject is Inactive' ?>
                                        </span>
                                    </label>
                                    <p class="text-gray-500 text-sm mt-2">
                                        When inactive, students cannot access the subject content.
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Description -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-align-left mr-2"></i>
                                Description
                            </label>
                            <textarea name="description" 
                                      rows="4"
                                      class="form-input"
                                      placeholder="Enter subject description..."><?= htmlspecialchars($subject['description'] ?? '') ?></textarea>
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

                <!-- Manage Students Tab -->
                <div id="students-tab" class="tab-content hidden">
                    <div class="space-y-6">
                        <!-- Enroll New Student -->
                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-6 rounded-xl border border-blue-200">
                            <h3 class="text-lg font-bold text-gray-800 mb-4">
                                <i class="fas fa-user-plus mr-2"></i>
                                Enroll New Student
                            </h3>
                            
                            <form method="POST" class="space-y-4">
                                <div class="flex flex-col md:flex-row md:items-end gap-4">
                                    <div class="flex-1">
                                        <label class="form-label">Select Student</label>
                                        <select name="student_id" required class="form-input">
                                            <option value="">Choose a student...</option>
                                            <?php foreach ($available_students as $student): ?>
                                                <option value="<?= $student['id'] ?>">
                                                    <?= htmlspecialchars($student['full_name'] ?: $student['username']) ?> 
                                                    (<?= htmlspecialchars($student['email']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (empty($available_students)): ?>
                                            <p class="text-gray-500 text-sm mt-2">
                                                All active students are already enrolled in this subject.
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div>
                                        <button type="submit" 
                                                name="enroll_student"
                                                class="btn-glow px-6 py-3 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-xl hover:from-green-700 hover:to-emerald-700 transition font-semibold"
                                                <?= empty($available_students) ? 'disabled' : '' ?>>
                                            <i class="fas fa-user-plus mr-2"></i>
                                            Enroll Student
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Enrolled Students List -->
                        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                                <h3 class="text-lg font-bold text-gray-800">
                                    <i class="fas fa-users mr-2"></i>
                                    Enrolled Students
                                    <span class="text-gray-600 font-normal">(<?= count($enrolled_students) ?>)</span>
                                </h3>
                            </div>
                            
                            <?php if (count($enrolled_students) > 0): ?>
                                <div class="divide-y divide-gray-200">
                                    <?php foreach ($enrolled_students as $student): ?>
                                        <div class="student-card p-4 hover:bg-gray-50">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center space-x-3">
                                                    <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-indigo-500 rounded-full flex items-center justify-center text-white font-bold">
                                                        <?= strtoupper(substr(htmlspecialchars($student['username']), 0, 1)) ?>
                                                    </div>
                                                    <div>
                                                        <h4 class="font-semibold text-gray-800">
                                                            <?= htmlspecialchars($student['full_name'] ?: $student['username']) ?>
                                                        </h4>
                                                        <p class="text-sm text-gray-600">
                                                            <?= htmlspecialchars($student['email']) ?>
                                                        </p>
                                                        <p class="text-xs text-gray-500 mt-1">
                                                            Enrolled: <?= date('F j, Y', strtotime($student['enrolled_at'])) ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                
                                                <div>
                                                    <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to remove this student from the subject?')">
                                                        <input type="hidden" name="remove_student_id" value="<?= $student['student_id'] ?>">
                                                        <button type="submit" 
                                                                name="remove_student"
                                                                class="px-4 py-2 border border-red-300 text-red-600 rounded-lg hover:bg-red-50 transition">
                                                            <i class="fas fa-user-times mr-2"></i>
                                                            Remove
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-8">
                                    <div class="text-gray-400 mb-4">
                                        <i class="fas fa-users-slash text-4xl"></i>
                                    </div>
                                    <p class="text-gray-600">No students are enrolled in this subject yet.</p>
                                    <p class="text-gray-500 text-sm mt-2">Use the form above to enroll students.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Bulk Actions -->
                        <div class="bg-gradient-to-r from-yellow-50 to-amber-50 p-6 rounded-xl border border-yellow-200">
                            <h4 class="font-bold text-gray-800 mb-2">
                                <i class="fas fa-download mr-2"></i>
                                Export Student List
                            </h4>
                            <p class="text-gray-600 mb-4">Download the list of enrolled students for this subject.</p>
                            <a href="#" 
                               class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-yellow-500 to-orange-500 text-white rounded-lg hover:from-yellow-600 hover:to-orange-600 transition">
                                <i class="fas fa-file-excel mr-2"></i>
                                Export to Excel
                            </a>
                        </div>
                        
                        <!-- Back Button -->
                        <div class="flex justify-between pt-6 border-t border-gray-200">
                            <button type="button" 
                                    class="px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-semibold"
                                    onclick="showTab('details-tab')">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to Details
                            </button>
                            
                            <div class="text-right">
                                <p class="text-gray-600 text-sm">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    <?= count($enrolled_students) ?> student(s) enrolled
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Subject Info Tab -->
                <div id="info-tab" class="tab-content hidden">
                    <div class="space-y-6">
                        <!-- Subject Details -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-white rounded-xl border border-gray-200 p-6">
                                <h4 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    Subject Details
                                </h4>
                                <div class="space-y-3">
                                    <div>
                                        <p class="text-sm text-gray-600 font-semibold">Subject Code</p>
                                        <p class="text-gray-800"><?= htmlspecialchars($subject['subject_code']) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600 font-semibold">Subject Name</p>
                                        <p class="text-gray-800"><?= htmlspecialchars($subject['subject_name']) ?></p>
                                    </div>
                                    
                                    <div>
                                        <p class="text-sm text-gray-600 font-semibold">Status</p>
                                        <span class="subject-badge <?= $subject['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                                            <?= $subject['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-white rounded-xl border border-gray-200 p-6">
                                <h4 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                                    <i class="fas fa-chalkboard-teacher mr-2"></i>
                                    Teacher Information
                                </h4>
                                <?php if ($subject['teacher_id']): ?>
                                    <div class="flex items-center space-x-3 mb-4">
                                        <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-indigo-500 rounded-full flex items-center justify-center text-white font-bold">
                                            <?= strtoupper(substr(htmlspecialchars($subject['teacher_name']), 0, 1)) ?>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-800">
                                                <?= htmlspecialchars($subject['teacher_full_name'] ?: $subject['teacher_name']) ?>
                                            </p>
                                            <p class="text-sm text-gray-600">Teacher</p>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <div class="text-gray-400 mb-2">
                                            <i class="fas fa-user-slash text-3xl"></i>
                                        </div>
                                        <p class="text-gray-600">No teacher assigned</p>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mt-6">
                                    <h5 class="font-semibold text-gray-800 mb-2">Subject Statistics</h5>
                                    <div class="space-y-2">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Enrolled Students:</span>
                                            <span class="font-semibold"><?= $subject_stats['total_students'] ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Assignments:</span>
                                            <span class="font-semibold"><?= $subject_stats['total_assignments'] ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Projects:</span>
                                            <span class="font-semibold"><?= $subject_stats['total_projects'] ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Activities:</span>
                                            <span class="font-semibold"><?= $subject_stats['total_activities'] ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Description -->
                        <?php if (!empty($subject['description'])): ?>
                        <div class="bg-white rounded-xl border border-gray-200 p-6">
                            <h4 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                                <i class="fas fa-align-left mr-2"></i>
                                Description
                            </h4>
                            <div class="prose max-w-none">
                                <p class="text-gray-700 whitespace-pre-wrap"><?= nl2br(htmlspecialchars($subject['description'])) ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Timestamps -->
                        <div class="bg-gradient-to-r from-gray-50 to-gray-100 rounded-xl border border-gray-200 p-6">
                            <h4 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                                <i class="fas fa-history mr-2"></i>
                                Timestamps
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <p class="text-sm text-gray-600 font-semibold">Created At</p>
                                    <p class="text-gray-800">
                                        <?= date('F j, Y g:i A', strtotime($subject['created_at'])) ?>
                                    </p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600 font-semibold">Last Updated</p>
                                    <p class="text-gray-800">
                                        <?= date('F j, Y g:i A', strtotime($subject['updated_at'])) ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Danger Zone -->
                        <div class="bg-gradient-to-r from-red-50 to-pink-50 rounded-xl border border-red-200 p-6">
                            <h4 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                Danger Zone
                            </h4>
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="font-semibold text-gray-800">Delete Subject</p>
                                        <p class="text-gray-600 text-sm">
                                            Permanently delete this subject and all related data. This action cannot be undone.
                                        </p>
                                    </div>
                                    <a href="delete_subject.php?id=<?= $subject_id ?>" 
                                       class="px-4 py-2 bg-gradient-to-r from-red-600 to-pink-600 text-white rounded-lg hover:from-red-700 hover:to-pink-700 transition font-semibold"
                                       onclick="return confirm('⚠️ WARNING: This will delete the subject and all related assignments, submissions, and enrollments. Are you sure?')">
                                        <i class="fas fa-trash-alt mr-2"></i>
                                        Delete Subject
                                    </a>
                                </div>
                                
                                <?php if ($subject['is_active']): ?>
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="font-semibold text-gray-800">Deactivate Subject</p>
                                        <p class="text-gray-600 text-sm">
                                            Temporarily deactivate this subject. Students will not be able to access it.
                                        </p>
                                    </div>
                                    <form method="POST" class="inline" onsubmit="return confirm('Deactivate this subject? Students will lose access.')">
                                        <input type="hidden" name="update_subject" value="1">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="hidden" name="subject_code" value="<?= htmlspecialchars($subject['subject_code']) ?>">
                                        <input type="hidden" name="subject_name" value="<?= htmlspecialchars($subject['subject_name']) ?>">
                                        <input type="hidden" name="credits" value="<?= $subject['credits'] ?>">
                                        <input type="hidden" name="semester" value="<?= htmlspecialchars($subject['semester']) ?>">
                                        <input type="hidden" name="academic_year" value="<?= htmlspecialchars($subject['academic_year']) ?>">
                                        <input type="hidden" name="teacher_id" value="<?= $subject['teacher_id'] ?>">
                                        <button type="submit" 
                                                class="px-4 py-2 border-2 border-yellow-300 text-yellow-700 rounded-lg hover:bg-yellow-50 transition font-semibold">
                                            <i class="fas fa-ban mr-2"></i>
                                            Deactivate
                                        </button>
                                    </form>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Back Button -->
                        <div class="flex justify-between pt-6 border-t border-gray-200">
                            <button type="button" 
                                    class="px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-semibold"
                                    onclick="showTab('details-tab')">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to Details
                            </button>
                            
                            <?php if ($current_user_role == 1): ?>
                                <a href="admin_dashboard.php" 
                                   class="px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-semibold">
                                    <i class="fas fa-th-large mr-2"></i>
                                    Back to Dashboard
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
                'details-tab': 'Edit Details',
                'students-tab': 'Manage Students',
                'info-tab': 'Subject Info'
            };
            
            document.querySelectorAll('.tab-button').forEach(button => {
                if (button.textContent.includes(buttons[tabId])) {
                    button.classList.add('active');
                }
            });
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Check if there's a hash in the URL for tab navigation
            const hash = window.location.hash.substring(1);
            if (hash && ['details-tab', 'students-tab', 'info-tab'].includes(hash)) {
                showTab(hash);
            }
            
            // Auto-focus on first input in active tab
            const activeTab = document.querySelector('.tab-content:not(.hidden)');
            if (activeTab) {
                const firstInput = activeTab.querySelector('input, select, textarea');
                if (firstInput) {
                    firstInput.focus();
                }
            }
        });
        
        // Confirm before removing student
        document.addEventListener('submit', function(e) {
            if (e.target && e.target.name === 'remove_student') {
                if (!confirm('Are you sure you want to remove this student from the subject?')) {
                    e.preventDefault();
                }
            }
        });
    </script>
</body>
</html>