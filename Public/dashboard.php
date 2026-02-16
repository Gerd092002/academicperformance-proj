<?php
require_once __DIR__ . '/../includes/auth.php';
check_login();

$role_id = $_SESSION['user']['role_id'];
switch($role_id) {
    case 1: // Admin
        header('Location: admin_dashboard.php'); break;
    case 2: // Teacher
        header('Location: teacher_dashboard.php'); break;
    case 3: // Student
        header('Location: student_dashboard.php'); break;
    default:
        logout_user(); exit;
}
?>
