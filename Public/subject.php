<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
check_login();
if ($_SESSION['user']['role_id'] != 2) { header('Location: login.php'); exit; }

$subject_id = $_GET['id'];
// Get subject
$stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ? AND teacher_id = ?");
$stmt->execute([$subject_id, $_SESSION['user']['id']]);
$subject = $stmt->fetch();
if (!$subject) { die('Subject not found or not yours'); }

// Get students and grades
$stmt = $pdo->prepare("
SELECT ss.id AS enrollment_id, u.id AS student_id, u.username, u.email, ss.status,
       g.score
FROM student_subject ss
JOIN users u ON ss.student_id = u.id
LEFT JOIN grades g ON g.student_subject_id = ss.id
WHERE ss.subject_id = ?
ORDER BY u.username ASC
");
$stmt->execute([$subject_id]);
$students = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Subject: <?= htmlspecialchars($subject['name']) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-6">
<h1 class="text-2xl font-semibold mb-4">Students in <?= htmlspecialchars($subject['name']) ?></h1>
<table class="min-w-full bg-white border border-gray-200 rounded-lg">
<thead>
<tr class="bg-gray-100">
<th class="py-2 px-4 border-b">Student</th>
<th class="py-2 px-4 border-b">Email</th>
<th class="py-2 px-4 border-b">Status</th>
<th class="py-2 px-4 border-b">Grades</th>
</tr>
</thead>
<tbody>
<?php foreach($students as $s): ?>
<tr>
<td class="py-2 px-4 border-b"><?= htmlspecialchars($s['username']) ?></td>
<td class="py-2 px-4 border-b"><?= htmlspecialchars($s['email']) ?></td>
<td class="py-2 px-4 border-b"><?= htmlspecialchars($s['status']) ?></td>
<td class="py-2 px-4 border-b"><?= isset($s['score']) ? htmlspecialchars($s['score']) : 'No grade yet' ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</body>
</html>
