<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_role(3);

// Fetch approved subjects
$subjects = $pdo->prepare("
SELECT ss.id AS student_subject_id, s.name
FROM student_subject ss
JOIN subjects s ON ss.subject_id=s.id
WHERE ss.student_id=? AND ss.status='approved'
");
$subjects->execute([$_SESSION['user_id']]);
$subjects = $subjects->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Subjects</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
<div class="w-full max-w-2xl bg-white p-6 rounded-xl shadow-lg">
<h2 class="text-xl font-bold mb-4">My Subjects</h2>
<?php if(count($subjects)==0) echo "<p>No approved subjects yet.</p>"; ?>
<?php foreach($subjects as $s): ?>
<div class="flex justify-between border-b py-2">
<span><?= htmlspecialchars($s['name']) ?></span>
<a href="student_view_grades.php?id=<?= $s['student_subject_id'] ?>" class="text-blue-600 hover:underline">View Grades</a>
</div>
<?php endforeach; ?>
</div>
</body>
</html>
