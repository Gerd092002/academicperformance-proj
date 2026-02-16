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
$assignment_id = $_GET['id'] ?? null;
$message = '';
$message_type = '';

// Check assignment exists with subject name
$stmt = $pdo->prepare("SELECT a.*, s.name as subject_name FROM assignments a JOIN subjects s ON a.subject_id = s.id WHERE a.id = ?");
$stmt->execute([$assignment_id]);
$assignment = $stmt->fetch();
if (!$assignment) die("Assignment not found.");

// Get subject ID for back link
$subject_id = $assignment['subject_id'];

// Check existing submission
$stmt = $pdo->prepare("SELECT * FROM submissions WHERE student_id = ? AND assignment_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$student_id, $assignment_id]);
$existing_submission = $stmt->fetch();

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_FILES['submission_file']['name'])) {
        $upload_dir = __DIR__ . '/../uploads/assignments/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        // Validate file type
        $allowed_types = ['pdf', 'doc', 'docx', 'txt', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip', 'rar'];
        $file_ext = strtolower(pathinfo($_FILES['submission_file']['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_ext, $allowed_types)) {
            $message = "File type not allowed. Allowed types: " . implode(', ', $allowed_types);
            $message_type = 'error';
        } elseif ($_FILES['submission_file']['size'] > 10 * 1024 * 1024) { // 10MB limit
            $message = "File size too large. Maximum size is 10MB.";
            $message_type = 'error';
        } else {
            $file_name = $student_id . '_' . $assignment_id . '_' . time() . '.' . $file_ext;
            $target_file = $upload_dir . $file_name;

            if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $target_file)) {
                if ($existing_submission) {
                    // Delete old file
                    $old_file = $upload_dir . $existing_submission['file_path'];
                    if (file_exists($old_file)) unlink($old_file);
                    
                    // Update existing submission
                    $stmt = $pdo->prepare("UPDATE submissions SET file_path = ?, created_at = NOW() WHERE id = ?");
                    $stmt->execute([$file_name, $existing_submission['id']]);
                    $message = "Assignment resubmitted successfully!";
                } else {
                    // Insert new submission
                    $stmt = $pdo->prepare("INSERT INTO submissions (student_id, assignment_id, file_path, created_at) VALUES (?, ?, ?, NOW())");
                    $stmt->execute([$student_id, $assignment_id, $file_name]);
                    $message = "Assignment submitted successfully!";
                }
                $message_type = 'success';
                
                // Refresh existing submission data
                $stmt = $pdo->prepare("SELECT * FROM submissions WHERE student_id = ? AND assignment_id = ? ORDER BY created_at DESC LIMIT 1");
                $stmt->execute([$student_id, $assignment_id]);
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
    <title>Submit Assignment - Student Portal</title>
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
            border-color: #10B981;
            background-color: rgba(16, 185, 129, 0.05);
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
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
        
        .due-date-badge {
            transition: all 0.3s ease;
        }
        
        .due-date-badge:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-4xl">
        <!-- Header -->
        <div class="mb-8 text-center">
            <h1 class="text-4xl font-bold text-white mb-2">Submit Assignment</h1>
            <p class="text-white/80">Upload your completed assignment work</p>
        </div>

        <div class="glass-card rounded-3xl shadow-2xl overflow-hidden slide-in">
            <!-- Assignment Header -->
            <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-8 py-6 text-white">
                <div class="flex justify-between items-center">
                    <div>
                        <div class="flex items-center mb-3">
                            <div class="bg-white/20 p-3 rounded-xl mr-4">
                                <i class="fas fa-file-alt text-2xl"></i>
                            </div>
                            <div>
                                <h2 class="text-2xl font-bold"><?= htmlspecialchars($assignment['name']) ?></h2>
                                <p class="text-green-100"><?= htmlspecialchars($assignment['subject_name']) ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm opacity-90">Total Points</div>
                        <div class="text-3xl font-bold"><?= $assignment['total_points'] ?? 'N/A' ?></div>
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
                    <!-- Left Column: Assignment Details -->
                    <div class="space-y-6">
                        <div class="bg-gradient-to-r from-gray-50 to-gray-100 p-6 rounded-2xl border border-gray-200">
                            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                                <i class="fas fa-info-circle text-green-600 mr-3"></i>
                                Assignment Details
                            </h3>
                            <div class="space-y-4">
                                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                    <span class="text-gray-600">Subject</span>
                                    <span class="font-semibold text-gray-800"><?= htmlspecialchars($assignment['subject_name']) ?></span>
                                </div>
                                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                    <span class="text-gray-600">Total Points</span>
                                    <span class="font-bold text-lg text-green-600"><?= $assignment['total_points'] ?? 'N/A' ?></span>
                                </div>
                                <?php if($assignment['due_date']): 
                                    $due_date = strtotime($assignment['due_date']);
                                    $is_overdue = $due_date < time();
                                    $days_left = ceil(($due_date - time()) / (60 * 60 * 24));
                                ?>
                                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                    <span class="text-gray-600">Due Date</span>
                                    <div class="text-right">
                                        <span class="font-semibold <?= $is_overdue ? 'text-red-600' : 'text-green-600' ?>">
                                            <?= date('M d, Y', $due_date) ?>
                                        </span>
                                        <div class="mt-1">
                                            <?php if($is_overdue): ?>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-800 due-date-badge">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                                    Overdue
                                                </span>
                                            <?php elseif($days_left <= 3): ?>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-yellow-100 text-yellow-800 due-date-badge">
                                                    <i class="fas fa-clock mr-1"></i>
                                                    <?= $days_left ?> day<?= $days_left !== 1 ? 's' : '' ?> left
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 due-date-badge">
                                                    <i class="far fa-clock mr-1"></i>
                                                    <?= $days_left ?> days left
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if($assignment['description']): ?>
                                <div class="pt-2">
                                    <div class="text-gray-600 mb-2">Description</div>
                                    <div class="bg-white p-4 rounded-lg border border-gray-200 text-gray-700">
                                        <?= nl2br(htmlspecialchars($assignment['description'])) ?>
                                    </div>
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
                        <div class="bg-gradient-to-r from-green-50 to-emerald-50 p-6 rounded-2xl border border-green-200">
                            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                                <i class="fas fa-cloud-upload-alt text-green-600 mr-3"></i>
                                Upload Your Assignment
                            </h3>

                            <form method="POST" enctype="multipart/form-data" id="uploadForm" class="space-y-6">
                                <!-- File Upload Area -->
                                <div id="fileUploadArea" class="file-upload-area p-8 text-center cursor-pointer transition-all duration-300">
                                    <input type="file" name="submission_file" id="submission_file" class="hidden" required>
                                    
                                    <div id="uploadIcon" class="mx-auto mb-4">
                                        <div class="w-20 h-20 bg-gradient-to-r from-green-100 to-emerald-100 rounded-full flex items-center justify-center mx-auto pulse-animation">
                                            <i class="fas fa-cloud-upload-alt text-green-600 text-3xl"></i>
                                        </div>
                                    </div>
                                    
                                    <div id="uploadText" class="space-y-2">
                                        <p class="text-lg font-semibold text-gray-700">Drag & drop your assignment file</p>
                                        <p class="text-gray-500">or click to browse</p>
                                        <p class="text-sm text-gray-400 mt-4">Supported formats: PDF, DOC, DOCX, TXT, PPT, XLS, JPG, PNG, ZIP</p>
                                        <p class="text-sm text-gray-400">Max file size: 10MB</p>
                                    </div>
                                    
                                    <div id="filePreview" class="hidden mt-6">
                                        <div class="bg-white p-4 rounded-xl border border-green-200 shadow-sm">
                                            <div class="flex items-center">
                                                <div class="file-icon bg-gradient-to-r from-green-100 to-emerald-200">
                                                    <i id="fileIcon" class="fas fa-file text-green-600 text-xl"></i>
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
                                        <span id="progressPercent" class="font-bold text-green-600">0%</span>
                                    </div>
                                    <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                                        <div id="progressBar" class="h-full bg-gradient-to-r from-green-500 to-emerald-500 rounded-full transition-all duration-300" style="width: 0%"></div>
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <div class="pt-4">
                                    <button type="submit" id="submitBtn" class="btn-glow w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white px-6 py-4 rounded-xl font-semibold text-lg hover:from-green-700 hover:to-emerald-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                                        <i class="fas fa-paper-plane mr-3"></i>
                                        <?= $existing_submission ? 'Resubmit Assignment' : 'Submit Assignment' ?>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Important Notes -->
                        <div class="bg-gradient-to-r from-amber-50 to-yellow-50 p-6 rounded-2xl border border-amber-200">
                            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                                <i class="fas fa-exclamation-triangle text-amber-600 mr-3"></i>
                                Important Notes
                            </h3>
                            <ul class="space-y-3">
                                <li class="flex items-start">
                                    <i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i>
                                    <span class="text-gray-700">Submit before the due date to avoid penalties</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i>
                                    <span class="text-gray-700">Include your name and student ID in the filename</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i>
                                    <span class="text-gray-700">Make sure all requirements are met before submission</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-check-circle text-green-500 mt-1 mr-3"></i>
                                    <span class="text-gray-700">Late submissions may receive reduced marks</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Quick Stats -->
                        <div class="bg-gradient-to-r from-blue-50 to-cyan-50 p-6 rounded-2xl border border-blue-200">
                            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                                <i class="fas fa-chart-bar text-blue-600 mr-3"></i>
                                Submission Stats
                            </h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="text-center p-4 bg-white rounded-xl border border-blue-100">
                                    <div class="text-2xl font-bold text-blue-600"><?= $assignment['total_points'] ?? '0' ?></div>
                                    <div class="text-sm text-gray-600 mt-1">Max Points</div>
                                </div>
                                <div class="text-center p-4 bg-white rounded-xl border border-blue-100">
                                    <div class="text-2xl font-bold text-green-600"><?= $existing_submission ? 'Submitted' : 'Pending' ?></div>
                                    <div class="text-sm text-gray-600 mt-1">Status</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mt-8 pt-6 border-t border-gray-200 flex flex-col sm:flex-row justify-between items-center space-y-4 sm:space-y-0">
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
            'ppt': 'fa-file-powerpoint',
            'pptx': 'fa-file-powerpoint',
            'xls': 'fa-file-excel',
            'xlsx': 'fa-file-excel',
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
            const allowedExts = ['pdf', 'doc', 'docx', 'txt', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip', 'rar'];
            
            if (!allowedExts.includes(fileExt)) {
                alert('File type not allowed. Please upload a valid file.');
                return;
            }

            // Update UI
            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            fileIcon.className = 'fas ' + (fileIcons[fileExt] || 'fa-file') + ' text-green-600 text-xl';
            
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
            
            // Simulate progress
            let progress = 0;
            const interval = setInterval(() => {
                progress += Math.random() * 20;
                if (progress > 90) {
                    clearInterval(interval);
                    progress = 90;
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
            
            // Add visual feedback for due date
            const dueDateBadge = document.querySelector('.due-date-badge');
            if (dueDateBadge && dueDateBadge.textContent.includes('Overdue')) {
                dueDateBadge.classList.add('animate-pulse');
            }
        });
    </script>
</body>
</html>