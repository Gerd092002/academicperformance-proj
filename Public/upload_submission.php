<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$student_id = $_SESSION['user_id'];
$type = $_GET['type']; // activity or project
$id = $_GET['id'];     // ID of activity/project

if ($type !== "activity" && $type !== "project") {
    die("Invalid submission type.");
}

if ($_SERVER['REQUEST_METHOD'] == "POST") {

    $file = $_FILES['file'];
    $filename = time() . '_' . $file['name'];
    $path = "uploads/" . $filename;

    move_uploaded_file($file['tmp_name'], $path);

    if ($type == "activity") {
        $sql = "INSERT INTO submissions (student_id, activity_id, file_path)
                VALUES (?, ?, ?)";
    } else {
        $sql = "INSERT INTO submissions (student_id, project_id, file_path)
                VALUES (?, ?, ?)";
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute([$student_id, $id, $path]);

    header("Location: dashboard.php?upload=success");
    exit;
}
?>
<form method="POST" enctype="multipart/form-data">
    <label>Upload Output:</label>
    <input type="file" name="file" required>
    <button type="submit">Submit</button>
</form>
