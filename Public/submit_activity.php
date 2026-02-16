<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
check_login();

// Only students
if ($_SESSION['user']['role_id'] != 3) {
    header('Location: login.php');
    exit;
}

$student_id = $_SESSION['user']['id'];
$activity_id = $_GET['id'] ?? null;
$message = '';
$message_type = '';

// Check activity exists
$stmt = $pdo->prepare("SELECT a.*, s.name as subject_name FROM activities a JOIN subjects s ON a.subject_id = s.id WHERE a.id = ?");
$stmt->execute([$activity_id]);
$activity = $stmt->fetch();
if (!$activity) die("Activity not found.");

// Get subject ID for back link
$subject_id = $activity['subject_id'];

// Check existing submission
$stmt = $pdo->prepare("SELECT * FROM submissions WHERE student_id = ? AND activity_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$student_id, $activity_id]);
$existing_submission = $stmt->fetch();

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_FILES['submission_file']['name'])) {
        $upload_dir = __DIR__ . '/../uploads/activities/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        // Validate file type
        $allowed_types = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'zip', 'rar'];
        $file_ext = strtolower(pathinfo($_FILES['submission_file']['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_ext, $allowed_types)) {
            $message = "File type not allowed. Allowed types: " . implode(', ', $allowed_types);
            $message_type = 'error';
        } elseif ($_FILES['submission_file']['size'] > 10 * 1024 * 1024) { // 10MB limit
            $message = "File size too large. Maximum size is 10MB.";
            $message_type = 'error';
        } else {
            $file_name = $student_id . '_' . $activity_id . '_' . time() . '.' . $file_ext;
            $target_file = $upload_dir . $file_name;

            if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $target_file)) {
                if ($existing_submission) {
                    // Delete old file
                    $old_file = $upload_dir . $existing_submission['file_path'];
                    if (file_exists($old_file)) unlink($old_file);
                    
                    // Update existing submission
                    $stmt = $pdo->prepare("UPDATE submissions SET file_path = ?, created_at = NOW() WHERE id = ?");
                    $stmt->execute([$file_name, $existing_submission['id']]);
                    $message = "Activity resubmitted successfully!";
                } else {
                    // Insert new submission
                    $stmt = $pdo->prepare("INSERT INTO submissions (student_id, activity_id, file_path, created_at) VALUES (?, ?, ?, NOW())");
                    $stmt->execute([$student_id, $activity_id, $file_name]);
                    $message = "Activity submitted successfully!";
                }
                $message_type = 'success';
                
                // Refresh existing submission data
                $stmt = $pdo->prepare("SELECT * FROM submissions WHERE student_id = ? AND activity_id = ? ORDER BY created_at DESC LIMIT 1");
                $stmt->execute([$student_id, $activity_id]);
                $existing_submission = $stmt->fetch();
            } else {
                $message = "Failed to upload file. Please try again.";
                $message_type = 'error';
            }
        }
    } else {
        $message = "Please choose a file to submit.";
        $message_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Activity - Student Portal</title>
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
        
        .file-upload-area {
            border: 2px dashed #cbd5e0;
            border-radius: 1rem;
            transition: all 0.3s ease;
        }
        
        .file-upload-area:hover, .file-upload-area.dragover {
            border-color: #4F46E5;
            background-color: rgba(79, 70, 229, 0.05);
        }
        
        .file-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            margin-right: 16px;
        }
        
        .file-preview {
            transition: all 0.3s ease;
        }
        
        .file-preview:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .slide-in {
            animation: slideIn 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55) forwards;
        }
        
        @keyframes slideIn {
            0% { opacity: 0; transform: translateY(30px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        
        .pulse-animation {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(79, 70, 229, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(79, 70, 229, 0); }
            100% { box-shadow: 0 0 0 0 rgba(79, 70, 229, 0); }
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-4xl">
        <!-- Header -->
        <div class="mb-8 text-center">
            <h1 class="text-4xl font-bold text-white mb-2">Submit Activity</h1>
            <p class="text-white/80">Upload your work and track your submission</p>
        </div>

        <div class="glass-card rounded-3xl shadow-2xl overflow-hidden slide-in">
            <!-- Activity Header -->
            <div class="bg-gradient-to-r from-blue-600 to-cyan-600 px-8 py-6 text-white">
                <div class="flex justify-between items-center">
                    <div>
                        <div class="flex items-center mb-3">
                            <div class="bg-white/20 p-3 rounded-xl mr-4">
                                <i class="fas fa-tasks text-2xl"></i>
                            </div>
                            <div>
                                <h2 class="text-2xl font-bold"><?= htmlspecialchars($activity['name']) ?></h2>
                                <p class="text-blue-100"><?= htmlspecialchars($activity['subject_name']) ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm opacity-90">Total Points</div>
                        <div class="text-3xl font-bold"><?= $activity['total_points'] ?? 'N/A' ?></div>
                    </div>
                </div>
            </div>

            <div class="p-8">
                <!-- Notification -->
                <?php if($message): ?>
                <div id="notification" class="mb-8 p-4 rounded-xl border-l-4 <?= $message_type == 'success' ? 'border-green-500 bg-gradient-to-r from-green-50 to-green-100 text-green-800' : 'border-red-500 bg-gradient-to-r from-red-50 to-red-100 text-red-800' ?> flex justify-between items-center slide-in">
                    <div class="flex items-center space-x-3">
                        <div class="p-2 rounded-full <?= $message_type == 'success' ? 'bg-green-500' : 'bg-red-500' ?>">
                            <i class="fas <?= $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> text-white"></i>
                        </div>
                        <div>
                            <p class="font-semibold"><?= $message_type == 'success' ? 'Success!' : 'Error!' ?></p>
                            <p><?= htmlspecialchars($message) ?></p>
                        </div>
                    </div>
                    <button onclick="dismissNotification()" class="<?= $message_type == 'success' ? 'text-green-600 hover:text-green-800' : 'text-red-600 hover:text-red-800' ?> transition">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Left Column: Activity Details -->
                    <div class="space-y-6">
                        <div class="bg-gradient-to-r from-gray-50 to-gray-100 p-6 rounded-2xl border border-gray-200">
                            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                                <i class="fas fa-info-circle text-blue-600 mr-3"></i>
                                Activity Details
                            </h3>
                            <div class="space-y-4">
                                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                    <span class="text-gray-600">Type</span>
                                    <span class="font-semibold text-gray-800"><?= htmlspecialchars($activity['type'] ?? 'Activity') ?></span>
                                </div>
                                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                    <span class="text-gray-600">Subject</span>
                                    <span class="font-semibold text-gray-800"><?= htmlspecialchars($activity['subject_name']) ?></span>
                                </div>
                                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                    <span class="text-gray-600">Total Points</span>
                                    <span class="font-bold text-lg text-blue-600"><?= $activity['total_points'] ?? 'N/A' ?></span>
                                </div>
                                <?php if($activity['due_date']): ?>
                                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                    <span class="text-gray-600">Deadline</span>
                                    <span class="font-semibold <?= strtotime($activity['due_date']) < time() ? 'text-red-600' : 'text-green-600' ?>">
                                        <?= date('M d, Y', strtotime($activity['due_date'])) ?>
                                        <?php if(strtotime($activity['due_date']) < time()): ?>
                                            <span class="text-xs bg-red-100 text-red-800 px-2 py-1 rounded-full ml-2">Overdue</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Submission History -->
                        <?php if($existing_submission): ?>
                        <div class="bg-gradient-to-r from-green-50 to-emerald-50 p-6 rounded-2xl border border-green-200">
                            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                                <i class="fas fa-history text-green-600 mr-3"></i>
                                Previous Submission
                            </h3>
                            <div class="file-preview bg-white p-4 rounded-xl border border-green-200">
                                <div class="flex items-center">
                                    <div class="file-icon bg-gradient-to-r from-green-100 to-emerald-200">
                                        <i class="fas fa-file-alt text-green-600 text-xl"></i>
                                    </div>
                                    <div class="flex-1">
                                        <div class="font-semibold text-gray-800"><?= htmlspecialchars($existing_submission['file_path']) ?></div>
                                        <div class="text-sm text-gray-600 mt-1">
                                            Submitted on <?= date('M d, Y \a\t h:i A', strtotime($existing_submission['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 text-sm text-green-700 bg-green-100 p-3 rounded-lg">
                                <i class="fas fa-info-circle mr-2"></i>
                                Uploading a new file will replace your previous submission.
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Right Column: Upload Form -->
                    <div class="space-y-6">
                        <div class="bg-gradient-to-r from-indigo-50 to-purple-50 p-6 rounded-2xl border border-indigo-200">
                            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                                <i class="fas fa-cloud-upload-alt text-indigo-600 mr-3"></i>
                                Upload Your Work
                            </h3>

                            <form method="POST" enctype="multipart/form-data" id="uploadForm" class="space-y-6">
                                <!-- File Upload Area -->
                                <div id="fileUploadArea" class="file-upload-area p-8 text-center cursor-pointer transition-all duration-300">
                                    <input type="file" name="submission_file" id="submission_file" class="hidden" required>
                                    
                                    <div id="uploadIcon" class="mx-auto mb-4">
                                        <div class="w-20 h-20 bg-gradient-to-r from-indigo-100 to-purple-100 rounded-full flex items-center justify-center mx-auto pulse-animation">
                                            <i class="fas fa-cloud-upload-alt text-indigo-600 text-3xl"></i>
                                        </div>
                                    </div>
                                    
                                    <div id="uploadText" class="space-y-2">
                                        <p class="text-lg font-semibold text-gray-700">Drag & drop your file here</p>
                                        <p class="text-gray-500">or click to browse</p>
                                        <p class="text-sm text-gray-400 mt-4">Supported formats: PDF, DOC, DOCX, TXT, JPG, PNG, ZIP, RAR</p>
                                        <p class="text-sm text-gray-400">Max file size: 10MB</p>
                                    </div>
                                    
                                    <div id="filePreview" class="hidden mt-6">
                                        <div class="bg-white p-4 rounded-xl border border-indigo-200 shadow-sm">
                                            <div class="flex items-center">
                                                <div class="file-icon bg-gradient-to-r from-indigo-100 to-purple-200">
                                                    <i id="fileIcon" class="fas fa-file text-indigo-600 text-xl"></i>
                                                </div>
                                                <div class="flex-1 text-left">
                                                    <div id="fileName" class="font-semibold text-gray-800 truncate"></div>
                                                    <div id="fileSize" class="text-sm text-gray-600 mt-1"></div>
                                                </div>
                                                <button type="button" onclick="removeFile()" class="text-red-500 hover:text-red-700 p-2">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Progress Bar (hidden by default) -->
                                <div id="progressContainer" class="hidden">
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-gray-700 font-medium">Uploading...</span>
                                        <span id="progressPercent" class="font-bold text-indigo-600">0%</span>
                                    </div>
                                    <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                                        <div id="progressBar" class="h-full bg-gradient-to-r from-indigo-500 to-purple-500 rounded-full transition-all duration-300" style="width: 0%"></div>
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <div class="pt-4">
                                    <button type="submit" id="submitBtn" class="btn-glow w-full bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-4 rounded-xl font-semibold text-lg hover:from-indigo-700 hover:to-purple-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                                        <i class="fas fa-paper-plane mr-3"></i>
                                        <?= $existing_submission ? 'Resubmit Activity' : 'Submit Activity' ?>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Help Section -->
                        <div class="bg-gradient-to-r from-amber-50 to-yellow-50 p-6 rounded-2xl border border-amber-200">
                            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                                <i class="fas fa-lightbulb text-amber-600 mr-3"></i>
                                Submission Tips
                            </h3>
                            <ul class="space-y-3">
                                <li class="flex items-start">
                                    <i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i>
                                    <span class="text-gray-700">Make sure your file is named clearly (e.g., YourName_Activity1.pdf)</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i>
                                    <span class="text-gray-700">Check that you're submitting the correct file before uploading</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i>
                                    <span class="text-gray-700">Keep a backup copy of your submission</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i>
                                    <span class="text-gray-700">You can resubmit if you need to update your work</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Back Button -->
                <div class="mt-8 pt-6 border-t border-gray-200">
                    <a href="student_dashboard.php?subject_id=<?= $subject_id ?>" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-gray-600 to-gray-700 text-white rounded-xl hover:from-gray-700 hover:to-gray-800 transition">
                        <i class="fas fa-arrow-left mr-3"></i>
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // DOM Elements
        const fileInput = document.getElementById('submission_file');
        const fileUploadArea = document.getElementById('fileUploadArea');
        const uploadText = document.getElementById('uploadText');
        const filePreview = document.getElementById('filePreview');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const fileIcon = document.getElementById('fileIcon');
        const progressContainer = document.getElementById('progressContainer');
        const progressBar = document.getElementById('progressBar');
        const progressPercent = document.getElementById('progressPercent');
        const submitBtn = document.getElementById('submitBtn');
        const uploadIcon = document.getElementById('uploadIcon');

        // File type icons mapping
        const fileIcons = {
            'pdf': 'fa-file-pdf',
            'doc': 'fa-file-word',
            'docx': 'fa-file-word',
            'txt': 'fa-file-alt',
            'jpg': 'fa-file-image',
            'jpeg': 'fa-file-image',
            'png': 'fa-file-image',
            'zip': 'fa-file-archive',
            'rar': 'fa-file-archive'
        };

        // File upload area click handler
        fileUploadArea.addEventListener('click', () => fileInput.click());

        // Drag and drop handlers
        ['dragenter', 'dragover'].forEach(eventName => {
            fileUploadArea.addEventListener(eventName, (e) => {
                e.preventDefault();
                fileUploadArea.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            fileUploadArea.addEventListener(eventName, (e) => {
                e.preventDefault();
                fileUploadArea.classList.remove('dragover');
                
                if (eventName === 'drop') {
                    const files = e.dataTransfer.files;
                    if (files.length > 0) {
                        fileInput.files = files;
                        handleFileSelection(files[0]);
                    }
                }
            });
        });

        // File input change handler
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFileSelection(e.target.files[0]);
            }
        });

        // Handle file selection
        function handleFileSelection(file) {
            if (file.size > 10 * 1024 * 1024) {
                alert('File size exceeds 10MB limit');
                return;
            }

            const fileExt = file.name.split('.').pop().toLowerCase();
            const allowedExts = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'zip', 'rar'];
            
            if (!allowedExts.includes(fileExt)) {
                alert('File type not allowed. Please upload a valid file.');
                return;
            }

            // Update UI
            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            fileIcon.className = 'fas ' + (fileIcons[fileExt] || 'fa-file') + ' text-indigo-600 text-xl';
            
            // Hide upload text, show preview
            uploadText.classList.add('hidden');
            uploadIcon.classList.add('hidden');
            filePreview.classList.remove('hidden');
            
            // Enable submit button
            submitBtn.disabled = false;
        }

        // Remove file
        function removeFile() {
            fileInput.value = '';
            uploadText.classList.remove('hidden');
            uploadIcon.classList.remove('hidden');
            filePreview.classList.add('hidden');
            submitBtn.disabled = true;
        }

        // Format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Form submission with simulated progress
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            if (!fileInput.files.length) {
                e.preventDefault();
                alert('Please select a file to upload');
                return;
            }

            // Show progress bar
            progressContainer.classList.remove('hidden');
            
            // Simulate progress (in real app, this would be handled by XMLHttpRequest with progress events)
            let progress = 0;
            const interval = setInterval(() => {
                progress += Math.random() * 20;
                if (progress > 90) {
                    clearInterval(interval);
                    progress = 90; // Hold at 90% until actual upload completes
                }
                updateProgress(progress);
            }, 200);
        });

        // Update progress bar
        function updateProgress(percent) {
            const roundedPercent = Math.min(Math.round(percent), 100);
            progressBar.style.width = roundedPercent + '%';
            progressPercent.textContent = roundedPercent + '%';
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

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Disable submit button initially
            submitBtn.disabled = true;
        });
    </script>
</body>
</html>