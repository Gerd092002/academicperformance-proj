<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
check_login();

// Only admin and teachers can edit activities
if (!in_array($_SESSION['user']['role_id'], [1, 2])) {
    header('Location: login.php');
    exit;
}

$current_user_id = $_SESSION['user']['id'];
$current_user_role = $_SESSION['user']['role_id'];
$error = $success = '';
$activity = [];
$subjects = [];
$activity_submissions = [];

// Get activity ID from query string
$activity_id = $_GET['id'] ?? null;

if (!$activity_id) {
    header('Location: ' . ($current_user_role == 1 ? 'admin_dashboard.php' : 'teacher_dashboard.php'));
    exit;
}

// Fetch activity information
$stmt = $pdo->prepare("
    SELECT a.*, 
           s.code as subject_code, s.name as subject_name, s.teacher_id,
           u.username as teacher_name, u.full_name as teacher_full_name,
           uc.username as created_by_name, uc.full_name as created_by_full_name
    FROM activities a
    JOIN subjects s ON a.subject_id = s.id
    LEFT JOIN users u ON s.teacher_id = u.id
    LEFT JOIN users uc ON a.created_by = uc.id
    WHERE a.id = ?
");
$stmt->execute([$activity_id]);
$activity = $stmt->fetch();

if (!$activity) {
    header('Location: ' . ($current_user_role == 1 ? 'admin_dashboard.php' : 'teacher_dashboard.php'));
    exit;
}

// Check permissions
if ($current_user_role == 2 && $activity['teacher_id'] != $current_user_id) {
    // Teacher can only edit activities in their own subjects
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

// Fetch activity submissions if the table exists
$activity_submissions = [];
try {
    $stmt = $pdo->prepare("
        SELECT asub.*, u.username, u.full_name, u.email, u.student_id
        FROM activity_submissions asub
        JOIN users u ON asub.student_id = u.id
        WHERE asub.activity_id = ?
        ORDER BY asub.submitted_at DESC
    ");
    $stmt->execute([$activity_id]);
    $activity_submissions = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table might not exist, that's okay
}

// Get activity statistics
$activity_stats = [
    'total_submissions' => count($activity_submissions),
    'graded_submissions' => 0,
    'average_grade' => 0
];

foreach ($activity_submissions as $submission) {
    if ($submission['grade'] !== null) {
        $activity_stats['graded_submissions']++;
        $activity_stats['average_grade'] += $submission['grade'];
    }
}

if ($activity_stats['graded_submissions'] > 0) {
    $activity_stats['average_grade'] = $activity_stats['average_grade'] / $activity_stats['graded_submissions'];
}

// Calculate submission rate
$total_students = 0;
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT ss.student_id) as total_students
        FROM student_subject ss
        WHERE ss.subject_id = ?
    ");
    $stmt->execute([$activity['subject_id']]);
    $result = $stmt->fetch();
    $total_students = $result ? $result['total_students'] : 0;
} catch (PDOException $e) {
    // Table might not exist
}

$submission_rate = $total_students > 0 ? ($activity_stats['total_submissions'] / $total_students) * 100 : 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_activity'])) {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
       
        $due_date = $_POST['due_date'];
        $subject_id = $_POST['subject_id'];
        $total_points = (int)$_POST['total_points'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $activity_type = $_POST['type'];
        
        // Validation
        if (empty($title)) {
            $error = "Activity title is required.";
        } elseif (empty($due_date)) {
            $error = "Due date is required.";
        } elseif (strtotime($due_date) < strtotime('today')) {
            $error = "Due date cannot be in the past.";
        } elseif (empty($subject_id)) {
            $error = "Subject is required.";
        } elseif ($total_points <= 0) {
            $error = "Total points must be greater than 0.";
        }
        
        // If no errors, update activity
        if (empty($error)) {
            try {
                $pdo->beginTransaction();
                
                // Check if created_by is already set, if not set it to current user
                $created_by = $activity['created_by'] ?: $current_user_id;
                
                // Update activity
                $stmt = $pdo->prepare("
                    UPDATE activities 
                    SET title = ?, description = ?, due_date = ?, 
                        subject_id = ?, total_points = ?, is_active = ?, type = ?,
                        created_by = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $title, $description, $due_date,
                    $subject_id, $total_points, $is_active, $activity_type,
                    $created_by, $activity_id
                ]);
                
                $pdo->commit();
                
                $success = "Activity updated successfully!";
                
                // Refresh activity data
                $stmt = $pdo->prepare("
                    SELECT a.*, 
                           s.code as subject_code, s.name as subject_name, s.teacher_id,
                           u.username as teacher_name, u.full_name as teacher_full_name,
                           uc.username as created_by_name, uc.full_name as created_by_full_name
                    FROM activities a
                    JOIN subjects s ON a.subject_id = s.id
                    LEFT JOIN users u ON s.teacher_id = u.id
                    LEFT JOIN users uc ON a.created_by = uc.id
                    WHERE a.id = ?
                ");
                $stmt->execute([$activity_id]);
                $activity = $stmt->fetch();
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
    
    // Handle grade submission
    elseif (isset($_POST['update_grade'])) {
        $submission_id = $_POST['submission_id'];
        $grade = $_POST['grade'];
        $feedback = trim($_POST['feedback'] ?? '');
        
        if (!is_numeric($grade) || $grade < 0 || $grade > $activity['total_points']) {
            $error = "Grade must be between 0 and " . $activity['total_points'];
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE activity_submissions 
                    SET grade = ?, feedback = ?, graded_at = NOW() 
                    WHERE id = ?
                ");
                
                $stmt->execute([$grade, $feedback, $submission_id]);
                
                $success = "Grade updated successfully!";
                
                // Refresh submissions list
                $stmt = $pdo->prepare("
                    SELECT asub.*, u.username, u.full_name, u.email, u.student_id
                    FROM activity_submissions asub
                    JOIN users u ON asub.student_id = u.id
                    WHERE asub.activity_id = ?
                    ORDER BY asub.submitted_at DESC
                ");
                $stmt->execute([$activity_id]);
                $activity_submissions = $stmt->fetchAll();
                
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
    
    // Handle submission deletion
    elseif (isset($_POST['delete_submission'])) {
        $submission_id = $_POST['delete_submission_id'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM activity_submissions WHERE id = ?");
            $stmt->execute([$submission_id]);
            
            $success = "Submission deleted successfully!";
            
            // Refresh submissions list
            $stmt = $pdo->prepare("
                SELECT asub.*, u.username, u.full_name, u.email, u.student_id
                FROM activity_submissions asub
                JOIN users u ON asub.student_id = u.id
                WHERE asub.activity_id = ?
                ORDER BY asub.submitted_at DESC
            ");
            $stmt->execute([$activity_id]);
            $activity_submissions = $stmt->fetchAll();
            
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
    <title>Edit Activity - <?= $current_user_role == 1 ? 'Admin' : 'Teacher' ?> Panel</title>
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
        
        .activity-badge {
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
        
        .badge-quiz {
            background: linear-gradient(to right, #8b5cf6, #7c3aed);
            color: white;
        }
        
        .badge-assignment {
            background: linear-gradient(to right, #3b82f6, #2563eb);
            color: white;
        }
        
        .badge-exam {
            background: linear-gradient(to right, #ef4444, #dc2626);
            color: white;
        }
        
        .badge-lab {
            background: linear-gradient(to right, #10b981, #059669);
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
        
        .form-select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
            background: white;
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
        }
        
        .form-select:focus {
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
        
        .activity-header {
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
                    <h1 class="text-3xl font-bold text-white">Edit Activity</h1>
                    <p class="text-gray-200">
                        <?= $current_user_role == 1 ? 'Administrator' : 'Teacher' ?> Panel - Activity Management
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
            <!-- Activity Header -->
            <div class="activity-header px-6 py-5">
                <div class="flex flex-col md:flex-row md:items-center justify-between">
                    <div class="flex items-center space-x-3 mb-4 md:mb-0">
                        <div class="bg-white p-3 rounded-xl">
                            <i class="fas fa-tasks text-indigo-600 text-xl"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-white"><?= htmlspecialchars($activity['title']) ?></h2>
                            <p class="text-indigo-100">
                                <?= htmlspecialchars($activity['subject_code']) ?>: <?= htmlspecialchars($activity['subject_name']) ?>
                                <?php if (!empty($activity['type'])): ?>
                                    <span class="activity-badge badge-<?= $activity['type'] ?> ml-2">
                                        <?= ucfirst($activity['type']) ?>
                                    </span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="bg-white/20 px-4 py-2 rounded-full">
                            <span class="text-white font-semibold">Activity ID: <?= $activity['id'] ?></span>
                        </div>
                        <?php
                        $due_date = strtotime($activity['due_date']);
                        $today = strtotime('today');
                        $status_class = '';
                        $status_text = '';
                        
                        if ($activity['is_active'] == 0) {
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
                        <span class="activity-badge <?= $status_class ?>">
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
                <!-- Activity Statistics -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                    <div class="stats-card p-4 rounded-xl">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-blue-600 font-semibold">Submissions</p>
                                <p class="text-2xl font-bold text-blue-800"><?= $activity_stats['total_submissions'] ?></p>
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
                                <p class="text-2xl font-bold text-green-800"><?= $activity_stats['graded_submissions'] ?></p>
                                <p class="text-xs text-gray-600 mt-1">
                                    of <?= $activity_stats['total_submissions'] ?>
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
                                    <?= $activity_stats['average_grade'] ? number_format($activity_stats['average_grade'], 1) : 'N/A' ?>
                                </p>
                                <p class="text-xs text-gray-600 mt-1">
                                    out of <?= $activity['total_points'] ?>
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
                                <p class="text-sm text-yellow-600 font-semibold">Total Points</p>
                                <p class="text-2xl font-bold text-yellow-800"><?= $activity['total_points'] ?></p>
                                <p class="text-xs text-gray-600 mt-1">
                                    Due: <?= date('M j, Y', strtotime($activity['due_date'])) ?>
                                </p>
                            </div>
                            <div class="bg-yellow-100 p-3 rounded-full">
                                <i class="fas fa-star text-yellow-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Activity Information Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="info-card">
                        <div class="info-label">Created By</div>
                        <div class="info-value">
                            <?= htmlspecialchars($activity['created_by_full_name'] ?: $activity['created_by_name'] ?: 'Not assigned') ?>
                        </div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Created Date</div>
                        <div class="info-value">
                            <?= date('F j, Y', strtotime($activity['created_at'])) ?>
                        </div>
                    </div>
                    <div class="info-card">
                        <div class="info-label">Last Updated</div>
                        <div class="info-value">
                            <?= date('F j, Y g:i A', strtotime($activity['updated_at'])) ?>
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
                            Activity Details
                        </button>
                        
                    </div>
                </div>

                <!-- Details Tab -->
                <div id="details-tab" class="tab-content">
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="update_activity" value="1">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Activity Title -->
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-heading mr-2"></i>
                                    Activity Title *
                                </label>
                                <input type="text" 
                                       name="title" 
                                       value="<?= htmlspecialchars($activity['title']) ?>" 
                                       required
                                       class="form-input"
                                       placeholder="e.g., Chapter 1 Quiz">
                            </div>
                            
                            <!-- Activity Type -->
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-tag mr-2"></i>
                                    Activity Type *
                                </label>
                                <select name="type" required class="form-select">
                                    <option value="assignment" <?= ($activity['type'] ?? 'assignment') == 'assignment' ? 'selected' : '' ?>>Assignment</option>
                                    <option value="quiz" <?= ($activity['type'] ?? 'assignment') == 'quiz' ? 'selected' : '' ?>>Quiz</option>
                                    <option value="exam" <?= ($activity['type'] ?? 'assignment') == 'exam' ? 'selected' : '' ?>>Exam</option>
                                    <option value="lab" <?= ($activity['type'] ?? 'assignment') == 'lab' ? 'selected' : '' ?>>Lab Activity</option>
                                    <option value="project" <?= ($activity['type'] ?? 'assignment') == 'project' ? 'selected' : '' ?>>Project</option>
                                </select>
                            </div>
                            
                            <!-- Subject -->
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-book mr-2"></i>
                                    Subject *
                                </label>
                                <select name="subject_id" required class="form-select" id="subjectSelect">
                                    <option value="">Select Subject</option>
                                    <?php foreach ($subjects as $subject): ?>
                                        <option value="<?= $subject['id'] ?>" 
                                                <?= $activity['subject_id'] == $subject['id'] ? 'selected' : '' ?>
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
                                       value="<?= $activity['total_points'] ?>" 
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
                                <input type="datetime-local" 
                                       name="due_date" 
                                       value="<?= date('Y-m-d\TH:i', strtotime($activity['due_date'])) ?>" 
                                       required
                                       class="form-input">
                            </div>
                            
                            <!-- Activity Status -->
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-power-off mr-2"></i>
                                    Activity Status
                                </label>
                                <div class="mt-2">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="checkbox" 
                                               name="is_active" 
                                               value="1" 
                                               <?= $activity['is_active'] ? 'checked' : '' ?>
                                               class="sr-only peer">
                                        <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
                                        <span class="ml-3 text-gray-700 font-medium">
                                            <?= $activity['is_active'] ? 'Activity is Active' : 'Activity is Inactive' ?>
                                        </span>
                                    </label>
                                    <p class="text-gray-500 text-sm mt-2">
                                        When inactive, students cannot submit to this activity.
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
                                        <?= htmlspecialchars($activity['teacher_full_name'] ?: $activity['teacher_name'] ?: 'No teacher assigned') ?>
                                    </p>
                                    <p class="text-gray-600 text-sm">
                                        <?= $current_user_role == 1 ? 'Will update based on selected subject' : 'You are the teacher for this activity' ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Description -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-align-left mr-2"></i>
                                Activity Description
                            </label>
                            <textarea name="description" 
                                      rows="3"
                                      class="form-input"
                                      placeholder="Brief description of the activity..."><?= htmlspecialchars($activity['description'] ?? '') ?></textarea>
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
                        <?php if (count($activity_submissions) > 0): ?>
                            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                                    <div class="flex justify-between items-center">
                                        <h3 class="text-lg font-bold text-gray-800">
                                            <i class="fas fa-file-upload mr-2"></i>
                                            Student Submissions
                                            <span class="text-gray-600 font-normal">(<?= count($activity_submissions) ?>)</span>
                                        </h3>
                                        <div class="text-sm text-gray-600">
                                            <?= $activity_stats['graded_submissions'] ?> graded, 
                                            <?= $activity_stats['total_submissions'] - $activity_stats['graded_submissions'] ?> pending
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="divide-y divide-gray-200">
                                    <?php foreach ($activity_submissions as $submission): ?>
                                        <?php
                                        $is_overdue = strtotime($submission['submitted_at']) > strtotime($activity['due_date']);
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
                                                            $percentage = ($submission['grade'] / $activity['total_points']) * 100;
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
                                <p class="text-gray-600 mb-6">Students haven't submitted any work for this activity.</p>
                                <div class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-500 to-indigo-500 text-white rounded-lg">
                                    <i class="fas fa-clock mr-2"></i>
                                    Due: <?= date('F j, Y g:i A', strtotime($activity['due_date'])) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
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
                                    <?= count($activity_submissions) ?> submission(s), 
                                    <?= $activity_stats['graded_submissions'] ?> graded
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
                        <label class="form-label">Grade (out of <?= $activity['total_points'] ?>)</label>
                        <input type="number" 
                               name="grade" 
                               id="modalGrade"
                               min="0"
                               max="<?= $activity['total_points'] ?>"
                               step="0.5"
                               class="form-input"
                               placeholder="Enter grade">
                        <p class="text-gray-500 text-sm mt-1">
                            Maximum points: <?= $activity['total_points'] ?>
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
                'details-tab': 'Activity Details',
                'submissions-tab': 'Submissions'
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
        document.getElementById('subjectSelect').addEventListener('change', function() {
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
            if (hash && ['details-tab', 'submissions-tab'].includes(hash)) {
                showTab(hash);
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