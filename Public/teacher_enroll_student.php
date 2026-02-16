<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_role(2);

// Fetch teacher's subjects
$subjects = $pdo->prepare("SELECT id, name FROM subjects WHERE teacher_id=?");
$subjects->execute([$_SESSION['user_id']]);
$subjects = $subjects->fetchAll();

// Fetch students
$students = $pdo->query("SELECT id, username FROM users WHERE role_id=3")->fetchAll();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $subject_id = $_POST['subject_id'];
    $student_id = $_POST['student_id'];
    $stmt = $pdo->prepare("INSERT INTO student_subject (student_id, subject_id, status) VALUES (?, ?, 'approved')");
    $stmt->execute([$student_id, $subject_id]);
    $success = "Student enrolled successfully.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Enroll Student</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
<div class="w-full max-w-md bg-white p-6 rounded-xl shadow-lg">
<h2 class="text-xl font-bold mb-4">Enroll Student to Subject</h2>
<form method="POST" class="space-y-4">
<div>
<label class="block text-sm text-gray-700">Select Subject</label>
<select name="subject_id" required class="w-full px-3 py-2 border rounded-lg">
<?php foreach ($subjects as $s): ?>
<option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
<?php endforeach; ?>
</select>
</div>
<div>
<label class="block text-sm text-gray-700">Select Student</label>
<select name="student_id" required class="w-full px-3 py-2 border rounded-lg">
<?php foreach ($students as $st): ?>
<option value="<?= $st['id'] ?>"><?= htmlspecialchars($st['username']) ?></option>
<?php endforeach; ?>
</select>
</div>
<button type="submit" class="w-full bg-gray-900 text-white py-2 rounded-lg hover:bg-gray-700">Enroll</button>
<?php if(!empty($success)) echo "<p class='text-green-600 text-sm mt-2'>$success</p>"; ?>
</form>
</div>
</body>
</html>
