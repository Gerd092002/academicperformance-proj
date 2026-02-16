<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

// Allow only admin (role_id = 1)
if ($_SESSION['role'] != 1) {
    echo "Unauthorized access.";
    exit;
}

// Fetch student list (users with role = user)
$stmt = $pdo->query("SELECT id, username FROM users WHERE role_id = 2 ORDER BY username");
$students = $stmt->fetchAll();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $student_id = $_POST['student_id'] ?? '';
    $subject = trim($_POST['subject'] ?? '');
    $score = $_POST['score'] ?? '';

    if (empty($student_id) || empty($subject) || empty($score)) {
        $error = "All fields are required.";
    } else {
        $insert = $pdo->prepare("INSERT INTO grades (student_id, subject, score) VALUES (?, ?, ?)");
        $insert->execute([$student_id, $subject, $score]);

        $success = "Grade added successfully.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Grade | Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center">

<div class="w-full max-w-lg bg-white p-8 rounded-lg shadow-lg">
    <h2 class="text-2xl font-semibold text-gray-800 mb-6 text-center">Add Student Grade</h2>

    <?php if (!empty($error)): ?>
        <p class="text-red-500 text-center mb-4"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <p class="text-green-600 text-center mb-4"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <form method="POST" class="space-y-5">

        <div>
            <label class="text-gray-700 text-sm mb-1 block">Select Student</label>
            <select name="student_id" required class="w-full p-2 border border-gray-300 rounded-lg">
                <option value="">-- Choose Student --</option>
                <?php foreach ($students as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['username']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="text-gray-700 text-sm mb-1 block">Subject</label>
            <input type="text" name="subject" required class="w-full p-2 border border-gray-300 rounded-lg" placeholder="e.g. Math, Science">
        </div>

        <div>
            <label class="text-gray-700 text-sm mb-1 block">Score</label>
            <input type="number" step="0.01" name="score" required class="w-full p-2 border border-gray-300 rounded-lg" placeholder="e.g. 89.5">
        </div>

        <button class="w-full bg-black text-white py-2 rounded-lg hover:bg-gray-900 transition">
            Save Grade
        </button>

        <a href="dashboard.php" class="block text-center text-gray-600 mt-4 underline">Back to Dashboard</a>
    </form>
</div>

</body>
</html>
