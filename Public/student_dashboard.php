<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
check_login();

// Only allow students
if ($_SESSION['user']['role_id'] != 3) {
    header('Location: login.php');
    exit;
}

$student_id = $_SESSION['user']['id'];
$selected_subject_id = $_GET['subject_id'] ?? null;
$search_query = strtolower($_GET['search_subject'] ?? '');
$subject_name = '';
$activities = [];
$projects = [];
$assignments = [];
$announcements = [];
$message = '';
$overall_grade = null;
$edit_mode = false;

// Fetch student details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$student_details = $stmt->fetch();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'] ?? null;
    $address = $_POST['address'] ?? null;
    
    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, address = ? WHERE id = ?");
    $stmt->execute([$full_name, $email, $phone, $address, $student_id]);
    
    // Update session
    $_SESSION['user']['full_name'] = $full_name;
    $_SESSION['user']['email'] = $email;
    
    $message = "Profile updated successfully!";
    $edit_mode = true; // Stay in edit mode
}

// Handle enrollment request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_subject_id'])) {
    $enroll_subject_id = $_POST['enroll_subject_id'];
    $stmt = $pdo->prepare("SELECT id FROM student_subject WHERE student_id = ? AND subject_id = ?");
    $stmt->execute([$student_id, $enroll_subject_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        $message = "You have already requested or are enrolled in this subject.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO student_subject (student_id, subject_id, status) VALUES (?, ?, 'pending')");
        $stmt->execute([$student_id, $enroll_subject_id]);
        $message = "Enrollment request submitted successfully!";
    }
}

// Fetch all subjects the student is enrolled in
$stmt = $pdo->prepare("
    SELECT s.id, s.name, ss.status
    FROM student_subject ss
    JOIN subjects s ON ss.subject_id = s.id
    WHERE ss.student_id = ?
");
$stmt->execute([$student_id]);
$subjects = $stmt->fetchAll();

// Fetch all available subjects for enrollment
$stmt = $pdo->prepare("
    SELECT s.id, s.name, u.username AS teacher_name
    FROM subjects s
    JOIN users u ON s.teacher_id = u.id
    WHERE s.id NOT IN (SELECT subject_id FROM student_subject WHERE student_id = ?)
");
$stmt->execute([$student_id]);
$available_subjects = $stmt->fetchAll();

// Fetch subject-related data if selected
if ($selected_subject_id) {
    // Get subject name
    $stmt = $pdo->prepare("SELECT name FROM subjects WHERE id = ?");
    $stmt->execute([$selected_subject_id]);
    $subject = $stmt->fetch();
    $subject_name = $subject ? $subject['name'] : '';

    // Activities with latest submission
    $stmt = $pdo->prepare("
        SELECT a.id, a.name AS activity_name, a.type, a.total_points,
               s.id AS submission_id, s.score, s.teacher_comment
        FROM activities a
        LEFT JOIN (
            SELECT s1.*
            FROM submissions s1
            INNER JOIN (
                SELECT activity_id, student_id, MAX(created_at) AS latest
                FROM submissions
                WHERE student_id = ?
                GROUP BY activity_id, student_id
            ) s2 ON s1.activity_id = s2.activity_id 
                AND s1.student_id = s2.student_id 
                AND s1.created_at = s2.latest
        ) s ON s.activity_id = a.id
        WHERE a.subject_id = ?
        ORDER BY a.id ASC
    ");
    $stmt->execute([$student_id, $selected_subject_id]);
    $activities = $stmt->fetchAll();

    // Assignments with latest submission
    $stmt = $pdo->prepare("
        SELECT a.id, a.name, a.description, a.due_date, a.total_points,
               s.id AS submission_id, s.score, s.teacher_comment
        FROM assignments a
        LEFT JOIN (
            SELECT s1.*
            FROM submissions s1
            INNER JOIN (
                SELECT assignment_id, student_id, MAX(created_at) AS latest
                FROM submissions
                WHERE student_id = ?
                GROUP BY assignment_id, student_id
            ) s2 ON s1.assignment_id = s2.assignment_id 
                AND s1.student_id = s2.student_id 
                AND s1.created_at = s2.latest
        ) s ON s.assignment_id = a.id
        WHERE a.subject_id = ?
        ORDER BY a.due_date ASC
    ");
    $stmt->execute([$student_id, $selected_subject_id]);
    $assignments = $stmt->fetchAll();

    // Projects with latest submission
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.description, p.due_date,
               s.id AS submission_id, s.score, s.teacher_comment
        FROM projects p
        LEFT JOIN (
            SELECT s1.*
            FROM submissions s1
            INNER JOIN (
                SELECT project_id, student_id, MAX(created_at) AS latest
                FROM submissions
                WHERE student_id = ?
                GROUP BY project_id, student_id
            ) s2 ON s1.project_id = s2.project_id 
                AND s1.student_id = s2.student_id 
                AND s1.created_at = s2.latest
        ) s ON s.project_id = p.id
        WHERE p.subject_id = ?
        ORDER BY p.due_date ASC
    ");
    $stmt->execute([$student_id, $selected_subject_id]);
    $projects = $stmt->fetchAll();

    // Announcements
    $stmt = $pdo->prepare("SELECT id, title, message, created_at FROM announcements WHERE subject_id = ? ORDER BY created_at DESC");
    $stmt->execute([$selected_subject_id]);
    $announcements = $stmt->fetchAll();

    // Calculate overall grade (only graded items)
    $total_score = 0;
    $total_points = 0;

    $all_items = array_merge($activities, $assignments, $projects);

    foreach ($all_items as $item) {
        if ($item['score'] !== null && isset($item['total_points'])) {
            $total_score += $item['score'];
            $total_points += $item['total_points'];
        }
    }

    $overall_grade = $total_points > 0 ? round(($total_score / $total_points) * 100, 2) : null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
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
        
        .sidebar {
            width: 280px;
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
            z-index: 1050;
        }
        
        .sidebar.active {
            transform: translateX(0);
            box-shadow: 5px 0 25px rgba(0, 0, 0, 0.1);
        }
        
        .main-content {
            transition: margin-left 0.3s ease-in-out;
            position: relative;
            z-index: 1;
        }
        
        .sidebar.active ~ .main-content {
            margin-left: 280px;
        }
        
        @media (max-width: 768px) {
            .sidebar.active ~ .main-content {
                margin-left: 0;
            }
            .sidebar {
                width: 280px;
                z-index: 1050;
            }
            .sidebar.active {
                transform: translateX(0);
            }
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .input-focus-effect {
            transition: all 0.3s ease;
        }
        
        .input-focus-effect:focus {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
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
        
        .hover-lift {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .hover-lift:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .progress-bar {
            height: 8px;
            border-radius: 4px;
            background-color: rgba(229, 231, 235, 0.5);
            overflow: hidden;
        }
        
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(to right, #10B981, #34D399);
            border-radius: 4px;
            transition: width 0.5s ease-in-out;
        }
        
        .grade-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 9999px;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 6px;
        }
        
        .status-approved { background-color: #10B981; }
        .status-pending { background-color: #F59E0B; }
        .status-rejected { background-color: #EF4444; }
        
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
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: rgba(241, 241, 241, 0.5);
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(193, 193, 193, 0.7);
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(161, 161, 161, 0.9);
        }
        
        .nav-item {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .nav-item.active {
            background: linear-gradient(90deg, #4F46E5, #7C3AED);
            color: white;
            transform: translateX(5px);
        }
        
        .nav-item.active i {
            color: white;
        }
        
        .nav-item:hover:not(.active) {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }
        
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1040;
            backdrop-filter: blur(2px);
        }
        
        .overlay.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        /* Ensure links are clickable */
        a, button {
            cursor: pointer;
        }
        
        /* Improve button clickability */
        .nav-item a {
            display: block;
            width: 100%;
            height: 100%;
            text-decoration: none;
        }
    </style>
</head>
<body class="overflow-x-hidden">
    <!-- Sidebar Overlay -->
    <div class="overlay" id="sidebar-overlay"></div>
    
    <!-- Sidebar -->
    <aside class="sidebar fixed top-0 left-0 h-full bg-gradient-to-b from-indigo-900 to-purple-900 text-white shadow-2xl">
        <div class="flex flex-col h-full">
            <!-- Sidebar Header -->
            <div class="p-6 border-b border-indigo-800">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center space-x-3">
                        <div class="bg-gradient-to-r from-indigo-500 to-purple-500 p-2 rounded-lg">
                            <i class="fas fa-graduation-cap text-white text-xl"></i>
                        </div>
                        <h2 class="text-xl font-bold">Student Portal</h2>
                    </div>
                    <button id="close-sidebar" class="lg:hidden text-white hover:text-indigo-300 hover:bg-white/10 p-2 rounded-full transition">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <!-- Student Profile -->
                <div class="flex items-center space-x-3 p-3 bg-gradient-to-r from-indigo-800/50 to-purple-800/50 rounded-xl">
                    <div class="relative">
                       <div class="w-14 h-14 bg-gradient-to-r from-indigo-500 to-purple-500 rounded-full flex items-center justify-center text-white font-bold text-xl shadow-lg">
    <?= strtoupper(substr(htmlspecialchars($_SESSION['user']['full_name'] ?? $_SESSION['user']['username']), 0, 1)) ?>
</div>
                        <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-500 rounded-full border-2 border-indigo-900"></div>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-bold"><?= htmlspecialchars($_SESSION['user']['full_name'] ?? $_SESSION['user']['username']) ?></h3>
                        <p class="text-sm text-indigo-300">Student</p>
                        <p class="text-xs text-indigo-400 mt-1">ID: <?= htmlspecialchars($_SESSION['user']['id']) ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Navigation -->
            <div class="flex-1 overflow-y-auto p-4">
                <ul class="space-y-2">
                    <li class="nav-item <?= !$selected_subject_id && !isset($_GET['edit_profile']) ? 'active' : '' ?>">
                        <a href="student_dashboard.php" class="flex items-center space-x-3 p-3 rounded-xl">
                            <div class="w-8 text-center">
                                <i class="fas fa-home text-indigo-300"></i>
                            </div>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    
                    <?php if(count($subjects) > 0): ?>
                    <li class="mt-6 mb-2">
                        <div class="px-3 text-xs font-semibold text-indigo-400 uppercase tracking-wider">My Subjects</div>
                    </li>
                    <?php foreach($subjects as $sub): ?>
                    <li class="nav-item <?= $selected_subject_id == $sub['id'] ? 'active' : '' ?>">
                        <a href="?subject_id=<?= $sub['id'] ?>" class="flex items-center space-x-3 p-3 rounded-xl">
                            <div class="w-8 text-center">
                                <i class="fas fa-book text-indigo-300"></i>
                            </div>
                            <div class="flex-1">
                                <span><?= htmlspecialchars($sub['name']) ?></span>
                                <div class="text-xs text-indigo-400 mt-1">
                                    <?php if($sub['status'] == 'pending'): ?>
                                        <span class="inline-flex items-center">
                                            <span class="status-indicator status-pending"></span>
                                            Pending
                                        </span>
                                    <?php elseif($sub['status'] == 'approved'): ?>
                                        <span class="inline-flex items-center">
                                            <span class="status-indicator status-approved"></span>
                                            Enrolled
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center">
                                            <span class="status-indicator status-rejected"></span>
                                            <?= ucfirst($sub['status']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    </li>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <li class="mt-6 mb-2">
                        <div class="px-3 text-xs font-semibold text-indigo-400 uppercase tracking-wider">Account</div>
                    </li>
                    <li class="nav-item <?= isset($_GET['edit_profile']) ? 'active' : '' ?>">
                        <a href="?edit_profile=1" class="flex items-center space-x-3 p-3 rounded-xl">
                            <div class="w-8 text-center">
                                <i class="fas fa-user-edit text-indigo-300"></i>
                            </div>
                            <span>Edit Profile</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="change_password.php" class="flex items-center space-x-3 p-3 rounded-xl">
                            <div class="w-8 text-center">
                                <i class="fas fa-key text-indigo-300"></i>
                            </div>
                            <span>Change Password</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="logout.php" class="flex items-center space-x-3 p-3 rounded-xl">
                            <div class="w-8 text-center">
                                <i class="fas fa-sign-out-alt text-indigo-300"></i>
                            </div>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Sidebar Footer -->
            <div class="p-4 border-t border-indigo-800">
                <div class="text-center text-indigo-400 text-sm">
                    <p>&copy; <?= date('Y') ?> Learning Portal</p>
                    <p class="text-xs mt-1">v1.0.0</p>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="main-content min-h-screen">
        <!-- Header -->
        <header class="dashboard-container shadow-lg z-10 relative">
            <div class="container mx-auto px-6 py-4">
                <div class="flex justify-between items-center">
                    <div class="flex items-center space-x-4">
                        <button id="toggle-sidebar" class="text-gray-700 hover:text-indigo-700 p-2 rounded-lg hover:bg-gray-100 transition">
                            <i class="fas fa-bars text-2xl"></i>
                        </button>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800">Student Dashboard</h1>
                            <p class="text-gray-600 text-sm">
                                <?php if($selected_subject_id): ?>
                                    <?= htmlspecialchars($subject_name) ?>
                                <?php elseif(isset($_GET['edit_profile'])): ?>
                                    Edit Profile
                                <?php else: ?>
                                    Welcome back, <?= htmlspecialchars($_SESSION['user']['full_name'] ?? $_SESSION['user']['username']) ?>!
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <div class="hidden md:block text-right">
                            <p class="font-medium text-gray-700"><?= htmlspecialchars($_SESSION['user']['full_name'] ?? $_SESSION['user']['username']) ?></p>
                            <p class="text-sm text-gray-500">Student</p>
                        </div>
                        <div class="relative">
                           <div class="w-10 h-10 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-full flex items-center justify-center text-white font-bold shadow-lg cursor-pointer" id="user-menu-btn">
                                <?= strtoupper(substr(htmlspecialchars($_SESSION['user']['full_name'] ?? $_SESSION['user']['username']), 0, 1)) ?>
                            </div>
                            <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-500 rounded-full border-2 border-white"></div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="container mx-auto px-4 py-6">
            <!-- Notification -->
            <?php if($message): ?>
            <div id="notification" class="mb-6 p-4 rounded-xl border-l-4 border-green-500 bg-gradient-to-r from-green-50 to-green-100 text-green-800 flex justify-between items-center slide-in shadow-lg">
                <div class="flex items-center space-x-3">
                    <div class="bg-green-500 p-2 rounded-full">
                        <i class="fas fa-check-circle text-white"></i>
                    </div>
                    <div>
                        <p class="font-semibold">Success!</p>
                        <p><?= htmlspecialchars($message) ?></p>
                    </div>
                </div>
                <button onclick="dismissNotification()" class="text-green-600 hover:text-green-800 transition">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            <?php endif; ?>

            <?php if(isset($_GET['edit_profile'])): ?>
            <!-- Edit Profile View -->
            <div class="slide-in">
                <div class="mb-8">
                    <div class="flex items-center mb-6">
                        <div class="bg-gradient-to-r from-indigo-100 to-purple-100 p-3 rounded-xl mr-4">
                            <i class="fas fa-user-edit text-indigo-600 text-2xl"></i>
                        </div>
                        <div>
                            <h2 class="text-3xl font-bold text-gray-800">Edit Profile</h2>
                            <p class="text-gray-600">Update your personal information</p>
                        </div>
                    </div>
                </div>

                <div class="dashboard-container rounded-2xl shadow-lg p-6">
                    <form method="POST" action="">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-gray-700 font-medium mb-2">Full Name</label>
                                <input type="text" name="full_name" value="<?= htmlspecialchars($student_details['full_name'] ?? '') ?>" 
                                       class="input-focus-effect w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-transparent" required>
                            </div>
                            <div>
                                <label class="block text-gray-700 font-medium mb-2">Username</label>
                                <input type="text" value="<?= htmlspecialchars($student_details['username'] ?? '') ?>" 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-100" disabled>
                                <p class="text-sm text-gray-500 mt-1">Username cannot be changed</p>
                            </div>
                            <div>
                                <label class="block text-gray-700 font-medium mb-2">Email Address</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($student_details['email'] ?? '') ?>" 
                                       class="input-focus-effect w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-transparent" required>
                            </div>
                            <div>
                                <label class="block text-gray-700 font-medium mb-2">Phone Number</label>
                                <input type="tel" name="phone" value="<?= htmlspecialchars($student_details['phone'] ?? '') ?>" 
                                       class="input-focus-effect w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-transparent">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-gray-700 font-medium mb-2">Address</label>
                                <textarea name="address" rows="3" 
                                          class="input-focus-effect w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-transparent"><?= htmlspecialchars($student_details['address'] ?? '') ?></textarea>
                            </div>
                            <div>
                                <label class="block text-gray-700 font-medium mb-2">Student ID</label>
                                <input type="text" value="<?= htmlspecialchars($student_details['id'] ?? '') ?>" 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-100" disabled>
                            </div>
                            <div>
                                <label class="block text-gray-700 font-medium mb-2">Role</label>
                                <input type="text" value="Student" 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-xl bg-gray-100" disabled>
                            </div>
                        </div>
                        
                        <div class="flex justify-end space-x-4 mt-8 pt-6 border-t border-gray-200">
                            <a href="?" class="btn-glow bg-gradient-to-r from-gray-600 to-gray-700 text-white px-6 py-3 rounded-xl hover:from-gray-700 hover:to-gray-800 transition">
                                Cancel
                            </a>
                            <button type="submit" name="update_profile" class="btn-glow bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-3 rounded-xl hover:from-indigo-700 hover:to-purple-700 transition">
                                <i class="fas fa-save mr-2"></i>
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <?php elseif(!$selected_subject_id): ?>
            <!-- Subject Selection View -->
            <div class="slide-in">
                <div class="mb-8 text-center">
                    <h2 class="text-3xl font-bold text-gray-800 mb-2">My Learning Journey</h2>
                    <p class="text-white-600 max-w-2xl mx-auto">Manage your enrolled subjects and discover new learning opportunities.</p>
                </div>

                <!-- Enrolled Subjects -->
                <div class="mb-10">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-800">Current Enrollment</h3>
                            <p class="text-white-500">Subjects you're currently enrolled in</p>
                        </div>
                        <span class="bg-gradient-to-r from-indigo-100 to-purple-100 text-indigo-800 px-4 py-2 rounded-full font-semibold">
                            <?= count($subjects) ?> subject<?= count($subjects) !== 1 ? 's' : '' ?>
                        </span>
                    </div>
                    
                    <?php if(count($subjects) > 0): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach($subjects as $sub): ?>
                        <a href="?subject_id=<?= $sub['id'] ?>" class="dashboard-container rounded-2xl shadow-lg hover-lift p-6 border border-gray-100 block group">
                            <div class="flex justify-between items-start mb-4">
                                <div class="flex-1">
                                    <h4 class="font-bold text-lg text-gray-800 group-hover:text-indigo-700 transition"><?= htmlspecialchars($sub['name']) ?></h4>
                                    <div class="mt-3">
                                        <?php if($sub['status'] == 'pending'): ?>
                                            <span class="grade-badge bg-gradient-to-r from-yellow-100 to-yellow-200 text-yellow-800 border border-yellow-300">
                                                <span class="status-indicator status-pending"></span>
                                                Pending Approval
                                            </span>
                                        <?php elseif($sub['status'] == 'approved'): ?>
                                            <span class="grade-badge bg-gradient-to-r from-green-100 to-green-200 text-green-800 border border-green-300">
                                                <span class="status-indicator status-approved"></span>
                                                Enrolled
                                            </span>
                                        <?php else: ?>
                                            <span class="grade-badge bg-gradient-to-r from-red-100 to-red-200 text-red-800 border border-red-300">
                                                <span class="status-indicator status-rejected"></span>
                                                <?= htmlspecialchars(ucfirst($sub['status'])) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="bg-gradient-to-r from-indigo-50 to-purple-50 p-3 rounded-xl group-hover:from-indigo-100 group-hover:to-purple-100 transition">
                                    <i class="fas fa-book-open text-indigo-600 text-xl"></i>
                                </div>
                            </div>
                            <div class="flex justify-between items-center mt-6 pt-4 border-t border-gray-100">
                                <span class="text-gray-600 text-sm">View details & progress</span>
                                <div class="bg-gradient-to-r from-indigo-500 to-purple-500 text-white p-2 rounded-full group-hover:from-indigo-600 group-hover:to-purple-600 transition transform group-hover:translate-x-2">
                                    <i class="fas fa-chevron-right"></i>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="dashboard-container rounded-2xl shadow-lg p-10 text-center">
                        <div class="w-24 h-24 bg-gradient-to-r from-indigo-100 to-purple-100 rounded-full flex items-center justify-center mx-auto mb-6">
                            <i class="fas fa-book-open text-indigo-500 text-4xl"></i>
                        </div>
                        <h4 class="text-2xl font-bold text-gray-800 mb-3">No Subjects Enrolled Yet</h4>
                        <p class="text-gray-600 mb-6 max-w-md mx-auto">You haven't enrolled in any subjects yet. Browse available subjects below to start your learning journey!</p>
                        <a href="#available-subjects" class="btn-glow inline-flex items-center space-x-2 bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-3 rounded-xl font-semibold">
                            <span>Browse Available Subjects</span>
                            <i class="fas fa-arrow-down"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Available Subjects -->
                <?php if($available_subjects): ?>
                <div id="available-subjects" class="mb-10 slide-in">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-800">Discover New Subjects</h3>
                            <p class="text-white-500">Expand your knowledge by enrolling in new subjects</p>
                        </div>
                        <span class="bg-gradient-to-r from-blue-100 to-cyan-100 text-blue-800 px-4 py-2 rounded-full font-semibold">
                            <?= count($available_subjects) ?> available
                        </span>
                    </div>
                    
                    <!-- Search Bar -->
                    <form method="GET" class="mb-8">
                        <div class="relative max-w-xl mx-auto">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                            <input type="text" name="search_subject" placeholder="Search subjects by name or teacher..." 
                                   value="<?= htmlspecialchars($_GET['search_subject'] ?? '') ?>"
                                   class="input-focus-effect w-full pl-12 pr-32 py-4 border border-gray-300 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-transparent shadow-sm">
                            <button type="submit" class="btn-glow absolute right-2 top-2 bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-2.5 rounded-xl hover:from-indigo-700 hover:to-purple-700 transition">
                                <i class="fas fa-search mr-2"></i>
                                Search
                            </button>
                        </div>
                    </form>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach($available_subjects as $sub): 
                            if ($search_query && strpos(strtolower($sub['name']), $search_query) === false && 
                                strpos(strtolower($sub['teacher_name']), $search_query) === false) continue;
                        ?>
                        <form method="POST" class="dashboard-container rounded-2xl shadow-lg hover-lift p-6 border border-gray-100">
                            <div class="flex justify-between items-start mb-4">
                                <div class="flex-1">
                                    <h4 class="font-bold text-lg text-gray-800 mb-2"><?= htmlspecialchars($sub['name']) ?></h4>
                                    <div class="flex items-center text-gray-600 text-sm bg-gradient-to-r from-gray-50 to-gray-100 p-3 rounded-lg">
                                        <div class="bg-gradient-to-r from-blue-500 to-cyan-500 text-white p-2 rounded-full mr-3">
                                            <i class="fas fa-chalkboard-teacher"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium">Teacher</p>
                                            <p class="text-gray-800"><?= htmlspecialchars($sub['teacher_name']) ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gradient-to-r from-blue-50 to-cyan-50 p-3 rounded-xl">
                                    <i class="fas fa-plus-circle text-blue-600 text-2xl"></i>
                                </div>
                            </div>
                            
                            <div class="flex justify-between items-center mt-6 pt-4 border-t border-gray-100">
                                <input type="hidden" name="enroll_subject_id" value="<?= $sub['id'] ?>">
                                <button type="submit" class="btn-glow bg-gradient-to-r from-blue-600 to-cyan-600 text-white px-5 py-2.5 rounded-xl hover:from-blue-700 hover:to-cyan-700 transition flex items-center space-x-2">
                                    <i class="fas fa-paper-plane"></i>
                                    <span>Request Enrollment</span>
                                </button>
                                <span class="text-gray-500 text-sm">Click to request</span>
                            </div>
                        </form>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if($search_query && empty(array_filter($available_subjects, function($sub) use ($search_query) { 
                        return strpos(strtolower($sub['name']), $search_query) !== false || 
                               strpos(strtolower($sub['teacher_name']), $search_query) !== false; 
                    }))): ?>
                    <div class="dashboard-container rounded-2xl shadow-lg p-10 text-center mt-8">
                        <div class="w-24 h-24 bg-gradient-to-r from-gray-100 to-gray-200 rounded-full flex items-center justify-center mx-auto mb-6">
                            <i class="fas fa-search text-gray-400 text-4xl"></i>
                        </div>
                        <h4 class="text-2xl font-bold text-gray-800 mb-3">No Results Found</h4>
                        <p class="text-gray-600 mb-6">No subjects match your search for "<span class="font-semibold"><?= htmlspecialchars($search_query) ?></span>".</p>
                        <a href="?" class="btn-glow inline-flex items-center space-x-2 bg-gradient-to-r from-gray-600 to-gray-700 text-white px-6 py-3 rounded-xl font-semibold">
                            <i class="fas fa-undo"></i>
                            <span>Clear Search</span>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php else: ?>
            <!-- Subject Detail View -->
            <div class="slide-in">
                <!-- Back Button and Subject Header -->
                <div class="mb-8">
                    <a href="?" class="inline-flex items-center text-gray-600 hover:text-indigo-700 mb-6 group transition">
                        <div class="bg-gradient-to-r from-gray-100 to-gray-200 p-2 rounded-full group-hover:from-indigo-100 group-hover:to-purple-100 transition mr-3">
                            <i class="fas fa-arrow-left text-gray-600 group-hover:text-indigo-600"></i>
                        </div>
                        <span class="font-medium">Back to Dashboard</span>
                    </a>
                    
                    <div class="dashboard-container rounded-2xl shadow-lg p-6 mb-6">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                            <div class="mb-4 md:mb-0">
                                <div class="flex items-center mb-3">
                                    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 p-3 rounded-xl mr-4">
                                        <i class="fas fa-book text-white text-2xl"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-3xl font-bold text-gray-800"><?= htmlspecialchars($subject_name) ?></h2>
                                        <div class="flex items-center text-gray-600 mt-2">
                                            <i class="fas fa-calendar-alt mr-2"></i>
                                            <span><?= date('F d, Y') ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if($overall_grade !== null): ?>
                            <div class="bg-gradient-to-r from-green-50 to-emerald-50 p-5 rounded-2xl border border-green-100 min-w-[280px]">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-gray-700 font-semibold">Overall Grade</span>
                                    <span class="font-bold text-2xl <?= $overall_grade >= 70 ? 'text-green-600' : ($overall_grade >= 50 ? 'text-yellow-600' : 'text-red-600') ?>">
                                        <?= $overall_grade ?>%
                                    </span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-bar-fill" style="width: <?= min(100, $overall_grade) ?>%"></div>
                                </div>
                                <div class="text-right mt-1">
                                    <span class="text-xs text-gray-500">
                                        <?= $overall_grade >= 70 ? 'Excellent!' : ($overall_grade >= 50 ? 'Good progress' : 'Needs improvement') ?>
                                    </span>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="bg-gradient-to-r from-gray-50 to-gray-100 p-5 rounded-2xl border border-gray-200 min-w-[280px]">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-gray-700 font-semibold">Overall Grade</span>
                                    <span class="font-bold text-2xl text-gray-500">N/A</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-bar-fill" style="width: 0%"></div>
                                </div>
                                <div class="text-right mt-1">
                                    <span class="text-xs text-gray-500">No graded items yet</span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Tabs Navigation -->
                <div class="dashboard-container rounded-2xl shadow-lg mb-6 p-2">
                    <div class="flex overflow-x-auto">
                        <button id="announcements-tab" class="tab-button active" onclick="switchTab('announcements')">
                            <div class="flex items-center">
                                <div class="bg-gradient-to-r from-indigo-100 to-purple-100 p-2 rounded-lg mr-3">
                                    <i class="fas fa-bullhorn text-indigo-600"></i>
                                </div>
                                <div class="text-left">
                                    <div class="font-semibold">Announcements</div>
                                    <div class="text-xs text-gray-500">Updates from teacher</div>
                                </div>
                            </div>
                        </button>
                        <button id="activities-tab" class="tab-button" onclick="switchTab('activities')">
                            <div class="flex items-center">
                                <div class="bg-gradient-to-r from-blue-100 to-cyan-100 p-2 rounded-lg mr-3">
                                    <i class="fas fa-tasks text-blue-600"></i>
                                </div>
                                <div class="text-left">
                                    <div class="font-semibold">Activities</div>
                                    <div class="text-xs text-gray-500">Practice exercises</div>
                                </div>
                            </div>
                        </button>
                        <button id="assignments-tab" class="tab-button" onclick="switchTab('assignments')">
                            <div class="flex items-center">
                                <div class="bg-gradient-to-r from-green-100 to-emerald-100 p-2 rounded-lg mr-3">
                                    <i class="fas fa-file-alt text-green-600"></i>
                                </div>
                                <div class="text-left">
                                    <div class="font-semibold">Assignments</div>
                                    <div class="text-xs text-gray-500">Homework tasks</div>
                                </div>
                            </div>
                        </button>
                        <button id="projects-tab" class="tab-button" onclick="switchTab('projects')">
                            <div class="flex items-center">
                                <div class="bg-gradient-to-r from-purple-100 to-pink-100 p-2 rounded-lg mr-3">
                                    <i class="fas fa-project-diagram text-purple-600"></i>
                                </div>
                                <div class="text-left">
                                    <div class="font-semibold">Projects</div>
                                    <div class="text-xs text-gray-500">Long-term work</div>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Tab Content -->
                <div id="announcements-content" class="tab-content">
                    <div class="dashboard-container rounded-2xl shadow-lg p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-2xl font-bold text-gray-800">Latest Announcements</h3>
                            <span class="bg-gradient-to-r from-indigo-100 to-purple-100 text-indigo-800 px-4 py-2 rounded-full font-semibold">
                                <?= count($announcements) ?> announcement<?= count($announcements) !== 1 ? 's' : '' ?>
                            </span>
                        </div>
                        <?php if($announcements): ?>
                            <div class="space-y-4">
                                <?php foreach($announcements as $ann): ?>
                                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border-l-4 border-indigo-500 p-5 rounded-xl hover-lift">
                                    <div class="flex justify-between items-start mb-3">
                                        <h4 class="font-bold text-lg text-gray-800"><?= htmlspecialchars($ann['title']) ?></h4>
                                        <div class="bg-white px-3 py-1 rounded-full text-sm text-gray-600 shadow-sm">
                                            <i class="far fa-clock mr-1"></i>
                                            <?= date('M d, Y', strtotime($ann['created_at'])) ?>
                                        </div>
                                    </div>
                                    <p class="text-gray-700 mb-4"><?= htmlspecialchars($ann['message']) ?></p>
                                    <div class="text-gray-500 text-sm flex items-center">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        Posted at <?= date('h:i A', strtotime($ann['created_at'])) ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-12">
                                <div class="w-24 h-24 bg-gradient-to-r from-gray-100 to-gray-200 rounded-full flex items-center justify-center mx-auto mb-6">
                                    <i class="fas fa-bullhorn text-gray-400 text-4xl"></i>
                                </div>
                                <h4 class="text-2xl font-bold text-gray-800 mb-3">No Announcements Yet</h4>
                                <p class="text-gray-600 max-w-md mx-auto">Your teacher hasn't posted any announcements for this subject yet. Check back later for updates!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div id="activities-content" class="tab-content hidden">
                    <div class="dashboard-container rounded-2xl shadow-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-50 to-cyan-50 px-6 py-5 border-b border-blue-100">
                            <h3 class="text-2xl font-bold text-gray-800">Activities</h3>
                            <p class="text-gray-600">Complete activities to earn points and improve your grade.</p>
                        </div>
                        <div class="p-6">
                            <?php renderTable($activities, 'activity', 'activities'); ?>
                        </div>
                    </div>
                </div>

                <div id="assignments-content" class="tab-content hidden">
                    <div class="dashboard-container rounded-2xl shadow-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-green-50 to-emerald-50 px-6 py-5 border-b border-green-100">
                            <h3 class="text-2xl font-bold text-gray-800">Assignments</h3>
                            <p class="text-gray-600">Submit assignments before the due date to avoid penalties.</p>
                        </div>
                        <div class="p-6">
                            <?php renderTable($assignments, 'assignment', 'assignments'); ?>
                        </div>
                    </div>
                </div>

                <div id="projects-content" class="tab-content hidden">
                    <div class="dashboard-container rounded-2xl shadow-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-purple-50 to-pink-50 px-6 py-5 border-b border-purple-100">
                            <h3 class="text-2xl font-bold text-gray-800">Projects</h3>
                            <p class="text-gray-600">Long-term projects with higher point values.</p>
                        </div>
                        <div class="p-6">
                            <?php renderTable($projects, 'project', 'projects'); ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>

        <!-- Footer -->
        <footer class="mt-12 pt-8 border-t border-gray-200">
            <div class="container mx-auto px-4 py-6">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <div class="flex items-center space-x-3 mb-4 md:mb-0">
                        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 p-2 rounded-lg">
                            <i class="fas fa-graduation-cap text-white"></i>
                        </div>
                        <div class="text-gray-600">
                            <p class="font-medium">Student Performance Tracker</p>
                            <p class="text-sm">&copy; <?= date('Y') ?> All rights reserved.</p>
                        </div>
                    </div>
                    <div class="flex space-x-6">
                        <a href="#" class="text-gray-600 hover:text-indigo-600 transition flex items-center space-x-2">
                            <i class="fas fa-question-circle"></i>
                            <span>Help Center</span>
                        </a>
                        <a href="#" class="text-gray-600 hover:text-indigo-600 transition flex items-center space-x-2">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
                        <a href="#" class="text-gray-600 hover:text-indigo-600 transition flex items-center space-x-2">
                            <i class="fas fa-envelope"></i>
                            <span>Contact</span>
                        </a>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <script>
        // Sidebar functionality
        const toggleSidebar = document.getElementById('toggle-sidebar');
        const closeSidebar = document.getElementById('close-sidebar');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        
        // Function to open sidebar
        function openSidebar() {
            sidebar.classList.add('active');
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        // Function to close sidebar
        function closeSidebarFunc() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
        
        // Event listeners
        toggleSidebar.addEventListener('click', openSidebar);
        closeSidebar.addEventListener('click', closeSidebarFunc);
        overlay.addEventListener('click', closeSidebarFunc);
        
        // Close sidebar on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeSidebarFunc();
            }
        });
        
        // Close sidebar on window resize (if mobile)
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                closeSidebarFunc();
            }
        });
        
        // Tab switching functionality
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Show selected tab content and activate tab button
            document.getElementById(tabName + '-content').classList.remove('hidden');
            document.getElementById(tabName + '-tab').classList.add('active');
        }
        
        // Dismiss notification
        function dismissNotification() {
            const notification = document.getElementById('notification');
            if (notification) {
                notification.style.transform = 'translateX(100%)';
                notification.style.opacity = '0';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }
        }
        
        // Initialize first tab as active if we're in subject view
        document.addEventListener('DOMContentLoaded', function() {
            <?php if($selected_subject_id): ?>
            switchTab('announcements');
            <?php endif; ?>
        });
    </script>

    <?php
    // Updated renderTable function
    function renderTable($items, $type, $typePlural) {
        if (!$items) { 
            echo '<div class="text-center py-12">
                    <div class="w-24 h-24 bg-gradient-to-r from-gray-100 to-gray-200 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-clipboard-list text-gray-400 text-4xl"></i>
                    </div>
                    <h4 class="text-2xl font-bold text-gray-800 mb-3">No ' . ucfirst($typePlural) . ' Yet</h4>
                    <p class="text-gray-600 max-w-md mx-auto">Your teacher hasn\'t added any ' . $typePlural . ' to this subject yet. Check back later!</p>
                  </div>'; 
            return; 
        }
        
        echo '<div class="overflow-x-auto rounded-xl border border-gray-200 shadow-sm">';
        echo '<table class="min-w-full divide-y divide-gray-200">';
        echo '<thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                <tr>
                  <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Name & Description</th>
                  <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Due Date / Type</th>
                  <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Total Points</th>
                  <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Your Score</th>
                  <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                  <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-100">';

        foreach($items as $i) {
            $itemName = htmlspecialchars($i['name'] ?? $i['activity_name']);
            $dueType = $i['due_date'] ?? $i['type'] ?? '-';
            $totalPoints = $i['total_points'] ?? 'N/A';
            
            // Determine score display
            $scoreDisplay = '-';
            $statusClass = 'bg-gradient-to-r from-gray-100 to-gray-200 text-gray-800';
            $statusText = 'Not Submitted';
            $scorePercentage = 0;
            
            if (!empty($i['submission_id']) && $i['score'] === null) {
                $scoreDisplay = "Pending";
                $statusClass = 'bg-gradient-to-r from-yellow-100 to-yellow-200 text-yellow-800';
                $statusText = 'Pending Review';
            } elseif ($i['score'] !== null && $totalPoints !== 'N/A' && is_numeric($totalPoints) && $totalPoints > 0) {
                $scoreDisplay = $i['score'] . ' / ' . $totalPoints;
                $scorePercentage = ($i['score'] / $totalPoints) * 100;
                
                if ($scorePercentage >= 70) {
                    $statusClass = 'bg-gradient-to-r from-green-100 to-emerald-200 text-green-800';
                } elseif ($scorePercentage >= 50) {
                    $statusClass = 'bg-gradient-to-r from-yellow-100 to-amber-200 text-yellow-800';
                } else {
                    $statusClass = 'bg-gradient-to-r from-red-100 to-pink-200 text-red-800';
                }
                $statusText = 'Graded';
            } elseif ($i['score'] !== null) {
                $scoreDisplay = $i['score'];
                $statusClass = 'bg-gradient-to-r from-green-100 to-emerald-200 text-green-800';
                $statusText = 'Graded';
            }
            
            // Teacher comment
            $comment = $i['teacher_comment'] ?? '';
            $commentBadge = '';
            if ($comment) {
                $shortComment = strlen($comment) > 30 ? substr($comment, 0, 30) . '...' : $comment;
                $commentBadge = '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gradient-to-r from-blue-100 to-cyan-100 text-blue-800 ml-2 cursor-help" title="' . htmlspecialchars($comment) . '">
                                 <i class="fas fa-comment-dots mr-1"></i> Feedback
                               </span>';
            }
            
            // Action button
            $actionButton = '';
            if (!empty($i['submission_id'])) {
                $actionButton = '<span class="inline-flex items-center px-4 py-2 rounded-full text-sm font-medium bg-gradient-to-r from-gray-100 to-gray-200 text-gray-800 border border-gray-300">
                                 <i class="fas fa-check mr-2"></i> Submitted
                               </span>';
            } else {
                $actionButton = '<a href="submit_' . $type . '.php?id=' . $i['id'] . '" class="btn-glow inline-flex items-center px-4 py-2 rounded-xl text-sm font-medium bg-gradient-to-r from-indigo-600 to-purple-600 text-white hover:from-indigo-700 hover:to-purple-700 transition">
                                 <i class="fas fa-upload mr-2"></i> Submit Now
                               </a>';
            }
            
            // Format due date if applicable
            if (isset($i['due_date']) && $i['due_date']) {
                $dueDate = date('M d, Y', strtotime($i['due_date']));
                $dueTypeHtml = '<div class="flex items-center">
                                  <div class="bg-gradient-to-r from-gray-100 to-gray-200 p-2 rounded-lg mr-3">
                                    <i class="fas fa-calendar-day text-gray-600"></i>
                                  </div>
                                  <div>
                                    <div class="font-medium">' . $dueDate . '</div>';
                
                // Check if overdue
                if (strtotime($i['due_date']) < time() && empty($i['submission_id'])) {
                    $dueTypeHtml .= '<div class="text-xs text-red-600 font-medium mt-1">
                                       <i class="fas fa-exclamation-triangle mr-1"></i> Overdue
                                     </div>';
                } else if (strtotime($i['due_date']) < time() && !empty($i['submission_id'])) {
                    $dueTypeHtml .= '<div class="text-xs text-green-600 font-medium mt-1">
                                       <i class="fas fa-check-circle mr-1"></i> Submitted on time
                                     </div>';
                } else if (!empty($i['submission_id'])) {
                    $dueTypeHtml .= '<div class="text-xs text-green-600 font-medium mt-1">
                                       <i class="fas fa-check-circle mr-1"></i> Submitted
                                     </div>';
                } else {
                    $daysLeft = ceil((strtotime($i['due_date']) - time()) / (60 * 60 * 24));
                    if ($daysLeft <= 3) {
                        $dueTypeHtml .= '<div class="text-xs text-yellow-600 font-medium mt-1">
                                           <i class="fas fa-clock mr-1"></i> ' . $daysLeft . ' day' . ($daysLeft !== 1 ? 's' : '') . ' left
                                         </div>';
                    } else {
                        $dueTypeHtml .= '<div class="text-xs text-gray-600 mt-1">
                                           <i class="far fa-clock mr-1"></i> ' . $daysLeft . ' days left
                                         </div>';
                    }
                }
                
                $dueTypeHtml .= '</div></div>';
                $dueType = $dueTypeHtml;
            } else if (isset($i['type'])) {
                $dueType = '<div class="flex items-center">
                              <div class="bg-gradient-to-r from-blue-100 to-cyan-100 p-2 rounded-lg mr-3">
                                <i class="fas fa-tag text-blue-600"></i>
                              </div>
                              <div class="font-medium">' . htmlspecialchars($i['type']) . '</div>
                            </div>';
            }
            
            echo '<tr class="hover:bg-gradient-to-r hover:from-gray-50 hover:to-gray-100 transition-all duration-200">
                    <td class="px-6 py-4">
                      <div class="font-bold text-gray-900 text-lg">' . $itemName . '</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-gray-700">' . $dueType . '</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-lg font-bold text-gray-900">' . $totalPoints . '</div>
                      <div class="text-xs text-gray-500">points</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="flex items-center">
                        <div class="font-bold text-xl ' . ($scorePercentage >= 70 ? 'text-green-600' : ($scorePercentage >= 50 ? 'text-yellow-600' : ($scorePercentage > 0 ? 'text-red-600' : 'text-gray-600'))) . '">
                          ' . $scoreDisplay . '
                        </div>
                        ' . $commentBadge . '
                      </div>
                      ' . ($scorePercentage > 0 ? '<div class="mt-2">
                            <div class="progress-bar">
                              <div class="progress-bar-fill" style="width: ' . min(100, $scorePercentage) . '%"></div>
                            </div>
                            <div class="text-xs text-gray-500 text-right mt-1">' . round($scorePercentage, 1) . '%</div>
                          </div>' : '') . '
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span class="grade-badge ' . $statusClass . ' border border-gray-300">
                        ' . $statusText . '
                      </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      ' . $actionButton . '
                    </td>
                  </tr>';
        }
        echo '</tbody></table></div>';
    }
    ?>
</body>
</html>