<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_role(3);

// Fetch all subjects not already requested/enrolled by student
$subjects = $pdo->prepare("
SELECT * FROM subjects WHERE id NOT IN 
(SELECT subject_id FROM student_subject WHERE student_id=?)
");
$subjects->execute([$_SESSION['user_id']]);
$subjects = $subjects->fetchAll();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $subject_id = $_POST['subject_id'];
    $stmt = $pdo->prepare("INSERT INTO student_subject (student_id, subject_id, status) VALUES (?, ?, 'pending')");
    $stmt->execute([$_SESSION['user_id'],$subject_id]);
    $success = "Enrollment request sent.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Request Subject</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
<div class="w-full max-w-md bg-white p-6 rounded-xl shadow-lg">
<h2 class="text-xl font-bold mb-4">Request Subject Enrollment</h2>
<form method="POST" class="space-y-4">
<div>
<label class="block text-sm text-gray-700">Select Subject</label>
<select name="subject_id" required class="w-full px-3 py-2 border rounded-lg">
<?php foreach($subjects as $s): ?>
<option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
<?php endforeach; ?>
</select>
</div>
<button type="submit" class="w-full bg-gray-900 text-white py-2 rounded-lg hover:bg-gray-700">Request Enrollment</button>
<?php if(!empty($success)) echo "<p class='text-green-600 text-sm mt-2'>$success</p>"; ?>
</form>
</div>
</body>
</html>
