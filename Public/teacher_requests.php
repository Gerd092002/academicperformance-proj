<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
check_login();

if ($_SESSION['user']['role_id'] != 2) { header('Location: login.php'); exit; }

$teacher_id = $_SESSION['user']['id'];

// Handle approve/reject
if (isset($_POST['action'], $_POST['enroll_id'])) {
    $action = $_POST['action'];
    $enroll_id = $_POST['enroll_id'];

    if (in_array($action, ['approved','rejected'])) {
        $stmt = $pdo->prepare("UPDATE student_subject SET status = ? WHERE id = ?");
        $stmt->execute([$action, $enroll_id]);
    }
}

// Get pending requests for this teacher’s subjects
$stmt = $pdo->prepare("
    SELECT ss.id AS enroll_id, u.username, u.email, s.name AS subject_name
    FROM student_subject ss
    JOIN subjects s ON ss.subject_id = s.id
    JOIN users u ON ss.student_id = u.id
    WHERE s.teacher_id = ? AND ss.status = 'pending'
");
$stmt->execute([$teacher_id]);
$requests = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Enrollment Requests</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-6">

<h1 class="text-2xl font-semibold mb-4">Pending Enrollment Requests</h1>

<?php if (!$requests): ?>
    <p class="text-gray-600">No pending requests.</p>
<?php else: ?>
<table class="min-w-full bg-white border border-gray-200 rounded-lg">
<thead>
<tr class="bg-gray-100">
    <th class="py-2 px-4 border-b">Student</th>
    <th class="py-2 px-4 border-b">Email</th>
    <th class="py-2 px-4 border-b">Subject</th>
    <th class="py-2 px-4 border-b">Actions</th>
</tr>
</thead>
<tbody>
<?php foreach($requests as $r): ?>
<tr>
    <td class="py-2 px-4 border-b"><?= htmlspecialchars($r['username']) ?></td>
    <td class="py-2 px-4 border-b"><?= htmlspecialchars($r['email']) ?></td>
    <td class="py-2 px-4 border-b"><?= htmlspecialchars($r['subject_name']) ?></td>
    <td class="py-2 px-4 border-b">
        <form method="POST" class="inline">
            <input type="hidden" name="enroll_id" value="<?= $r['enroll_id'] ?>">
            <button type="submit" name="action" value="approved" class="bg-green-600 text-white px-2 py-1 rounded hover:bg-green-500">Approve</button>
            <button type="submit" name="action" value="rejected" class="bg-red-600 text-white px-2 py-1 rounded hover:bg-red-500">Reject</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>

</body>
</html>
