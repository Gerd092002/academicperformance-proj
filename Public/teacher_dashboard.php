<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
check_login();

if ($_SESSION['user']['role_id'] != 2) {
    header('Location: login.php');
    exit;
}

$teacher_id = $_SESSION['user']['id'];

// Handle student actions
if (isset($_POST['student_id'], $_POST['subject_id'], $_POST['action'])) {
    $student_id = $_POST['student_id'];
    $subject_id = $_POST['subject_id'];
    $action = $_POST['action'];

    if ($action === 'add') {
        $stmt = $pdo->prepare("SELECT id FROM student_subject WHERE student_id=? AND subject_id=?");
        $stmt->execute([$student_id, $subject_id]);
        if (!$stmt->fetch()) {
            $insert = $pdo->prepare("INSERT INTO student_subject (student_id, subject_id, status) VALUES (?, ?, 'approved')");
            $insert->execute([$student_id, $subject_id]);
            $success_message = "Student added successfully!";
        }
    } elseif ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE student_subject SET status='approved' WHERE student_id=? AND subject_id=?");
        $stmt->execute([$student_id, $subject_id]);
        $success_message = "Enrollment request approved!";
    } elseif ($action === 'deny' || $action === 'remove') {
        $stmt = $pdo->prepare("DELETE FROM student_subject WHERE student_id=? AND subject_id=?");
        $stmt->execute([$student_id, $subject_id]);
        $success_message = $action === 'deny' ? "Enrollment request denied!" : "Student removed from subject!";
    }
}

// Fetch teacher subjects with pending requests details
$stmt = $pdo->prepare("
    SELECT s.id, s.name,
           COUNT(DISTINCT ss.id) AS total_students,
           SUM(CASE WHEN ss.status='pending' THEN 1 ELSE 0 END) AS pending_requests,
           GROUP_CONCAT(DISTINCT 
               CASE WHEN ss.status='pending' 
               THEN CONCAT(u.username, '|', u.email, '|', ss.id) 
               END SEPARATOR ';'
           ) AS pending_students_info
    FROM subjects s
    LEFT JOIN student_subject ss ON s.id = ss.subject_id
    LEFT JOIN users u ON ss.student_id = u.id
    WHERE s.teacher_id=?
    GROUP BY s.id
    ORDER BY s.name
");
$stmt->execute([$teacher_id]);
$subjects = $stmt->fetchAll();

// Process pending students info
foreach ($subjects as &$subject) {
    $subject['pending_students'] = [];
    if ($subject['pending_students_info']) {
        $students = explode(';', $subject['pending_students_info']);
        foreach ($students as $student) {
            if ($student) {
                list($username, $email, $request_id) = explode('|', $student);
                $subject['pending_students'][] = [
                    'username' => $username,
                    'email' => $email,
                    'request_id' => $request_id
                ];
            }
        }
    }
}
unset($subject);

// Calculate total pending requests across all subjects
$total_pending_requests = array_sum(array_column($subjects, 'pending_requests'));

// Selected subject
$selected_subject_id = $_GET['subject_id'] ?? null;
$selected_subject = null;
if ($selected_subject_id) {
    foreach ($subjects as $subject) {
        if ($subject['id'] == $selected_subject_id) {
            $selected_subject = $subject;
            break;
        }
    }
}

// Student search
$search_query = trim($_GET['search'] ?? '');
$students = [];
if ($search_query && $selected_subject_id) {
    $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE role_id=3 AND username LIKE ? LIMIT 20");
    $stmt->execute(["%$search_query%"]);
    $students = $stmt->fetchAll();
}

// Fetch enrolled students & pending requests for selected subject
$enrolled_students = $pending_requests = [];
if ($selected_subject_id) {
    $stmt = $pdo->prepare("
        SELECT u.full_name, u.id, u.username, u.email, ss.status
        FROM student_subject ss
        JOIN users u ON ss.student_id = u.id
        WHERE ss.subject_id=? AND ss.status='approved'
        ORDER BY u.username
    ");
    $stmt->execute([$selected_subject_id]);
    $enrolled_students = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT u.full_name, u.id, u.username, u.email
        FROM student_subject ss
        JOIN users u ON ss.student_id = u.id
        WHERE ss.subject_id=? AND ss.status='pending'
        
    ");
    $stmt->execute([$selected_subject_id]);
    $pending_requests = $stmt->fetchAll();

    // Fetch assignments/projects/activities/announcements
    $tables = ['assignments','projects','activities','announcements'];
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SELECT * FROM $table WHERE subject_id=? ORDER BY created_at DESC");
        $stmt->execute([$selected_subject_id]);
        ${$table} = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Teacher Dashboard</title>
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
        transition: margin-left 0.3s ease;
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
    
    /* Slide Drawer Styles */
    .slide-drawer {
        position: fixed;
        left: -320px;
        top: 0;
        width: 320px;
        height: 100vh;
        background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
        color: white;
        z-index: 1000;
        transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        overflow-y: auto;
        box-shadow: 5px 0 25px rgba(0, 0, 0, 0.3);
    }
    
    .slide-drawer.open {
        left: 0;
    }
    
    .drawer-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 999;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s ease, visibility 0.3s ease;
    }
    
    .drawer-overlay.active {
        opacity: 1;
        visibility: visible;
    }
    
    .hamburger-btn {
        transition: transform 0.3s ease;
    }
    
    .hamburger-btn:hover {
        transform: scale(1.1);
    }
    
    .notification-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #ef4444;
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: bold;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
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
    
    .status-indicator {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 6px;
    }
    
    .status-approved { background-color: #10B981; }
    .status-pending { background-color: #F59E0B; }
    
    .badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 9999px;
        padding: 0.25rem 0.75rem;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    /* Custom Scrollbar for Drawer */
    .slide-drawer::-webkit-scrollbar {
        width: 6px;
    }
    
    .slide-drawer::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 3px;
    }
    
    .slide-drawer::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.3);
        border-radius: 3px;
    }
    
    .slide-drawer::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.5);
    }
</style>
</head>
<body class="p-4">

<!-- Slide Drawer -->
<div class="slide-drawer" id="slideDrawer">
    <div class="p-6">
        <!-- Drawer Header -->
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center space-x-3">
                <div class="bg-gradient-to-r from-indigo-500 to-purple-500 p-2 rounded-lg">
                    <i class="fas fa-chalkboard-teacher text-white text-xl"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold">Teacher Panel</h2>
                    <p class="text-gray-300 text-sm">Navigation Menu</p>
                </div>
            </div>
            <button onclick="closeDrawer()" class="text-gray-400 hover:text-white transition">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <!-- Teacher Profile Card -->
        <div class="mb-8 p-4 bg-gradient-to-r from-indigo-800/50 to-purple-800/50 rounded-2xl border border-indigo-700/30">
    <div class="flex items-center space-x-3 mb-3">
        <div class="w-12 h-12 bg-gradient-to-r from-white to-gray-200 rounded-full flex items-center justify-center text-indigo-600 font-bold text-lg">
            <?= strtoupper(substr(htmlspecialchars($_SESSION['user']['full_name'] ?? $_SESSION['user']['username']), 0, 1)) ?>
        </div>
        <div>
            <p class="font-semibold"><?= htmlspecialchars($_SESSION['user']['full_name'] ?? $_SESSION['user']['username']) ?></p>
            <p class="text-indigo-300 text-sm">Teacher</p>
        </div>
    </div>
    <div class="text-center pt-3 border-t border-indigo-700/30">
        <p class="text-indigo-200 text-sm">ID: <?= htmlspecialchars($_SESSION['user']['id']) ?></p>
    </div>
</div>
        
        <!-- Navigation Menu -->
        <nav class="mb-8">
            <h3 class="text-lg font-semibold text-gray-300 mb-4 flex items-center">
                <i class="fas fa-bars mr-2"></i>
                Quick Navigation
            </h3>
            <div class="space-y-2">
                <a href="teacher_dashboard.php" class="flex items-center space-x-3 p-3 rounded-xl hover:bg-indigo-800/30 transition <?= !$selected_subject_id ? 'bg-indigo-800/30' : '' ?>">
                    <div class="w-8 h-8 bg-gradient-to-r from-indigo-500 to-purple-500 rounded-lg flex items-center justify-center">
                        <i class="fas fa-home text-white"></i>
                    </div>
                    <span>Dashboard Home</span>
                </a>
                
                <a href="add_subject_teacher.php" class="flex items-center space-x-3 p-3 rounded-xl hover:bg-indigo-800/30 transition">
                    <div class="w-8 h-8 bg-gradient-to-r from-green-500 to-emerald-500 rounded-lg flex items-center justify-center">
                        <i class="fas fa-plus-circle text-white"></i>
                    </div>
                    <span>Add New Subject</span>
                </a>
                
                <a href="profile.php" class="flex items-center space-x-3 p-3 rounded-xl hover:bg-indigo-800/30 transition">
                    <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-cyan-500 rounded-lg flex items-center justify-center">
                        <i class="fas fa-user-cog text-white"></i>
                    </div>
                    <span>My Profile</span>
                </a>
                
                <a href="settings.php" class="flex items-center space-x-3 p-3 rounded-xl hover:bg-indigo-800/30 transition">
                    <div class="w-8 h-8 bg-gradient-to-r from-gray-500 to-gray-700 rounded-lg flex items-center justify-center">
                        <i class="fas fa-cog text-white"></i>
                    </div>
                    <span>Settings</span>
                </a>
            </div>
        </nav>
        
        <!-- My Subjects Section -->
        <div class="mb-8">
            <h3 class="text-lg font-semibold text-gray-300 mb-4 flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-book-open mr-2"></i>
                    My Subjects
                </div>
                <span class="bg-gradient-to-r from-indigo-500 to-purple-500 text-white text-xs px-2 py-1 rounded-full">
                    <?= count($subjects) ?>
                </span>
            </h3>
            
            <div class="space-y-3 max-h-60 overflow-y-auto pr-2">
                <?php if($subjects): ?>
                    <?php foreach($subjects as $subject): ?>
                        <a href="?subject_id=<?= $subject['id'] ?>" 
                           onclick="closeDrawer()"
                           class="block p-3 rounded-xl hover:bg-indigo-800/30 transition <?= $selected_subject_id == $subject['id'] ? 'bg-indigo-800/30 border-l-4 border-indigo-500' : '' ?>">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <h4 class="font-medium"><?= htmlspecialchars($subject['name']) ?></h4>
                                    <div class="flex items-center text-sm text-gray-400 mt-1">
                                        <span class="mr-3">
                                            <i class="fas fa-users mr-1"></i>
                                            <?= $subject['total_students'] ?>
                                        </span>
                                        <?php if($subject['pending_requests'] > 0): ?>
                                            <span class="text-yellow-400">
                                                <i class="fas fa-clock mr-1"></i>
                                                <?= $subject['pending_requests'] ?> pending
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <i class="fas fa-chevron-right text-gray-500"></i>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-gray-400 text-sm p-3 text-center">No subjects yet</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Enrollment Requests Section -->
        <div class="mb-8">
            <h3 class="text-lg font-semibold text-gray-300 mb-4 flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-user-clock mr-2"></i>
                    Enrollment Requests
                </div>
                <?php if($total_pending_requests > 0): ?>
                    <span class="notification-badge"><?= $total_pending_requests ?></span>
                <?php endif; ?>
            </h3>
            
            <div class="space-y-3 max-h-60 overflow-y-auto pr-2">
                <?php if($total_pending_requests > 0): ?>
                    <?php foreach($subjects as $subject): ?>
                        <?php if($subject['pending_requests'] > 0): ?>
                            <div class="bg-gradient-to-r from-yellow-900/20 to-amber-900/20 border-l-4 border-yellow-500 p-3 rounded-r-xl">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="font-medium text-yellow-300"><?= htmlspecialchars($subject['name']) ?></h4>
                                    <span class="text-yellow-400 text-sm"><?= $subject['pending_requests'] ?> new</span>
                                </div>
                                
                                <div class="space-y-2">
                                    <?php foreach($subject['pending_students'] as $student): ?>
                                        <div class="flex items-center justify-between p-2 bg-yellow-900/10 rounded-lg">
                                            <div>
                                                <p class="text-sm font-medium"><?= htmlspecialchars($student['username']) ?></p>
                                                <p class="text-xs text-gray-400"><?= htmlspecialchars($student['email']) ?></p>
                                            </div>
                                            
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="mt-3 text-center">
                                    <a href="?subject_id=<?= $subject['id'] ?>" 
                                       onclick="closeDrawer()"
                                       class="text-yellow-400 hover:text-yellow-300 text-sm font-medium transition">
                                        <i class="fas fa-external-link-alt mr-1"></i>
                                        Go to subject
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-gray-400 text-sm p-3 text-center">No pending requests</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="p-4 bg-gradient-to-r from-indigo-900/30 to-purple-900/30 rounded-2xl border border-indigo-700/20">
            <h4 class="font-semibold mb-3 text-gray-300">Quick Stats</h4>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-gray-400 text-sm">Total Subjects</span>
                    <span class="font-bold"><?= count($subjects) ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-400 text-sm">Total Students</span>
                    <span class="font-bold"><?= array_sum(array_column($subjects, 'total_students')) ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-400 text-sm">Pending Requests</span>
                    <span class="font-bold text-yellow-400"><?= $total_pending_requests ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Drawer Overlay -->
<div class="drawer-overlay" id="drawerOverlay" onclick="closeDrawer()"></div>

<!-- Main Content -->
<div id="mainContent">
    <!-- Header -->
    <header class="dashboard-container rounded-2xl shadow-2xl mb-6 relative">
        <div class="container mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <button onclick="toggleDrawer()" class="hamburger-btn p-2 rounded-lg hover:bg-gray-100 transition">
                        <div class="w-6 h-6 flex flex-col justify-between">
                            <span class="w-6 h-0.5 bg-gray-700 rounded"></span>
                            <span class="w-6 h-0.5 bg-gray-700 rounded"></span>
                            <span class="w-6 h-0.5 bg-gray-700 rounded"></span>
                        </div>
                    </button>
                    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 p-2 rounded-lg">
                        <i class="fas fa-chalkboard-teacher text-white text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Teacher Dashboard</h1>
                        <p class="text-gray-600 text-sm">Welcome back, <?= htmlspecialchars($_SESSION['user']['username']) ?>!</p>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <?php if($total_pending_requests > 0): ?>
                    <div class="relative">
                        <button onclick="toggleDrawer()" class="relative p-2">
                            <i class="fas fa-bell text-2xl text-yellow-600"></i>
                            <span class="notification-badge"><?= $total_pending_requests ?></span>
                        </button>
                    </div>
                    <?php endif; ?>
                    
                   <div class="text-right hidden md:block">
                        <p class="font-medium text-gray-700"><?= htmlspecialchars($_SESSION['user']['full_name'] ?? $_SESSION['user']['username']) ?></p>
                        <p class="text-sm text-gray-500">Teacher ID: <?= htmlspecialchars($_SESSION['user']['id']) ?></p>
                    </div>
                    <div class="relative">
                        <div class="w-10 h-10 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-full flex items-center justify-center text-white font-bold shadow-lg">
                             <?= strtoupper(substr(htmlspecialchars($_SESSION['user']['full_name'] ?? $_SESSION['user']['username']), 0, 1)) ?>
                        </div>
                        <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-500 rounded-full border-2 border-white"></div>
                    </div>
                    <a href="logout.php" class="btn-glow bg-gradient-to-r from-red-500 to-red-600 text-white px-4 py-2 rounded-lg hover:from-red-600 hover:to-red-700 transition flex items-center space-x-2">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-2">
        <!-- Success Message -->
        <?php if(isset($success_message)): ?>
        <div class="mb-6 p-4 rounded-xl border-l-4 border-green-500 bg-gradient-to-r from-green-50 to-green-100 text-green-800 flex justify-between items-center slide-in shadow-lg">
            <div class="flex items-center space-x-3">
                <div class="bg-green-500 p-2 rounded-full">
                    <i class="fas fa-check-circle text-white"></i>
                </div>
                <div>
                    <p class="font-semibold">Success!</p>
                    <p><?= htmlspecialchars($success_message) ?></p>
                </div>
            </div>
            <button onclick="this.parentElement.remove()" class="text-green-600 hover:text-green-800 transition">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        <?php endif; ?>

        <?php if(!$selected_subject_id): ?>
        <!-- Dashboard Home View -->
        <div class="slide-in">
            <div class="mb-8 text-center">
                <h2 class="text-3xl font-bold text-gray-800 mb-2">Teacher Dashboard</h2>
                <p class="text-white-600 max-w-2xl mx-auto">Manage your subjects, students, and teaching materials in one place.</p>
            </div>

            <!-- Welcome Card -->
            <div class="dashboard-container rounded-2xl shadow-lg p-8 mb-10">
                <div class="flex flex-col md:flex-row items-center justify-between">
                    <div class="mb-6 md:mb-0 md:mr-8">
                        <h3 class="text-2xl font-bold text-gray-800 mb-3">Welcome, Teacher!</h3>
                        <p class="text-gray-600 mb-4">You're currently managing <?= count($subjects) ?> subject<?= count($subjects) !== 1 ? 's' : '' ?> with a total of <?= array_sum(array_column($subjects, 'total_students')) ?> enrolled students.</p>
                        <div class="flex flex-wrap gap-4">
                            <a href="add_subject_teacher.php" class="btn-glow bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-3 rounded-xl font-semibold hover:from-indigo-700 hover:to-purple-700 transition">
                                <i class="fas fa-plus mr-2"></i>
                                Add New Subject
                            </a>
                            <?php if($total_pending_requests > 0): ?>
                            <button onclick="toggleDrawer()" class="btn-glow bg-gradient-to-r from-yellow-600 to-amber-600 text-white px-6 py-3 rounded-xl font-semibold hover:from-yellow-700 hover:to-amber-700 transition">
                                <i class="fas fa-clock mr-2"></i>
                                View Pending Requests (<?= $total_pending_requests ?>)
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="bg-gradient-to-r from-indigo-100 to-purple-100 p-6 rounded-2xl">
                        <i class="fas fa-chalkboard-teacher text-indigo-600 text-6xl"></i>
                    </div>
                </div>
            </div>

            <!-- My Subjects Grid -->
            <div class="mb-10">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h3 class="text-xl font-semibold text-gray-800">My Subjects</h3>
                        <p class="text-white-500">Subjects you're currently teaching</p>
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
                                <?php if(isset($sub['code'])): ?>
                                    <p class="text-gray-500 text-sm mt-1">Code: <?= htmlspecialchars($sub['code']) ?></p>
                                <?php endif; ?>
                                <div class="mt-4 flex space-x-4">
                                    <div class="text-center">
                                        <div class="text-lg font-bold text-gray-800"><?= $sub['total_students'] ?></div>
                                        <div class="text-xs text-gray-500">Students</div>
                                    </div>
                                    <?php if($sub['pending_requests'] > 0): ?>
                                    <div class="text-center">
                                        <div class="text-lg font-bold text-yellow-600"><?= $sub['pending_requests'] ?></div>
                                        <div class="text-xs text-gray-500">Pending</div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="bg-gradient-to-r from-indigo-50 to-purple-50 p-3 rounded-xl group-hover:from-indigo-100 group-hover:to-purple-100 transition">
                                <i class="fas fa-book-open text-indigo-600 text-xl"></i>
                            </div>
                        </div>
                        <div class="flex justify-between items-center mt-6 pt-4 border-t border-gray-100">
                            <span class="text-gray-600 text-sm">Manage subject</span>
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
                    <h4 class="text-2xl font-bold text-gray-800 mb-3">No Subjects Yet</h4>
                    <p class="text-gray-600 mb-6 max-w-md mx-auto">You haven't added any subjects yet. Start by adding your first subject!</p>
                    <a href="add_subject_teacher.php" class="btn-glow inline-flex items-center space-x-2 bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-3 rounded-xl font-semibold">
                        <i class="fas fa-plus mr-2"></i>
                        <span>Add Your First Subject</span>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php else: ?>
        <!-- Subject Detail View -->
        <div class="slide-in">
            <!-- Back Button and Subject Header -->
            <div class="mb-8">
                <a href="teacher_dashboard.php" class="inline-flex items-center text-gray-600 hover:text-indigo-700 mb-6 group transition">
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
                                    <h2 class="text-3xl font-bold text-gray-800"><?= htmlspecialchars($selected_subject['name']) ?></h2>
                                    <?php if(isset($selected_subject['code'])): ?>
                                        <p class="text-gray-600">Subject Code: <?= htmlspecialchars($selected_subject['code']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex space-x-4">
                            <div class="bg-gradient-to-r from-blue-50 to-cyan-50 p-5 rounded-2xl border border-blue-100 min-w-[180px]">
                                <div class="text-center">
                                    <div class="text-3xl font-bold text-gray-800"><?= $selected_subject['total_students'] ?></div>
                                    <div class="text-gray-600">Enrolled Students</div>
                                </div>
                            </div>
                            <div class="bg-gradient-to-r from-<?= $selected_subject['pending_requests'] > 0 ? 'yellow' : 'gray' ?>-50 to-<?= $selected_subject['pending_requests'] > 0 ? 'amber' : 'gray' ?>-50 p-5 rounded-2xl border border-<?= $selected_subject['pending_requests'] > 0 ? 'yellow' : 'gray' ?>-100 min-w-[180px]">
                                <div class="text-center">
                                    <div class="text-3xl font-bold <?= $selected_subject['pending_requests'] > 0 ? 'text-yellow-600' : 'text-gray-800' ?>"><?= $selected_subject['pending_requests'] ?></div>
                                    <div class="text-gray-600">Pending Requests</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="dashboard-container rounded-2xl shadow-lg mb-6 p-2">
                <div class="flex overflow-x-auto">
                    <button id="students-tab" class="tab-button active" onclick="switchTab('students')">
                        <div class="flex items-center">
                            <div class="bg-gradient-to-r from-indigo-100 to-purple-100 p-2 rounded-lg mr-3">
                                <i class="fas fa-users text-indigo-600"></i>
                            </div>
                            <div class="text-left">
                                <div class="font-semibold">Students</div>
                                <div class="text-xs text-gray-500">Manage enrollments</div>
                            </div>
                        </div>
                    </button>
                    <button id="assignments-tab" class="tab-button" onclick="switchTab('assignments')">
                        <div class="flex items-center">
                            <div class="bg-gradient-to-r from-blue-100 to-cyan-100 p-2 rounded-lg mr-3">
                                <i class="fas fa-file-alt text-blue-600"></i>
                            </div>
                            <div class="text-left">
                                <div class="font-semibold">Assignments</div>
                                <div class="text-xs text-gray-500">Homework tasks</div>
                            </div>
                        </div>
                    </button>
                    <button id="projects-tab" class="tab-button" onclick="switchTab('projects')">
                        <div class="flex items-center">
                            <div class="bg-gradient-to-r from-green-100 to-emerald-100 p-2 rounded-lg mr-3">
                                <i class="fas fa-project-diagram text-green-600"></i>
                            </div>
                            <div class="text-left">
                                <div class="font-semibold">Projects</div>
                                <div class="text-xs text-gray-500">Long-term work</div>
                            </div>
                        </div>
                    </button>
                    <button id="activities-tab" class="tab-button" onclick="switchTab('activities')">
                        <div class="flex items-center">
                            <div class="bg-gradient-to-r from-yellow-100 to-amber-100 p-2 rounded-lg mr-3">
                                <i class="fas fa-tasks text-yellow-600"></i>
                            </div>
                            <div class="text-left">
                                <div class="font-semibold">Activities</div>
                                <div class="text-xs text-gray-500">Practice exercises</div>
                            </div>
                        </div>
                    </button>
                    <button id="announcements-tab" class="tab-button" onclick="switchTab('announcements')">
                        <div class="flex items-center">
                            <div class="bg-gradient-to-r from-purple-100 to-pink-100 p-2 rounded-lg mr-3">
                                <i class="fas fa-bullhorn text-purple-600"></i>
                            </div>
                            <div class="text-left">
                                <div class="font-semibold">Announcements</div>
                                <div class="text-xs text-gray-500">Updates & news</div>
                            </div>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Tab Content -->
            <div id="students-content" class="tab-content">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Pending Requests -->
                    <div class="dashboard-container rounded-2xl shadow-lg p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-xl font-bold text-gray-800">Pending Enrollment Requests</h3>
                            <?php if(count($pending_requests) > 0): ?>
                                <span class="bg-gradient-to-r from-yellow-100 to-amber-100 text-yellow-800 px-4 py-2 rounded-full font-semibold">
                                    <?= count($pending_requests) ?> pending
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if($pending_requests): ?>
                            <div class="space-y-4">
                                <?php foreach($pending_requests as $p): ?>
                                <div class="bg-gradient-to-r from-yellow-50 to-amber-50 border-l-4 border-yellow-500 p-4 rounded-xl">
                                    <div class="flex justify-between items-center mb-3">
                                        <div>
                                            <h4 class="font-bold text-gray-800"><?= htmlspecialchars($p['full_name']) ?></h4>
                                            <p class="text-gray-600 text-sm"><?= htmlspecialchars($p['username']) ?></p>
                                            <p class="text-gray-600 text-sm"><?= htmlspecialchars($p['email']) ?></p>
                                        </div>
                                        
                                    </div>
                                    <div class="flex space-x-2">
                                        <form method="POST" class="flex-1">
                                            <input type="hidden" name="student_id" value="<?= $p['id'] ?>">
                                            <input type="hidden" name="subject_id" value="<?= $selected_subject_id ?>">
                                            <button type="submit" name="action" value="approve" 
                                                    class="btn-glow w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white py-2 rounded-xl hover:from-green-700 hover:to-emerald-700 transition">
                                                <i class="fas fa-check mr-2"></i>
                                                Approve
                                            </button>
                                        </form>
                                        <form method="POST" class="flex-1">
                                            <input type="hidden" name="student_id" value="<?= $p['id'] ?>">
                                            <input type="hidden" name="subject_id" value="<?= $selected_subject_id ?>">
                                            <button type="submit" name="action" value="deny" 
                                                    class="btn-glow w-full bg-gradient-to-r from-red-600 to-pink-600 text-white py-2 rounded-xl hover:from-red-700 hover:to-pink-700 transition">
                                                <i class="fas fa-times mr-2"></i>
                                                Deny
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <div class="w-16 h-16 bg-gradient-to-r from-gray-100 to-gray-200 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-check text-gray-400 text-2xl"></i>
                                </div>
                                <h4 class="text-xl font-bold text-gray-800 mb-2">No Pending Requests</h4>
                                <p class="text-gray-600">All enrollment requests have been processed.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Enrolled Students -->
                    <div class="dashboard-container rounded-2xl shadow-lg p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-xl font-bold text-gray-800">Enrolled Students</h3>
                            <span class="bg-gradient-to-r from-green-100 to-emerald-100 text-green-800 px-4 py-2 rounded-full font-semibold">
                                <?= count($enrolled_students) ?> enrolled
                            </span>
                        </div>
                        
                        <!-- Search Students Form -->
                        <form method="GET" class="mb-6">
                            <input type="hidden" name="subject_id" value="<?= $selected_subject_id ?>">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-search text-gray-400"></i>
                                </div>
                                <input type="text" name="search" 
                                       value="<?= htmlspecialchars($search_query) ?>" 
                                       placeholder="Search students to add..." 
                                       class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-transparent">
                                <button type="submit" class="absolute right-2 top-2 bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-4 py-2 rounded-lg hover:from-indigo-700 hover:to-purple-700 transition">
                                    Search
                                </button>
                            </div>
                        </form>
                        
                        <?php if($enrolled_students): ?>
                            <div class="space-y-3 max-h-96 overflow-y-auto">
                                <?php foreach($enrolled_students as $es): ?>
                                <div class="flex items-center justify-between p-4 bg-gradient-to-r from-gray-50 to-gray-100 rounded-xl hover-lift">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-gradient-to-r from-indigo-500 to-purple-500 rounded-full flex items-center justify-center text-white font-bold">
                                            <?= strtoupper(substr(htmlspecialchars($es['username']), 0, 1)) ?>
                                        </div>
                                        <div>
                                            <h4 class="font-bold text-gray-800"><?= htmlspecialchars($es['username']) ?></h4>
                                            <p class="text-gray-600 text-sm"><?= htmlspecialchars($es['email']) ?></p>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span class="badge bg-gradient-to-r from-green-100 to-emerald-200 text-green-800 border border-green-300">
                                            <span class="status-indicator status-approved"></span>
                                            Enrolled
                                        </span>
                                        <form method="POST">
                                            <input type="hidden" name="student_id" value="<?= $es['id'] ?>">
                                            <input type="hidden" name="subject_id" value="<?= $selected_subject_id ?>">
                                            <button type="submit" name="action" value="remove" 
                                                    onclick="return confirm('Are you sure you want to remove this student?')"
                                                    class="text-red-600 hover:text-red-800 transition">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-8">
                                <div class="w-16 h-16 bg-gradient-to-r from-gray-100 to-gray-200 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-users text-gray-400 text-2xl"></i>
                                </div>
                                <h4 class="text-xl font-bold text-gray-800 mb-2">No Students Enrolled</h4>
                                <p class="text-gray-600 mb-4">Search for students to add them to this subject.</p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Search Results -->
                        <?php if($search_query && $students): ?>
                        <div class="mt-8 pt-6 border-t border-gray-200">
                            <h4 class="font-bold text-gray-800 mb-4">Search Results</h4>
                            <div class="space-y-3">
                                <?php foreach($students as $stu): 
                                    $isEnrolled = false;
                                    foreach($enrolled_students as $es) {
                                        if($es['id'] == $stu['id']) {
                                            $isEnrolled = true;
                                            break;
                                        }
                                    }
                                    if($isEnrolled) continue;
                                ?>
                                <div class="flex items-center justify-between p-4 bg-gradient-to-r from-blue-50 to-cyan-50 rounded-xl">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-cyan-500 rounded-full flex items-center justify-center text-white font-bold">
                                            <?= strtoupper(substr(htmlspecialchars($stu['username']), 0, 1)) ?>
                                        </div>
                                        <div>
                                            <h4 class="font-bold text-gray-800"><?= htmlspecialchars($stu['username']) ?></h4>
                                            <p class="text-gray-600 text-sm"><?= htmlspecialchars($stu['email']) ?></p>
                                        </div>
                                    </div>
                                    <form method="POST">
                                        <input type="hidden" name="student_id" value="<?= $stu['id'] ?>">
                                        <input type="hidden" name="subject_id" value="<?= $selected_subject_id ?>">
                                        <button type="submit" name="action" value="add" 
                                                class="btn-glow bg-gradient-to-r from-green-600 to-emerald-600 text-white px-4 py-2 rounded-xl hover:from-green-700 hover:to-emerald-700 transition">
                                            <i class="fas fa-plus mr-2"></i>
                                            Add
                                        </button>
                                    </form>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php elseif($search_query && empty($students)): ?>
                        <div class="mt-8 pt-6 border-t border-gray-200 text-center">
                            <p class="text-gray-600">No students found matching "<?= htmlspecialchars($search_query) ?>".</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php 
            // Function to render items with consistent design
            function renderTeacherItems($items, $type) {
                global $pdo, $selected_subject_id;
                if (!$items) {
                    echo '<div class="text-center py-12">
                            <div class="w-24 h-24 bg-gradient-to-r from-gray-100 to-gray-200 rounded-full flex items-center justify-center mx-auto mb-6">
                                <i class="fas fa-clipboard-list text-gray-400 text-4xl"></i>
                            </div>
                            <h4 class="text-2xl font-bold text-gray-800 mb-3">No ' . ucfirst($type) . ' Yet</h4>
                            <p class="text-gray-600 max-w-md mx-auto">You haven\'t added any ' . $type . ' to this subject yet.</p>
                            <a href="add_' . $type . '.php?subject_id=' . $selected_subject_id . '" 
                               class="btn-glow inline-flex items-center space-x-2 bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-3 rounded-xl font-semibold mt-4">
                                <i class="fas fa-plus mr-2"></i>
                                <span>Add ' . ucfirst($type) . '</span>
                            </a>
                          </div>';
                    return;
                }
                
                echo '<div class="overflow-x-auto rounded-xl border border-gray-200 shadow-sm">';
                echo '<table class="min-w-full divide-y divide-gray-200">';
                echo '<thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                        <tr>
                          <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Title</th>
                          <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Due Date</th>
                          <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Submissions</th>
                          <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Actions</th>
                        </tr>
                      </thead>
                      <tbody class="bg-white divide-y divide-gray-100">';

                foreach($items as $i) {
                    $id_col = $type.'_id';
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM submissions WHERE $id_col=?");
                    $stmt->execute([$i['id']]);
                    $submission_count = $stmt->fetchColumn();
                    
                    $dueDate = isset($i['due_date']) && $i['due_date'] ? date('M d, Y', strtotime($i['due_date'])) : 'No due date';
                    
                    echo '<tr class="hover:bg-gradient-to-r hover:from-gray-50 hover:to-gray-100 transition-all duration-200">
                            <td class="px-6 py-4">
                              <div class="font-bold text-gray-900 text-lg">' . htmlspecialchars($i['name']) . '</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                              <div class="text-gray-700">' . $dueDate . '</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                              <div class="text-lg font-bold text-gray-900">' . $submission_count . '</div>
                              <div class="text-xs text-gray-500">submissions</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap space-x-2">
                              <a href="view_submissions.php?'.$id_col.'='.$i['id'].'" 
                                 class="btn-glow inline-flex items-center px-4 py-2 rounded-xl text-sm font-medium bg-gradient-to-r from-blue-600 to-cyan-600 text-white hover:from-blue-700 hover:to-cyan-700 transition">
                                <i class="fas fa-eye mr-2"></i>
                                View & Grade
                              </a>
                              <a href="edit_' . $type . '.php?id=' . $i['id'] . '" 
                                 class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-medium bg-gradient-to-r from-gray-100 to-gray-200 text-gray-800 hover:from-gray-200 hover:to-gray-300 transition">
                                <i class="fas fa-edit mr-2"></i>
                                Edit
                              </a>
                            </td>
                          </tr>';
                }
                echo '</tbody></table></div>';
                
                // Add button
                echo '<div class="mt-6 text-right">
                        <a href="add_' . $type . '.php?subject_id=' . $selected_subject_id . '" 
                           class="btn-glow inline-flex items-center space-x-2 bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-3 rounded-xl font-semibold">
                          <i class="fas fa-plus mr-2"></i>
                          <span>Add New ' . ucfirst($type) . '</span>
                        </a>
                      </div>';
            }
            ?>

            <div id="assignments-content" class="tab-content hidden">
                <div class="dashboard-container rounded-2xl shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-50 to-cyan-50 px-6 py-5 border-b border-blue-100">
                        <h3 class="text-2xl font-bold text-gray-800">Assignments</h3>
                        <p class="text-gray-600">Manage homework assignments and grade submissions.</p>
                    </div>
                    <div class="p-6">
                        <?php renderTeacherItems($assignments, 'assignment'); ?>
                    </div>
                </div>
            </div>

            <div id="projects-content" class="tab-content hidden">
                <div class="dashboard-container rounded-2xl shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 px-6 py-5 border-b border-green-100">
                        <h3 class="text-2xl font-bold text-gray-800">Projects</h3>
                        <p class="text-gray-600">Manage long-term projects and student submissions.</p>
                    </div>
                    <div class="p-6">
                        <?php renderTeacherItems($projects, 'project'); ?>
                    </div>
                </div>
            </div>

            <div id="activities-content" class="tab-content hidden">
                <div class="dashboard-container rounded-2xl shadow-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-yellow-50 to-amber-50 px-6 py-5 border-b border-yellow-100">
                        <h3 class="text-2xl font-bold text-gray-800">Activities</h3>
                        <p class="text-gray-600">Manage practice activities and exercises.</p>
                    </div>
                    <div class="p-6">
                        <?php renderTeacherItems($activities, 'activity'); ?>
                    </div>
                </div>
            </div>

            <div id="announcements-content" class="tab-content hidden">
                <div class="dashboard-container rounded-2xl shadow-lg p-6">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-2xl font-bold text-gray-800">Announcements</h3>
                            <p class="text-gray-600">Share updates and important information with your students.</p>
                        </div>
                        <a href="add_announcement.php?subject_id=<?= $selected_subject_id ?>" 
                           class="btn-glow bg-gradient-to-r from-purple-600 to-pink-600 text-white px-6 py-3 rounded-xl font-semibold hover:from-purple-700 hover:to-pink-700 transition">
                            <i class="fas fa-plus mr-2"></i>
                            New Announcement
                        </a>
                    </div>
                    
                    <?php if($announcements): ?>
                        <div class="space-y-4">
                            <?php foreach($announcements as $ann): ?>
                            <div class="bg-gradient-to-r from-purple-50 to-pink-50 border-l-4 border-purple-500 p-5 rounded-xl hover-lift">
                                <div class="flex justify-between items-start mb-3">
                                    <h4 class="font-bold text-lg text-gray-800"><?= htmlspecialchars($ann['title']) ?></h4>
                                    <div class="flex items-center space-x-2">
                                        <span class="bg-white px-3 py-1 rounded-full text-sm text-gray-600 shadow-sm">
                                            <i class="far fa-clock mr-1"></i>
                                            <?= date('M d, Y', strtotime($ann['created_at'])) ?>
                                        </span>
                                        <a href="edit_announcement.php?id=<?= $ann['id'] ?>" class="text-gray-500 hover:text-indigo-600">
                                            <i class="fas fa-edit"></i>
                                        </a>
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
                            <p class="text-gray-600 max-w-md mx-auto">Share updates and important information with your students by creating an announcement.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

<script>
    // Drawer Functions
    function toggleDrawer() {
        const drawer = document.getElementById('slideDrawer');
        const overlay = document.getElementById('drawerOverlay');
        const mainContent = document.getElementById('mainContent');
        
        drawer.classList.toggle('open');
        overlay.classList.toggle('active');
        
        // Shift main content on desktop for better UX
        if (window.innerWidth >= 768) {
            if (drawer.classList.contains('open')) {
                mainContent.style.marginLeft = '320px';
                mainContent.style.transition = 'margin-left 0.3s ease';
            } else {
                mainContent.style.marginLeft = '0';
            }
        }
    }
    
    function closeDrawer() {
        const drawer = document.getElementById('slideDrawer');
        const overlay = document.getElementById('drawerOverlay');
        const mainContent = document.getElementById('mainContent');
        
        drawer.classList.remove('open');
        overlay.classList.remove('active');
        mainContent.style.marginLeft = '0';
    }
    
    
    // Tab switching functionality
    function switchTab(tabName) {
        // Hide all tab contents
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.add('hidden');
        });
        
        // Remove active class from all tabs
        document.querySelectorAll('.tab-button').forEach(button => {
            button.classList.remove('active');
            button.classList.remove('bg-gradient-to-r', 'from-indigo-50', 'to-purple-50');
        });
        
        // Show selected tab content and activate tab button
        const tabContent = document.getElementById(tabName + '-content');
        const tabButton = document.getElementById(tabName + '-tab');
        
        if (tabContent) tabContent.classList.remove('hidden');
        if (tabButton) {
            tabButton.classList.add('active');
            tabButton.classList.add('bg-gradient-to-r', 'from-indigo-50', 'to-purple-50');
        }
    }
    
    // Close drawer when clicking outside on mobile
    document.addEventListener('click', function(event) {
        const drawer = document.getElementById('slideDrawer');
        const overlay = document.getElementById('drawerOverlay');
        const hamburger = document.querySelector('.hamburger-btn');
        
        if (window.innerWidth < 768 && 
            drawer.classList.contains('open') && 
            !drawer.contains(event.target) && 
            !hamburger.contains(event.target)) {
            closeDrawer();
        }
    });
    
    // Initialize first tab as active if we're in subject view
    document.addEventListener('DOMContentLoaded', function() {
        <?php if($selected_subject_id): ?>
        switchTab('students');
        document.getElementById('students-tab').classList.add('bg-gradient-to-r', 'from-indigo-50', 'to-purple-50');
        <?php endif; ?>
        
        // Close drawer with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeDrawer();
            }
        });
    });
</script>
</body>
</html>