<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_role(2); // Teacher only

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject_name = trim($_POST['subject_name']);
    if ($subject_name) {
        $stmt = $pdo->prepare("INSERT INTO subjects (name, teacher_id) VALUES (?, ?)");
        $stmt->execute([$subject_name, $_SESSION['user_id']]);
        $success = "Subject added successfully.";
    } else {
        $error = "Subject name cannot be empty.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Subject</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
<div class="w-full max-w-md bg-white p-6 rounded-xl shadow-lg">
<h2 class="text-xl font-bold mb-4">Add Subject</h2>

<form method="POST" class="space-y-4">
<div>
<label class="block text-sm text-gray-700">Subject Name</label>
<input type="text" name="subject_name" required class="w-full px-3 py-2 border rounded-lg">
</div>
<button type="submit" class="w-full bg-gray-900 text-white py-2 rounded-lg hover:bg-gray-700">Add Subject</button>
<?php if(!empty($error)) echo "<p class='text-red-500 text-sm mt-2'>$error</p>"; ?>
<?php if(!empty($success)) echo "<p class='text-green-600 text-sm mt-2'>$success</p>"; ?>
</form>
</div>
</body>
</html>
