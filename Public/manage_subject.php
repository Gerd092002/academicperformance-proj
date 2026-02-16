<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
check_login();

if ($_SESSION['user']['role_id'] != 2) { header('Location: login.php'); exit; }

$teacher_id = $_SESSION['user']['id'];
$subject_id = $_GET['subject_id'] ?? null;
if (!$subject_id) { header('Location: teacher_dashboard.php'); exit; }

// confirm this teacher owns the subject
$stmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ? AND teacher_id = ?");
$stmt->execute([$subject_id, $teacher_id]);
$subject = $stmt->fetch();
if (!$subject) { header('Location: teacher_dashboard.php'); exit; }

$message = '';

// CREATE handlers (three types)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_assignment'])) {
        $name = trim($_POST['name']);
        $desc = trim($_POST['description']);
        $due = $_POST['due_date'] ?: null;
        $points = (int)($_POST['total_points'] ?? 100);
        $stmt = $pdo->prepare("INSERT INTO assignments (subject_id, name, description, due_date, total_points) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$subject_id, $name, $desc, $due, $points]);
        $message = "Assignment created.";
    }

    if (isset($_POST['create_project'])) {
        $name = trim($_POST['name']);
        $desc = trim($_POST['description']);
        $due = $_POST['due_date'] ?: null;
        $points = (int)($_POST['total_points'] ?? 100);
        $stmt = $pdo->prepare("INSERT INTO projects (subject_id, name, description, due_date, total_points) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$subject_id, $name, $desc, $due, $points]);
        $message = "Project created.";
    }

    if (isset($_POST['create_activity'])) {
        $name = trim($_POST['name']);
        $type = $_POST['type'] ?? 'activity';
        $points = (int)($_POST['total_points'] ?? 100);
        $stmt = $pdo->prepare("INSERT INTO activities (subject_id, name, type, total_points) VALUES (?, ?, ?, ?)");
        $stmt->execute([$subject_id, $name, $type, $points]);
        $message = "Activity created.";
    }

    // handle adding student to subject (approved directly)
    if (isset($_POST['student_id_to_add'])) {
        $stu = (int)$_POST['student_id_to_add'];
        $stmt = $pdo->prepare("SELECT id FROM student_subject WHERE student_id = ? AND subject_id = ?");
        $stmt->execute([$stu, $subject_id]);
        if (!$stmt->fetch()) {
            $ins = $pdo->prepare("INSERT INTO student_subject (student_id, subject_id, status) VALUES (?, ?, 'approved')");
            $ins->execute([$stu, $subject_id]);
            $message = "Student added to subject.";
        } else {
            $message = "Student already in subject.";
        }
    }
}

// fetch lists to show
$stmt = $pdo->prepare("SELECT * FROM assignments WHERE subject_id = ? ORDER BY created_at DESC");
$stmt->execute([$subject_id]); $assignments = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM projects WHERE subject_id = ? ORDER BY created_at DESC");
$stmt->execute([$subject_id]); $projects = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM activities WHERE subject_id = ? ORDER BY created_at DESC");
$stmt->execute([$subject_id]); $activities = $stmt->fetchAll();

// For add-student search
$search = trim($_GET['search_student'] ?? '');
$students_search = [];
if ($search) {
    $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE role_id = 3 AND username LIKE ? LIMIT 30");
    $stmt->execute(["%$search%"]);
    $students_search = $stmt->fetchAll();
}

// enrolled students for subject
$stmt = $pdo->prepare("
  SELECT u.id, u.username, u.email, ss.status
  FROM student_subject ss
  JOIN users u ON ss.student_id = u.id
  WHERE ss.subject_id = ?
");
$stmt->execute([$subject_id]); $enrolled = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Manage <?= htmlspecialchars($subject['name']) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
  <a href="teacher_dashboard.php" class="text-blue-600 underline mb-4 inline-block">← Back</a>
  <h1 class="text-2xl font-bold mb-2"><?= htmlspecialchars($subject['name']) ?></h1>

  <?php if($message): ?>
    <div class="mb-4 p-3 bg-green-100 text-green-800 rounded"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="bg-white p-4 rounded shadow">
      <h3 class="font-semibold mb-2">Create Assignment</h3>
      <form method="POST" class="space-y-2">
        <input type="hidden" name="create_assignment" value="1">
        <input name="name" required placeholder="Title" class="w-full border p-2">
        <textarea name="description" placeholder="Description" class="w-full border p-2"></textarea>
        <div class="flex gap-2">
          <input type="date" name="due_date" class="border p-2">
          <input type="number" name="total_points" value="100" class="border p-2" placeholder="Total points">
        </div>
        <button class="bg-blue-600 text-white px-3 py-2 rounded">Create Assignment</button>
      </form>
    </div>

    <div class="bg-white p-4 rounded shadow">
      <h3 class="font-semibold mb-2">Create Project</h3>
      <form method="POST" class="space-y-2">
        <input type="hidden" name="create_project" value="1">
        <input name="name" required placeholder="Title" class="w-full border p-2">
        <textarea name="description" placeholder="Description" class="w-full border p-2"></textarea>
        <div class="flex gap-2">
          <input type="date" name="due_date" class="border p-2">
          <input type="number" name="total_points" value="100" class="border p-2" placeholder="Total points">
        </div>
        <button class="bg-blue-600 text-white px-3 py-2 rounded">Create Project</button>
      </form>
    </div>

    <div class="bg-white p-4 rounded shadow md:col-span-2">
      <h3 class="font-semibold mb-2">Create Activity / Quiz / Exam</h3>
      <form method="POST" class="space-y-2">
        <input type="hidden" name="create_activity" value="1">
        <input name="name" required placeholder="Title" class="w-full border p-2">
        <select name="type" class="w-full border p-2">
          <option value="activity">Activity</option>
          <option value="quiz">Quiz</option>
          <option value="exam">Exam</option>
        </select>
        <input type="number" name="total_points" value="100" class="border p-2" placeholder="Total points">
        <button class="bg-blue-600 text-white px-3 py-2 rounded">Create Activity</button>
      </form>
    </div>
  </div>

  <!-- Lists -->
  <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="bg-white p-4 rounded shadow">
      <h4 class="font-semibold mb-2">Assignments</h4>
      <?php if(!$assignments) echo "<p class='text-gray-600'>None</p>"; ?>
      <?php foreach($assignments as $a): ?>
        <div class="border-b py-2">
          <div class="flex justify-between items-center">
            <div>
              <div class="font-medium"><?= htmlspecialchars($a['name']) ?></div>
              <div class="text-xs text-gray-600">Due: <?= $a['due_date'] ?: '—' ?></div>
            </div>
            <div class="text-right">
              <a href="view_submissions.php?type=assignment&id=<?= $a['id'] ?>" class="text-blue-600 underline">Submissions</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="bg-white p-4 rounded shadow">
      <h4 class="font-semibold mb-2">Projects</h4>
      <?php if(!$projects) echo "<p class='text-gray-600'>None</p>"; ?>
      <?php foreach($projects as $p): ?>
        <div class="border-b py-2">
          <div class="flex justify-between items-center">
            <div>
              <div class="font-medium"><?= htmlspecialchars($p['name']) ?></div>
              <div class="text-xs text-gray-600">Due: <?= $p['due_date'] ?: '—' ?></div>
            </div>
            <div>
              <a href="view_submissions.php?type=project&id=<?= $p['id'] ?>" class="text-blue-600 underline">Submissions</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="bg-white p-4 rounded shadow">
      <h4 class="font-semibold mb-2">Activities</h4>
      <?php if(!$activities) echo "<p class='text-gray-600'>None</p>"; ?>
      <?php foreach($activities as $act): ?>
        <div class="border-b py-2">
          <div class="flex justify-between items-center">
            <div>
              <div class="font-medium"><?= htmlspecialchars($act['name']) ?></div>
              <div class="text-xs text-gray-600"><?= htmlspecialchars($act['type']) ?></div>
            </div>
            <div>
              <a href="view_submissions.php?type=activity&id=<?= $act['id'] ?>" class="text-blue-600 underline">Submissions</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Add student to subject -->
  <div class="mt-6 bg-white p-4 rounded shadow">
    <h4 class="font-semibold mb-2">Add Student to Subject</h4>
    <form method="GET" class="mb-3">
      <input type="hidden" name="subject_id" value="<?= $subject_id ?>">
      <input type="text" name="search_student" placeholder="Search student by username" class="w-full border p-2" value="<?= htmlspecialchars($_GET['search_student'] ?? '') ?>">
      <button class="mt-2 bg-gray-900 text-white px-3 py-2 rounded">Search</button>
    </form>

    <?php if($students_search): ?>
      <table class="w-full border">
        <?php foreach($students_search as $stu): ?>
          <tr class="border-t">
            <td class="p-2"><?= htmlspecialchars($stu['username']) ?> <div class="text-xs text-gray-500"><?= htmlspecialchars($stu['email']) ?></div></td>
            <td class="p-2 text-right">
              <form method="POST">
                <input type="hidden" name="student_id_to_add" value="<?= $stu['id'] ?>">
                <button class="bg-green-600 text-white px-2 py-1 rounded">Add</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>

    <h5 class="mt-4 font-semibold">Enrolled Students</h5>
    <?php if($enrolled): ?>
      <ul class="list-disc pl-5">
        <?php foreach($enrolled as $e): ?>
          <li><?= htmlspecialchars($e['username']) ?> — <?= htmlspecialchars($e['status']) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="text-gray-600">No students enrolled.</p>
    <?php endif; ?>
  </div>

</body>
</html>
