<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
check_login();

// Only teachers can add projects
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
$name = $title = $description = $total_points = $due_date = $subject_id = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject_id = $_POST['subject_id'];
    $name = trim($_POST['name']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $total_points = $_POST['total_points'];
    $due_date = $_POST['due_date'];

    // Verify teacher owns the subject
    $stmt = $pdo->prepare("SELECT id FROM subjects WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$subject_id, $teacher_id]);
    if (!$stmt->fetch()) {
        $error = "Invalid subject selection.";
    } elseif (empty($name) || empty($total_points)) {
        $error = "Project Name and Total Points are required.";
    } elseif ($due_date && strtotime($due_date) < time()) {
        $error = "Due date must be in the future.";
    } else {
        try {
            // Insert project using your table structure
            $stmt = $pdo->prepare("
                INSERT INTO projects 
                (subject_id, name, title, description, due_date, total_points, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $subject_id, 
                $name, 
                !empty($title) ? $title : null,
                !empty($description) ? $description : null,
                !empty($due_date) ? $due_date : null,
                $total_points,
                $teacher_id
            ]);
            
            $project_id = $pdo->lastInsertId();
            $success = "Project created successfully!";
            
            // Clear form on success if checkbox is checked
            if (isset($_POST['clear_on_success']) && $_POST['clear_on_success'] == '1') {
                $name = $title = $description = $total_points = $due_date = '';
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
    <title>Create New Project - Teacher Dashboard</title>
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
            background: linear-gradient(to right, #10b981, #059669);
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
        
        .project-type-card {
            transition: all 0.3s ease;
            border: 2px solid #e5e7eb;
            cursor: pointer;
        }
        
        .project-type-card:hover {
            border-color: #10b981;
            transform: translateY(-2px);
        }
        
        .project-type-card.selected {
            border-color: #10b981;
            background: linear-gradient(to right, #f0fdf4, #dcfce7);
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: #10b981;
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }
        
        .milestone-item {
            transition: all 0.3s ease;
        }
        
        .milestone-item:hover {
            background: #f8fafc;
        }
    </style>
</head>
<body class="p-4 md:p-6">
    <div class="max-w-5xl mx-auto">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div class="flex items-center space-x-3">
                <a href="teacher_dashboard.php<?= $selected_subject_id ? '?subject_id=' . $selected_subject_id . '&tab=projects' : '' ?>" 
                   class="text-gray-700 hover:text-emerald-700 transition">
                    <div class="bg-white p-2 rounded-lg shadow-sm hover-lift">
                        <i class="fas fa-arrow-left text-lg"></i>
                    </div>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-white">Create New Project</h1>
                    <p class="text-gray-200">Design a comprehensive project for your students</p>
                </div>
            </div>
            
            <div class="flex items-center space-x-4">
                <div class="hidden md:block text-right">
                    <p class="font-medium text-white"><?= htmlspecialchars($_SESSION['user']['username']) ?></p>
                    <p class="text-gray-200 text-sm">Teacher</p>
                </div>
                <div class="w-10 h-10 bg-gradient-to-r from-emerald-600 to-green-600 rounded-full flex items-center justify-center text-white font-bold shadow-lg">
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
                        <span class="mt-2 text-sm font-medium text-gray-600">Project Details</span>
                    </div>
                    
                    <div class="flex flex-col items-center">
                        <div class="step-indicator">
                            <span>3</span>
                        </div>
                        <span class="mt-2 text-sm font-medium text-gray-600">Requirements</span>
                    </div>
                    
                    <div class="flex flex-col items-center">
                        <div class="step-indicator">
                            <span>4</span>
                        </div>
                        <span class="mt-2 text-sm font-medium text-gray-600">Review</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Form Card -->
        <div class="glass-card rounded-2xl shadow-2xl overflow-hidden mb-6">
            <!-- Form Header -->
            <div class="bg-gradient-to-r from-emerald-500 to-green-500 px-6 py-5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="bg-white p-3 rounded-xl">
                            <i class="fas fa-project-diagram text-emerald-600 text-xl"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-white">Project Details</h2>
                            <p class="text-emerald-100">Create a comprehensive project for your students</p>
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
                            <?php if(isset($project_id)): ?>
                                <p class="text-sm mt-1">Project ID: <span class="font-mono"><?= $project_id ?></span></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex space-x-3 mt-4">
                        <a href="add_project.php<?= $subject_id ? '?subject_id=' . $subject_id : '' ?>" 
                           class="flex-1 text-center bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                            <i class="fas fa-plus mr-2"></i>
                            Create Another
                        </a>
                        <a href="teacher_dashboard.php?subject_id=<?= $subject_id ?>&tab=projects" 
                           class="flex-1 text-center bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700 transition">
                            <i class="fas fa-list mr-2"></i>
                            View Projects
                        </a>
                        <?php if(isset($project_id)): ?>
                        <a href="view_submissions.php?project_id=<?= $project_id ?>" 
                           class="flex-1 text-center bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                            <i class="fas fa-eye mr-2"></i>
                            View Submissions
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Main Form -->
            <form method="POST" class="p-6" id="projectForm">
                <!-- Step 1: Basic Information -->
                <div id="step1" class="tab-content active">
                    <h3 class="text-xl font-bold text-gray-800 mb-6 pb-3 border-b border-gray-200">
                        <i class="fas fa-info-circle mr-2 text-emerald-600"></i>
                        Basic Information
                    </h3>
                    
                    <!-- Subject Selection -->
                    <div class="mb-8">
                        <label class="block mb-2 text-gray-700 font-semibold">
                            <i class="fas fa-book mr-2 text-emerald-600"></i>
                            Select Subject *
                        </label>
                        <?php if(empty($subjects)): ?>
                            <div class="bg-gradient-to-r from-red-50 to-red-100 border-l-4 border-red-500 p-4 rounded-xl">
                                <p class="text-red-800 font-semibold">No Subjects Found</p>
                                <p class="text-red-600">You need to create a subject first before adding projects.</p>
                                <a href="add_subject_teacher.php" class="inline-block mt-2 text-emerald-600 hover:text-emerald-800 font-medium">
                                    <i class="fas fa-plus mr-1"></i> Create a Subject
                                </a>
                            </div>
                        <?php else: ?>
                            <select name="subject_id" required 
                                    class="w-full border-2 border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-300 focus:border-transparent transition appearance-none">
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

                    <!-- Project Type Selection -->
                    <div class="mb-8">
                        <label class="block mb-4 text-gray-700 font-semibold">
                            <i class="fas fa-tags mr-2 text-emerald-600"></i>
                            Project Type (Optional)
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4" id="projectTypeSelector">
                            <div class="project-type-card p-4 rounded-xl text-center" data-type="research">
                                <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-cyan-500 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <i class="fas fa-search text-white text-xl"></i>
                                </div>
                                <h4 class="font-bold text-gray-800">Research</h4>
                                <p class="text-gray-600 text-sm mt-1">Academic study</p>
                            </div>
                            
                            <div class="project-type-card p-4 rounded-xl text-center" data-type="coding">
                                <div class="w-12 h-12 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <i class="fas fa-code text-white text-xl"></i>
                                </div>
                                <h4 class="font-bold text-gray-800">Coding</h4>
                                <p class="text-gray-600 text-sm mt-1">Programming project</p>
                            </div>
                            
                            <div class="project-type-card p-4 rounded-xl text-center" data-type="design">
                                <div class="w-12 h-12 bg-gradient-to-r from-yellow-500 to-amber-500 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <i class="fas fa-paint-brush text-white text-xl"></i>
                                </div>
                                <h4 class="font-bold text-gray-800">Design</h4>
                                <p class="text-gray-600 text-sm mt-1">Creative design work</p>
                            </div>
                            
                            <div class="project-type-card p-4 rounded-xl text-center" data-type="presentation">
                                <div class="w-12 h-12 bg-gradient-to-r from-red-500 to-orange-500 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <i class="fas fa-presentation text-white text-xl"></i>
                                </div>
                                <h4 class="font-bold text-gray-800">Presentation</h4>
                                <p class="text-gray-600 text-sm mt-1">Oral presentation</p>
                            </div>
                        </div>
                        <input type="hidden" name="project_type" id="projectTypeInput" value="">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <!-- Project Name (Required) -->
                        <div>
                            <label class="block mb-2 text-gray-700 font-semibold">
                                <i class="fas fa-heading mr-2 text-emerald-600"></i>
                                Project Name *
                            </label>
                            <input type="text" name="name" required 
                                   value="<?= htmlspecialchars($name) ?>"
                                   placeholder="e.g., Final Project, Capstone Project, Research Paper, etc."
                                   class="w-full border-2 border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-300 focus:border-transparent transition">
                            <p class="text-gray-500 text-sm mt-2">Main name of the project</p>
                        </div>

                        <!-- Project Title (Optional) -->
                        <div>
                            <label class="block mb-2 text-gray-700 font-semibold">
                                <i class="fas fa-tag mr-2 text-emerald-600"></i>
                                Project Title (Optional)
                            </label>
                            <input type="text" name="title" 
                                   value="<?= htmlspecialchars($title) ?>"
                                   placeholder="e.g., 'Analysis of Machine Learning Algorithms'"
                                   class="w-full border-2 border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-300 focus:border-transparent transition">
                            <p class="text-gray-500 text-sm mt-2">Specific title or subtitle</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <!-- Total Points -->
                        <div>
                            <label class="block mb-2 text-gray-700 font-semibold">
                                <i class="fas fa-star mr-2 text-yellow-500"></i>
                                Total Points *
                            </label>
                            <input type="number" name="total_points" min="1" max="1000" required 
                                   value="<?= htmlspecialchars($total_points ?: '100') ?>"
                                   placeholder="e.g., 100"
                                   class="w-full border-2 border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-300 focus:border-transparent transition">
                            <p class="text-gray-500 text-sm mt-2">Total points students can earn</p>
                        </div>

                        <!-- Due Date -->
                        <div>
                            <label class="block mb-2 text-gray-700 font-semibold">
                                <i class="far fa-calendar-alt mr-2 text-emerald-600"></i>
                                Due Date
                            </label>
                            <div class="relative">
                                <input type="date" name="due_date" 
                                       value="<?= htmlspecialchars($due_date) ?>"
                                       class="w-full border-2 border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-300 focus:border-transparent transition">
                                <div class="absolute right-3 top-3 text-gray-400">
                                    <i class="fas fa-calendar-day"></i>
                                </div>
                            </div>
                            <p class="text-gray-500 text-sm mt-2">Final submission deadline</p>
                        </div>
                    </div>

                    <div class="flex justify-end pt-6 border-t border-gray-200">
                        <button type="button" onclick="nextStep(2)" 
                                class="btn-glow px-8 py-3 bg-gradient-to-r from-emerald-600 to-green-600 text-white rounded-xl hover:from-emerald-700 hover:to-green-700 transition font-semibold">
                            Next: Project Details
                            <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Project Details -->
                <div id="step2" class="tab-content">
                    <h3 class="text-xl font-bold text-gray-800 mb-6 pb-3 border-b border-gray-200">
                        <i class="fas fa-clipboard-list mr-2 text-emerald-600"></i>
                        Project Description
                    </h3>

                    <!-- Description -->
                    <div class="mb-8">
                        <label class="block mb-2 text-gray-700 font-semibold">
                            <i class="fas fa-align-left mr-2 text-emerald-600"></i>
                            Project Description
                        </label>
                        <textarea name="description" rows="8" 
                                  placeholder="Provide a detailed description of the project, including objectives, scope, and expected outcomes..."
                                  class="w-full border-2 border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-300 focus:border-transparent transition"><?= htmlspecialchars($description) ?></textarea>
                        <div class="flex justify-between items-center mt-2">
                            <p class="text-gray-500 text-sm">Describe what students need to accomplish</p>
                            <span id="descCharCount" class="character-counter text-gray-400 text-sm">0/5000 characters</span>
                        </div>
                    </div>

                    <!-- Learning Objectives -->
                    <div class="mb-8">
                        <label class="block mb-2 text-gray-700 font-semibold">
                            <i class="fas fa-bullseye mr-2 text-emerald-600"></i>
                            Learning Objectives (Optional)
                        </label>
                        <div id="objectivesContainer" class="space-y-3">
                            <div class="flex items-center space-x-3">
                                <input type="text" placeholder="Enter a learning objective..." 
                                       class="flex-1 border-2 border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-300 focus:border-transparent transition objective-input">
                                <button type="button" onclick="addObjective()" 
                                        class="bg-emerald-100 text-emerald-600 p-3 rounded-xl hover:bg-emerald-200 transition">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mt-3 hidden" id="objectivesList">
                            <h5 class="text-sm font-medium text-gray-700 mb-2">Learning Objectives:</h5>
                            <div id="objectivesDisplay" class="space-y-2"></div>
                        </div>
                        <input type="hidden" name="learning_objectives" id="learningObjectivesInput" value="">
                    </div>

                    <div class="flex justify-between pt-6 border-t border-gray-200">
                        <button type="button" onclick="prevStep(1)" 
                                class="px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-semibold">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Back
                        </button>
                        
                        <button type="button" onclick="nextStep(3)" 
                                class="btn-glow px-8 py-3 bg-gradient-to-r from-emerald-600 to-green-600 text-white rounded-xl hover:from-emerald-700 hover:to-green-700 transition font-semibold">
                            Next: Requirements
                            <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 3: Requirements -->
                <div id="step3" class="tab-content">
                    <h3 class="text-xl font-bold text-gray-800 mb-6 pb-3 border-b border-gray-200">
                        <i class="fas fa-tasks mr-2 text-emerald-600"></i>
                        Project Requirements
                    </h3>

                    <!-- Project Requirements -->
                    <div class="mb-8">
                        <label class="block mb-2 text-gray-700 font-semibold">
                            <i class="fas fa-list-check mr-2 text-emerald-600"></i>
                            Key Requirements (Optional)
                        </label>
                        <div id="requirementsContainer" class="space-y-3">
                            <div class="flex items-center space-x-3">
                                <input type="text" placeholder="Enter a requirement..." 
                                       class="flex-1 border-2 border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-emerald-300 focus:border-transparent transition requirement-input">
                                <button type="button" onclick="addRequirement()" 
                                        class="bg-emerald-100 text-emerald-600 p-3 rounded-xl hover:bg-emerald-200 transition">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mt-3 hidden" id="requirementsList">
                            <h5 class="text-sm font-medium text-gray-700 mb-2">Project Requirements:</h5>
                            <div id="requirementsDisplay" class="space-y-2"></div>
                        </div>
                        <input type="hidden" name="project_requirements" id="projectRequirementsInput" value="">
                    </div>

                    <!-- Group Project Toggle -->
                    <div class="mb-8 p-4 bg-gradient-to-r from-gray-50 to-gray-100 rounded-xl border border-gray-200">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h4 class="font-semibold text-gray-800">
                                    <i class="fas fa-users mr-2 text-emerald-600"></i>
                                    Group Project
                                </h4>
                                <p class="text-gray-600 text-sm">Allow students to work in groups</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="is_group_project" value="1" id="groupProjectToggle">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div id="groupSettings" class="mt-4 pl-10 hidden">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block mb-2 text-gray-700 font-semibold">
                                        Maximum Group Size
                                    </label>
                                    <input type="number" name="max_group_size" min="2" max="10" value="4"
                                           class="w-full border-2 border-gray-300 rounded-xl px-4 py-3">
                                    <p class="text-gray-500 text-sm mt-2">Maximum students per group</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Milestones Section -->
                    <div class="mb-8 p-4 bg-gradient-to-r from-blue-50 to-cyan-50 rounded-xl border border-blue-200">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h4 class="font-semibold text-gray-800">
                                    <i class="fas fa-flag-checkered mr-2 text-blue-600"></i>
                                    Project Milestones (Optional)
                                </h4>
                                <p class="text-gray-600 text-sm">Break down the project into phases</p>
                            </div>
                            <button type="button" onclick="addMilestone()" 
                                    class="bg-blue-100 text-blue-600 px-4 py-2 rounded-xl hover:bg-blue-200 transition">
                                <i class="fas fa-plus mr-2"></i>
                                Add Milestone
                            </button>
                        </div>
                        
                        <div id="milestonesContainer" class="space-y-4">
                            <!-- Milestones will be added here dynamically -->
                        </div>
                        <input type="hidden" name="milestones" id="milestonesInput" value="">
                    </div>

                    <div class="flex justify-between pt-6 border-t border-gray-200">
                        <button type="button" onclick="prevStep(2)" 
                                class="px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-semibold">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Back
                        </button>
                        
                        <button type="button" onclick="nextStep(4)" 
                                class="btn-glow px-8 py-3 bg-gradient-to-r from-emerald-600 to-green-600 text-white rounded-xl hover:from-emerald-700 hover:to-green-700 transition font-semibold">
                            Next: Review & Create
                            <i class="fas fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 4: Review & Create -->
                <div id="step4" class="tab-content">
                    <h3 class="text-xl font-bold text-gray-800 mb-6 pb-3 border-b border-gray-200">
                        <i class="fas fa-check-circle mr-2 text-green-600"></i>
                        Review & Create
                    </h3>

                    <!-- Review Summary -->
                    <div class="mb-8 p-6 bg-gradient-to-r from-gray-50 to-gray-100 rounded-xl border border-gray-200">
                        <h4 class="text-lg font-bold text-gray-800 mb-4">Project Summary</h4>
                        
                        <div class="space-y-4">
                            <div class="flex">
                                <div class="w-1/3 text-gray-600 font-medium">Subject:</div>
                                <div id="reviewSubject" class="w-2/3 text-gray-800">-</div>
                            </div>
                            <div class="flex">
                                <div class="w-1/3 text-gray-600 font-medium">Project Name:</div>
                                <div id="reviewName" class="w-2/3 text-gray-800">-</div>
                            </div>
                            <div class="flex">
                                <div class="w-1/3 text-gray-600 font-medium">Project Title:</div>
                                <div id="reviewTitle" class="w-2/3 text-gray-800">-</div>
                            </div>
                            <div class="flex">
                                <div class="w-1/3 text-gray-600 font-medium">Due Date:</div>
                                <div id="reviewDueDate" class="w-2/3 text-gray-800">-</div>
                            </div>
                            <div class="flex">
                                <div class="w-1/3 text-gray-600 font-medium">Total Points:</div>
                                <div id="reviewTotalPoints" class="w/2/3 text-gray-800">-</div>
                            </div>
                            <div class="flex">
                                <div class="w-1/3 text-gray-600 font-medium">Project Type:</div>
                                <div id="reviewType" class="w-2/3 text-gray-800">-</div>
                            </div>
                            <div class="flex">
                                <div class="w-1/3 text-gray-600 font-medium">Group Project:</div>
                                <div id="reviewGroup" class="w-2/3 text-gray-800">-</div>
                            </div>
                            <div class="flex items-start">
                                <div class="w-1/3 text-gray-600 font-medium pt-1">Description:</div>
                                <div id="reviewDescription" class="w-2/3 text-gray-800 max-h-32 overflow-y-auto">-</div>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Options -->
                    <div class="mb-8 p-4 bg-gradient-to-r from-emerald-50 to-green-50 rounded-xl border border-emerald-200">
                        <h4 class="font-semibold text-gray-800 mb-3">Additional Options</h4>
                        
                        <div class="space-y-3">
                            <label class="flex items-center">
                                <input type="checkbox" name="clear_on_success" value="1" 
                                       class="mr-3 h-5 w-5 text-emerald-600 rounded">
                                <span class="text-gray-700">Clear form after successful creation</span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="notify_students" value="1" checked
                                       class="mr-3 h-5 w-5 text-emerald-600 rounded">
                                <span class="text-gray-700">Notify enrolled students about new project</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-between pt-6 border-t border-gray-200">
                        <button type="button" onclick="prevStep(3)" 
                                class="px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-semibold">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Back
                        </button>
                        
                        <div class="space-x-4">
                            <button type="button" onclick="saveAsDraft()" 
                                    class="px-6 py-3 border-2 border-emerald-300 text-emerald-600 rounded-xl hover:bg-emerald-50 transition font-semibold">
                                <i class="far fa-save mr-2"></i>
                                Save as Draft
                            </button>
                            
                            <button type="submit" 
                                    class="btn-glow px-8 py-3 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-xl hover:from-green-700 hover:to-emerald-700 transition font-semibold">
                                <i class="fas fa-check mr-2"></i>
                                Create Project
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
                Tips for Effective Projects
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="p-4 bg-gradient-to-r from-emerald-50 to-green-50 rounded-xl">
                    <div class="flex items-center mb-2">
                        <div class="bg-emerald-100 p-2 rounded-lg mr-3">
                            <i class="fas fa-flag-checkered text-emerald-600"></i>
                        </div>
                        <h4 class="font-semibold text-gray-800">Clear Milestones</h4>
                    </div>
                    <p class="text-gray-600 text-sm">Break large projects into manageable phases with deadlines.</p>
                </div>
                
                <div class="p-4 bg-gradient-to-r from-blue-50 to-cyan-50 rounded-xl">
                    <div class="flex items-center mb-2">
                        <div class="bg-blue-100 p-2 rounded-lg mr-3">
                            <i class="fas fa-users text-blue-600"></i>
                        </div>
                        <h4 class="font-semibold text-gray-800">Group Guidelines</h4>
                    </div>
                    <p class="text-gray-600 text-sm">Specify group size and collaboration expectations.</p>
                </div>
                
                <div class="p-4 bg-gradient-to-r from-purple-50 to-pink-50 rounded-xl">
                    <div class="flex items-center mb-2">
                        <div class="bg-purple-100 p-2 rounded-lg mr-3">
                            <i class="fas fa-ruler-combined text-purple-600"></i>
                        </div>
                        <h4 class="font-semibold text-gray-800">Clear Rubric</h4>
                    </div>
                    <p class="text-gray-600 text-sm">Explain exactly how the project will be evaluated.</p>
                </div>
                
                <div class="p-4 bg-gradient-to-r from-yellow-50 to-amber-50 rounded-xl">
                    <div class="flex items-center mb-2">
                        <div class="bg-yellow-100 p-2 rounded-lg mr-3">
                            <i class="fas fa-book-open text-yellow-600"></i>
                        </div>
                        <h4 class="font-semibold text-gray-800">Learning Goals</h4>
                    </div>
                    <p class="text-gray-600 text-sm">Clearly state what students should learn from the project.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Step Navigation
        let currentStep = 1;
        let milestoneCounter = 0;
        
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
            if (step === 4) {
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
                        errorMessage = 'Please enter a project name.';
                        document.querySelector('input[name="name"]').focus();
                    } else if (!subject) {
                        errorMessage = 'Please select a subject.';
                        document.querySelector('select[name="subject_id"]').focus();
                    } else if (!totalPoints || totalPoints <= 0) {
                        errorMessage = 'Please enter a valid total points value.';
                        document.querySelector('input[name="total_points"]').focus();
                    }
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
        
        // Project Type Selection
        const projectTypeCards = document.querySelectorAll('.project-type-card');
        const projectTypeInput = document.getElementById('projectTypeInput');
        
        projectTypeCards.forEach(card => {
            card.addEventListener('click', function() {
                // Remove selected class from all cards
                projectTypeCards.forEach(c => c.classList.remove('selected'));
                
                // Add selected class to clicked card
                this.classList.add('selected');
                
                // Update hidden input
                const selectedType = this.getAttribute('data-type');
                projectTypeInput.value = selectedType;
            });
        });
        
        // Learning Objectives
        let objectives = [];
        
        function addObjective() {
            const input = document.querySelector('.objective-input');
            const value = input.value.trim();
            
            if (value) {
                objectives.push(value);
                updateObjectivesDisplay();
                input.value = '';
                
                // Show objectives list
                document.getElementById('objectivesList').classList.remove('hidden');
            }
        }
        
        function updateObjectivesDisplay() {
            const container = document.getElementById('objectivesDisplay');
            container.innerHTML = '';
            
            objectives.forEach((obj, index) => {
                const div = document.createElement('div');
                div.className = 'flex items-center justify-between bg-white p-3 rounded-lg border border-gray-200';
                div.innerHTML = `
                    <div class="flex items-center">
                        <div class="w-6 h-6 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mr-3">
                            ${index + 1}
                        </div>
                        <span>${obj}</span>
                    </div>
                    <button type="button" onclick="removeObjective(${index})" 
                            class="text-red-500 hover:text-red-700">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                container.appendChild(div);
            });
            
            // Update hidden input
            document.getElementById('learningObjectivesInput').value = JSON.stringify(objectives);
        }
        
        function removeObjective(index) {
            objectives.splice(index, 1);
            updateObjectivesDisplay();
            
            if (objectives.length === 0) {
                document.getElementById('objectivesList').classList.add('hidden');
            }
        }
        
        // Project Requirements
        let requirements = [];
        
        function addRequirement() {
            const input = document.querySelector('.requirement-input');
            const value = input.value.trim();
            
            if (value) {
                requirements.push(value);
                updateRequirementsDisplay();
                input.value = '';
                
                // Show requirements list
                document.getElementById('requirementsList').classList.remove('hidden');
            }
        }
        
        function updateRequirementsDisplay() {
            const container = document.getElementById('requirementsDisplay');
            container.innerHTML = '';
            
            requirements.forEach((req, index) => {
                const div = document.createElement('div');
                div.className = 'flex items-center justify-between bg-white p-3 rounded-lg border border-gray-200';
                div.innerHTML = `
                    <div class="flex items-center">
                        <div class="w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mr-3">
                            <i class="fas fa-check text-xs"></i>
                        </div>
                        <span>${req}</span>
                    </div>
                    <button type="button" onclick="removeRequirement(${index})" 
                            class="text-red-500 hover:text-red-700">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                container.appendChild(div);
            });
            
            // Update hidden input
            document.getElementById('projectRequirementsInput').value = JSON.stringify(requirements);
        }
        
        function removeRequirement(index) {
            requirements.splice(index, 1);
            updateRequirementsDisplay();
            
            if (requirements.length === 0) {
                document.getElementById('requirementsList').classList.add('hidden');
            }
        }
        
        // Group Project Toggle
        const groupProjectToggle = document.getElementById('groupProjectToggle');
        const groupSettings = document.getElementById('groupSettings');
        
        groupProjectToggle.addEventListener('change', function() {
            if (this.checked) {
                groupSettings.classList.remove('hidden');
            } else {
                groupSettings.classList.add('hidden');
            }
        });
        
        // Milestones
        let milestones = [];
        
        function addMilestone() {
            milestoneCounter++;
            const container = document.getElementById('milestonesContainer');
            
            const milestoneDiv = document.createElement('div');
            milestoneDiv.className = 'milestone-item bg-white p-4 rounded-xl border border-gray-200';
            milestoneDiv.innerHTML = `
                <div class="flex justify-between items-center mb-3">
                    <h5 class="font-semibold text-gray-800">Milestone ${milestoneCounter}</h5>
                    <button type="button" onclick="removeMilestone(this)" 
                            class="text-red-500 hover:text-red-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                    <div>
                        <label class="block mb-1 text-gray-700 text-sm">Title</label>
                        <input type="text" class="milestone-title w-full border border-gray-300 rounded-lg px-3 py-2" 
                               placeholder="e.g., Proposal, First Draft, etc.">
                    </div>
                    <div>
                        <label class="block mb-1 text-gray-700 text-sm">Due Date</label>
                        <input type="date" class="milestone-date w-full border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                </div>
                <div>
                    <label class="block mb-1 text-gray-700 text-sm">Description</label>
                    <textarea class="milestone-desc w-full border border-gray-300 rounded-lg px-3 py-2" 
                              rows="2" placeholder="Brief description of this milestone"></textarea>
                </div>
            `;
            
            container.appendChild(milestoneDiv);
            updateMilestonesInput();
            
            // Add event listeners to new inputs
            const inputs = milestoneDiv.querySelectorAll('input, textarea');
            inputs.forEach(input => {
                input.addEventListener('input', updateMilestonesInput);
            });
        }
        
        function removeMilestone(button) {
            const milestoneDiv = button.closest('.milestone-item');
            milestoneDiv.remove();
            updateMilestonesInput();
        }
        
        function updateMilestonesInput() {
            const milestonesData = [];
            const milestoneItems = document.querySelectorAll('.milestone-item');
            
            milestoneItems.forEach((item, index) => {
                const title = item.querySelector('.milestone-title').value.trim();
                const date = item.querySelector('.milestone-date').value;
                const desc = item.querySelector('.milestone-desc').value.trim();
                
                if (title || date || desc) {
                    milestonesData.push({
                        title: title,
                        due_date: date,
                        description: desc,
                        order: index + 1
                    });
                }
            });
            
            document.getElementById('milestonesInput').value = JSON.stringify(milestonesData);
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
            
            // Title
            const title = document.querySelector('input[name="title"]').value;
            document.getElementById('reviewTitle').textContent = title || 'Not specified';
            
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
            
            // Project Type
            const projectType = document.getElementById('projectTypeInput').value;
            document.getElementById('reviewType').textContent = 
                projectType ? projectType.charAt(0).toUpperCase() + projectType.slice(1) : 'Not specified';
            
            // Group Project
            const isGroup = document.getElementById('groupProjectToggle').checked;
            document.getElementById('reviewGroup').textContent = isGroup ? 'Yes' : 'No';
            
            // Description
            const description = document.querySelector('textarea[name="description"]').value;
            document.getElementById('reviewDescription').textContent = 
                description || 'No description provided';
            
            // Limit description preview length
            if (description && description.length > 200) {
                document.getElementById('reviewDescription').textContent = 
                    description.substring(0, 200) + '...';
            }
        }
        
        // Save as Draft (placeholder function)
        function saveAsDraft() {
            if (confirm('Save this project as a draft? You can publish it later.')) {
                alert('Draft feature not implemented yet. Creating as published project.');
                document.getElementById('projectForm').submit();
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
        document.getElementById('projectForm').addEventListener('submit', function(e) {
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
            
            // Add project type to form if selected
            projectTypeCards.forEach(card => {
                card.addEventListener('click', function() {
                    const selectedType = this.getAttribute('data-type');
                    // Add hidden input to form
                    let typeInput = document.querySelector('input[name="project_type"]');
                    if (!typeInput) {
                        typeInput = document.createElement('input');
                        typeInput.type = 'hidden';
                        typeInput.name = 'project_type';
                        document.getElementById('projectForm').appendChild(typeInput);
                    }
                    typeInput.value = selectedType;
                });
            });
        });
    </script>
</body>
</html>