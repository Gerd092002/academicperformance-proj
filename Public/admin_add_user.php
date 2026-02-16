<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_role(1); // Admin only

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role_id = $_POST['role_id'];

    if ($username && $email && $password && $role_id) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $email, $hash, $role_id]);
        $success = "Account created successfully.";
    } else {
        $error = "All fields are required.";
    }
}

$roles = $pdo->query("SELECT id, role_name FROM roles WHERE id IN (2,3)")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add User</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
<div class="w-full max-w-md bg-white p-6 rounded-xl shadow-lg">
<h2 class="text-xl font-bold mb-4">Add Teacher or Student</h2>

<form method="POST" class="space-y-4">
<div>
<label class="block text-sm text-gray-700">Username</label>
<input type="text" name="username" required class="w-full px-3 py-2 border rounded-lg">
</div>
<div>
<label class="block text-sm text-gray-700">Email</label>
<input type="email" name="email" required class="w-full px-3 py-2 border rounded-lg">
</div>
<div>
<label class="block text-sm text-gray-700">Password</label>
<input type="password" name="password" required class="w-full px-3 py-2 border rounded-lg">
</div>
<div>
<label class="block text-sm text-gray-700">Role</label>
<select name="role_id" required class="w-full px-3 py-2 border rounded-lg">
<?php foreach ($roles as $r): ?>
<option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['role_name']) ?></option>
<?php endforeach; ?>
</select>
</div>
<button type="submit" class="w-full bg-gray-900 text-white py-2 rounded-lg hover:bg-gray-700">Add Account</button>
<?php if(!empty($error)) echo "<p class='text-red-500 text-sm mt-2'>$error</p>"; ?>
<?php if(!empty($success)) echo "<p class='text-green-600 text-sm mt-2'>$success</p>"; ?>
</form>
</div>
</body>
</html>
    