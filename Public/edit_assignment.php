<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
check_login();

// Only admin and teachers can edit assignments
if (!in_array($_SESSION['user']['role_id'], [1, 2])) {
    header('Location: login.php');
    exit;
}

$current_user_id = $_SESSION['user']['id'];
$current_user_role = $_SESSION['user']['role_id'];
$error = $success = '';
$submissions = [];

// Get assignment ID from query string
$assignment_id = $_GET['id'] ?? null;

if (!$assignment_id) {
    header('Location: ' . ($current_user_role == 1 ? 'admin_dashboard.php' : 'teacher_dashboard.php'));
    exit;
}

// Fetch assignment information with subject details
$stmt = $pdo->prepare("
    SELECT a.*, 
           s.code, s.name, s.teacher_id,
           u.username as teacher_name, u.full_name as teacher_full_name
    FROM assignments a
    JOIN subjects s ON a.subject_id = s.id
    LEFT JOIN users u ON s.teacher_id = u.id
    WHERE a.id = ?
");
$stmt->execute([$assignment_id]);
$assignment = $stmt->fetch();

if (!$assignment) {
    header('Location: ' . ($current_user_role == 1 ? 'admin_dashboard.php' : 'teacher_dashboard.php'));
    exit;
}

// Check permissions
if ($current_user_role == 2 && $assignment['teacher_id'] != $current_user_id) {
    // Teacher can only edit assignments in their own subjects
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
        WHERE s.is_active = 1
        ORDER BY s.code  -- Changed from subject_code to code
    ");
    $stmt->execute();
    $subjects = $stmt->fetchAll();
} else {
    // Teacher can only assign to their own subjects
    $stmt = $pdo->prepare("
        SELECT s.*, u.username as teacher_name, u.full_name as teacher_full_name
        FROM subjects s
        LEFT JOIN users u ON s.teacher_id = u.id
        WHERE s.teacher_id = ? AND s.is_active = 1
        ORDER BY s.code
    ");
    $stmt->execute([$current_user_id]);
    $subjects = $stmt->fetchAll();
}

// Fetch assignment submissions
$stmt = $pdo->prepare("
    SELECT s.*, u.username, u.full_name, u.email
    FROM submissions s
    JOIN users u ON s.student_id = u.id
    WHERE s.assignment_id = ?
    ORDER BY s.submitted_at DESC
");
$stmt->execute([$assignment_id]);
$submissions = $stmt->fetchAll();

// Handle form submission
if (isset($_POST['update_assignment'])) {
    $name = trim($_POST['title']);  // Changed from $title to $name
    $description = trim($_POST['description']);
    $instructions = trim($_POST['instructions']);
    $total_points = (float)$_POST['max_points'];  // Changed from $max_points to $total_points
    $due_date = $_POST['due_date'];
    $subject_id = $_POST['subject_id'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validation
    if (empty($name)) {  // Changed from $title to $name
        $error = "Assignment title is required.";
    } elseif (empty($total_points) || $total_points <= 0) {  // Changed from $max_points to $total_points
        $error = "Maximum points must be greater than 0.";
    } elseif (empty($due_date)) {
        $error = "Due date is required.";
    } elseif (strtotime($due_date) < strtotime('today')) {
        $error = "Due date cannot be in the past.";
    } elseif (empty($subject_id)) {
        $error = "Subject is required.";
    }
    
    // If no errors, update assignment
    if (empty($error)) {
        try {
            // Check if assignments table has 'instructions' column
            // If not, we need to adjust the query
            $stmt = $pdo->prepare("
                UPDATE assignments 
                SET name = ?, description = ?, total_points = ?, 
                    due_date = ?, subject_id = ?, is_active = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            
            $stmt->execute([
                $name, $description, $total_points,  // Changed parameters
                $due_date, $subject_id, $is_active, $assignment_id
            ]);
            
            $success = "Assignment updated successfully!";
            
            // Update the $assignment array with new values
            $assignment['name'] = $name;
            $assignment['description'] = $description;
            $assignment['total_points'] = $total_points;
            $assignment['due_date'] = $due_date;
            $assignment['subject_id'] = $subject_id;
            $assignment['is_active'] = $is_active;
            
            // Update subject info if changed
            foreach ($subjects as $subject) {
                if ($subject['id'] == $subject_id) {
                    $assignment['code'] = $subject['code'];
                    $assignment['subject_name'] = $subject['name'];
                    $assignment['teacher_name'] = $subject['teacher_name'];
                    $assignment['teacher_full_name'] = $subject['teacher_full_name'];
                    break;
                }
            }
            
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
    
    // Handle grade submission
    elseif (isset($_POST['update_grade'])) {
        $submission_id = $_POST['submission_id'];
        $grade = $_POST['grade'];
        $feedback = trim($_POST['feedback'] ?? '');
        
        if (!is_numeric($grade) || $grade < 0 || $grade > $assignment['max_points']) {
            $error = "Grade must be between 0 and " . $assignment['max_points'];
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE submissions 
                    SET grade = ?, feedback = ?, graded_at = NOW() 
                    WHERE id = ?
                ");
                
                $stmt->execute([$grade, $feedback, $submission_id]);
                
                $success = "Grade updated successfully!";
                
                // Refresh submissions list
                $stmt = $pdo->prepare("
                    SELECT s.*, u.username, u.full_name, u.email
                    FROM submissions s
                    JOIN users u ON s.student_id = u.id
                    WHERE s.assignment_id = ?
                    ORDER BY s.submitted_at DESC
                ");
                $stmt->execute([$assignment_id]);
                $submissions = $stmt->fetchAll();
                
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
    
    // Handle submission deletion
    elseif (isset($_POST['delete_submission'])) {
        $submission_id = $_POST['delete_submission_id'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM submissions WHERE id = ?");
            $stmt->execute([$submission_id]);
            
            $success = "Submission deleted successfully!";
            
            // Refresh submissions list
            $stmt = $pdo->prepare("
                SELECT s.*, u.username, u.full_name, u.email
                FROM submissions s
                JOIN users u ON s.student_id = u.id
                WHERE s.assignment_id = ?
                ORDER BY s.submitted_at DESC
            ");
            $stmt->execute([$assignment_id]);
            $submissions = $stmt->fetchAll();
            
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }


// Get assignment statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_submissions,
        COUNT(CASE WHEN score IS NOT NULL THEN 1 END) as graded_submissions,
        AVG(score) as average_grade,
        MIN(score) as min_grade,
        MAX(score) as max_grade
    FROM submissions 
    WHERE assignment_id = ?
");
$stmt->execute([$assignment_id]);
$assignment_stats = $stmt->fetch();

// Calculate submission rate
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT ss.student_id) as total_students
    FROM student_subject ss
    WHERE ss.subject_id = ?
");
$stmt->execute([$assignment['subject_id']]);
$total_students = $stmt->fetch()['total_students'];
$submission_rate = $total_students > 0 ? ($assignment_stats['total_submissions'] / $total_students) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Assignment - <?= $current_user_role == 1 ? 'Admin' : 'Teacher' ?> Panel</title>
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
        
        .assignment-badge {
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
        
        .submission-card {
            transition: all 0.3s ease;
            border-left: 4px solid #3b82f6;
        }
        
        .submission-card.graded {
            border-left-color: #10b981;
        }
        
        .submission-card.overdue {
            border-left-color: #ef4444;
        }
        
        .submission-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .assignment-header {
            background: linear-gradient(to right, #4f46e5, #7c3aed);
        }
        
        .grade-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.25rem;
        }
        
        .grade-a { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .grade-b { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; }
        .grade-c { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
        .grade-d { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
        
        .progress-bar {
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(to right, #3b82f6, #2563eb);
            border-radius: 4px;
            transition: width 0.3s ease;
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
                    <h1 class="text-3xl font-bold text-white">Edit Assignment</h1>
                    <p class="text-gray-200">
                        <?= $current_user_role == 1 ? 'Administrator' : 'Teacher' ?> Panel - Assignment Management
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
            <!-- Assignment Header -->
            <div class="assignment-header px-6 py-5">
                <div class="flex flex-col md:flex-row md:items-center justify-between">
                    <div class="flex items-center space-x-3 mb-4 md:mb-0">
                        <div class="bg-white p-3 rounded-xl">
                            <i class="fas fa-tasks text-indigo-600 text-xl"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-white"><?= htmlspecialchars($assignment['name']) ?></h2>
                            <p class="text-indigo-100"><?= htmlspecialchars($assignment['code']) ?>: <?= htmlspecialchars($assignment['name']) ?></p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="bg-white/20 px-4 py-2 rounded-full">
                            <span class="text-white font-semibold">Assignment ID: <?= $assignment['id'] ?></span>
                        </div>
                        <?php
                        $due_date = strtotime($assignment['due_date']);
                        $today = strtotime('today');
                        $status_class = '';
                        $status_text = '';
                        
                        if ($assignment['is_active'] == 0) {
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
                        <span class="assignment-badge <?= $status_class ?>">
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
                <!-- Assignment Statistics -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                    <div class="stats-card p-4 rounded-xl">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-blue-600 font-semibold">Submissions</p>
                                <p class="text-2xl font-bold text-blue-800"><?= $assignment_stats['total_submissions'] ?></p>
                                <p class="text-xs text-gray-600 mt-1">
                                    <?= number_format($submission_rate, 1) ?>% submission rate
                                </p>
                            </div>
                            <div class="bg-blue-100 p-3 rounded-full">
                                <i class="fas fa-file-upload text-blue-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="progress-bar mt-2">
                            <div class="progress-fill" style="width: <?= min($submission_rate, 100) ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="stats-card p-4 rounded-xl">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-green-600 font-semibold">Graded</p>
                                <p class="text-2xl font-bold text-green-800"><?= $assignment_stats['graded_submissions'] ?></p>
                                <p class="text-xs text-gray-600 mt-1">
                                    of <?= $assignment_stats['total_submissions'] ?>
                                </p>
                            </div>
                            <div class="bg-green-100 p-3 rounded-full">
                                <i class="fas fa-check-circle text-green-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-card p-4 rounded-xl">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-purple-600 font-semibold">Average Grade</p>
                                <p class="text-2xl font-bold text-purple-800">
                                    <?= $assignment_stats['average_grade'] ? number_format($assignment_stats['average_grade'], 1) : 'N/A' ?>
                                </p>
                                
                            </div>
                            <div class="bg-purple-100 p-3 rounded-full">
                                <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-card p-4 rounded-xl">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-yellow-600 font-semibold">Max Points</p>
                                <p class="text-2xl font-bold text-yellow-800"><?= $assignment['total_points'] ?></p>
                                <p class="text-xs text-gray-600 mt-1">
                                    <?= $assignment['due_date'] ?>
                                </p>
                            </div>
                            <div class="bg-yellow-100 p-3 rounded-full">
                                <i class="fas fa-star text-yellow-600 text-xl"></i>
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
                                onclick="showTab('submissions-tab')">
                            <i class="fas fa-file-upload mr-2"></i>
                            Submissions
                            <?php if (count($submissions) > 0): ?>
                                <span class="ml-2 bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">
                                    <?= count($submissions) ?>
                                </span>
                            <?php endif; ?>
                        </button>
                        <button type="button" 
                                class="tab-button" 
                                onclick="showTab('grades-tab')">
                            <i class="fas fa-chart-bar mr-2"></i>
                            Grade Analysis
                        </button>
                    </div>
                </div>

                <!-- Edit Details Tab -->
                <div id="details-tab" class="tab-content">
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="update_assignment" value="1">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Assignment Title -->
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-heading mr-2"></i>
                                    Assignment Title *
                                </label>
                                <input type="text" 
                                       name="title" 
                                       value="<?= htmlspecialchars($assignment['name']) ?>" 
                                       required
                                       class="form-input"
                                       placeholder="e.g., Midterm Exam">
                            </div>
                            
                            <!-- Subject -->
<div class="form-group">
    <label class="form-label">
        <i class="fas fa-book mr-2"></i>
        Subject *
    </label>
    <select name="subject_id" required class="form-input">  <!-- Changed from name="id" to name="subject_id" -->
        <option value="">Select Subject</option>
        <?php foreach ($subjects as $subject): ?>
            <option value="<?= $subject['id'] ?>" 
                    <?= $assignment['subject_id'] == $subject['id'] ? 'selected' : '' ?>
                    data-teacher="<?= htmlspecialchars($subject['teacher_full_name'] ?: $subject['teacher_name']) ?>">
                <?= htmlspecialchars($subject['code']) ?>: <?= htmlspecialchars($subject['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
                            
                            <!-- Max Points -->
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-star mr-2"></i>
                                    Maximum Points *
                                </label>
                                <input type="number" 
                                       name="max_points" 
                                       value="<?= $assignment['total_points'] ?>" 
                                       required
                                       min="1"
                                       max="1000"
                                       step="0.5"
                                       class="form-input"
                                       placeholder="e.g., 100">
                            </div>
                            
                            <!-- Due Date -->
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-calendar-times mr-2"></i>
                                    Due Date *
                                </label>
                                <input type="datetime-local" 
                                       name="due_date" 
                                       value="<?= date('Y-m-d\TH:i', strtotime($assignment['due_date'])) ?>" 
                                       required
                                       class="form-input">
                            </div>
                            
                            <!-- Assignment Status -->
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-power-off mr-2"></i>
                                    Assignment Status
                                </label>
                                <div class="mt-2">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox" 
                                               name="is_active" 
                                               value="1" 
                                               <?= $assignment['is_active'] ? 'checked' : '' ?>
                                               class="sr-only peer">
                                        <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                                        <span class="ml-3 text-gray-700 font-medium">
                                            <?= $assignment['is_active'] ? 'Assignment is Active' : 'Assignment is Inactive' ?>
                                        </span>
                                    </label>
                                    <p class="text-gray-500 text-sm mt-2">
                                        When inactive, students cannot submit to this assignment.
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Teacher Info (Read-only) -->
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-user-tie mr-2"></i>
                                    Teacher
                                </label>
                                <div class="p-3 bg-gray-50 rounded-lg">
                                    <p class="font-semibold text-gray-800" id="teacher-display">
                                        <?= htmlspecialchars($assignment['teacher_full_name'] ?: $assignment['teacher_name']) ?>
                                    </p>
                                    <p class="text-gray-600 text-sm">
                                        <?= $current_user_role == 1 ? 'Will update based on selected subject' : 'You are the teacher for this assignment' ?>
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
                                      rows="3"
                                      class="form-input"
                                      placeholder="Brief description of the assignment..."><?= htmlspecialchars($assignment['description'] ?? '') ?></textarea>
                        </div>
                        
                        <!-- Instructions -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-list-ol mr-2"></i>
                                Instructions
                            </label>
                            <textarea name="instructions" 
                                      rows="5"
                                      class="form-input"
                                      placeholder="Detailed instructions for students..."><?= htmlspecialchars($assignment['instructions'] ?? '') ?></textarea>
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

                <!-- Submissions Tab -->
                <div id="submissions-tab" class="tab-content hidden">
                    <div class="space-y-6">
                        <?php if (count($submissions) > 0): ?>
                            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                                    <div class="flex justify-between items-center">
                                        <h3 class="text-lg font-bold text-gray-800">
                                            <i class="fas fa-file-upload mr-2"></i>
                                            Student Submissions
                                            <span class="text-gray-600 font-normal">(<?= count($submissions) ?>)</span>
                                        </h3>
                                        <div class="text-sm text-gray-600">
                                            <?= $assignment_stats['graded_submissions'] ?> graded, 
                                            <?= $assignment_stats['total_submissions'] - $assignment_stats['graded_submissions'] ?> pending
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="divide-y divide-gray-200">
                                    <?php foreach ($submissions as $submission): ?>
                                        <?php
                                        $is_overdue = strtotime($submission['submitted_at']) > strtotime($assignment['due_date']);
                                        $card_class = $submission['grade'] !== null ? 'graded' : ($is_overdue ? 'overdue' : '');
                                        ?>
                                        <div class="submission-card <?= $card_class ?> p-4 hover:bg-gray-50">
                                            <div class="flex flex-col md:flex-row md:items-center justify-between">
                                                <div class="flex items-center space-x-3 mb-4 md:mb-0">
                                                    <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-indigo-500 rounded-full flex items-center justify-center text-white font-bold">
                                                        <?= strtoupper(substr(htmlspecialchars($submission['username']), 0, 1)) ?>
                                                    </div>
                                                    <div>
                                                        <h4 class="font-semibold text-gray-800">
                                                            <?= htmlspecialchars($submission['full_name'] ?: $submission['username']) ?>
                                                        </h4>
                                                        <p class="text-sm text-gray-600">
                                                            <?= htmlspecialchars($submission['email']) ?>
                                                        </p>
                                                        <p class="text-xs text-gray-500 mt-1">
                                                            Submitted: <?= date('F j, Y g:i A', strtotime($submission['submitted_at'])) ?>
                                                            <?php if ($is_overdue): ?>
                                                                <span class="text-red-600 font-semibold ml-2">
                                                                    <i class="fas fa-clock mr-1"></i>Late
                                                                </span>
                                                            <?php endif; ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                
                                                <div class="flex items-center space-x-4">
                                                    <?php if ($submission['grade'] !== null): ?>
                                                        <div class="text-center">
                                                            <?php
                                                            $percentage = ($submission['grade'] / $assignment['max_points']) * 100;
                                                            $grade_class = 'grade-';
                                                            if ($percentage >= 90) $grade_class .= 'a';
                                                            elseif ($percentage >= 80) $grade_class .= 'b';
                                                            elseif ($percentage >= 70) $grade_class .= 'c';
                                                            else $grade_class .= 'd';
                                                            ?>
                                                            <div class="grade-circle <?= $grade_class ?> mx-auto">
                                                                <?= $submission['grade'] ?>
                                                            </div>
                                                            <p class="text-xs text-gray-600 mt-1">
                                                                <?= number_format($percentage, 1) ?>%
                                                            </p>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-yellow-600 font-semibold">
                                                            <i class="fas fa-clock mr-2"></i>
                                                            Not Graded
                                                        </span>
                                                    <?php endif; ?>
                                                    
                                                    <div class="flex space-x-2">
                                                        <!-- Grade/Edit Button -->
                                                        <button type="button" 
                                                                onclick="openGradeModal(<?= $submission['id'] ?>, '<?= htmlspecialchars($submission['full_name'] ?: $submission['username']) ?>', <?= $submission['grade'] ? $submission['grade'] : 'null' ?>, `<?= htmlspecialchars($submission['feedback'] ?? '') ?>`)"
                                                                class="px-4 py-2 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-lg hover:from-green-700 hover:to-emerald-700 transition">
                                                            <i class="fas fa-<?= $submission['grade'] ? 'edit' : 'grade' ?> mr-2"></i>
                                                            <?= $submission['grade'] ? 'Edit Grade' : 'Grade' ?>
                                                        </button>
                                                        
                                                        <!-- Delete Button -->
                                                        <form method="POST" class="inline" onsubmit="return confirm('Delete this submission? This cannot be undone.')">
                                                            <input type="hidden" name="delete_submission_id" value="<?= $submission['id'] ?>">
                                                            <button type="submit" 
                                                                    name="delete_submission"
                                                                    class="px-4 py-2 border border-red-300 text-red-600 rounded-lg hover:bg-red-50 transition">
                                                                <i class="fas fa-trash-alt mr-2"></i>
                                                                Delete
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <?php if (!empty($submission['feedback'])): ?>
                                                <div class="mt-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
                                                    <p class="text-sm font-semibold text-gray-800 mb-1">Feedback:</p>
                                                    <p class="text-gray-700 whitespace-pre-wrap"><?= nl2br(htmlspecialchars($submission['feedback'])) ?></p>
                                                    <?php if ($submission['graded_at']): ?>
                                                        <p class="text-xs text-gray-500 mt-2">
                                                            Graded: <?= date('F j, Y g:i A', strtotime($submission['graded_at'])) ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-12 bg-white rounded-xl border border-gray-200">
                                <div class="text-gray-400 mb-4">
                                    <i class="fas fa-inbox text-4xl"></i>
                                </div>
                                <h3 class="text-xl font-bold text-gray-700 mb-2">No Submissions Yet</h3>
                                <p class="text-gray-600 mb-6">Students haven't submitted any work for this assignment.</p>
                                <div class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-500 to-indigo-500 text-white rounded-lg">
                                    <i class="fas fa-clock mr-2"></i>
                                    Due: <?= date('F j, Y g:i A', strtotime($assignment['due_date'])) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Export Options -->
                        <div class="bg-gradient-to-r from-yellow-50 to-amber-50 p-6 rounded-xl border border-yellow-200">
                            <h4 class="font-bold text-gray-800 mb-2">
                                <i class="fas fa-download mr-2"></i>
                                Export Options
                            </h4>
                            <p class="text-gray-600 mb-4">Download submission data for this assignment.</p>
                            <div class="flex flex-wrap gap-3">
                                <a href="#" class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-green-500 to-emerald-500 text-white rounded-lg hover:from-green-600 hover:to-emerald-600 transition">
                                    <i class="fas fa-file-excel mr-2"></i>
                                    Export Grades
                                </a>
                                <a href="#" class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-500 to-indigo-500 text-white rounded-lg hover:from-blue-600 hover:to-indigo-600 transition">
                                    <i class="fas fa-file-pdf mr-2"></i>
                                    Export Submissions
                                </a>
                                <a href="#" class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-purple-500 to-pink-500 text-white rounded-lg hover:from-purple-600 hover:to-pink-600 transition">
                                    <i class="fas fa-chart-bar mr-2"></i>
                                    Export Analytics
                                </a>
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
                            
                            <div class="text-right">
                                <p class="text-gray-600 text-sm">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    <?= count($submissions) ?> submission(s), 
                                    <?= $assignment_stats['graded_submissions'] ?> graded
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grade Analysis Tab -->
                <div id="grades-tab" class="tab-content hidden">
                    <div class="space-y-6">
                        <!-- Grade Distribution -->
                        <div class="bg-white rounded-xl border border-gray-200 p-6">
                            <h4 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                                <i class="fas fa-chart-pie mr-2"></i>
                                Grade Distribution
                            </h4>
                            
                            <?php if ($assignment_stats['total_submissions'] > 0 && $assignment_stats['graded_submissions'] > 0): ?>
                                <?php
                                // Calculate grade distribution
                                $stmt = $pdo->prepare("
                                    SELECT 
                                        COUNT(CASE WHEN grade >= ? * 0.9 THEN 1 END) as a_count,
                                        COUNT(CASE WHEN grade >= ? * 0.8 AND grade < ? * 0.9 THEN 1 END) as b_count,
                                        COUNT(CASE WHEN grade >= ? * 0.7 AND grade < ? * 0.8 THEN 1 END) as c_count,
                                        COUNT(CASE WHEN grade >= ? * 0.6 AND grade < ? * 0.7 THEN 1 END) as d_count,
                                        COUNT(CASE WHEN grade < ? * 0.6 THEN 1 END) as f_count
                                    FROM submissions 
                                    WHERE assignment_id = ? AND grade IS NOT NULL
                                ");
                                $stmt->execute([
                                    $assignment['max_points'], $assignment['max_points'], $assignment['max_points'],
                                    $assignment['max_points'], $assignment['max_points'], $assignment['max_points'],
                                    $assignment['max_points'], $assignment['max_points'], $assignment_id
                                ]);
                                $grade_dist = $stmt->fetch();
                                
                                $total_graded = $grade_dist['a_count'] + $grade_dist['b_count'] + $grade_dist['c_count'] + $grade_dist['d_count'] + $grade_dist['f_count'];
                                ?>
                                
                                <div class="space-y-4">
                                    <!-- A Grades -->
                                    <div>
                                        <div class="flex justify-between mb-1">
                                            <span class="font-semibold text-green-700">A (90-100%)</span>
                                            <span class="font-semibold"><?= $grade_dist['a_count'] ?> (<?= $total_graded > 0 ? number_format(($grade_dist['a_count'] / $total_graded) * 100, 1) : 0 ?>%)</span>
                                        </div>
                                        <div class="progress-bar">
                                            <div class="progress-fill bg-gradient-to-r from-green-500 to-emerald-500" 
                                                 style="width: <?= $total_graded > 0 ? ($grade_dist['a_count'] / $total_graded) * 100 : 0 ?>%"></div>
                                        </div>
                                    </div>
                                    
                                    <!-- B Grades -->
                                    <div>
                                        <div class="flex justify-between mb-1">
                                            <span class="font-semibold text-blue-700">B (80-89%)</span>
                                            <span class="font-semibold"><?= $grade_dist['b_count'] ?> (<?= $total_graded > 0 ? number_format(($grade_dist['b_count'] / $total_graded) * 100, 1) : 0 ?>%)</span>
                                        </div>
                                        <div class="progress-bar">
                                            <div class="progress-fill bg-gradient-to-r from-blue-500 to-indigo-500" 
                                                 style="width: <?= $total_graded > 0 ? ($grade_dist['b_count'] / $total_graded) * 100 : 0 ?>%"></div>
                                        </div>
                                    </div>
                                    
                                    <!-- C Grades -->
                                    <div>
                                        <div class="flex justify-between mb-1">
                                            <span class="font-semibold text-yellow-700">C (70-79%)</span>
                                            <span class="font-semibold"><?= $grade_dist['c_count'] ?> (<?= $total_graded > 0 ? number_format(($grade_dist['c_count'] / $total_graded) * 100, 1) : 0 ?>%)</span>
                                        </div>
                                        <div class="progress-bar">
                                            <div class="progress-fill bg-gradient-to-r from-yellow-500 to-orange-500" 
                                                 style="width: <?= $total_graded > 0 ? ($grade_dist['c_count'] / $total_graded) * 100 : 0 ?>%"></div>
                                        </div>
                                    </div>
                                    
                                    <!-- D/F Grades -->
                                    <div>
                                        <div class="flex justify-between mb-1">
                                            <span class="font-semibold text-red-700">D/F (Below 70%)</span>
                                            <span class="font-semibold"><?= $grade_dist['d_count'] + $grade_dist['f_count'] ?> (<?= $total_graded > 0 ? number_format((($grade_dist['d_count'] + $grade_dist['f_count']) / $total_graded) * 100, 1) : 0 ?>%)</span>
                                        </div>
                                        <div class="progress-bar">
                                            <div class="progress-fill bg-gradient-to-r from-red-500 to-pink-500" 
                                                 style="width: <?= $total_graded > 0 ? (($grade_dist['d_count'] + $grade_dist['f_count']) / $total_graded) * 100 : 0 ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Statistics Summary -->
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6 pt-6 border-t border-gray-200">
                                    <div class="text-center">
                                        <p class="text-sm text-gray-600">Average</p>
                                        <p class="text-2xl font-bold text-gray-800"><?= number_format($assignment_stats['average_grade'], 1) ?></p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-sm text-gray-600">Highest</p>
                                        <p class="text-2xl font-bold text-green-600"><?= $assignment_stats['max_grade'] ?></p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-sm text-gray-600">Lowest</p>
                                        <p class="text-2xl font-bold text-red-600"><?= $assignment_stats['min_grade'] ?></p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-sm text-gray-600">Median</p>
                                        <p class="text-2xl font-bold text-blue-600">
                                            <?php
                                            $stmt = $pdo->prepare("
                                                SELECT AVG(grade) as median
                                                FROM (
                                                    SELECT grade, @rownum:=@rownum+1 as 'row_number', @total_rows:=@rownum
                                                    FROM submissions, (SELECT @rownum:=0) r
                                                    WHERE assignment_id = ? AND grade IS NOT NULL
                                                    ORDER BY grade
                                                ) as t1
                                                WHERE t1.row_number IN (FLOOR((@total_rows+1)/2), FLOOR((@total_rows+2)/2))
                                            ");
                                            $stmt->execute([$assignment_id]);
                                            $median = $stmt->fetch()['median'];
                                            echo $median ? number_format($median, 1) : 'N/A';
                                            ?>
                                        </p>
                                    </div>
                                </div>
                                
                            <?php else: ?>
                                <div class="text-center py-8">
                                    <div class="text-gray-400 mb-4">
                                        <i class="fas fa-chart-line text-4xl"></i>
                                    </div>
                                    <p class="text-gray-600">Not enough graded submissions for analysis.</p>
                                    <p class="text-gray-500 text-sm mt-2">Grade some submissions to see statistics.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Performance Insights -->
                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl border border-blue-200 p-6">
                            <h4 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                                <i class="fas fa-lightbulb mr-2"></i>
                                Performance Insights
                            </h4>
                            
                            <?php if ($assignment_stats['graded_submissions'] > 0): ?>
                                <div class="space-y-4">
                                    <?php
                                    $average_percentage = ($assignment_stats['average_grade'] / $assignment['max_points']) * 100;
                                    
                                    if ($average_percentage >= 85) {
                                        $insight = "Excellent overall performance! Students are mastering this material.";
                                        $icon = "fa-smile";
                                        $color = "text-green-600";
                                    } elseif ($average_percentage >= 70) {
                                        $insight = "Good performance overall. Some students may need additional support.";
                                        $icon = "fa-meh";
                                        $color = "text-yellow-600";
                                    } elseif ($average_percentage >= 60) {
                                        $insight = "Average performance. Consider reviewing key concepts with the class.";
                                        $icon = "fa-frown";
                                        $color = "text-orange-600";
                                    } else {
                                        $insight = "Low performance. May indicate assignment difficulty or lack of understanding.";
                                        $icon = "fa-sad-tear";
                                        $color = "text-red-600";
                                    }
                                    ?>
                                    
                                    <div class="flex items-start space-x-3">
                                        <div class="bg-white p-2 rounded-lg">
                                            <i class="fas <?= $icon ?> <?= $color ?> text-xl"></i>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-800 mb-1">Overall Assessment</p>
                                            <p class="text-gray-700"><?= $insight ?></p>
                                            <p class="text-sm text-gray-600 mt-2">
                                                Class Average: <?= number_format($average_percentage, 1) ?>%
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <!-- Recommendations -->
                                    <div class="mt-4 p-4 bg-white rounded-lg border border-gray-200">
                                        <p class="font-semibold text-gray-800 mb-2">Recommendations:</p>
                                        <ul class="text-gray-700 text-sm space-y-1">
                                            <li class="flex items-center">
                                                <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                                Review common mistakes during next class session
                                            </li>
                                            <li class="flex items-center">
                                                <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                                Consider offering extra credit or makeup assignment
                                            </li>
                                            <li class="flex items-center">
                                                <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                                Provide detailed feedback on graded submissions
                                            </li>
                                            <?php if ($submission_rate < 80): ?>
                                                <li class="flex items-center">
                                                    <i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>
                                                    Low submission rate (<?= number_format($submission_rate, 1) ?>%) - consider sending reminders
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                            <?php else: ?>
                                <p class="text-gray-600 text-center py-4">Submit grades to get performance insights.</p>
                            <?php endif; ?>
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
                                    <i class="fas fa-chart-bar mr-1"></i>
                                    Based on <?= $assignment_stats['graded_submissions'] ?> graded submission(s)
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Grade Modal -->
    <div id="gradeModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 hidden">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
            <div class="bg-gradient-to-r from-green-500 to-emerald-500 px-6 py-4 rounded-t-2xl">
                <h3 class="text-xl font-bold text-white">
                    <i class="fas fa-grade mr-2"></i>
                    Grade Submission
                </h3>
                <p class="text-green-100" id="studentName"></p>
            </div>
            
            <form method="POST" class="p-6">
                <input type="hidden" name="update_grade" value="1">
                <input type="hidden" name="submission_id" id="modalSubmissionId">
                
                <div class="space-y-4">
                    <div class="form-group">
                        <label class="form-label">Grade (out of <?= $assignment['max_points'] ?>)</label>
                        <input type="number" 
                               name="grade" 
                               id="modalGrade"
                               min="0"
                               max="<?= $assignment['max_points'] ?>"
                               step="0.5"
                               class="form-input"
                               placeholder="Enter grade">
                        <p class="text-gray-500 text-sm mt-1">
                            Maximum points: <?= $assignment['max_points'] ?>
                        </p>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Feedback (Optional)</label>
                        <textarea name="feedback" 
                                  id="modalFeedback"
                                  rows="4"
                                  class="form-input"
                                  placeholder="Provide feedback to the student..."></textarea>
                    </div>
                </div>
                
                <div class="flex justify-between mt-6 pt-6 border-t border-gray-200">
                    <button type="button" 
                            onclick="closeGradeModal()"
                            class="px-6 py-2 border-2 border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    
                    <button type="submit" 
                            class="px-6 py-2 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-lg hover:from-green-700 hover:to-emerald-700 transition font-semibold">
                        <i class="fas fa-save mr-2"></i>
                        Save Grade
                    </button>
                </div>
            </form>
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
                'submissions-tab': 'Submissions',
                'grades-tab': 'Grade Analysis'
            };
            
            document.querySelectorAll('.tab-button').forEach(button => {
                if (button.textContent.includes(buttons[tabId])) {
                    button.classList.add('active');
                }
            });
        }
        
        // Grade modal functionality
        function openGradeModal(submissionId, studentName, currentGrade, currentFeedback) {
            document.getElementById('modalSubmissionId').value = submissionId;
            document.getElementById('studentName').textContent = 'Grading submission for: ' + studentName;
            document.getElementById('modalGrade').value = currentGrade !== null ? currentGrade : '';
            document.getElementById('modalFeedback').value = currentFeedback;
            
            document.getElementById('gradeModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            
            // Focus on grade input
            setTimeout(() => {
                document.getElementById('modalGrade').focus();
            }, 100);
        }
        
        function closeGradeModal() {
            document.getElementById('gradeModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
        
        // Close modal on background click
        document.getElementById('gradeModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeGradeModal();
            }
        });
        
        // Close modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !document.getElementById('gradeModal').classList.contains('hidden')) {
                closeGradeModal();
            }
        });
        
        // Update teacher display when subject changes
        document.querySelector('select[name="subject_id"]').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const teacherName = selectedOption.getAttribute('data-teacher');
            if (teacherName) {
                document.getElementById('teacher-display').textContent = teacherName;
            }
        });
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Check if there's a hash in the URL for tab navigation
            const hash = window.location.hash.substring(1);
            if (hash && ['details-tab', 'submissions-tab', 'grades-tab'].includes(hash)) {
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
            
            // Set min date for due date to today
            const dueDateInput = document.querySelector('input[name="due_date"]');
            if (dueDateInput) {
                const today = new Date().toISOString().slice(0, 16);
                dueDateInput.min = today;
            }
        });
        
        // Confirm before deleting submission
        document.addEventListener('submit', function(e) {
            if (e.target && e.target.name === 'delete_submission') {
                if (!confirm('Are you sure you want to delete this submission? This cannot be undone.')) {
                    e.preventDefault();
                }
            }
        });
    </script>
</body>
</html>