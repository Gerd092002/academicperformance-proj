<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
check_login();

// Only teachers
if ($_SESSION['user']['role_id'] != 2) {
    header("Location: login.php");
    exit;
}

// Whitelists for validation
$allowed_columns = ['assignment_id', 'project_id', 'activity_id'];
$allowed_tables = [
    'assignment_id' => 'assignments',
    'project_id' => 'projects', 
    'activity_id' => 'activities'
];

$column = null;
$id = null;

// Determine which ID was passed
foreach ($allowed_columns as $col) {
    if (isset($_GET[$col]) && !empty($_GET[$col])) {
        $column = $col;
        $id = intval($_GET[$col]);
        break;
    }
}

// Get subject_id with more flexible validation
$subject_id = null;
if (isset($_GET['subject_id']) && !empty($_GET['subject_id'])) {
    $subject_id = intval($_GET['subject_id']);
}

// Debug logging (remove in production)
error_log("Debug: column=$column, id=$id, subject_id=$subject_id");

if (!$column || !$id) {
    die("Invalid request. Please specify an item ID (assignment_id, project_id, or activity_id).");
}

// Validate table name
if (!isset($allowed_tables[$column])) {
    die("Invalid item type.");
}

$item_table = $allowed_tables[$column];
$item_type = str_replace('_id', '', $column);

// If subject_id is not provided, try to get it from the item itself
if (!$subject_id) {
    try {
        $stmt = $pdo->prepare("SELECT subject_id FROM $item_table WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        if ($result && !empty($result['subject_id'])) {
            $subject_id = intval($result['subject_id']);
            error_log("Debug: Retrieved subject_id=$subject_id from $item_table");
        } else {
            die("Could not determine subject for this item.");
        }
    } catch (PDOException $e) {
        error_log("Database error (get subject from item): " . $e->getMessage());
        die("Error retrieving item information.");
    }
}

// Now continue with the rest of the code...
// Fetch item details with authorization check
$item_name = 'Unknown';
$due_date = null;

try {
    // First verify the teacher has access to this subject (if teacher_subject table exists)
    // If you don't have teacher_subject table, you can skip this check or implement differently
    $has_access = true; // Default to true
    
    // Check if teacher_subject table exists and use it for authorization
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as access FROM teacher_subject WHERE teacher_id = ? AND subject_id = ?");
        $stmt->execute([$_SESSION['user']['id'], $subject_id]);
        $access = $stmt->fetch();
        
        if ($access && $access['access'] == 0) {
            // Teacher might not be assigned to this subject, but could still view submissions
            // You can choose to be strict or lenient here
            error_log("Warning: Teacher " . $_SESSION['user']['id'] . " not assigned to subject $subject_id");
            // Uncomment the next line if you want strict authorization
            // die("Unauthorized access to this subject.");
        }
    } catch (PDOException $e) {
        // teacher_subject table might not exist, skip authorization
        error_log("Note: teacher_subject table check skipped: " . $e->getMessage());
    }
    
    // Fetch item details - handle different column names for different tables
    if ($item_table == 'assignments') {
        $stmt = $pdo->prepare("SELECT name, due_date FROM $item_table WHERE id = ?");
        $stmt->execute([$id]);
    } else if ($item_table == 'activities') {
        $stmt = $pdo->prepare("SELECT COALESCE(title, name) as item_name, due_date FROM $item_table WHERE id = ?");
        $stmt->execute([$id]);
    } else {
        // For projects table (if exists)
        $stmt = $pdo->prepare("SELECT COALESCE(title, name) as item_name, due_date FROM $item_table WHERE id = ?");
        $stmt->execute([$id]);
    }
    
    $item = $stmt->fetch();
    
    if ($item) {
        if ($item_table == 'assignments') {
            $item_name = htmlspecialchars($item['name'] ?? 'Unknown');
        } else {
            $item_name = htmlspecialchars($item['item_name'] ?? 'Unknown');
        }
        $due_date = $item['due_date'] ?? null;
    } else {
        error_log("Item not found: $item_table id=$id");
        die("Item not found.");
    }
} catch (PDOException $e) {
    error_log("Database error (fetch item): " . $e->getMessage());
    die("Error retrieving item details. Please try again later.");
}

// Fetch subject name
$subject_name = '';
try {
    $stmt = $pdo->prepare("SELECT code, name FROM subjects WHERE id = ?");
    $stmt->execute([$subject_id]);
    $subject = $stmt->fetch();
    
    if ($subject) {
        $subject_name = htmlspecialchars($subject['code'] ?? '') . ': ' . htmlspecialchars($subject['name'] ?? 'Unknown');
    } else {
        $subject_name = 'Subject ID: ' . htmlspecialchars($subject_id);
    }
} catch (PDOException $e) {
    error_log("Database error (fetch subject): " . $e->getMessage());
    $subject_name = 'Subject ID: ' . htmlspecialchars($subject_id);
}

// Fetch submissions with student details
$submissions = [];
try {
    // First, verify the item exists and belongs to the correct subject
    $stmt = $pdo->prepare("SELECT COUNT(*) as item_exists FROM $item_table WHERE id = ?");
    $stmt->execute([$id]);
    $item_exists = $stmt->fetch();
    
    if (!$item_exists || $item_exists['item_exists'] == 0) {
        die("Item not found.");
    }
    
    // Now fetch submissions
    $stmt = $pdo->prepare("
        SELECT s.*, 
               u.username, u.full_name, u.email, u.id as user_id,
               CASE 
                   WHEN s.submitted_at > i.due_date THEN 1
                   ELSE 0
               END as is_late
        FROM submissions s 
        JOIN users u ON u.id = s.student_id
        JOIN $item_table i ON i.id = s.$column
        WHERE s.$column = ? 
        ORDER BY s.submitted_at DESC
    ");
    $stmt->execute([$id]);
    $submissions = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Database error (fetch submissions): " . $e->getMessage());
    // Don't die, just show empty submissions
    $submissions = [];
}

// Get submission statistics
$total_submissions = count($submissions);
$graded_submissions = 0;
$late_submissions = 0;
$average_score = 0;

foreach ($submissions as $sub) {
    if ($sub['score'] !== null) {
        $graded_submissions++;
        $average_score += floatval($sub['score']);
    }
    if ($sub['is_late']) {
        $late_submissions++;
    }
}

if ($graded_submissions > 0) {
    $average_score = $average_score / $graded_submissions;
}

// Get total students in subject
$total_students = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT student_id) as total FROM student_subject WHERE subject_id = ?");
    $stmt->execute([$subject_id]);
    $result = $stmt->fetch();
    $total_students = $result['total'] ?? 0;
} catch (PDOException $e) {
    error_log("Database error (count students): " . $e->getMessage());
    // Try alternative if student_subject table doesn't exist
    try {
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT student_id) as total FROM submissions WHERE $column = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        $total_students = $result['total'] ?? $total_submissions;
    } catch (PDOException $e2) {
        error_log("Alternative count also failed: " . $e2->getMessage());
        $total_students = $total_submissions;
    }
}

$submission_rate = $total_students > 0 ? ($total_submissions / $total_students) * 100 : 0;

// Function to sanitize file paths for download
function sanitizeFilePath($file_path) {
    if (empty($file_path)) return null;
    
    // Remove any directory traversal attempts
    $file_path = str_replace(['../', './', '..\\', '.\\'], '', $file_path);
    
    // Ensure path is within submissions directory
    $base_dir = 'uploads/submissions/';
    
    // Check if path starts with base directory
    if (strpos($file_path, $base_dir) !== 0) {
        // If not, prepend base directory
        $file_path = $base_dir . basename($file_path);
    }
    
    return htmlspecialchars($file_path, ENT_QUOTES, 'UTF-8');
}

// Function to determine grade class
function getGradeClass($score) {
    if ($score >= 90) return 'grade-a';
    if ($score >= 80) return 'grade-b';
    if ($score >= 70) return 'grade-c';
    return 'grade-d';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Submissions - Teacher Panel</title>
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
        
        .submission-card.late {
            border-left-color: #ef4444;
        }
        
        .submission-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
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
        
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .badge-late {
            background: linear-gradient(to right, #ef4444, #dc2626);
            color: white;
        }
        
        .badge-graded {
            background: linear-gradient(to right, #10b981, #059669);
            color: white;
        }
        
        .badge-pending {
            background: linear-gradient(to right, #f59e0b, #d97706);
            color: white;
        }
        
        .type-badge {
            background: linear-gradient(to right, #8b5cf6, #7c3aed);
            color: white;
        }
    </style>
</head>
<body class="p-4 md:p-6">
    <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div class="flex items-center space-x-3">
                <a href="teacher_dashboard.php<?= $subject_id ? '?subject_id=' . htmlspecialchars($subject_id) : '' ?>" 
                   class="text-gray-700 hover:text-indigo-700 transition">
                    <div class="bg-white p-2 rounded-lg shadow-sm hover-lift">
                        <i class="fas fa-arrow-left text-lg"></i>
                    </div>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-white">Student Submissions</h1>
                    <p class="text-gray-200">
                        Teacher Panel - Grade Management
                    </p>
                </div>
            </div>
            
            <div class="flex items-center space-x-4">
                <div class="hidden md:block text-right">
                    <p class="font-medium text-white"><?= htmlspecialchars($_SESSION['user']['username'] ?? 'Teacher') ?></p>
                    <p class="text-gray-200 text-sm">Teacher</p>
                </div>
                <div class="w-10 h-10 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-full flex items-center justify-center text-white font-bold shadow-lg">
                    <?= strtoupper(substr(htmlspecialchars($_SESSION['user']['username'] ?? 'T'), 0, 1)) ?>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="glass-card rounded-2xl shadow-2xl overflow-hidden mb-6">
            <!-- Header Section -->
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-5">
                <div class="flex flex-col md:flex-row md:items-center justify-between">
                    <div class="flex items-center space-x-3 mb-4 md:mb-0">
                        <div class="bg-white p-3 rounded-xl">
                            <i class="fas fa-file-upload text-indigo-600 text-xl"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-white"><?= $item_name ?></h2>
                            <p class="text-indigo-100">
                                <?php if ($subject_name): ?>
                                    <?= $subject_name ?>
                                <?php endif; ?>
                                <span class="type-badge badge ml-2">
                                    <i class="fas fa-<?= $item_type == 'assignment' ? 'tasks' : ($item_type == 'project' ? 'project-diagram' : 'clipboard-check') ?> mr-1"></i>
                                    <?= htmlspecialchars(ucfirst($item_type)) ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="bg-white/20 px-4 py-2 rounded-full">
                        <span class="text-white font-semibold">ID: <?= htmlspecialchars($id) ?></span>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                    <div class="stats-card p-4 rounded-xl">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-blue-600 font-semibold">Submissions</p>
                                <p class="text-2xl font-bold text-blue-800"><?= htmlspecialchars($total_submissions) ?></p>
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
                                <p class="text-2xl font-bold text-green-800"><?= htmlspecialchars($graded_submissions) ?></p>
                                <p class="text-xs text-gray-600 mt-1">
                                    of <?= htmlspecialchars($total_submissions) ?>
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
                                <p class="text-sm text-purple-600 font-semibold">Average Score</p>
                                <p class="text-2xl font-bold text-purple-800">
                                    <?= $graded_submissions > 0 ? number_format($average_score, 1) : 'N/A' ?>
                                </p>
                                <p class="text-xs text-gray-600 mt-1">
                                    <?= htmlspecialchars($graded_submissions) ?> graded submissions
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
                                <p class="text-sm text-red-600 font-semibold">Late Submissions</p>
                                <p class="text-2xl font-bold text-red-800"><?= htmlspecialchars($late_submissions) ?></p>
                                <p class="text-xs text-gray-600 mt-1">
                                    of <?= htmlspecialchars($total_submissions) ?> total
                                </p>
                            </div>
                            <div class="bg-red-100 p-3 rounded-full">
                                <i class="fas fa-clock text-red-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submissions Table -->
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-bold text-gray-800">
                                <i class="fas fa-users mr-2"></i>
                                Student Submissions
                                <span class="text-gray-600 font-normal">(<?= htmlspecialchars($total_submissions) ?>)</span>
                            </h3>
                            <div class="text-sm text-gray-600">
                                <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full"><?= htmlspecialchars($graded_submissions) ?> graded</span>
                                <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full ml-2"><?= htmlspecialchars($total_submissions - $graded_submissions) ?> pending</span>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (count($submissions) > 0): ?>
                        <div class="divide-y divide-gray-200">
                            <?php foreach ($submissions as $sub): ?>
                                <?php
                                $card_class = '';
                                if ($sub['score'] !== null) {
                                    $card_class = 'graded';
                                } elseif ($sub['is_late']) {
                                    $card_class = 'late';
                                }
                                
                                $sanitized_file_path = !empty($sub['file_path']) ? sanitizeFilePath($sub['file_path']) : null;
                                ?>
                                <div class="submission-card <?= $card_class ?> p-4 hover:bg-gray-50">
                                    <div class="flex flex-col md:flex-row md:items-center justify-between">
                                        <div class="flex items-center space-x-3 mb-4 md:mb-0">
                                            <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-indigo-500 rounded-full flex items-center justify-center text-white font-bold">
                                                <?= strtoupper(substr(htmlspecialchars($sub['username'] ?? 'S'), 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="flex items-center space-x-2">
                                                    <h4 class="font-semibold text-gray-800">
                                                        <?= htmlspecialchars($sub['full_name'] ?: ($sub['username'] ?? 'Student')) ?>
                                                    </h4>
                                                    <?php if ($sub['is_late']): ?>
                                                        <span class="badge badge-late">
                                                            <i class="fas fa-clock mr-1"></i>
                                                            Late
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if ($sub['score'] !== null): ?>
                                                        <span class="badge badge-graded">
                                                            <i class="fas fa-check mr-1"></i>
                                                            Graded
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge badge-pending">
                                                            <i class="fas fa-clock mr-1"></i>
                                                            Pending
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="text-sm text-gray-600">
                                                    <?= htmlspecialchars($sub['email'] ?? 'No email') ?>
                                                </p>
                                                <p class="text-xs text-gray-500 mt-1">
                                                    Student ID: <?= htmlspecialchars($sub['student_id'] ?? 'N/A') ?> | 
                                                    Submitted: <?= date('F j, Y g:i A', strtotime($sub['submitted_at'] ?? 'now')) ?>
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <div class="flex items-center space-x-4">
                                            <?php if ($sub['score'] !== null): ?>
                                                <div class="text-center">
                                                    <div class="grade-circle <?= getGradeClass($sub['score']) ?> mx-auto">
                                                        <?= htmlspecialchars($sub['score']) ?>
                                                    </div>
                                                    <p class="text-xs text-gray-600 mt-1">
                                                        <?= htmlspecialchars($sub['score']) ?> points
                                                    </p>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-yellow-600 font-semibold">
                                                    <i class="fas fa-clock mr-2"></i>
                                                    Not Graded
                                                </span>
                                            <?php endif; ?>
                                            
                                            <div class="flex space-x-2">
                                                <!-- View/Grade Button -->
                                                <a href="grade_submission.php?id=<?= htmlspecialchars($sub['id'] ?? '') ?>&<?= htmlspecialchars($column) ?>=<?= htmlspecialchars($id) ?>&subject_id=<?= htmlspecialchars($subject_id) ?>" 
                                                   class="px-4 py-2 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-lg hover:from-green-700 hover:to-emerald-700 transition">
                                                    <i class="fas fa-<?= $sub['score'] ? 'edit' : 'grade' ?> mr-2"></i>
                                                    <?= $sub['score'] ? 'Edit Grade' : 'Grade' ?>
                                                </a>
                                                
                                                <!-- Download Button -->
                                                <?php if ($sanitized_file_path): ?>
                                                    <?php
                                                    $absolute_path = __DIR__ . '/../' . $sanitized_file_path;
                                                    if (file_exists($absolute_path) && is_file($absolute_path)):
                                                    ?>
                                                        <a href="../<?= $sanitized_file_path ?>" 
                                                           target="_blank"
                                                           class="px-4 py-2 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-lg hover:from-blue-700 hover:to-indigo-700 transition">
                                                            <i class="fas fa-download mr-2"></i>
                                                            Download
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="px-4 py-2 bg-gray-300 text-gray-600 rounded-lg cursor-not-allowed" title="File not found">
                                                            <i class="fas fa-file-exclamation mr-2"></i>
                                                            File Missing
                                                        </span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($sub['feedback'])): ?>
                                        <div class="mt-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
                                            <p class="text-sm font-semibold text-gray-800 mb-1">Feedback:</p>
                                            <p class="text-gray-700 whitespace-pre-wrap"><?= nl2br(htmlspecialchars($sub['feedback'])) ?></p>
                                            <?php if ($sub['graded_at']): ?>
                                                <p class="text-xs text-gray-500 mt-2">
                                                    Graded: <?= date('F j, Y g:i A', strtotime($sub['graded_at'])) ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($sub['comments'])): ?>
                                        <div class="mt-4 p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                                            <p class="text-sm font-semibold text-gray-800 mb-1">Student Comments:</p>
                                            <p class="text-gray-700 whitespace-pre-wrap"><?= nl2br(htmlspecialchars($sub['comments'])) ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <div class="text-gray-400 mb-4">
                                <i class="fas fa-inbox text-4xl"></i>
                            </div>
                            <h3 class="text-xl font-bold text-gray-700 mb-2">No Submissions Yet</h3>
                            <p class="text-gray-600 mb-6">Students haven't submitted any work for this <?= htmlspecialchars($item_type) ?>.</p>
                            <div class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-500 to-indigo-500 text-white rounded-lg">
                                <i class="fas fa-info-circle mr-2"></i>
                                Waiting for student submissions
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Actions -->
                <div class="flex justify-between items-center mt-6 pt-6 border-t border-gray-200">
                    <div class="text-sm text-gray-600">
                        <i class="fas fa-info-circle mr-1"></i>
                        Showing <?= htmlspecialchars($total_submissions) ?> submission(s) | 
                        Total Students: <?= htmlspecialchars($total_students) ?>
                    </div>
                    
                    <div class="flex space-x-3">
                        <button onclick="window.print()" 
                                class="px-4 py-2 border-2 border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
                            <i class="fas fa-print mr-2"></i>
                            Print Report
                        </button>
                        
                        <a href="export_submissions.php?<?= htmlspecialchars($column) ?>=<?= htmlspecialchars($id) ?>&subject_id=<?= htmlspecialchars($subject_id) ?>" 
                           class="px-4 py-2 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-lg hover:from-indigo-700 hover:to-purple-700 transition">
                            <i class="fas fa-file-export mr-2"></i>
                            Export to CSV
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Print optimized CSS
        const printStyles = `
            @media print {
                body { background: white !important; }
                .glass-card { background: white !important; box-shadow: none !important; }
                .btn-glow, .stats-card, .submission-card { box-shadow: none !important; }
                .no-print { display: none !important; }
                a[href]:after { content: none !important; }
                .badge { background: #f1f1f1 !important; color: #333 !important; }
            }
        `;
        
        const styleSheet = document.createElement("style");
        styleSheet.type = "text/css";
        styleSheet.innerText = printStyles;
        document.head.appendChild(styleSheet);
    </script>
</body>
</html>