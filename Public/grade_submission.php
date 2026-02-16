<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
check_login();

// Only teachers
if ($_SESSION['user']['role_id'] != 2) {
    header("Location: login.php");
    exit;
}

$submission_id = $_GET['id'] ?? null;
$subject_id = $_GET['subject_id'] ?? null;

if (!$submission_id) {
    die("Error: No submission ID provided.");
}

try {
    // First, fetch the submission with all related information
    $stmt = $pdo->prepare("
        SELECT s.*, 
               u.username, u.full_name, u.email, u.id,
               a.name as assignment_title, a.total_points as assignment_points,
               act.title as activity_title, act.total_points as activity_points,
               p.name as project_name, p.total_points as project_points
        FROM submissions s 
        JOIN users u ON u.id = s.student_id
        LEFT JOIN assignments a ON s.assignment_id = a.id
        LEFT JOIN activities act ON s.activity_id = act.id
        LEFT JOIN projects p ON s.project_id = p.id
        WHERE s.id = ?
    ");
    $stmt->execute([$submission_id]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$submission) {
        die("<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative m-4'>
                <strong class='font-bold'>Error!</strong>
                <span class='block sm:inline'>Submission not found with ID: $submission_id</span>
            </div>");
    }

    // Determine what type of submission this is
    $item_type = '';
    $item_id = null;
    $item_name = '';
    $max_points = 100;
    
    if (!empty($submission['assignment_id'])) {
        $item_type = 'assignment';
        $item_id = $submission['assignment_id'];
        $item_name = $submission['assignment_title'] ?? 'Assignment #' . $item_id;
        $max_points = $submission['assignment_points'] ?? 100;
        $column = 'assignment_id';
    } elseif (!empty($submission['activity_id'])) {
        $item_type = 'activity';
        $item_id = $submission['activity_id'];
        $item_name = $submission['activity_title'] ?? 'Activity #' . $item_id;
        $max_points = $submission['activity_points'] ?? 100;
        $column = 'activity_id';
    } elseif (!empty($submission['project_id'])) {
        $item_type = 'project';
        $item_id = $submission['project_id'];
        $item_name = $submission['project_name'] ?? 'Project #' . $item_id;
        $max_points = $submission['project_points'] ?? 100;
        $column = 'project_id';
    } else {
        die("<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative m-4'>
                <strong class='font-bold'>Error!</strong>
                <span class='block sm:inline'>Submission is not associated with any item (assignment, activity, or project).</span>
            </div>");
    }

    // Get subject information
    $subject_name = '';
    if ($subject_id) {
        $stmt = $pdo->prepare("SELECT code, name FROM subjects WHERE id = ?");
        $stmt->execute([$subject_id]);
        $subject = $stmt->fetch();
        if ($subject) {
            $subject_name = $subject['code'] . ': ' . $subject['name'];
        }
    }

    // Handle form submission
    $success = '';
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $score = $_POST['score'] ?? null;
        $comment = trim($_POST['comment'] ?? '');
        $feedback = trim($_POST['feedback'] ?? '');

        // Validation
        if ($score === null || $score === '') {
            $error = "Please enter a score.";
        } elseif (!is_numeric($score)) {
            $error = "Score must be a number.";
        } elseif ($score < 0 || $score > $max_points) {
            $error = "Score must be between 0 and $max_points.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE submissions 
                    SET score = ?, teacher_comment = ?,  graded_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$score, $comment, $submission_id]);
                
                $success = "Grade submitted successfully!";
                
                // Refresh submission data
                $stmt = $pdo->prepare("
                    SELECT s.*, 
                           u.username, u.full_name, u.email, u.id,
                           a.name as assignment_title, a.total_points as assignment_points,
                           act.title as activity_title, act.total_points as activity_points,
                           p.name as project_name, p.total_points as project_points
                    FROM submissions s 
                    JOIN users u ON u.id = s.student_id
                    LEFT JOIN assignments a ON s.assignment_id = a.id
                    LEFT JOIN activities act ON s.activity_id = act.id
                    LEFT JOIN projects p ON s.project_id = p.id
                    WHERE s.id = ?
                ");
                $stmt->execute([$submission_id]);
                $submission = $stmt->fetch(PDO::FETCH_ASSOC);
                
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Submission - Teacher Panel</title>
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
        
        .grade-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 2rem;
            margin: 0 auto;
        }
        
        .grade-a { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .grade-b { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; }
        .grade-c { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
        .grade-d { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
        
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
        
        .form-textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
            background: white;
            min-height: 120px;
            resize: vertical;
        }
        
        .form-textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .score-slider {
            width: 100%;
            height: 10px;
            -webkit-appearance: none;
            background: #e5e7eb;
            border-radius: 5px;
            outline: none;
        }
        
        .score-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #667eea;
            cursor: pointer;
            border: 3px solid white;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
        }
        
        .score-slider::-moz-range-thumb {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #667eea;
            cursor: pointer;
            border: 3px solid white;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .badge-assignment {
            background: linear-gradient(to right, #3b82f6, #2563eb);
            color: white;
        }
        
        .badge-activity {
            background: linear-gradient(to right, #8b5cf6, #7c3aed);
            color: white;
        }
        
        .badge-project {
            background: linear-gradient(to right, #10b981, #059669);
            color: white;
        }
        
        .badge-late {
            background: linear-gradient(to right, #ef4444, #dc2626);
            color: white;
        }
        
        .badge-submitted {
            background: linear-gradient(to right, #f59e0b, #d97706);
            color: white;
        }
    </style>
</head>
<body class="p-4 md:p-6">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div class="flex items-center space-x-3">
                <a href="view_submissions.php?<?= $column ?>=<?= $item_id ?>&subject_id=<?= $subject_id ?>" 
                   class="text-gray-700 hover:text-indigo-700 transition">
                    <div class="bg-white p-2 rounded-lg shadow-sm hover-lift">
                        <i class="fas fa-arrow-left text-lg"></i>
                    </div>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-white">Grade Submission</h1>
                    <p class="text-gray-200">
                        Teacher Panel - Evaluation
                    </p>
                </div>
            </div>
            
            <div class="hidden md:block">
                <div class="w-10 h-10 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-full flex items-center justify-center text-white font-bold shadow-lg">
                    <?= strtoupper(substr(htmlspecialchars($_SESSION['user']['username']), 0, 1)) ?>
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
                            <i class="fas fa-graduation-cap text-indigo-600 text-xl"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-white"><?= htmlspecialchars($item_name) ?></h2>
                            <div class="flex items-center space-x-2 mt-1">
                                <?php if ($subject_name): ?>
                                    <span class="text-indigo-100"><?= htmlspecialchars($subject_name) ?></span>
                                <?php endif; ?>
                                <span class="badge badge-<?= $item_type ?>">
                                    <i class="fas fa-<?= $item_type == 'assignment' ? 'tasks' : ($item_type == 'project' ? 'project-diagram' : 'clipboard-check') ?> mr-1"></i>
                                    <?= ucfirst($item_type) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="badge badge-submitted">
                            <i class="fas fa-calendar-alt mr-1"></i>
                            <?= date('M j, Y', strtotime($submission['submitted_at'])) ?>
                        </span>
                        <span class="bg-white/20 px-3 py-1 rounded-full text-white text-sm">
                            ID: <?= $submission_id ?>
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
                <!-- Student Information -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                    <div class="info-card">
                        <div class="info-label">Student</div>
                        <div class="info-value">
                            <?= htmlspecialchars($submission['full_name'] ?: $submission['username']) ?>
                        </div>
                        <p class="text-sm text-gray-600 mt-1">
                            <?= htmlspecialchars($submission['email']) ?>
                        </p>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-label">Student ID</div>
                        <div class="info-value">
                            <?= htmlspecialchars($submission['student_id']) ?>
                        </div>
                        <p class="text-sm text-gray-600 mt-1">
                            Username: <?= htmlspecialchars($submission['username']) ?>
                        </p>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-label">Submission Date</div>
                        <div class="info-value">
                            <?= date('F j, Y g:i A', strtotime($submission['submitted_at'])) ?>
                        </div>
                        <p class="text-sm text-gray-600 mt-1">
                            <?php if ($submission['graded_at']): ?>
                                Last graded: <?= date('M j, Y', strtotime($submission['graded_at'])) ?>
                            <?php else: ?>
                                Not graded yet
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <!-- Current Grade Display -->
                <?php if ($submission['score'] !== null): ?>
                    <div class="mb-8 p-6 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl border border-blue-200">
                        <div class="text-center">
                            <p class="text-sm font-semibold text-blue-700 mb-2">CURRENT GRADE</p>
                            <?php
                            $percentage = ($submission['score'] / $max_points) * 100;
                            $grade_class = 'grade-';
                            if ($percentage >= 90) $grade_class .= 'a';
                            elseif ($percentage >= 80) $grade_class .= 'b';
                            elseif ($percentage >= 70) $grade_class .= 'c';
                            else $grade_class .= 'd';
                            ?>
                            <div class="grade-circle <?= $grade_class ?>">
                                <?= number_format($submission['score'], 1) ?>
                            </div>
                            <p class="text-lg font-bold text-gray-800 mt-2">
                                <?= number_format($percentage, 1) ?>%
                            </p>
                            <p class="text-sm text-gray-600">
                                out of <?= $max_points ?> maximum points
                            </p>
                            <p class="text-xs text-gray-500 mt-2">
                                <?php if ($submission['graded_at']): ?>
                                    Graded on: <?= date('F j, Y', strtotime($submission['graded_at'])) ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- File Download -->
                <?php if (!empty($submission['file_path'])): ?>
                    <div class="mb-8 p-4 bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl border border-green-200">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="bg-green-100 p-2 rounded-lg">
                                    <i class="fas fa-file text-green-600"></i>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800">Submitted File</p>
                                    <p class="text-sm text-gray-600">Download the student's submission</p>
                                </div>
                            </div>
                            <a href="../<?= htmlspecialchars($submission['file_path']) ?>" 
                               target="_blank"
                               class="px-4 py-2 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-lg hover:from-green-700 hover:to-emerald-700 transition">
                                <i class="fas fa-download mr-2"></i>
                                Download File
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Grading Form -->
                <form method="POST" class="space-y-6">
                    <div class="bg-white rounded-xl border border-gray-200 p-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-edit mr-2"></i>
                            Grade Submission
                        </h3>
                        
                        <!-- Score Input -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-star mr-2"></i>
                                Score (out of <?= $max_points ?>)
                            </label>
                            <div class="space-y-3">
                                <input type="range" 
                                       name="score" 
                                       id="scoreSlider"
                                       min="0" 
                                       max="<?= $max_points ?>" 
                                       step="0.5"
                                       value="<?= htmlspecialchars($submission['score'] ?? ($max_points * 0.7)) ?>"
                                       class="score-slider"
                                       oninput="updateScoreValue(this.value)">
                                
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">0</span>
                                    <div class="text-center">
                                        <input type="number" 
                                               name="score_display" 
                                               id="scoreInput"
                                               min="0" 
                                               max="<?= $max_points ?>" 
                                               step="0.5"
                                               value="<?= htmlspecialchars($submission['score'] ?? ($max_points * 0.7)) ?>"
                                               class="w-32 text-center p-2 border-2 border-indigo-300 rounded-lg text-lg font-bold text-indigo-700"
                                               oninput="updateScoreSlider(this.value)">
                                        <p class="text-xs text-gray-500 mt-1">/ <?= $max_points ?></p>
                                    </div>
                                    <span class="text-sm text-gray-600"><?= $max_points ?></span>
                                </div>
                                
                                <!-- Grade Preview -->
                                <div id="gradePreview" class="mt-2 p-3 bg-gray-50 rounded-lg hidden">
                                    <p class="text-sm font-semibold text-gray-800">Grade Preview:</p>
                                    <p id="gradeText" class="text-lg font-bold"></p>
                                    <p id="gradePercentage" class="text-sm text-gray-600"></p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Teacher Comments -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-comment mr-2"></i>
                                Teacher Comments (Internal)
                            </label>
                            <textarea name="comment" 
                                      class="form-textarea"
                                      placeholder="Internal notes about this submission..."><?= htmlspecialchars($submission['teacher_comment'] ?? '') ?></textarea>
                            <p class="text-gray-500 text-sm mt-1">These comments are for teacher reference only</p>
                        </div>
                        
                    
                        
                        <!-- Form Actions -->
                        <div class="flex justify-between pt-6 border-t border-gray-200">
                            <a href="view_submissions.php?<?= $column ?>=<?= $item_id ?>&subject_id=<?= $subject_id ?>" 
                               class="px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-semibold">
                                <i class="fas fa-times mr-2"></i>
                                Cancel
                            </a>
                            
                            <div class="flex space-x-3">
                              
                                <button type="submit" 
                                        class="btn-glow px-8 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-xl hover:from-indigo-700 hover:to-purple-700 transition font-semibold">
                                    <i class="fas fa-save mr-2"></i>
                                    Save Grade
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Update score input when slider changes
        function updateScoreValue(value) {
            document.getElementById('scoreInput').value = value;
            updateGradePreview(value);
        }
        
        // Update slider when input changes
        function updateScoreSlider(value) {
            const max = <?= $max_points ?>;
            if (value < 0) value = 0;
            if (value > max) value = max;
            document.getElementById('scoreSlider').value = value;
            updateGradePreview(value);
        }
        
        // Update grade preview
        function updateGradePreview(score) {
            const max = <?= $max_points ?>;
            const percentage = (score / max) * 100;
            const gradePreview = document.getElementById('gradePreview');
            const gradeText = document.getElementById('gradeText');
            const gradePercentage = document.getElementById('gradePercentage');
            
            let grade = '';
            let color = '';
            
            if (percentage >= 90) {
                grade = 'A (Excellent)';
                color = 'text-green-600';
            } else if (percentage >= 80) {
                grade = 'B (Good)';
                color = 'text-blue-600';
            } else if (percentage >= 70) {
                grade = 'C (Satisfactory)';
                color = 'text-yellow-600';
            } else if (percentage >= 60) {
                grade = 'D (Needs Improvement)';
                color = 'text-orange-600';
            } else {
                grade = 'F (Fail)';
                color = 'text-red-600';
            }
            
            gradeText.innerHTML = `<span class="${color}">${grade}</span>`;
            gradePercentage.textContent = `Score: ${score}/${max} (${percentage.toFixed(1)}%)`;
            gradePreview.classList.remove('hidden');
        }
        
        // Preview grade
        function previewGrade() {
            const score = document.getElementById('scoreInput').value;
            if (score !== '') {
                updateGradePreview(score);
                
                // Scroll to preview
                document.getElementById('gradePreview').scrollIntoView({ 
                    behavior: 'smooth',
                    block: 'center'
                });
            }
        }
        
        // Initialize grade preview if there's an existing score
        document.addEventListener('DOMContentLoaded', function() {
            const currentScore = document.getElementById('scoreInput').value;
            if (currentScore !== '') {
                updateGradePreview(currentScore);
            }
        });
    </script>
</body>
</html>