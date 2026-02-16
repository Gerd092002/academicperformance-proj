<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
check_login();

if ($_SESSION['user']['role_id'] != 3) {
    header('Location: login.php');
    exit;
}

$student_id = $_SESSION['user']['id'];
$subject_id = $_GET['id'];

// Get subject info
$stmt = $pdo->prepare("
    SELECT s.id, s.name
    FROM subjects s
    JOIN student_subject ss ON ss.subject_id = s.id
    WHERE s.id = ? AND ss.student_id = ?
");
$stmt->execute([$subject_id, $student_id]);
$subject = $stmt->fetch();
if (!$subject) die('Subject not found or not enrolled');

// Get student grades for this subject
$stmt = $pdo->prepare("
    SELECT g.score, g.date_recorded, a.title AS activity
    FROM grades g
    LEFT JOIN activities a ON g.activity_id = a.id
    JOIN student_subject ss ON g.student_subject_id = ss.id
    WHERE ss.student_id = ? AND ss.subject_id = ?
    ORDER BY g.date_recorded DESC
");
$stmt->execute([$student_id, $subject_id]);
$grades = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Grades – <?= htmlspecialchars($subject['name']) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-6">

<h1 class="text-2xl font-semibold mb-4"><?= htmlspecialchars($subject['name']) ?> – My Grades</h1>

<?php if (!$grades): ?>
    <p class="text-gray-600">No grades recorded yet.</p>
<?php else: ?>
<table class="min-w-full bg-white border border-gray-200 rounded-lg">
<thead>
<tr class="bg-gray-100">
    <th class="py-2 px-4 border-b">Activity</th>
    <th class="py-2 px-4 border-b">Score</th>
    <th class="py-2 px-4 border-b">Date</th>
</tr>
</thead>
<tbody>
<?php foreach($grades as $g): ?>
<tr>
    <td class="py-2 px-4 border-b"><?= htmlspecialchars($g['activity'] ?? 'Overall') ?></td>
    <td class="py-2 px-4 border-b"><?= htmlspecialchars($g['score']) ?></td>
    <td class="py-2 px-4 border-b"><?= htmlspecialchars($g['date_recorded']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>

</body>
</html>
