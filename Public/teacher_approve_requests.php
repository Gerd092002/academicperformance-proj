<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_role(2);

// Fetch pending requests for teacher's subjects
$requests = $pdo->prepare("
SELECT ss.id, u.username AS student, s.name AS subject
FROM student_subject ss
JOIN users u ON ss.student_id = u.id
JOIN subjects s ON ss.subject_id = s.id
WHERE s.teacher_id = ? AND ss.status='pending'
");
$requests->execute([$_SESSION['user_id']]);
$requests = $requests->fetchAll();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $id = $_POST['request_id'];
    $action = $_POST['action']; // approve/reject
    $status = $action==='approve' ? 'approved':'rejected';
    $stmt = $pdo->prepare("UPDATE student_subject SET status=? WHERE id=?");
    $stmt->execute([$status,$id]);
    header("Location: teacher_approve_requests.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Approve Requests</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
<div class="w-full max-w-2xl bg-white p-6 rounded-xl shadow-lg">
<h2 class="text-xl font-bold mb-4">Pending Enrollment Requests</h2>
<?php if(count($requests)==0) echo "<p>No pending requests.</p>"; ?>
<?php foreach($requests as $r): ?>
<div class="flex justify-between items-center border-b py-2">
<span><?= htmlspecialchars($r['student']) ?> → <?= htmlspecialchars($r['subject']) ?></span>
<form method="POST" class="flex space-x-2">
<input type="hidden" name="request_id" value="<?= $r['id'] ?>">
<button type="submit" name="action" value="approve" class="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-500">Approve</button>
<button type="submit" name="action" value="reject" class="bg-red-600 text-white px-3 py-1 rounded hover:bg-red-500">Reject</button>
</form>
</div>
<?php endforeach; ?>
</div>
</body>
</html>
