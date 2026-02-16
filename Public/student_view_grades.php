<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_role(3);

$student_subject_id = $_GET['id'] ?? 0;

// Fetch subject name
$stmt = $pdo->prepare("
SELECT s.name
FROM student_subject ss
JOIN subjects s ON ss.subject_id=s.id
WHERE ss.id=? AND ss.student_id=?
");
$stmt->execute([$student_subject_id, $_SESSION['user_id']]);
$subject = $stmt->fetch();

if(!$subject) die("Access denied.");

// Fetch grades
$stmt = $pdo->prepare("SELECT score, date_recorded FROM grades WHERE student_subject_id=?");
$stmt->execute([$student_subject_id]);
$grades = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($subject['name']) ?> Grades</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
<div class="w-full max-w-md bg-white p-6 rounded-xl shadow-lg">
<h2 class="text-xl font-bold mb-4"><?= htmlspecialchars($subject['name']) ?> Grades</h2>
<?php if(count($grades)==0) echo "<p>No grades yet.</p>"; ?>
<?php foreach($grades as $g): ?>
<div class="flex justify-between border-b py-2">
<span><?= htmlspecialchars($g['score']) ?></span>
<span class="text-gray-500 text-sm"><?= $g['date_recorded'] ?></span>
</div>
<?php endforeach; ?>
</div>
</body>
</html>
