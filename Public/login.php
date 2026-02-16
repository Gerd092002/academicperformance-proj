<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT id, username, full_name, email, password_hash, role_id FROM users WHERE username = ? OR email = ? LIMIT 1");
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        login_user($user);

        // Redirect based on role
        switch ($user['role_id']) {
            case 1: // Admin
                header('Location: dashboard.php');
                break;
            case 2: // Teacher
                header('Location: teacher_dashboard.php');
                break;
            case 3: // Student
                header('Location: student_dashboard.php');
                break;
        }
        exit;
    } else {
        $error = "Invalid username/email or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – Student Performance Tracker</title>
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
            overflow-x: hidden;
        }
        
        .login-container {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .floating-element {
            animation: float 6s ease-in-out infinite;
        }
        
        .floating-element-2 {
            animation: float 8s ease-in-out infinite;
            animation-delay: 1s;
        }
        
        .floating-element-3 {
            animation: float 7s ease-in-out infinite;
            animation-delay: 2s;
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
        
        .role-icon {
            transition: all 0.3s ease;
        }
        
        .role-icon:hover {
            transform: scale(1.1);
            filter: drop-shadow(0 5px 5px rgba(0,0,0,0.2));
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">
    <!-- Background decorative elements -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-20 -left-20 w-64 h-64 bg-white rounded-full opacity-10 floating-element"></div>
        <div class="absolute top-1/3 -right-16 w-48 h-48 bg-purple-300 rounded-full opacity-10 floating-element-2"></div>
        <div class="absolute bottom-1/4 left-1/4 w-32 h-32 bg-blue-300 rounded-full opacity-10 floating-element-3"></div>
        <div class="absolute bottom-20 right-20 w-40 h-40 bg-indigo-200 rounded-full opacity-10 floating-element"></div>
    </div>
    
    <div class="login-container w-full max-w-md rounded-2xl shadow-2xl overflow-hidden slide-in">
        <div class="bg-gradient-to-r from-indigo-600 to-purple-700 p-8 text-center">
            <div class="flex justify-center mb-4">
                <div class="relative">
                    <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center">
                        <i class="fas fa-graduation-cap text-indigo-600 text-3xl"></i>
                    </div>
                    <div class="absolute -top-1 -right-1 w-6 h-6 bg-yellow-400 rounded-full flex items-center justify-center">
                        <i class="fas fa-chart-line text-xs text-gray-800"></i>
                    </div>
                </div>
            </div>
            <h1 class="text-3xl font-bold text-white mb-2">Student Performance Tracker</h1>
            <p class="text-indigo-200">Login to access your dashboard</p>
        </div>
        
        <div class="bg-white p-8">
            <?php if (!empty($error)): ?>
                <div id="error-message" class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-r-lg flex items-start shake">
                    <i class="fas fa-exclamation-circle text-red-500 text-lg mt-0.5 mr-3"></i>
                    <div>
                        <p class="font-medium">Login Failed</p>
                        <p class="text-sm"><?= htmlspecialchars($error) ?></p>
                    </div>
                    <button onclick="document.getElementById('error-message').remove()" class="ml-auto text-red-400 hover:text-red-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-6" id="loginForm">
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">
                        <i class="fas fa-user mr-2 text-indigo-500"></i>
                        Username or Email
                    </label>
                    <div class="relative">
                        <input 
                            type="text" 
                            name="identifier" 
                            required 
                            placeholder="Enter your username or email" 
                            class="input-focus-effect w-full px-4 py-3 pl-11 border border-gray-300 rounded-xl focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none transition-all"
                            id="identifierInput"
                            autocomplete="username"
                        >
                        <div class="absolute left-3 top-3.5 text-gray-400">
                            <i class="fas fa-at"></i>
                        </div>
                        <div class="absolute right-3 top-3.5">
                            <i id="identifierValidIcon" class="fas fa-check text-green-500 hidden"></i>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">You can use either your username or email address</p>
                </div>
                
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">
                        <i class="fas fa-lock mr-2 text-indigo-500"></i>
                        Password
                    </label>
                    <div class="relative">
                        <input 
                            type="password" 
                            name="password" 
                            required 
                            placeholder="Enter your password" 
                            class="input-focus-effect w-full px-4 py-3 pl-11 border border-gray-300 rounded-xl focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none transition-all"
                            id="passwordInput"
                            autocomplete="current-password"
                        >
                        <div class="absolute left-3 top-3.5 text-gray-400">
                            <i class="fas fa-key"></i>
                        </div>
                        <div class="absolute right-3 top-3.5 password-toggle" id="togglePassword">
                            <i class="fas fa-eye text-gray-400"></i>
                        </div>
                    </div>
                    <div class="flex justify-between items-center">
                        <div class="flex items-center">
                            <input type="checkbox" id="rememberMe" name="remember" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                            <label for="rememberMe" class="ml-2 text-sm text-gray-700">Remember me</label>
                        </div>
                        <a href="forgot_password.php" class="text-sm text-indigo-600 hover:text-indigo-800 hover:underline">Forgot password?</a>
                    </div>
                </div>
                
                <button 
                    type="submit" 
                    class="btn-glow w-full bg-gradient-to-r from-indigo-600 to-purple-600 text-white py-3.5 rounded-xl font-semibold text-lg transition-all relative"
                    id="loginButton"
                >
                    <span id="buttonText">Login</span>
                    <div id="buttonSpinner" class="hidden absolute right-4 top-3.5">
                        <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-white"></div>
                    </div>
                </button>
                
                <!-- Demo credentials info -->
                
            </form>
            
            
                
                <div class="text-center">
                    
                    <p class="mt-6 text-xs text-gray-500">
                        &copy; <?= date("Y") ?> Student Performance Tracking System
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Loading overlay (hidden by default) -->
    <div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center hidden z-50">
        <div class="text-center">
            <div class="w-16 h-16 border-4 border-white border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
            <p class="text-white text-xl font-semibold">Logging you in...</p>
            <p class="text-gray-300 mt-2">Redirecting to your dashboard</p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            const loginButton = document.getElementById('loginButton');
            const buttonText = document.getElementById('buttonText');
            const buttonSpinner = document.getElementById('buttonSpinner');
            const identifierInput = document.getElementById('identifierInput');
            const passwordInput = document.getElementById('passwordInput');
            const togglePassword = document.getElementById('togglePassword');
            const passwordIcon = togglePassword.querySelector('i');
            const identifierValidIcon = document.getElementById('identifierValidIcon');
            const loadingOverlay = document.getElementById('loadingOverlay');
            
            // Form validation on input
            identifierInput.addEventListener('input', function() {
                const value = this.value.trim();
                if (value.length > 2 && (value.includes('@') || value.length >= 3)) {
                    identifierValidIcon.classList.remove('hidden');
                    identifierInput.classList.remove('border-red-300');
                    identifierInput.classList.add('border-green-300');
                } else {
                    identifierValidIcon.classList.add('hidden');
                    identifierInput.classList.remove('border-green-300');
                }
            });
            
            // Toggle password visibility
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                passwordIcon.classList.toggle('fa-eye');
                passwordIcon.classList.toggle('fa-eye-slash');
                passwordIcon.classList.toggle('text-indigo-500');
                
                // Animate the icon
                this.style.transform = 'scale(1.2)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 150);
            });
            
            // Add pulsing animation to login button every 10 seconds
            setInterval(() => {
                loginButton.classList.add('pulse');
                setTimeout(() => {
                    loginButton.classList.remove('pulse');
                }, 2000);
            }, 10000);
            
            // Form submission with loading state
            form.addEventListener('submit', function(e) {
                // Prevent default only if validation fails
                if (!identifierInput.value.trim() || !passwordInput.value) {
                    e.preventDefault();
                    
                    // Shake animation for empty fields
                    if (!identifierInput.value.trim()) {
                        identifierInput.classList.add('border-red-500');
                        identifierInput.classList.add('shake');
                        setTimeout(() => {
                            identifierInput.classList.remove('shake');
                        }, 500);
                    }
                    
                    if (!passwordInput.value) {
                        passwordInput.classList.add('border-red-500');
                        passwordInput.classList.add('shake');
                        setTimeout(() => {
                            passwordInput.classList.remove('shake');
                        }, 500);
                    }
                    
                    return;
                }
                
                // Show loading state
                buttonText.textContent = 'Logging in...';
                buttonSpinner.classList.remove('hidden');
                loginButton.disabled = true;
                loginButton.style.opacity = '0.8';
                loginButton.style.cursor = 'not-allowed';
                
                // Show loading overlay after a short delay
                setTimeout(() => {
                    loadingOverlay.classList.remove('hidden');
                }, 800);
                
                // Form will submit normally, PHP will handle redirect
            });
            
            // Input focus effects
            const inputs = [identifierInput, passwordInput];
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('ring-2', 'ring-indigo-200');
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('ring-2', 'ring-indigo-200');
                });
            });
            
            // Enter key to submit
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !loginButton.disabled) {
                    loginButton.click();
                }
            });
        });
        
        // Function to fill demo credentials
        function fillCredentials(username, password) {
            const identifierInput = document.getElementById('identifierInput');
            const passwordInput = document.getElementById('passwordInput');
            
            // Animate inputs
            identifierInput.value = username;
            identifierInput.classList.add('bg-green-50', 'border-green-400');
            identifierInput.dispatchEvent(new Event('input'));
            
            passwordInput.value = password;
            passwordInput.classList.add('bg-green-50', 'border-green-400');
            
            // Animate button
            const loginButton = document.getElementById('loginButton');
            loginButton.classList.add('bg-gradient-to-r', 'from-green-600', 'to-green-500');
            
            // Show success message
            const buttonText = document.getElementById('buttonText');
            const originalText = buttonText.textContent;
            buttonText.textContent = `Logging in as ${username}...`;
            
            // Reset after 2 seconds
            setTimeout(() => {
                identifierInput.classList.remove('bg-green-50', 'border-green-400');
                passwordInput.classList.remove('bg-green-50', 'border-green-400');
                loginButton.classList.remove('bg-gradient-to-r', 'from-green-600', 'to-green-500');
                buttonText.textContent = originalText;
                
                // Submit the form after a short delay
                setTimeout(() => {
                    document.getElementById('loginForm').submit();
                }, 500);
            }, 2000);
        }
        
        // Add some floating particles effect
        function createFloatingParticles() {
            const colors = ['rgba(102, 126, 234, 0.2)', 'rgba(118, 75, 162, 0.2)', 'rgba(255, 255, 255, 0.1)'];
            
            for (let i = 0; i < 15; i++) {
                const particle = document.createElement('div');
                const size = Math.random() * 20 + 5;
                const color = colors[Math.floor(Math.random() * colors.length)];
                
                particle.style.position = 'fixed';
                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;
                particle.style.backgroundColor = color;
                particle.style.borderRadius = '50%';
                particle.style.left = `${Math.random() * 100}vw`;
                particle.style.top = `${Math.random() * 100}vh`;
                particle.style.pointerEvents = 'none';
                particle.style.zIndex = '1';
                
                // Animation
                particle.animate([
                    { transform: 'translateY(0px) rotate(0deg)', opacity: 0.7 },
                    { transform: `translateY(${-window.innerHeight}px) rotate(${Math.random() * 360}deg)`, opacity: 0 }
                ], {
                    duration: Math.random() * 3000 + 4000,
                    delay: Math.random() * 5000
                });
                
                document.body.appendChild(particle);
                
                // Remove after animation
                setTimeout(() => {
                    particle.remove();
                }, 10000);
            }
        }
        
        // Create particles periodically
        setInterval(createFloatingParticles, 3000);
        createFloatingParticles(); // Initial particles
    </script>
</body>
</html>