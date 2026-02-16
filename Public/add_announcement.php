<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
check_login();

// Only allow teachers
if ($_SESSION['user']['role_id'] != 2) {
    header('Location: login.php');
    exit;
}

$teacher_id = $_SESSION['user']['id'];
$subject_id = $_GET['subject_id'] ?? null;
$message = '';
$error = '';

// Verify teacher owns the subject
if ($subject_id) {
    $stmt = $pdo->prepare("SELECT id, name FROM subjects WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$subject_id, $teacher_id]);
    $subject = $stmt->fetch();
    
    if (!$subject) {
        header('Location: teacher_dashboard.php');
        exit;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $announcement_message = trim($_POST['message']);
    $subject_id = $_POST['subject_id'];
    
    // Validate inputs
    if (empty($title)) {
        $error = "Title is required.";
    } elseif (empty($announcement_message)) {
        $error = "Message is required.";
    } elseif (strlen($title) > 200) {
        $error = "Title must be less than 200 characters.";
    } else {
        // Verify teacher still owns the subject
        $stmt = $pdo->prepare("SELECT id FROM subjects WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$subject_id, $teacher_id]);
        if (!$stmt->fetch()) {
            $error = "You don't have permission to add announcements to this subject.";
        } else {
            // Insert announcement
            $stmt = $pdo->prepare("INSERT INTO announcements (subject_id, title, message, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$subject_id, $title, $announcement_message]);
            
            $message = "Announcement added successfully!";
            
            // Redirect after 2 seconds
            header("refresh:2;url=teacher_dashboard.php?subject_id=$subject_id");
        }
    }
}

// If no subject_id provided, redirect to dashboard
if (!$subject_id) {
    header('Location: teacher_dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Announcement</title>
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
        
        .character-count {
            transition: color 0.3s ease;
        }
    </style>
</head>
<body class="overflow-x-hidden">
    <!-- Back to Dashboard -->
    <div class="container mx-auto px-4 py-6">
        <a href="teacher_dashboard.php<?= $subject_id ? '?subject_id=' . $subject_id : '' ?>" class="inline-flex items-center text-white hover:text-indigo-200 mb-6 group transition">
            <div class="bg-white/20 p-2 rounded-full group-hover:bg-white/30 transition mr-3">
                <i class="fas fa-arrow-left text-white"></i>
            </div>
            <span class="font-medium">Back to Dashboard</span>
        </a>
    </div>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-2">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="mb-8 text-center">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-r from-purple-500 to-pink-500 rounded-2xl mb-4">
                    <i class="fas fa-bullhorn text-white text-3xl"></i>
                </div>
                <h1 class="text-3xl font-bold text-white mb-2">Create New Announcement</h1>
                <p class="text-white/80">
                    <?php if(isset($subject['name'])): ?>
                        For: <span class="font-semibold"><?= htmlspecialchars($subject['name']) ?></span>
                    <?php endif; ?>
                </p>
            </div>

            <!-- Notifications -->
            <?php if($message): ?>
            <div class="mb-6 p-4 rounded-xl border-l-4 border-green-500 bg-gradient-to-r from-green-50 to-green-100 text-green-800 flex justify-between items-center slide-in shadow-lg">
                <div class="flex items-center space-x-3">
                    <div class="bg-green-500 p-2 rounded-full">
                        <i class="fas fa-check-circle text-white"></i>
                    </div>
                    <div>
                        <p class="font-semibold">Success!</p>
                        <p><?= htmlspecialchars($message) ?></p>
                        <p class="text-sm mt-1">Redirecting to dashboard...</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Error Message -->
            <?php if($error): ?>
            <div class="mb-6 p-4 rounded-xl border-l-4 border-red-500 bg-gradient-to-r from-red-50 to-red-100 text-red-800 flex justify-between items-center slide-in shadow-lg">
                <div class="flex items-center space-x-3">
                    <div class="bg-red-500 p-2 rounded-full">
                        <i class="fas fa-exclamation-circle text-white"></i>
                    </div>
                    <div>
                        <p class="font-semibold">Error!</p>
                        <p><?= htmlspecialchars($error) ?></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Announcement Form -->
            <div class="dashboard-container rounded-2xl shadow-2xl overflow-hidden slide-in">
                <div class="bg-gradient-to-r from-purple-600 to-pink-600 px-6 py-5">
                    <h2 class="text-2xl font-bold text-white">Announcement Details</h2>
                    <p class="text-white/90">Share important information with your students</p>
                </div>
                
                <form method="POST" action="" class="p-6">
                    <input type="hidden" name="subject_id" value="<?= htmlspecialchars($subject_id) ?>">
                    
                    <div class="space-y-6">
                        <!-- Title -->
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">
                                <span class="flex items-center">
                                    <i class="fas fa-heading mr-2 text-purple-600"></i>
                                    Announcement Title
                                    <span class="text-red-500 ml-1">*</span>
                                </span>
                            </label>
                            <input type="text" 
                                   name="title" 
                                   maxlength="200"
                                   value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                                   placeholder="Enter announcement title (e.g., 'Exam Schedule', 'Project Deadline Extended')"
                                   class="input-focus-effect w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-300 focus:border-transparent"
                                   required>
                            <div class="flex justify-between items-center mt-2">
                                <p class="text-gray-500 text-sm">Keep it clear and concise</p>
                                <span id="title-count" class="character-count text-sm text-gray-500">0/200</span>
                            </div>
                        </div>
                        
                        <!-- Message -->
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">
                                <span class="flex items-center">
                                    <i class="fas fa-comment-alt mr-2 text-purple-600"></i>
                                    Announcement Message
                                    <span class="text-red-500 ml-1">*</span>
                                </span>
                            </label>
                            <textarea 
                                name="message" 
                                rows="8"
                                placeholder="Write your announcement message here. You can include details, instructions, dates, or any important information for your students..."
                                class="input-focus-effect w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-purple-300 focus:border-transparent resize-none"
                                required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                            <div class="flex justify-between items-center mt-2">
                                <p class="text-gray-500 text-sm">Be specific and provide all necessary details</p>
                            </div>
                        </div>
                        
                        <!-- Preview Card -->
                        <div class="bg-gradient-to-r from-purple-50 to-pink-50 border-l-4 border-purple-500 p-5 rounded-xl">
                            <h3 class="font-bold text-gray-800 mb-3 flex items-center">
                                <i class="fas fa-eye mr-2 text-purple-600"></i>
                                Preview
                            </h3>
                            <div class="space-y-2">
                                <div class="flex justify-between items-start">
                                    <h4 id="preview-title" class="font-bold text-lg text-gray-800">
                                        <?= htmlspecialchars($_POST['title'] ?? 'Announcement Title') ?>
                                    </h4>
                                    <span class="bg-white px-3 py-1 rounded-full text-sm text-gray-600 shadow-sm">
                                        <i class="far fa-clock mr-1"></i>
                                        <?= date('M d, Y') ?>
                                    </span>
                                </div>
                                <p id="preview-message" class="text-gray-700">
                                    <?= htmlspecialchars($_POST['message'] ?? 'Your announcement message will appear here...') ?>
                                </p>
                                <div class="text-gray-500 text-sm flex items-center">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    Posted just now
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="flex flex-col sm:flex-row justify-end space-y-4 sm:space-y-0 sm:space-x-4 mt-8 pt-6 border-t border-gray-200">
                        <a href="teacher_dashboard.php<?= $subject_id ? '?subject_id=' . $subject_id : '' ?>" 
                           class="btn-glow bg-gradient-to-r from-gray-600 to-gray-700 text-white px-6 py-3 rounded-xl hover:from-gray-700 hover:to-gray-800 transition text-center">
                            <i class="fas fa-times mr-2"></i>
                            Cancel
                        </a>
                        <button type="submit" 
                                class="btn-glow bg-gradient-to-r from-purple-600 to-pink-600 text-white px-6 py-3 rounded-xl hover:from-purple-700 hover:to-pink-700 transition text-center">
                            <i class="fas fa-paper-plane mr-2"></i>
                            Publish Announcement
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Tips Card -->
            <div class="dashboard-container rounded-2xl shadow-lg mt-6 p-6 slide-in">
                <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-lightbulb mr-2 text-yellow-500"></i>
                    Tips for Effective Announcements
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex items-start space-x-3">
                        <div class="bg-gradient-to-r from-green-100 to-emerald-100 p-2 rounded-lg">
                            <i class="fas fa-check-circle text-green-600"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800">Be Clear & Concise</h4>
                            <p class="text-gray-600 text-sm">Use simple language and get straight to the point.</p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="bg-gradient-to-r from-blue-100 to-cyan-100 p-2 rounded-lg">
                            <i class="fas fa-calendar-alt text-blue-600"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800">Include Important Dates</h4>
                            <p class="text-gray-600 text-sm">Always mention deadlines, exam dates, or event dates.</p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="bg-gradient-to-r from-purple-100 to-pink-100 p-2 rounded-lg">
                            <i class="fas fa-exclamation-circle text-purple-600"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800">Highlight Urgent Information</h4>
                            <p class="text-gray-600 text-sm">Use emphasis for time-sensitive or critical announcements.</p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="bg-gradient-to-r from-indigo-100 to-purple-100 p-2 rounded-lg">
                            <i class="fas fa-question-circle text-indigo-600"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800">Provide Contact Information</h4>
                            <p class="text-gray-600 text-sm">Let students know how they can reach you for questions.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="mt-12 pt-8 border-t border-white/20">
        <div class="container mx-auto px-4 py-6">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="flex items-center space-x-3 mb-4 md:mb-0">
                    <div class="bg-white/20 p-2 rounded-lg">
                        <i class="fas fa-graduation-cap text-white"></i>
                    </div>
                    <div class="text-white/80">
                        <p class="font-medium">Learning Management System</p>
                        <p class="text-sm">&copy; <?= date('Y') ?> All rights reserved.</p>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Character counter for title
        const titleInput = document.querySelector('input[name="title"]');
        const titleCount = document.getElementById('title-count');
        const previewTitle = document.getElementById('preview-title');
        const previewMessage = document.getElementById('preview-message');
        const messageInput = document.querySelector('textarea[name="message"]');
        
        function updateCharacterCount() {
            const length = titleInput.value.length;
            titleCount.textContent = `${length}/200`;
            
            // Change color when approaching limit
            if (length > 180) {
                titleCount.classList.remove('text-gray-500');
                titleCount.classList.add('text-yellow-600');
            } else if (length > 195) {
                titleCount.classList.remove('text-yellow-600');
                titleCount.classList.add('text-red-600');
            } else {
                titleCount.classList.remove('text-yellow-600', 'text-red-600');
                titleCount.classList.add('text-gray-500');
            }
        }
        
        function updatePreview() {
            previewTitle.textContent = titleInput.value || 'Announcement Title';
            previewMessage.textContent = messageInput.value || 'Your announcement message will appear here...';
        }
        
        // Initialize
        updateCharacterCount();
        updatePreview();
        
        // Add event listeners
        titleInput.addEventListener('input', () => {
            updateCharacterCount();
            updatePreview();
        });
        
        messageInput.addEventListener('input', updatePreview);
        
        // Form submission validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const title = titleInput.value.trim();
            const message = messageInput.value.trim();
            
            if (!title) {
                e.preventDefault();
                alert('Please enter a title for the announcement.');
                titleInput.focus();
                return;
            }
            
            if (!message) {
                e.preventDefault();
                alert('Please enter a message for the announcement.');
                messageInput.focus();
                return;
            }
            
            if (title.length > 200) {
                e.preventDefault();
                alert('Title must be less than 200 characters.');
                titleInput.focus();
                return;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Publishing...';
            submitBtn.disabled = true;
        });
    </script>
</body>
</html>