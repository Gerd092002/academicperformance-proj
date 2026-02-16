<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
check_login();

// Ensure only admin can access
if ($_SESSION['user']['role_id'] != 1) {
    header('Location: login.php');
    exit;
}

// Get role from URL
$role = $_GET['role'] ?? 3; // default student
$role_name = $role == 2 ? 'Teacher' : 'Student';
$role_color = $role == 2 ? 'from-blue-600 to-cyan-600' : 'from-green-600 to-emerald-600';
$role_icon = $role == 2 ? 'fa-chalkboard-teacher' : 'fa-user-graduate';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Basic validations
    if (empty($username) || empty($email) || empty($password)) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Check if username or email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$username, $email]);

        if ($stmt->fetch()) {
            $error = "Username or email already taken.";
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $insert = $pdo->prepare("INSERT INTO users (username, email, password_hash, role_id) VALUES (?, ?, ?, ?)");
            $insert->execute([$username, $email, $hash, $role]);
            $success = "$role_name account created successfully!";
            $success_id = $pdo->lastInsertId();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register <?= $role_name ?> - Admin Panel</title>
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
        
        .form-container {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        
        .floating-element {
            animation: float 6s ease-in-out infinite;
        }
        
        .floating-element-2 {
            animation: float 8s ease-in-out infinite;
            animation-delay: 1s;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
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
        
        .btn-glow:after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 5px;
            height: 5px;
            background: rgba(255, 255, 255, 0.5);
            opacity: 0;
            border-radius: 100%;
            transform: scale(1, 1) translate(-50%);
            transform-origin: 50% 50%;
        }
        
        .btn-glow:focus:not(:active)::after {
            animation: ripple 1s ease-out;
        }
        
        @keyframes ripple {
            0% {
                transform: scale(0, 0);
                opacity: 1;
            }
            100% {
                transform: scale(40, 40);
                opacity: 0;
            }
        }
        
        .password-toggle {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .password-toggle:hover {
            transform: scale(1.1);
        }
        
        .shake {
            animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both;
        }
        
        @keyframes shake {
            10%, 90% { transform: translate3d(-1px, 0, 0); }
            20%, 80% { transform: translate3d(2px, 0, 0); }
            30%, 50%, 70% { transform: translate3d(-3px, 0, 0); }
            40%, 60% { transform: translate3d(3px, 0, 0); }
        }
        
        .slide-in {
            animation: slideIn 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55) forwards;
        }
        
        @keyframes slideIn {
            0% { opacity: 0; transform: translateY(30px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(102, 126, 234, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(102, 126, 234, 0); }
            100% { box-shadow: 0 0 0 0 rgba(102, 126, 234, 0); }
        }
    </style>
</head>
<body class="p-4">
    <!-- Background decorative elements -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-20 -left-20 w-64 h-64 bg-white rounded-full opacity-10 floating-element"></div>
        <div class="absolute top-1/3 -right-16 w-48 h-48 bg-purple-300 rounded-full opacity-10 floating-element-2"></div>
        <div class="absolute bottom-1/4 left-1/4 w-32 h-32 bg-blue-300 rounded-full opacity-10 floating-element"></div>
        <div class="absolute bottom-20 right-20 w-40 h-40 bg-indigo-200 rounded-full opacity-10 floating-element-2"></div>
    </div>
    
    <!-- Back Button -->
    <div class="fixed top-6 left-6 z-10">
        <a href="admin_dashboard.php" class="inline-flex items-center text-white hover:text-gray-200 group transition">
            <div class="bg-white/20 p-2 rounded-full group-hover:bg-white/30 transition mr-3">
                <i class="fas fa-arrow-left text-white"></i>
            </div>
            <span class="font-medium">Back to Dashboard</span>
        </a>
    </div>
    
    <div class="form-container w-full max-w-xl mx-auto rounded-3xl shadow-2xl overflow-hidden slide-in">
        <!-- Header -->
        <div class="bg-gradient-to-r <?= $role_color ?> p-8 text-center">
            <div class="flex justify-center mb-4">
                <div class="relative">
                    <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center">
                        <i class="fas <?= $role_icon ?> text-3xl <?= $role == 2 ? 'text-blue-600' : 'text-green-600' ?>"></i>
                    </div>
                    <div class="absolute -top-1 -right-1 w-8 h-8 bg-white rounded-full flex items-center justify-center">
                        <i class="fas fa-user-plus <?= $role == 2 ? 'text-blue-600' : 'text-green-600' ?> text-sm"></i>
                    </div>
                </div>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">Create <?= $role_name ?> Account</h1>
            <p class="text-white/90">Add a new <?= strtolower($role_name) ?> to the system</p>
        </div>
        
        <!-- Success Message -->
        <?php if(isset($success) && $success): ?>
        <div id="successMessage" class="mx-6 mt-6 p-4 rounded-xl border-l-4 border-green-500 bg-gradient-to-r from-green-50 to-green-100 text-green-800 flex justify-between items-center slide-in shadow-lg">
            <div class="flex items-center space-x-3">
                <div class="bg-green-500 p-2 rounded-full">
                    <i class="fas fa-check-circle text-white"></i>
                </div>
                <div>
                    <p class="font-semibold">Success!</p>
                    <p><?= htmlspecialchars($success) ?></p>
                    <?php if(isset($success_id)): ?>
                    <p class="text-sm mt-1">Account ID: <span class="font-bold">#<?= $success_id ?></span></p>
                    <?php endif; ?>
                </div>
            </div>
            <button onclick="document.getElementById('successMessage').remove()" class="text-green-600 hover:text-green-800 transition">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        <?php endif; ?>
        
        <!-- Error Message -->
        <?php if(isset($error) && $error): ?>
        <div id="errorMessage" class="mx-6 mt-6 p-4 rounded-xl border-l-4 border-red-500 bg-gradient-to-r from-red-50 to-red-100 text-red-800 flex justify-between items-center slide-in shadow-lg shake">
            <div class="flex items-center space-x-3">
                <div class="bg-red-500 p-2 rounded-full">
                    <i class="fas fa-exclamation-circle text-white"></i>
                </div>
                <div>
                    <p class="font-semibold">Registration Failed</p>
                    <p><?= htmlspecialchars($error) ?></p>
                </div>
            </div>
            <button onclick="document.getElementById('errorMessage').remove()" class="text-red-600 hover:text-red-800 transition">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        <?php endif; ?>
        
        <div class="p-8">
            <form method="POST" class="space-y-6" id="registerForm">
                <!-- Username Field -->
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700 flex items-center">
                        <i class="fas fa-user <?= $role == 2 ? 'text-blue-500' : 'text-green-500' ?> mr-2"></i>
                        Username
                    </label>
                    <div class="relative">
                        <input 
                            type="text" 
                            name="username" 
                            required 
                            placeholder="Enter username" 
                            class="input-focus-effect w-full px-4 py-3 pl-11 border border-gray-300 rounded-xl focus:border-<?= $role == 2 ? 'blue' : 'green' ?>-500 focus:ring-2 focus:ring-<?= $role == 2 ? 'blue' : 'green' ?>-200 focus:outline-none transition-all"
                            id="usernameInput"
                            autocomplete="off"
                            minlength="3"
                            maxlength="50"
                        >
                        <div class="absolute left-3 top-3.5 text-gray-400">
                            <i class="fas fa-at"></i>
                        </div>
                        <div class="absolute right-3 top-3.5">
                            <i id="usernameValidIcon" class="fas fa-check text-green-500 hidden"></i>
                            <i id="usernameErrorIcon" class="fas fa-times text-red-500 hidden"></i>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">3-50 characters, letters and numbers only</p>
                </div>
                
                <!-- Email Field -->
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700 flex items-center">
                        <i class="fas fa-envelope <?= $role == 2 ? 'text-blue-500' : 'text-green-500' ?> mr-2"></i>
                        Email Address
                    </label>
                    <div class="relative">
                        <input 
                            type="email" 
                            name="email" 
                            required 
                            placeholder="Enter email address" 
                            class="input-focus-effect w-full px-4 py-3 pl-11 border border-gray-300 rounded-xl focus:border-<?= $role == 2 ? 'blue' : 'green' ?>-500 focus:ring-2 focus:ring-<?= $role == 2 ? 'blue' : 'green' ?>-200 focus:outline-none transition-all"
                            id="emailInput"
                            autocomplete="email"
                        >
                        <div class="absolute left-3 top-3.5 text-gray-400">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="absolute right-3 top-3.5">
                            <i id="emailValidIcon" class="fas fa-check text-green-500 hidden"></i>
                            <i id="emailErrorIcon" class="fas fa-times text-red-500 hidden"></i>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Must be a valid email address</p>
                </div>
                
                <!-- Password Field -->
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700 flex items-center">
                        <i class="fas fa-lock <?= $role == 2 ? 'text-blue-500' : 'text-green-500' ?> mr-2"></i>
                        Password
                    </label>
                    <div class="relative">
                        <input 
                            type="password" 
                            name="password" 
                            required 
                            placeholder="Enter password" 
                            class="input-focus-effect w-full px-4 py-3 pl-11 border border-gray-300 rounded-xl focus:border-<?= $role == 2 ? 'blue' : 'green' ?>-500 focus:ring-2 focus:ring-<?= $role == 2 ? 'blue' : 'green' ?>-200 focus:outline-none transition-all"
                            id="passwordInput"
                            autocomplete="new-password"
                            minlength="6"
                        >
                        <div class="absolute left-3 top-3.5 text-gray-400">
                            <i class="fas fa-key"></i>
                        </div>
                        <div class="absolute right-3 top-3.5 password-toggle" id="togglePassword">
                            <i class="fas fa-eye text-gray-400"></i>
                        </div>
                    </div>
                    <div class="mt-2">
                        <div class="text-xs text-gray-600 mb-1">Password strength:</div>
                        <div class="flex items-center space-x-2">
                            <div class="h-1 flex-1 bg-gray-200 rounded-full overflow-hidden">
                                <div id="passwordStrength" class="h-full bg-red-500 w-0 transition-all duration-300"></div>
                            </div>
                            <span id="strengthText" class="text-xs font-medium text-red-500">Very weak</span>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500">Minimum 6 characters, include letters and numbers</p>
                </div>
                
                <!-- Confirm Password Field -->
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700 flex items-center">
                        <i class="fas fa-lock <?= $role == 2 ? 'text-blue-500' : 'text-green-500' ?> mr-2"></i>
                        Confirm Password
                    </label>
                    <div class="relative">
                        <input 
                            type="password" 
                            name="confirm_password" 
                            required 
                            placeholder="Confirm password" 
                            class="input-focus-effect w-full px-4 py-3 pl-11 border border-gray-300 rounded-xl focus:border-<?= $role == 2 ? 'blue' : 'green' ?>-500 focus:ring-2 focus:ring-<?= $role == 2 ? 'blue' : 'green' ?>-200 focus:outline-none transition-all"
                            id="confirmPasswordInput"
                            autocomplete="new-password"
                            minlength="6"
                        >
                        <div class="absolute left-3 top-3.5 text-gray-400">
                            <i class="fas fa-key"></i>
                        </div>
                        <div class="absolute right-3 top-3.5 password-toggle" id="toggleConfirmPassword">
                            <i class="fas fa-eye text-gray-400"></i>
                        </div>
                    </div>
                    <div class="flex items-center mt-2" id="passwordMatch">
                        <i class="fas fa-times text-red-500 mr-2 hidden" id="passwordMismatchIcon"></i>
                        <i class="fas fa-check text-green-500 mr-2 hidden" id="passwordMatchIcon"></i>
                        <span class="text-xs text-gray-600" id="matchText">Passwords must match</span>
                    </div>
                </div>
                
                <!-- Account Type Info -->
                <div class="p-4 bg-gradient-to-r <?= $role == 2 ? 'from-blue-50 to-cyan-50' : 'from-green-50 to-emerald-50' ?> rounded-xl border <?= $role == 2 ? 'border-blue-200' : 'border-green-200' ?>">
                    <div class="flex items-center">
                        <div class="mr-3">
                            <i class="fas <?= $role_icon ?> text-xl <?= $role == 2 ? 'text-blue-600' : 'text-green-600' ?>"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800">Creating <?= $role_name ?> Account</p>
                            <p class="text-sm text-gray-600">
                                <?= $role == 2 
                                    ? 'Teachers can create subjects, manage students, and grade assignments.' 
                                    : 'Students can enroll in subjects, submit assignments, and view grades.' 
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Submit Button -->
                <button 
                    type="submit" 
                    class="btn-glow w-full bg-gradient-to-r <?= $role_color ?> text-white py-3.5 rounded-xl font-semibold text-lg transition-all relative"
                    id="registerButton"
                >
                    <span id="buttonText">Create <?= $role_name ?> Account</span>
                    <div id="buttonSpinner" class="hidden absolute right-4 top-3.5">
                        <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-white"></div>
                    </div>
                </button>
                
                <!-- Additional Options -->
                <div class="text-center">
                    <p class="text-gray-600 text-sm">
                        Create a different account type:
                        <a href="?role=2" class="font-semibold text-blue-600 hover:text-blue-800 transition <?= $role == 2 ? 'hidden' : '' ?>">
                            Teacher
                        </a>
                        <span class="<?= $role == 2 ? '' : 'hidden' ?>">Teacher</span>
                        <span class="mx-2">|</span>
                        <a href="?role=3" class="font-semibold text-green-600 hover:text-green-800 transition <?= $role == 3 ? 'hidden' : '' ?>">
                            Student
                        </a>
                        <span class="<?= $role == 3 ? '' : 'hidden' ?>">Student</span>
                    </p>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registerForm');
            const registerButton = document.getElementById('registerButton');
            const buttonText = document.getElementById('buttonText');
            const buttonSpinner = document.getElementById('buttonSpinner');
            const usernameInput = document.getElementById('usernameInput');
            const emailInput = document.getElementById('emailInput');
            const passwordInput = document.getElementById('passwordInput');
            const confirmPasswordInput = document.getElementById('confirmPasswordInput');
            const togglePassword = document.getElementById('togglePassword');
            const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
            const passwordStrength = document.getElementById('passwordStrength');
            const strengthText = document.getElementById('strengthText');
            
            // Real-time validation flags
            let isUsernameValid = false;
            let isEmailValid = false;
            let isPasswordValid = false;
            let isPasswordMatch = false;
            
            // Username validation
            usernameInput.addEventListener('input', function() {
                const value = this.value.trim();
                const usernameValidIcon = document.getElementById('usernameValidIcon');
                const usernameErrorIcon = document.getElementById('usernameErrorIcon');
                
                if (value.length >= 3 && /^[a-zA-Z0-9_]+$/.test(value)) {
                    isUsernameValid = true;
                    usernameValidIcon.classList.remove('hidden');
                    usernameErrorIcon.classList.add('hidden');
                    this.classList.remove('border-red-300');
                    this.classList.add('border-green-300');
                } else {
                    isUsernameValid = false;
                    usernameValidIcon.classList.add('hidden');
                    usernameErrorIcon.classList.remove('hidden');
                    this.classList.remove('border-green-300');
                    this.classList.add('border-red-300');
                }
                updateRegisterButton();
            });
            
            // Email validation
            emailInput.addEventListener('input', function() {
                const value = this.value.trim();
                const emailValidIcon = document.getElementById('emailValidIcon');
                const emailErrorIcon = document.getElementById('emailErrorIcon');
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if (emailRegex.test(value)) {
                    isEmailValid = true;
                    emailValidIcon.classList.remove('hidden');
                    emailErrorIcon.classList.add('hidden');
                    this.classList.remove('border-red-300');
                    this.classList.add('border-green-300');
                } else {
                    isEmailValid = false;
                    emailValidIcon.classList.add('hidden');
                    emailErrorIcon.classList.remove('hidden');
                    this.classList.remove('border-green-300');
                    this.classList.add('border-red-300');
                }
                updateRegisterButton();
            });
            
            // Password strength calculation
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                
                // Length check
                if (password.length >= 6) strength += 25;
                if (password.length >= 8) strength += 15;
                
                // Complexity checks
                if (/[A-Z]/.test(password)) strength += 20;
                if (/[a-z]/.test(password)) strength += 20;
                if (/[0-9]/.test(password)) strength += 20;
                if (/[^A-Za-z0-9]/.test(password)) strength += 20;
                
                // Cap at 100
                strength = Math.min(strength, 100);
                
                // Update visual indicator
                passwordStrength.style.width = strength + '%';
                
                // Update text and color
                if (strength < 40) {
                    passwordStrength.style.backgroundColor = '#ef4444';
                    strengthText.textContent = 'Very weak';
                    strengthText.className = 'text-xs font-medium text-red-500';
                    isPasswordValid = false;
                } else if (strength < 60) {
                    passwordStrength.style.backgroundColor = '#f59e0b';
                    strengthText.textContent = 'Weak';
                    strengthText.className = 'text-xs font-medium text-yellow-500';
                    isPasswordValid = false;
                } else if (strength < 80) {
                    passwordStrength.style.backgroundColor = '#10b981';
                    strengthText.textContent = 'Good';
                    strengthText.className = 'text-xs font-medium text-green-500';
                    isPasswordValid = true;
                } else {
                    passwordStrength.style.backgroundColor = '#10b981';
                    strengthText.textContent = 'Strong';
                    strengthText.className = 'text-xs font-medium text-green-500';
                    isPasswordValid = true;
                }
                
                // Check password match
                checkPasswordMatch();
                updateRegisterButton();
            });
            
            // Password match check
            confirmPasswordInput.addEventListener('input', checkPasswordMatch);
            
            function checkPasswordMatch() {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                const passwordMismatchIcon = document.getElementById('passwordMismatchIcon');
                const passwordMatchIcon = document.getElementById('passwordMatchIcon');
                const matchText = document.getElementById('matchText');
                
                if (confirmPassword.length === 0) {
                    isPasswordMatch = false;
                    passwordMismatchIcon.classList.add('hidden');
                    passwordMatchIcon.classList.add('hidden');
                    matchText.textContent = 'Passwords must match';
                    matchText.className = 'text-xs text-gray-600';
                    confirmPasswordInput.classList.remove('border-red-300', 'border-green-300');
                } else if (password === confirmPassword && password.length >= 6) {
                    isPasswordMatch = true;
                    passwordMismatchIcon.classList.add('hidden');
                    passwordMatchIcon.classList.remove('hidden');
                    matchText.textContent = 'Passwords match';
                    matchText.className = 'text-xs text-green-600';
                    confirmPasswordInput.classList.remove('border-red-300');
                    confirmPasswordInput.classList.add('border-green-300');
                } else {
                    isPasswordMatch = false;
                    passwordMismatchIcon.classList.remove('hidden');
                    passwordMatchIcon.classList.add('hidden');
                    matchText.textContent = 'Passwords do not match';
                    matchText.className = 'text-xs text-red-600';
                    confirmPasswordInput.classList.remove('border-green-300');
                    confirmPasswordInput.classList.add('border-red-300');
                }
                updateRegisterButton();
            }
            
            // Toggle password visibility
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
                this.querySelector('i').classList.toggle('text-gray-400');
                this.querySelector('i').classList.toggle('<?= $role == 2 ? 'text-blue-500' : 'text-green-500' ?>');
                
                // Animate the icon
                this.style.transform = 'scale(1.2)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 150);
            });
            
            toggleConfirmPassword.addEventListener('click', function() {
                const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                confirmPasswordInput.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
                this.querySelector('i').classList.toggle('text-gray-400');
                this.querySelector('i').classList.toggle('<?= $role == 2 ? 'text-blue-500' : 'text-green-500' ?>');
                
                // Animate the icon
                this.style.transform = 'scale(1.2)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 150);
            });
            
            // Update register button state
            function updateRegisterButton() {
                const isValid = isUsernameValid && isEmailValid && isPasswordValid && isPasswordMatch;
                
                if (isValid) {
                    registerButton.disabled = false;
                    registerButton.style.opacity = '1';
                    registerButton.style.cursor = 'pointer';
                    registerButton.classList.add('pulse');
                } else {
                    registerButton.disabled = true;
                    registerButton.style.opacity = '0.7';
                    registerButton.style.cursor = 'not-allowed';
                    registerButton.classList.remove('pulse');
                }
            }
            
            // Form submission with loading state
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Validate all fields
                if (!isUsernameValid || !isEmailValid || !isPasswordValid || !isPasswordMatch) {
                    // Shake animation for invalid fields
                    if (!isUsernameValid) {
                        usernameInput.classList.add('shake');
                        setTimeout(() => usernameInput.classList.remove('shake'), 500);
                    }
                    if (!isEmailValid) {
                        emailInput.classList.add('shake');
                        setTimeout(() => emailInput.classList.remove('shake'), 500);
                    }
                    if (!isPasswordValid) {
                        passwordInput.classList.add('shake');
                        setTimeout(() => passwordInput.classList.remove('shake'), 500);
                    }
                    if (!isPasswordMatch) {
                        confirmPasswordInput.classList.add('shake');
                        setTimeout(() => confirmPasswordInput.classList.remove('shake'), 500);
                    }
                    return;
                }
                
                // Show loading state
                buttonText.textContent = 'Creating Account...';
                buttonSpinner.classList.remove('hidden');
                registerButton.disabled = true;
                registerButton.style.opacity = '0.8';
                registerButton.style.cursor = 'not-allowed';
                
                // Form will submit normally
                this.submit();
            });
            
            // Input focus effects
            const inputs = [usernameInput, emailInput, passwordInput, confirmPasswordInput];
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('ring-2', 'ring-<?= $role == 2 ? 'blue' : 'green' ?>-200');
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('ring-2', 'ring-<?= $role == 2 ? 'blue' : 'green' ?>-200');
                });
            });
            
            // Initial button state
            updateRegisterButton();
            
            // Add floating particles effect
            createFloatingParticles();
            setInterval(createFloatingParticles, 3000);
        });
        
        // Create floating particles
        function createFloatingParticles() {
            const colors = ['rgba(102, 126, 234, 0.1)', 'rgba(118, 75, 162, 0.1)', 'rgba(255, 255, 255, 0.05)'];
            
            for (let i = 0; i < 3; i++) {
                const particle = document.createElement('div');
                const size = Math.random() * 15 + 5;
                const color = colors[Math.floor(Math.random() * colors.length)];
                
                particle.style.position = 'fixed';
                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;
                particle.style.backgroundColor = color;
                particle.style.borderRadius = '50%';
                particle.style.left = `${Math.random() * 100}vw`;
                particle.style.top = `${100 + Math.random() * 20}vh`;
                particle.style.pointerEvents = 'none';
                particle.style.zIndex = '1';
                
                // Animation
                particle.animate([
                    { transform: 'translateY(0px) rotate(0deg)', opacity: 0.7 },
                    { transform: `translateY(${-window.innerHeight - 100}px) rotate(${Math.random() * 360}deg)`, opacity: 0 }
                ], {
                    duration: Math.random() * 5000 + 8000,
                    delay: Math.random() * 3000
                });
                
                document.body.appendChild(particle);
                
                // Remove after animation
                setTimeout(() => {
                    particle.remove();
                }, 15000);
            }
        }
    </script>
</body>
</html>