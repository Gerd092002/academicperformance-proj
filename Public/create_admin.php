<?php
require_once __DIR__ . '/../includes/db.php';

$username = "admin";
$full_name = "Administrator";
$email = "admin@example.com";
$password = "admin123!";
$role_id = 1; // Admin

$password_hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO users (username, full_name, email, password_hash, role_id) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([$username, $full_name, $email, $password_hash, $role_id]);

echo "Admin account created successfully!";
