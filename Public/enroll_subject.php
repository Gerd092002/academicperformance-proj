<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
check_login();

if ($_SESSION['user']['role_id'] != 3) { header('Location: login.php'); exit; }

$student_id = $_SESSION['user']['id'];

// Handle request submission
if (isset($_POST['subject_id'])) {
    $subject_id = $_POST['subject_id'];

    // Check if already requested/enrolled
    $stmt = $pdo->prepare("SELECT * FROM student_subject WHERE student_id = ? AND subject_id = ?");
    $stmt->execute([$student_id, $subject_id]);
    if (!$stmt->fetch()) {
        $insert = $pdo->prepare("INSERT INTO student_subject (student_id, subject_id, status) VALUES (?, ?, 'pending')");
        $insert->execute([$student_id, $subject_id]);
        $success = "Enrollment request submitted.";
    } else {
        $error = "You have already requested or enrolled in this subject.";
    }
}

// Get available subjects (not yet requested/enrolled)
$stmt = $pdo->prepare("
    SELECT * FROM subjects 
    WHERE id NOT IN (
        SELECT subject_id FROM student_subject WHERE student_id = ?
    )
");
$stmt->execute([$student_id]);
$available_subjects = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Enroll in Subject</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-6">

<h1 class="text-2xl font-semibold mb-4">Available Subjects</h1>

<?php if (!empty($error)): ?>
    <p class="text-red-600 mb-4"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>
<?php if (!empty($success)): ?>
    <p class="text-green-600 mb-4"><?= htmlspecialchars($success) ?></p>
<?php endif; ?>

<form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
<?php foreach($available_subjects as $sub): ?>
    <div class="p-4 bg-white rounded-lg shadow">
        <h2 class="text-lg font-medium"><?= htmlspecialchars($sub['name']) ?></h2>
        <button type="submit" name="subject_id" value="<?= $sub['id'] ?>" class="mt-2 bg-gray-900 text-white py-1 px-3 rounded hover:bg-gray-700">Request Enrollment</button>
    </div>
<?php endforeach; ?>
</form>

</body>
</html>
