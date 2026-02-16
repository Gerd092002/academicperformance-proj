<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
check_login();

if ($_SESSION['user']['role_id'] != 1) {
    header('Location: login.php');
    exit;
}

// Fetch all teachers, students, and subjects
$teachers = $pdo->query("SELECT id, username, email FROM users WHERE role_id = 2")->fetchAll();
$students = $pdo->query("SELECT id, username, email FROM users WHERE role_id = 3")->fetchAll();
$subjects = $pdo->query("SELECT s.id, s.name, u.username as teacher_name, s.created_at 
                         FROM subjects s 
                         JOIN users u ON s.teacher_id = u.id 
                         WHERE u.role_id = 2 
                         ORDER BY s.created_at DESC")->fetchAll();

// Get counts
$teacher_count = count($teachers);
$student_count = count($students);
$subject_count = count($subjects);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard</title>
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
    
    .stat-card {
        transition: all 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
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
                <div class="bg-gradient-to-r from-red-500 to-pink-500 p-2 rounded-lg">
                    <i class="fas fa-user-shield text-white text-xl"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold">Admin Panel</h2>
                    <p class="text-gray-300 text-sm">System Management</p>
                </div>
            </div>
            <button onclick="closeDrawer()" class="text-gray-400 hover:text-white transition">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <!-- Admin Profile Card -->
        <div class="mb-8 p-4 bg-gradient-to-r from-red-800/50 to-pink-800/50 rounded-2xl border border-red-700/30">
            <div class="flex items-center space-x-3 mb-3">
                <div class="w-12 h-12 bg-gradient-to-r from-white to-gray-200 rounded-full flex items-center justify-center text-red-600 font-bold text-lg">
                    <?= strtoupper(substr(htmlspecialchars($_SESSION['user']['username']), 0, 1)) ?>
                </div>
                <div>
                    <p class="font-semibold"><?= htmlspecialchars($_SESSION['user']['username']) ?></p>
                    <p class="text-red-300 text-sm">Administrator</p>
                </div>
            </div>
            <div class="text-center pt-3 border-t border-red-700/30">
                <p class="text-red-200 text-sm">ID: <?= htmlspecialchars($_SESSION['user']['id']) ?></p>
            </div>
        </div>
        
        <!-- Navigation Menu -->
        <nav class="mb-8">
            <h3 class="text-lg font-semibold text-gray-300 mb-4 flex items-center">
                <i class="fas fa-cog mr-2"></i>
                System Management
            </h3>
            <div class="space-y-2">
                <a href="admin_dashboard.php" class="flex items-center space-x-3 p-3 rounded-xl hover:bg-red-800/30 transition bg-red-800/30">
                    <div class="w-8 h-8 bg-gradient-to-r from-red-500 to-pink-500 rounded-lg flex items-center justify-center">
                        <i class="fas fa-home text-white"></i>
                    </div>
                    <span>Dashboard</span>
                </a>
                
                <a href="register.php?role=2" class="flex items-center space-x-3 p-3 rounded-xl hover:bg-red-800/30 transition">
                    <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-cyan-500 rounded-lg flex items-center justify-center">
                        <i class="fas fa-chalkboard-teacher text-white"></i>
                    </div>
                    <span>Add Teacher</span>
                </a>
                
                <a href="register.php?role=3" class="flex items-center space-x-3 p-3 rounded-xl hover:bg-red-800/30 transition">
                    <div class="w-8 h-8 bg-gradient-to-r from-green-500 to-emerald-500 rounded-lg flex items-center justify-center">
                        <i class="fas fa-user-graduate text-white"></i>
                    </div>
                    <span>Add Student</span>
                </a>
                
                <a href="manage_subjects.php" class="flex items-center space-x-3 p-3 rounded-xl hover:bg-red-800/30 transition">
                    <div class="w-8 h-8 bg-gradient-to-r from-yellow-500 to-amber-500 rounded-lg flex items-center justify-center">
                        <i class="fas fa-book text-white"></i>
                    </div>
                    <span>Manage Subjects</span>
                </a>
                
                <a href="system_logs.php" class="flex items-center space-x-3 p-3 rounded-xl hover:bg-red-800/30 transition">
                    <div class="w-8 h-8 bg-gradient-to-r from-purple-500 to-pink-500 rounded-lg flex items-center justify-center">
                        <i class="fas fa-history text-white"></i>
                    </div>
                    <span>System Logs</span>
                </a>
                
                <a href="settings.php" class="flex items-center space-x-3 p-3 rounded-xl hover:bg-red-800/30 transition">
                    <div class="w-8 h-8 bg-gradient-to-r from-gray-500 to-gray-700 rounded-lg flex items-center justify-center">
                        <i class="fas fa-sliders-h text-white"></i>
                    </div>
                    <span>System Settings</span>
                </a>
            </div>
        </nav>
        
        <!-- System Overview -->
        <div class="mb-8 p-4 bg-gradient-to-r from-red-900/30 to-pink-900/30 rounded-2xl border border-red-700/20">
            <h4 class="font-semibold mb-3 text-gray-300">System Overview</h4>
            <div class="space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-gray-400 text-sm">Total Teachers</span>
                    <span class="font-bold text-blue-300"><?= $teacher_count ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-400 text-sm">Total Students</span>
                    <span class="font-bold text-green-300"><?= $student_count ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-400 text-sm">Total Subjects</span>
                    <span class="font-bold text-yellow-300"><?= $subject_count ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-gray-400 text-sm">Active Users</span>
                    <span class="font-bold text-purple-300"><?= $teacher_count + $student_count + 1 ?></span>
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
                    <div class="bg-gradient-to-r from-red-600 to-pink-600 p-2 rounded-lg">
                        <i class="fas fa-user-shield text-white text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Admin Dashboard</h1>
                        <p class="text-gray-600 text-sm">Welcome back, <?= htmlspecialchars($_SESSION['user']['username']) ?>!</p>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <div class="text-right hidden md:block">
                        <p class="font-medium text-gray-700"><?= htmlspecialchars($_SESSION['user']['username']) ?></p>
                        <p class="text-sm text-gray-500">Administrator</p>
                    </div>
                    <div class="relative">
                        <div class="w-10 h-10 bg-gradient-to-r from-red-600 to-pink-600 rounded-full flex items-center justify-center text-white font-bold shadow-lg">
                            <?= strtoupper(substr(htmlspecialchars($_SESSION['user']['username']), 0, 1)) ?>
                        </div>
                        <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-green-500 rounded-full border-2 border-white"></div>
                    </div>
                    <a href="logout.php" class="btn-glow bg-gradient-to-r from-red-500 to-pink-500 text-white px-4 py-2 rounded-lg hover:from-red-600 hover:to-pink-600 transition flex items-center space-x-2">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-2">
        <!-- Welcome Card -->
        <div class="dashboard-container rounded-2xl shadow-lg p-8 mb-10">
            <div class="flex flex-col md:flex-row items-center justify-between">
                <div class="mb-6 md:mb-0 md:mr-8">
                    <h3 class="text-2xl font-bold text-gray-800 mb-3">System Administration</h3>
                    <p class="text-gray-600 mb-4">You are managing <?= $teacher_count ?> teachers, <?= $student_count ?> students, and <?= $subject_count ?> subjects in the system.</p>
                    <div class="flex flex-wrap gap-4">
                        <a href="register.php?role=2" class="btn-glow bg-gradient-to-r from-blue-600 to-cyan-600 text-white px-6 py-3 rounded-xl font-semibold hover:from-blue-700 hover:to-cyan-700 transition">
                            <i class="fas fa-chalkboard-teacher mr-2"></i>
                            Add Teacher
                        </a>
                        <a href="register.php?role=3" class="btn-glow bg-gradient-to-r from-green-600 to-emerald-600 text-white px-6 py-3 rounded-xl font-semibold hover:from-green-700 hover:to-emerald-700 transition">
                            <i class="fas fa-user-graduate mr-2"></i>
                            Add Student
                        </a>
                        <a href="manage_subjects.php" class="btn-glow bg-gradient-to-r from-yellow-600 to-amber-600 text-white px-6 py-3 rounded-xl font-semibold hover:from-yellow-700 hover:to-amber-700 transition">
                            <i class="fas fa-book mr-2"></i>
                            Manage Subjects
                        </a>
                    </div>
                </div>
                <div class="bg-gradient-to-r from-red-100 to-pink-100 p-6 rounded-2xl">
                    <i class="fas fa-user-shield text-red-600 text-6xl"></i>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
            <div class="dashboard-container rounded-2xl shadow-lg p-6 stat-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-gradient-to-r from-blue-100 to-cyan-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-chalkboard-teacher text-blue-600 text-2xl"></i>
                    </div>
                    <span class="text-3xl font-bold text-blue-600"><?= $teacher_count ?></span>
                </div>
                <h4 class="text-lg font-semibold text-gray-800 mb-2">Teachers</h4>
                <p class="text-gray-600 text-sm">Active teaching staff</p>
            </div>
            
            <div class="dashboard-container rounded-2xl shadow-lg p-6 stat-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-gradient-to-r from-green-100 to-emerald-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-user-graduate text-green-600 text-2xl"></i>
                    </div>
                    <span class="text-3xl font-bold text-green-600"><?= $student_count ?></span>
                </div>
                <h4 class="text-lg font-semibold text-gray-800 mb-2">Students</h4>
                <p class="text-gray-600 text-sm">Registered learners</p>
            </div>
            
            <div class="dashboard-container rounded-2xl shadow-lg p-6 stat-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-gradient-to-r from-yellow-100 to-amber-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-book text-yellow-600 text-2xl"></i>
                    </div>
                    <span class="text-3xl font-bold text-yellow-600"><?= $subject_count ?></span>
                </div>
                <h4 class="text-lg font-semibold text-gray-800 mb-2">Subjects</h4>
                <p class="text-gray-600 text-sm">Courses available</p>
            </div>
            
            <div class="dashboard-container rounded-2xl shadow-lg p-6 stat-card">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-gradient-to-r from-purple-100 to-pink-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-users text-purple-600 text-2xl"></i>
                    </div>
                    <span class="text-3xl font-bold text-purple-600"><?= $teacher_count + $student_count ?></span>
                </div>
                <h4 class="text-lg font-semibold text-gray-800 mb-2">Total Users</h4>
                <p class="text-gray-600 text-sm">System accounts</p>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="dashboard-container rounded-2xl shadow-lg mb-6 p-2">
            <div class="flex overflow-x-auto">
                <button id="teachers-tab" class="tab-button active" onclick="switchTab('teachers')">
                    <div class="flex items-center">
                        <div class="bg-gradient-to-r from-blue-100 to-cyan-100 p-2 rounded-lg mr-3">
                            <i class="fas fa-chalkboard-teacher text-blue-600"></i>
                        </div>
                        <div class="text-left">
                            <div class="font-semibold">Teachers</div>
                            <div class="text-xs text-gray-500">Teaching staff</div>
                        </div>
                    </div>
                </button>
                <button id="students-tab" class="tab-button" onclick="switchTab('students')">
                    <div class="flex items-center">
                        <div class="bg-gradient-to-r from-green-100 to-emerald-100 p-2 rounded-lg mr-3">
                            <i class="fas fa-user-graduate text-green-600"></i>
                        </div>
                        <div class="text-left">
                            <div class="font-semibold">Students</div>
                            <div class="text-xs text-gray-500">Registered learners</div>
                        </div>
                    </div>
                </button>
                <button id="subjects-tab" class="tab-button" onclick="switchTab('subjects')">
                    <div class="flex items-center">
                        <div class="bg-gradient-to-r from-yellow-100 to-amber-100 p-2 rounded-lg mr-3">
                            <i class="fas fa-book text-yellow-600"></i>
                        </div>
                        <div class="text-left">
                            <div class="font-semibold">Subjects</div>
                            <div class="text-xs text-gray-500">Course catalog</div>
                        </div>
                    </div>
                </button>
            </div>
        </div>

        <!-- Tab Content -->
        <div id="teachers-content" class="tab-content">
            <div class="dashboard-container rounded-2xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-50 to-cyan-50 px-6 py-5 border-b border-blue-100">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-2xl font-bold text-gray-800">Teachers Management</h3>
                            <p class="text-gray-600">Manage teaching staff and their accounts</p>
                        </div>
                        <a href="register.php?role=2" class="btn-glow bg-gradient-to-r from-blue-600 to-cyan-600 text-white px-6 py-3 rounded-xl font-semibold hover:from-blue-700 hover:to-cyan-700 transition">
                            <i class="fas fa-plus mr-2"></i>
                            Add New Teacher
                        </a>
                    </div>
                </div>
                <div class="p-6">
                    <?php if($teacher_count > 0): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach($teachers as $t): ?>
                            <div class="bg-gradient-to-r from-gray-50 to-gray-100 rounded-2xl shadow-sm hover-lift p-6 border border-gray-200">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-cyan-500 rounded-full flex items-center justify-center text-white font-bold text-lg">
                                            <?= strtoupper(substr(htmlspecialchars($t['username']), 0, 1)) ?>
                                        </div>
                                        <div>
                                            <h4 class="font-bold text-gray-800"><?= htmlspecialchars($t['username']) ?></h4>
                                            <p class="text-gray-600 text-sm">Teacher ID: <?= $t['id'] ?></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <p class="text-gray-700 mb-2 flex items-center">
                                        <i class="fas fa-envelope text-gray-400 mr-2"></i>
                                        <?= htmlspecialchars($t['email']) ?>
                                    </p>
                                    
                                </div>
                                
                                <div class="flex justify-between items-center pt-4 border-t border-gray-200">
                                    <a href="edit_user.php?id=<?= $t['id'] ?>" 
                                       class="btn-glow bg-gradient-to-r from-yellow-500 to-amber-500 text-white px-4 py-2 rounded-lg hover:from-yellow-600 hover:to-amber-600 transition flex items-center space-x-2">
                                        <i class="fas fa-edit"></i>
                                        <span>Edit</span>
                                    </a>
                                    <a href="delete_user.php?id=<?= $t['id'] ?>" 
                                       onclick="return confirm('Are you sure you want to delete this teacher? This action cannot be undone.');"
                                       class="btn-glow bg-gradient-to-r from-red-500 to-pink-500 text-white px-4 py-2 rounded-lg hover:from-red-600 hover:to-pink-600 transition flex items-center space-x-2">
                                        <i class="fas fa-trash-alt"></i>
                                        <span>Delete</span>
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <div class="w-24 h-24 bg-gradient-to-r from-gray-100 to-gray-200 rounded-full flex items-center justify-center mx-auto mb-6">
                                <i class="fas fa-chalkboard-teacher text-gray-400 text-4xl"></i>
                            </div>
                            <h4 class="text-2xl font-bold text-gray-800 mb-3">No Teachers Found</h4>
                            <p class="text-gray-600 mb-6 max-w-md mx-auto">There are no teachers registered in the system yet. Add your first teacher to get started.</p>
                            <a href="register.php?role=2" class="btn-glow inline-flex items-center space-x-2 bg-gradient-to-r from-blue-600 to-cyan-600 text-white px-6 py-3 rounded-xl font-semibold">
                                <i class="fas fa-plus mr-2"></i>
                                <span>Add First Teacher</span>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div id="students-content" class="tab-content hidden">
            <div class="dashboard-container rounded-2xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 px-6 py-5 border-b border-green-100">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-2xl font-bold text-gray-800">Students Management</h3>
                            <p class="text-gray-600">Manage student accounts and enrollments</p>
                        </div>
                        <a href="register.php?role=3" class="btn-glow bg-gradient-to-r from-green-600 to-emerald-600 text-white px-6 py-3 rounded-xl font-semibold hover:from-green-700 hover:to-emerald-700 transition">
                            <i class="fas fa-plus mr-2"></i>
                            Add New Student
                        </a>
                    </div>
                </div>
                <div class="p-6">
                    <?php if($student_count > 0): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach($students as $s): ?>
                            <div class="bg-gradient-to-r from-gray-50 to-gray-100 rounded-2xl shadow-sm hover-lift p-6 border border-gray-200">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-12 h-12 bg-gradient-to-r from-green-500 to-emerald-500 rounded-full flex items-center justify-center text-white font-bold text-lg">
                                            <?= strtoupper(substr(htmlspecialchars($s['username']), 0, 1)) ?>
                                        </div>
                                        <div>
                                            <h4 class="font-bold text-gray-800"><?= htmlspecialchars($s['username']) ?></h4>
                                            <p class="text-gray-600 text-sm">Student ID: <?= $s['id'] ?></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <p class="text-gray-700 mb-2 flex items-center">
                                        <i class="fas fa-envelope text-gray-400 mr-2"></i>
                                        <?= htmlspecialchars($s['email']) ?>
                                    </p>
                                   
                                </div>
                                
                                <div class="flex justify-between items-center pt-4 border-t border-gray-200">
                                    <a href="edit_user.php?id=<?= $s['id'] ?>" 
                                       class="btn-glow bg-gradient-to-r from-yellow-500 to-amber-500 text-white px-4 py-2 rounded-lg hover:from-yellow-600 hover:to-amber-600 transition flex items-center space-x-2">
                                        <i class="fas fa-edit"></i>
                                        <span>Edit</span>
                                    </a>
                                    <a href="delete_user.php?id=<?= $s['id'] ?>" 
                                       onclick="return confirm('Are you sure you want to delete this student? This action cannot be undone.');"
                                       class="btn-glow bg-gradient-to-r from-red-500 to-pink-500 text-white px-4 py-2 rounded-lg hover:from-red-600 hover:to-pink-600 transition flex items-center space-x-2">
                                        <i class="fas fa-trash-alt"></i>
                                        <span>Delete</span>
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <div class="w-24 h-24 bg-gradient-to-r from-gray-100 to-gray-200 rounded-full flex items-center justify-center mx-auto mb-6">
                                <i class="fas fa-user-graduate text-gray-400 text-4xl"></i>
                            </div>
                            <h4 class="text-2xl font-bold text-gray-800 mb-3">No Students Found</h4>
                            <p class="text-gray-600 mb-6 max-w-md mx-auto">There are no students registered in the system yet. Add your first student to get started.</p>
                            <a href="register.php?role=3" class="btn-glow inline-flex items-center space-x-2 bg-gradient-to-r from-green-600 to-emerald-600 text-white px-6 py-3 rounded-xl font-semibold">
                                <i class="fas fa-plus mr-2"></i>
                                <span>Add First Student</span>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div id="subjects-content" class="tab-content hidden">
            <div class="dashboard-container rounded-2xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-yellow-50 to-amber-50 px-6 py-5 border-b border-yellow-100">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-2xl font-bold text-gray-800">Subjects Management</h3>
                            <p class="text-gray-600">Manage course subjects and assignments</p>
                        </div>
                        <a href="add_subject.php" class="btn-glow bg-gradient-to-r from-yellow-600 to-amber-600 text-white px-6 py-3 rounded-xl font-semibold hover:from-yellow-700 hover:to-amber-700 transition">
                            <i class="fas fa-plus mr-2"></i>
                            Add New Subject
                        </a>
                    </div>
                </div>
                <div class="p-6">
                    <?php if($subject_count > 0): ?>
                        <div class="overflow-x-auto rounded-xl border border-gray-200 shadow-sm">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Subject</th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Code</th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Teacher</th>
                                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                    <?php foreach($subjects as $subject): ?>
                                    <tr class="hover:bg-gradient-to-r hover:from-gray-50 hover:to-gray-100 transition-all duration-200">
                                        <td class="px-6 py-4">
                                            <div class="font-bold text-gray-900"><?= htmlspecialchars($subject['name']) ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="badge bg-gradient-to-r from-gray-100 to-gray-200 text-gray-800 border border-gray-300">
                                                <?= htmlspecialchars($subject['code'] ?? 'N/A') ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-cyan-500 rounded-full flex items-center justify-center text-white text-sm font-bold mr-2">
                                                    <?= strtoupper(substr(htmlspecialchars($subject['teacher_name']), 0, 1)) ?>
                                                </div>
                                                <span><?= htmlspecialchars($subject['teacher_name']) ?></span>
                                            </div>
                                        </td>
                                      
                                        <td class="px-6 py-4 whitespace-nowrap space-x-2">
                                            <a href="edit_subject.php?id=<?= $subject['id'] ?>" 
                                               class="btn-glow inline-flex items-center px-4 py-2 rounded-xl text-sm font-medium bg-gradient-to-r from-yellow-500 to-amber-500 text-white hover:from-yellow-600 hover:to-amber-600 transition">
                                                <i class="fas fa-edit mr-2"></i>
                                                Edit
                                            </a>
                                            <a href="delete_subject.php?id=<?= $subject['id'] ?>" 
                                               onclick="return confirm('Are you sure you want to delete this subject? This will also delete all associated assignments and enrollments.');"
                                               class="btn-glow inline-flex items-center px-4 py-2 rounded-xl text-sm font-medium bg-gradient-to-r from-red-500 to-pink-500 text-white hover:from-red-600 hover:to-pink-600 transition">
                                                <i class="fas fa-trash-alt mr-2"></i>
                                                Delete
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <div class="w-24 h-24 bg-gradient-to-r from-gray-100 to-gray-200 rounded-full flex items-center justify-center mx-auto mb-6">
                                <i class="fas fa-book text-gray-400 text-4xl"></i>
                            </div>
                            <h4 class="text-2xl font-bold text-gray-800 mb-3">No Subjects Found</h4>
                            <p class="text-gray-600 mb-6 max-w-md mx-auto">There are no subjects created in the system yet. Add your first subject to get started.</p>
                            <a href="add_subject.php" class="btn-glow inline-flex items-center space-x-2 bg-gradient-to-r from-yellow-600 to-amber-600 text-white px-6 py-3 rounded-xl font-semibold">
                                <i class="fas fa-plus mr-2"></i>
                                <span>Add First Subject</span>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
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
    
    // Initialize first tab as active
    document.addEventListener('DOMContentLoaded', function() {
        switchTab('teachers');
        
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