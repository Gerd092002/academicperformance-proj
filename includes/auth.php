<?php
session_start();
require_once __DIR__ . '/db.php'; // Add this to access database

function login_user($user) {
    session_regenerate_id(true);
    $_SESSION['user'] = $user;
}

function logout_user() {
    $_SESSION = [];
    session_destroy();
}

function check_login() {
    global $pdo; // Access the database connection
    
    if (!isset($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }
    
    // Check if full_name exists in session, if not fetch from database
    if (!isset($_SESSION['user']['full_name']) || empty($_SESSION['user']['full_name'])) {
        $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user']['id']]);
        $user_data = $stmt->fetch();
        
        if ($user_data) {
            // Update session with missing data
            $_SESSION['user']['full_name'] = $user_data['full_name'];
            $_SESSION['user']['email'] = $user_data['email'];
        }
    }
}
?>