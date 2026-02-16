<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
check_login();

// Only teachers can add assignments
if ($_SESSION['user']['role_id'] != 2) {
    header('Location: login.php');
    exit;
}

$teacher_id = $_SESSION['user']['id'];

// Check if subject_id is passed via GET (from teacher dashboard)
$selected_subject_id = $_GET['subject_id'] ?? null;

// Get subjects handled by this teacher
$stmt = $pdo->prepare("SELECT id, name, code FROM subjects WHERE teacher_id = ? ORDER BY name");
$stmt->execute([$teacher_id]);
$subjects = $stmt->fetchAll();

// Initialize variables
$error = $success = '';
$name = $description = $total_points = $due_date = $subject_id = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject_id = $_POST['subject_id'];
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $total_points = $_POST['total_points'];
    $due_date = $_POST['due_date'];

    // Verify teacher owns the subject
    $stmt = $pdo->prepare("SELECT id FROM subjects WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$subject_id, $teacher_id]);
    if (!$stmt->fetch()) {
        $error = "Invalid subject selection.";
    } elseif (empty($name) || empty($total_points)) {
        $error = "Name and Total Points are required.";
    } elseif ($due_date && strtotime($due_date) < time()) {
        $error = "Due date must be in the future.";
    } else {
        try {
            // Insert assignment using your table structure
            $stmt = $pdo->prepare("
                INSERT INTO assignments 
                (subject_id, name, description, due_date, total_points, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $subject_id, 
                $name, 
                $description, 
                !empty($due_date) ? $due_date : null,
                $total_points
            ]);
            
            $assignment_id = $pdo->lastInsertId();
            $success = "Assignment created successfully!";
            
            // Clear form on success if checkbox is checked
            if (isset($_POST['clear_on_success']) && $_POST['clear_on_success'] == '1') {
                $name = $description = $total_points = $due_date = '';
                $subject_id = $selected_subject_id;
            }
            
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// If subject_id is passed via GET, pre-select it
if ($selected_subject_id && empty($_POST)) {
    foreach ($subjects as $sub) {
        if ($sub['id'] == $selected_subject_id) {
            $subject_id = $selected_subject_id;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Assignment - Teacher Dashboard</title>
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
        
        .step-indicator {
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .step-indicator.active {
            background: linear-gradient(to right, #4f46e5, #7c3aed);
            color: white;
            transform: scale(1.1);
        }
        
        .step-indicator.completed {
            background: #10b981;
            color: white;
        }
        
        .character-counter {
            transition: color 0.3s ease;
        }
        
        .character-counter.warning {
            color: #f59e0b;
        }
        
        .character-counter.error {
            color: #ef4444;
        }
        
        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body class="p-4 md:p-6">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div class="flex items-center space-x-3">
                <a href="teacher_dashboard.php<?= $selected_subject_id ? '?subject_id=' . $selected_subject_id . '&tab=assignments' : '' ?>" 
                   class="text-gray-700 hover:text-indigo-700 transition">
                    <div class="bg-white p-2 rounded-lg shadow-sm hover-lift">
                        <i class="fas fa-arrow-left text-lg"></i>
                    </div>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-white">Create New Assignment</h1>
                    <p class="text-gray-200">Design an assignment for your students</p>
                </div>
            </div>
            
            <div class="flex items-center space-x-4">
                <div class="hidden md:block text-right">
                    <p class="font-medium text-white"><?= htmlspecialchars($_SESSION['user']['username']) ?></p>
                    <p class="text-gray-200 text-sm">Teacher</p>
                </div>
                <div class="w-10 h-10 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-full flex items-center justify-center text-white font-bold shadow-lg">
                    <?= strtoupper(substr(htmlspecialchars($_SESSION['user']['username']), 0, 1)) ?>
                </div>
            </div>
        </div>

        <!-- Progress Steps -->
        <div class="glass-card rounded-2xl shadow-lg p-6 mb-6">
            <div class="flex justify-between items-center relative">
                <!-- Line connecting steps -->
                <div class="absolute top-1/2 left-0 right-0 h-1 bg-gray-200 -translate-y-1/2 z-0"></div>
                
                <!-- Steps -->
                <div class="relative z-10 flex justify-between w-full">
                    <div class="flex flex-col items-center">
                        <div class="step-indicator active">
                            <span>1</span>
                        </div>
                        <span class="mt-2 text-sm font-medium text-gray-700">Basic Info</span>
                    </div>
                    
                    <div class="flex flex-col items-center">
                        <div class="step-indicator">
                            <span>2</span>
                        </div>
                        <span class="mt-2 text-sm font-medium text-gray-600">Details</span>
                    </div>
                    
                    <div class="flex flex-col items-center">
                        <div class="step-indicator">
                            <span>3</span>
                        </div>
                        <span class="mt-2 text-sm font-medium text-gray-600">Review</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Form Card -->
        <div class="glass-card rounded-2xl shadow-2xl overflow-hidden mb-6">
            <!-- Form Header -->
            <div class="bg-gradient-to-r from-blue-500 to-cyan-500 px-6 py-5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="bg-white p-3 rounded-xl">
                            <i class="fas fa-file-alt text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-white">Assignment Details</h2>
                            <p class="text-blue-100">Fill in all required information</p>
                        </div>
                    </div>
                    <div class="bg-white/20 px-4 py-2 rounded-full">
                        <span class="text-white font-semibold">Required fields marked with *</span>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <?php if (!empty($error)): ?>
                <div class="mx-6 mt-6 p-4 rounded-xl border-l-4 border-red-500 bg-gradient-to-r from-red-50 to-red-100 text-red-800 flex justify-between items-center">
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
                            <?php if(isset($assignment_id)): ?>
                                <p class="text-sm mt-1">Assignment ID: <span class="font-mono"><?= $assignment_id ?></span></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex space-x-3 mt-4">
                        <a href="add_assignment.php<?= $subject_id ? '?subject_id=' . $subject_id : '' ?>" 
                           class="flex-1 text-center bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                            <i class="fas fa-plus mr-2"></i>
                            Create Another
                        </a>
                        <a href="teacher_dashboard.php?subject_id=<?= $subject_id ?>&tab=assignments" 
                           class="flex-1 text-center bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                            <i class="fas fa-list mr-2"></i>
                            View Assignments
                        </a>
                        <?php if(isset($assignment_id)): ?>
                        <a href="view_submissions.php?assignment_id=<?= $assignment_id ?>" 
                           class="flex-1 text-center bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                            <i class="fas fa-eye mr-2"></i>
                            View Submissions
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Main Form -->
            <form method="POST" class="p-6" id="assignmentForm">
                <!-- Step 1: Basic Information -->
                <div id="step1" class="tab-content active">
                    <h3 class="text-xl font-bold text-gray-800 mb-6 pb-3 border-b border-gray-200">
                        <i class="fas fa-info-circle mr-2 text-blue-600"></i>
                        Basic Information
                    </h3>
                    
                    <!-- Subject Selection -->
                    <div class="mb-8">
                        <label class="block mb-2 text-gray-700 font-semibold">
                            <i class="fas fa-book mr-2 text-blue-600"></i>
                            Select Subject *
                        </label>
                        <?php if(empty($subjects)): ?>
                            <div class="bg-gradient-to-r from-red-50 to-red-100 border-l-4 border-red-500 p-4 rounded-xl">
                                <p class="text-red-800 font-semibold">No Subjects Found</p>
                                <p class="text-red-600">You need to create a subject first before adding assignments.</p>
                                <a href="add_subject_teacher.php" class="inline-block mt-2 text-blue-600 hover:text-blue-800 font-medium">
                                    <i class="fas fa-plus mr-1"></i> Create a Subject
                                </a>
                            </div>
                        <?php else: ?>
                            <select name="subject_id" required 
                                    class="w-full border-2 border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-300 focus:border-transparent transition appearance-none">
                                <option value="">Choose a subject...</option>
                                <?php foreach($subjects as $sub): ?>
                                    <option value="<?= $sub['id'] ?>" 
                                        <?= ($subject_id == $sub['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sub['name']) ?>
                                        <?php if(!empty($sub['code'])): ?>
                                            (<?= htmlspecialchars($sub['code']) ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    <!-- Assignment Name -->
                    <div class="mb-6">
                        <label class="block mb-2 text-gray-700 font-semibold">
                            <i class="fas fa-heading mr-2 text-blue-600"></i>
                            Assignment Name *
                        </label>
                        <input type="text" name="name" required 
                               value="<?= htmlspecialchars($name) ?>"
                               placeholder="e.g., Midterm Essay, Lab Report, Final Project, etc."
                               class="w-full border-2 border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-300 focus:border-transparent transition">
                        <p class="text-gray-500 text-sm mt-2">Make it descriptive and clear</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <!-- Total Points -->
                        <div>
                            <label class="block mb-2 text-gray-700 font-semibold">
                                <i class="fas fa-star mr-2 text-yellow-500"></i>
                                Total Points *
                            </label>
                            <input type="number" name="total_points" min="1" max="1000" required 
                                   value="<?= htmlspecialchars($total_points ?: '100') ?>"
                                   placeholder="e.g., 100"
                                   class="w-full border-2 border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-300 focus:border-transparent transition">
                            <p class="text-gray-500 text-sm mt-2">Total points students can earn</p>
                        </div>

                        <!-- Due Date -->
                        <div>
                            <label class="block mb-2 text-gray-700 font-semibold">
                                <i class="far fa-calendar-alt mr-2 text-blue-600"></i>
                                Due Date
                            </label>
                            <div class="relative">
                                <input type="date" name="due_date" 
                                       value="<?= htmlspecialchars($due_date) ?>"
                                       class="w-full border-2 border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-300 focus:border-transparent transition">
                                <div class="absolute right-3 top-3 text-gray-400">
                                    <i class="fas fa-calendar-day"></i>
                                </div>
                            </div>
                            <p class="text-gray-500 text-sm mt-2">Set a deadline for this assignment</p>
                        </div>
                    </div>

                    <div class="flex justify-end pt-6 border-t border-gray-200">
                        <button type="button" onclick="nextStep(2)" 
                                class="btn-glow px-8 py-3 bg-gradient-to-r from-blue-600 to-cyan-600 text-white rounded-xl hover:from-blue-700 hover:to-cyan-700 transition font-semibold">
                            Next: Assignment Details
                            <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Assignment Details -->
                <div id="step2" class="tab-content">
                    <h3 class="text-xl font-bold text-gray-800 mb-6 pb-3 border-b border-gray-200">
                        <i class="fas fa-clipboard-list mr-2 text-blue-600"></i>
                        Assignment Description
                    </h3>

                    <!-- Description -->
                    <div class="mb-8">
                        <label class="block mb-2 text-gray-700 font-semibold">
                            <i class="fas fa-align-left mr-2 text-blue-600"></i>
                            Description / Instructions
                        </label>
                        <textarea name="description" rows="10" 
                                  placeholder="Provide detailed instructions, requirements, and evaluation criteria..."
                                  class="w-full border-2 border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-300 focus:border-transparent transition"><?= htmlspecialchars($description) ?></textarea>
                        <div class="flex justify-between items-center mt-2">
                            <p class="text-gray-500 text-sm">Be specific about what students need to do</p>
                            <span id="descCharCount" class="character-counter text-gray-400 text-sm">0/5000 characters</span>
                        </div>
                    </div>

                    <div class="flex justify-between pt-6 border-t border-gray-200">
                        <button type="button" onclick="prevStep(1)" 
                                class="px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-semibold">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Back
                        </button>
                        
                        <button type="button" onclick="nextStep(3)" 
                                class="btn-glow px-8 py-3 bg-gradient-to-r from-blue-600 to-cyan-600 text-white rounded-xl hover:from-blue-700 hover:to-cyan-700 transition font-semibold">
                            Next: Review & Create
                            <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 3: Review & Create -->
                <div id="step3" class="tab-content">
                    <h3 class="text-xl font-bold text-gray-800 mb-6 pb-3 border-b border-gray-200">
                        <i class="fas fa-check-circle mr-2 text-green-600"></i>
                        Review & Create
                    </h3>

                    <!-- Review Summary -->
                    <div class="mb-8 p-6 bg-gradient-to-r from-gray-50 to-gray-100 rounded-xl border border-gray-200">
                        <h4 class="text-lg font-bold text-gray-800 mb-4">Assignment Summary</h4>
                        
                        <div class="space-y-4">
                            <div class="flex">
                                <div class="w-1/3 text-gray-600 font-medium">Subject:</div>
                                <div id="reviewSubject" class="w-2/3 text-gray-800">-</div>
                            </div>
                            <div class="flex">
                                <div class="w-1/3 text-gray-600 font-medium">Assignment Name:</div>
                                <div id="reviewName" class="w-2/3 text-gray-800">-</div>
                            </div>
                            <div class="flex">
                                <div class="w-1/3 text-gray-600 font-medium">Due Date:</div>
                                <div id="reviewDueDate" class="w-2/3 text-gray-800">-</div>
                            </div>
                            <div class="flex">
                                <div class="w-1/3 text-gray-600 font-medium">Total Points:</div>
                                <div id="reviewTotalPoints" class="w-2/3 text-gray-800">-</div>
                            </div>
                            <div class="flex items-start">
                                <div class="w-1/3 text-gray-600 font-medium pt-1">Description:</div>
                                <div id="reviewDescription" class="w-2/3 text-gray-800 max-h-40 overflow-y-auto">-</div>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Options -->
                    <div class="mb-8 p-4 bg-gradient-to-r from-blue-50 to-cyan-50 rounded-xl border border-blue-200">
                        <h4 class="font-semibold text-gray-800 mb-3">Additional Options</h4>
                        
                        <div class="space-y-3">
                            <label class="flex items-center">
                                <input type="checkbox" name="clear_on_success" value="1" 
                                       class="mr-3 h-5 w-5 text-blue-600 rounded">
                                <span class="text-gray-700">Clear form after successful creation</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="notify_students" value="1" checked
                                       class="mr-3 h-5 w-5 text-blue-600 rounded">
                                <span class="text-gray-700">Notify enrolled students about new assignment</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-between pt-6 border-t border-gray-200">
                        <button type="button" onclick="prevStep(2)" 
                                class="px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-semibold">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Back
                        </button>
                        
                        <div class="space-x-4">
                            <button type="button" onclick="saveAsDraft()" 
                                    class="px-6 py-3 border-2 border-blue-300 text-blue-600 rounded-xl hover:bg-blue-50 transition font-semibold">
                                <i class="far fa-save mr-2"></i>
                                Save as Draft
                            </button>
                            
                            <button type="submit" 
                                    class="btn-glow px-8 py-3 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-xl hover:from-green-700 hover:to-emerald-700 transition font-semibold">
                                <i class="fas fa-check mr-2"></i>
                                Create Assignment
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Help Section -->
        <div class="glass-card rounded-2xl shadow-lg p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-lightbulb mr-3 text-yellow-500"></i>
                Tips for Effective Assignments
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="p-4 bg-gradient-to-r from-blue-50 to-cyan-50 rounded-xl">
                    <div class="flex items-center mb-2">
                        <div class="bg-blue-100 p-2 rounded-lg mr-3">
                            <i class="fas fa-calendar-check text-blue-600"></i>
                        </div>
                        <h4 class="font-semibold text-gray-800">Clear Deadlines</h4>
                    </div>
                    <p class="text-gray-600 text-sm">Set realistic due dates and consider student workload.</p>
                </div>
                
                <div class="p-4 bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl">
                    <div class="flex items-center mb-2">
                        <div class="bg-green-100 p-2 rounded-lg mr-3">
                            <i class="fas fa-list-ol text-green-600"></i>
                        </div>
                        <h4 class="font-semibold text-gray-800">Detailed Instructions</h4>
                    </div>
                    <p class="text-gray-600 text-sm">Explain exactly what students need to do and how they'll be graded.</p>
                </div>
                
                <div class="p-4 bg-gradient-to-r from-purple-50 to-pink-50 rounded-xl">
                    <div class="flex items-center mb-2">
                        <div class="bg-purple-100 p-2 rounded-lg mr-3">
                            <i class="fas fa-balance-scale text-purple-600"></i>
                        </div>
                        <h4 class="font-semibold text-gray-800">Appropriate Points</h4>
                    </div>
                    <p class="text-gray-600 text-sm">Match the point value to the assignment's complexity and length.</p>
                </div>
                
                <div class="p-4 bg-gradient-to-r from-yellow-50 to-amber-50 rounded-xl">
                    <div class="flex items-center mb-2">
                        <div class="bg-yellow-100 p-2 rounded-lg mr-3">
                            <i class="fas fa-book-open text-yellow-600"></i>
                        </div>
                        <h4 class="font-semibold text-gray-800">Learning Objectives</h4>
                    </div>
                    <p class="text-gray-600 text-sm">Clearly state what students should learn from the assignment.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Step Navigation
        let currentStep = 1;
        
        function nextStep(step) {
            // Validate current step before proceeding
            if (!validateStep(currentStep)) {
                return;
            }
            
            document.getElementById(`step${currentStep}`).classList.remove('active');
            document.getElementById(`step${step}`).classList.add('active');
            updateStepIndicator(currentStep, step);
            currentStep = step;
            
            // Update review section
            if (step === 3) {
                updateReviewSection();
            }
        }
        
        function prevStep(step) {
            document.getElementById(`step${currentStep}`).classList.remove('active');
            document.getElementById(`step${step}`).classList.add('active');
            updateStepIndicator(currentStep, step, false);
            currentStep = step;
        }
        
        function updateStepIndicator(oldStep, newStep, forward = true) {
            const oldIndicator = document.querySelector(`.step-indicator:nth-child(${oldStep})`);
            const newIndicator = document.querySelector(`.step-indicator:nth-child(${newStep})`);
            
            if (forward) {
                oldIndicator.classList.remove('active');
                oldIndicator.classList.add('completed');
                newIndicator.classList.add('active');
            } else {
                oldIndicator.classList.remove('active');
                newIndicator.classList.remove('completed');
                newIndicator.classList.add('active');
            }
        }
        
        // Form Validation
        function validateStep(step) {
            let isValid = true;
            let errorMessage = '';
            
            switch(step) {
                case 1:
                    const name = document.querySelector('input[name="name"]').value.trim();
                    const subject = document.querySelector('select[name="subject_id"]').value;
                    const totalPoints = document.querySelector('input[name="total_points"]').value;
                    
                    if (!name) {
                        errorMessage = 'Please enter an assignment name.';
                        document.querySelector('input[name="name"]').focus();
                    } else if (!subject) {
                        errorMessage = 'Please select a subject.';
                        document.querySelector('select[name="subject_id"]').focus();
                    } else if (!totalPoints || totalPoints <= 0) {
                        errorMessage = 'Please enter a valid total points value.';
                        document.querySelector('input[name="total_points"]').focus();
                    }
                    break;
                    
                case 2:
                    // No validation needed for description (it's optional in your schema)
                    break;
            }
            
            if (errorMessage) {
                alert('Please fix the following:\n\n' + errorMessage);
                isValid = false;
            }
            
            return isValid;
        }
        
        // Character Counter
        function setupCharacterCounter() {
            const textarea = document.querySelector('textarea[name="description"]');
            const counter = document.getElementById('descCharCount');
            
            textarea.addEventListener('input', function() {
                const length = this.value.length;
                counter.textContent = `${length}/5000 characters`;
                
                counter.className = 'character-counter text-sm';
                if (length > 4000) {
                    counter.classList.add('warning');
                }
                if (length > 5000) {
                    counter.classList.add('error');
                } else {
                    counter.classList.add('text-gray-400');
                }
            });
            
            // Initialize
            const initialLength = textarea.value.length;
            counter.textContent = `${initialLength}/5000 characters`;
        }
        
        // Update Review Section
        function updateReviewSection() {
            // Subject
            const subjectSelect = document.querySelector('select[name="subject_id"]');
            const selectedSubject = subjectSelect.options[subjectSelect.selectedIndex];
            document.getElementById('reviewSubject').textContent = selectedSubject.text || '-';
            
            // Name
            document.getElementById('reviewName').textContent = 
                document.querySelector('input[name="name"]').value || '-';
            
            // Due Date
            const dueDate = document.querySelector('input[name="due_date"]').value;
            if (dueDate) {
                const date = new Date(dueDate);
                document.getElementById('reviewDueDate').textContent = 
                    date.toLocaleDateString('en-US', { 
                        weekday: 'long', 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    });
            } else {
                document.getElementById('reviewDueDate').textContent = 'No due date set';
            }
            
            // Total Points
            document.getElementById('reviewTotalPoints').textContent = 
                document.querySelector('input[name="total_points"]').value + ' points' || '-';
            
            // Description
            const description = document.querySelector('textarea[name="description"]').value;
            document.getElementById('reviewDescription').textContent = 
                description || 'No description provided';
            
            // Limit description preview length
            if (description.length > 300) {
                document.getElementById('reviewDescription').textContent = 
                    description.substring(0, 300) + '...';
            }
        }
        
        // Save as Draft (placeholder function - you can implement this later)
        function saveAsDraft() {
            if (confirm('Save this assignment as a draft? You can publish it later.')) {
                // Note: Your current table doesn't have a draft field
                // You could add an `is_published` or `status` field later
                alert('Draft feature not implemented yet. Creating as published assignment.');
                document.getElementById('assignmentForm').submit();
            }
        }
        
        // Set minimum date for due date
        const dueDateInput = document.querySelector('input[name="due_date"]');
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        
        dueDateInput.min = `${year}-${month}-${day}`;
        
        // Form submission validation
        document.getElementById('assignmentForm').addEventListener('submit', function(e) {
            if (!validateStep(1)) {
                e.preventDefault();
                alert('Please complete all required fields in step 1.');
                return false;
            }
            return true;
        });
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            setupCharacterCounter();
            
            // Set default total points to 100 if empty
            const totalPointsInput = document.querySelector('input[name="total_points"]');
            if (!totalPointsInput.value) {
                totalPointsInput.value = 100;
            }
        });
    </script>
</body>
</html>