<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
check_login();

if ($_SESSION['user']['role_id'] != 2) {
    header('Location: login.php');
    exit;
}

$teacher_id = $_SESSION['user']['id'];
$subject_id = $_GET['id'] ?? null;
if (!$subject_id) { header('Location: teacher_dashboard.php'); exit; }

// Fetch subject info
$stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ? AND teacher_id = ?");
$stmt->execute([$subject_id, $teacher_id]);
$subject = $stmt->fetch();
if (!$subject) { header('Location: teacher_dashboard.php'); exit; }

// Handle posting new assignment/project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assignment_name'], $_POST['type'])) {
    $name = trim($_POST['assignment_name']);
    $type = $_POST['type'];
    if ($name) {
        $stmt = $pdo->prepare("INSERT INTO activities (name, type, subject_id) VALUES (?, ?, ?)");
        $stmt->execute([$name, $type, $subject_id]);
    }
}

// Handle grade input
if (isset($_POST['grade_student_id'], $_POST['grade_activity_id'], $_POST['score'])) {
    $student_id = $_POST['grade_student_id'];
    $activity_id = $_POST['grade_activity_id'];
    $score = $_POST['score'];
    $total = $_POST['total'] ?? 100;

    // Check if grade exists
    $stmt = $pdo->prepare("SELECT id FROM grades WHERE student_id = ? AND activity_id = ?");
    $stmt->execute([$student_id, $activity_id]);
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("UPDATE grades SET score = ?, total = ? WHERE student_id = ? AND activity_id = ?");
        $stmt->execute([$score, $total, $student_id, $activity_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO grades (student_id, activity_id, score, total) VALUES (?, ?, ?, ?)");
        $stmt->execute([$student_id, $activity_id, $score, $total]);
    }
}

// Fetch enrolled students
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.email, ss.status
    FROM student_subject ss
    JOIN users u ON ss.student_id = u.id
    WHERE ss.subject_id = ? AND ss.status = 'approved'
");
$stmt->execute([$subject_id]);
$students = $stmt->fetchAll();

// Fetch activities
$stmt = $pdo->prepare("SELECT * FROM activities WHERE subject_id = ?");
$stmt->execute([$subject_id]);
$activities = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($subject['name']) ?> – Teacher</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-6">

<div class="flex justify-between items-center mb-6">
    <h1 class="text-3xl font-bold"><?= htmlspecialchars($subject['name']) ?></h1>
    <a href="teacher_dashboard.php" class="bg-gray-700 text-white px-4 py-2 rounded hover:bg-gray-600">Back</a>
</div>

<!-- Enrolled Students -->
<h2 class="text-2xl font-semibold mb-4">Enrolled Students</h2>
<?php if(!$students): ?>
    <p class="text-gray-600">No students enrolled yet.</p>
<?php else: ?>
<table class="min-w-full bg-white border border-gray-200 rounded-lg mb-6">
<thead class="bg-gray-100">
<tr>
    <th class="py-2 px-4 border-b">Student</th>
    <th class="py-2 px-4 border-b">Email</th>
    <?php foreach($activities as $a): ?>
        <th class="py-2 px-4 border-b"><?= htmlspecialchars($a['name']) ?></th>
    <?php endforeach; ?>
</tr>
</thead>
<tbody>
<?php foreach($students as $s): ?>
<tr>
    <td class="py-2 px-4 border-b"><?= htmlspecialchars($s['username']) ?></td>
    <td class="py-2 px-4 border-b"><?= htmlspecialchars($s['email']) ?></td>
    <?php foreach($activities as $a): ?>
        <?php
        $stmt = $pdo->prepare("SELECT score, total FROM grades WHERE student_id = ? AND activity_id = ?");
        $stmt->execute([$s['id'], $a['id']]);
        $grade = $stmt->fetch();
        ?>
        <td class="py-2 px-4 border-b">
            <form method="POST" class="flex items-center gap-1">
                <input type="hidden" name="grade_student_id" value="<?= $s['id'] ?>">
                <input type="hidden" name="grade_activity_id" value="<?= $a['id'] ?>">
                <input type="number" name="score" value="<?= $grade['score'] ?? '' ?>" class="w-16 px-1 border rounded">
                <input type="number" name="total" value="<?= $grade['total'] ?? 100 ?>" class="w-16 px-1 border rounded">
                <button type="submit" class="bg-green-600 text-white px-2 rounded hover:bg-green-500">Save</button>
            </form>
        </td>
    <?php endforeach; ?>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>

<!-- Add Assignment/Project -->
<h2 class="text-2xl font-semibold mb-4">Post New Assignment/Project</h2>
<form method="POST" class="mb-6 space-y-3">
    <div>
        <label class="block text-gray-700">Assignment Name</label>
        <input type="text" name="assignment_name" required class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-gray-600">
    </div>
    <div>
        <label class="block text-gray-700">Type</label>
        <select name="type" class="w-full px-3 py-2 border rounded">
            <option value="assignment">Assignment</option>
            <option value="project">Project</option>
        </select>
    </div>
    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-500">Post</button>
</form>

</body>
</html>
