<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
check_login();

// Ensure only admin can access
if ($_SESSION['user']['role_id'] == 3) {
    header('Location: login.php');
    exit;
}

// Fetch all teachers for dropdown
$teachers = $pdo->query("SELECT id, username FROM users WHERE role_id = 2 ORDER BY username")->fetchAll();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $teacher_id = $_POST['teacher_id'] ?? null;
    $description = trim($_POST['description'] ?? '');

    // Basic validations
    if (empty($name) || empty($teacher_id)) {
        $error = "Subject name and teacher are required.";
    } else {
        // Check if subject already exists
        $stmt = $pdo->prepare("SELECT id FROM subjects WHERE name = ? OR code = ? LIMIT 1");
        $stmt->execute([$name, $code]);
        
        if ($stmt->fetch()) {
            $error = "Subject name or code already exists.";
        } else {
            $insert = $pdo->prepare("INSERT INTO subjects (name, code, teacher_id, description) VALUES (?, ?, ?, ?)");
            $insert->execute([$name, $code, $teacher_id, $description]);
            $success = "Subject created successfully!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Subject - Admin Panel</title>
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
</style>
</head>
<body class="p-4">
    <!-- Back Button -->
    <div class="fixed top-6 left-6 z-10">
        <a href="admin_dashboard.php" class="inline-flex items-center text-white hover:text-gray-200 group transition">
            <div class="bg-white/20 p-2 rounded-full group-hover:bg-white/30 transition mr-3">
                <i class="fas fa-arrow-left text-white"></i>
            </div>
            <span class="font-medium">Back to Dashboard</span>
        </a>
    </div>
    
    <div class="max-w-2xl mx-auto bg-white rounded-3xl shadow-2xl overflow-hidden mt-16">
        <!-- Header -->
        <div class="bg-gradient-to-r from-yellow-600 to-amber-600 p-8 text-center">
            <div class="flex justify-center mb-4">
                <div class="relative">
                    <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center">
                        <i class="fas fa-book text-yellow-600 text-3xl"></i>
                    </div>
                    <div class="absolute -top-1 -right-1 w-8 h-8 bg-white rounded-full flex items-center justify-center">
                        <i class="fas fa-plus text-yellow-600 text-sm"></i>
                    </div>
                </div>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">Create New Subject</h1>
            <p class="text-white/90">Add a new course to the system</p>
        </div>
        
        <!-- Success/Error Messages -->
        <?php if(isset($success) && $success): ?>
        <div class="mx-6 mt-6 p-4 rounded-xl border-l-4 border-green-500 bg-green-50 text-green-800">
            <div class="flex items-center space-x-3">
                <i class="fas fa-check-circle text-green-500"></i>
                <div>
                    <p class="font-semibold">Success!</p>
                    <p><?= htmlspecialchars($success) ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if(isset($error) && $error): ?>
        <div class="mx-6 mt-6 p-4 rounded-xl border-l-4 border-red-500 bg-red-50 text-red-800">
            <div class="flex items-center space-x-3">
                <i class="fas fa-exclamation-circle text-red-500"></i>
                <div>
                    <p class="font-semibold">Error!</p>
                    <p><?= htmlspecialchars($error) ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="p-8">
            <form method="POST" class="space-y-6">
                <!-- Subject Name -->
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">
                        <i class="fas fa-book mr-2 text-yellow-500"></i>
                        Subject Name
                    </label>
                    <input type="text" name="name" required 
                           placeholder="e.g., Mathematics, Computer Science" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-yellow-300 focus:border-yellow-500">
                </div>
                
                <!-- Subject Code -->
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">
                        <i class="fas fa-hashtag mr-2 text-yellow-500"></i>
                        Subject Code (Optional)
                    </label>
                    <input type="text" name="code" 
                           placeholder="e.g., MATH101, CS101" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-yellow-300 focus:border-yellow-500">
                </div>
                
                <!-- Select Teacher -->
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">
                        <i class="fas fa-chalkboard-teacher mr-2 text-yellow-500"></i>
                        Assign Teacher
                    </label>
                    <select name="teacher_id" required 
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-yellow-300 focus:border-yellow-500">
                        <option value="">Select a teacher...</option>
                        <?php foreach($teachers as $teacher): ?>
                            <option value="<?= $teacher['id'] ?>"><?= htmlspecialchars($teacher['username']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Description -->
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">
                        <i class="fas fa-align-left mr-2 text-yellow-500"></i>
                        Description (Optional)
                    </label>
                    <textarea name="description" rows="4" 
                              placeholder="Enter subject description..." 
                              class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-yellow-300 focus:border-yellow-500"></textarea>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" 
                        class="w-full bg-gradient-to-r from-yellow-600 to-amber-600 text-white py-3.5 rounded-xl font-semibold text-lg hover:from-yellow-700 hover:to-amber-700 transition">
                    <i class="fas fa-plus mr-2"></i>
                    Create Subject
                </button>
            </form>
        </div>
    </div>
</body>
</html>