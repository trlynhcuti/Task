<?php
session_start();
include __DIR__ . '/../../db.php';
include __DIR__ . '/../../includes/auth.php';
include __DIR__ . '/../../includes/header.php';

/* Detect mysqli connection */
$db = null;
if (isset($mysqli) && $mysqli instanceof mysqli) $db = $mysqli;
elseif (isset($conn) && $conn instanceof mysqli) $db = $conn;
else die("Không tìm thấy kết nối MySQLi.");

/* Check user login */
$currentUserId = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : null;
if (!$currentUserId) {
    header("Location: /html/sign-in.html");
    exit;
}

/* Get project_id */
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
if ($project_id <= 0) die("Project ID không hợp lệ.");

/* Load project (mysqli_query) */
$sql = "SELECT id, name, owner_id FROM projects WHERE id = " . $project_id . " LIMIT 1";
$res = mysqli_query($db, $sql);
if (!$res) {
    die("Lỗi truy vấn: " . mysqli_error($db));
}
$project = mysqli_fetch_assoc($res);
if (!$project) die("Không tìm thấy project.");

/* Load project members (kept in case you want to show list, but we won't insert them) */
$sql = "
    SELECT pm.user_id, u.name
    FROM project_members pm
    JOIN users u ON u.id = pm.user_id
    WHERE pm.project_id = " . $project_id . "
    ORDER BY u.name
";
$members = [];
$res = mysqli_query($db, $sql);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $members[] = $row;
    }
} else {
    die("Lỗi truy vấn thành viên: " . mysqli_error($db));
}

/* Determine user permission */
$currentPermission = null;
/* First try to fetch permission from project_members */
$sql = "SELECT permission FROM project_members WHERE project_id = $project_id AND user_id = $currentUserId LIMIT 1";
$res = mysqli_query($db, $sql);
if ($res && mysqli_num_rows($res) > 0) {
    $row = mysqli_fetch_assoc($res);
    $currentPermission = $row['permission'];
}

/* Owner always has permission */
if (isset($project['owner_id']) && intval($project['owner_id']) === $currentUserId) {
    $currentPermission = "owner";
}

/* Only owner + editor can create task */
if (!in_array($currentPermission, ['owner', 'editor'])) {
    die("<p style='color:#b00020;'>Bạn không có quyền tạo task.</p>");
}

/* Handle POST create task */
$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($title === '') {
        $error = "Tiêu đề không được để trống.";
    } else {
        // Escape inputs
        $title_esc = mysqli_real_escape_string($db, $title);
        $description_esc = mysqli_real_escape_string($db, $description);

        // Build insert SQL WITHOUT assigned_to and status (columns deleted)
        $sql_ins = "
            INSERT INTO tasks (project_id, title, description, created_by, created_at, updated_at)
            VALUES ($project_id, '$title_esc', '$description_esc', $currentUserId, NOW(), NOW())
        ";

        if (mysqli_query($db, $sql_ins)) {
            // Redirect to project detail
            header("Location: /task_management/pages/projects/detail.php?id=" . urlencode($project_id));
            exit;
        } else {
            $error = "Lỗi tạo task: " . mysqli_error($db);
        }
    }
}

/* Helper */
function e($v) { return htmlspecialchars($v, ENT_QUOTES, "UTF-8"); }

?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Tạo Task - <?= e($project['name']) ?></title>
<style>
body {
    margin-top: 60px;
    padding: 30px;
    background: #f5f6fa;
    font-family: Arial;
}
.card {
    background: #fff;
    max-width: 750px;
    margin: auto;
    padding: 22px;
    border-radius: 14px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.07);
}
label { font-weight: bold; margin-top: 12px; display: block; }
input, textarea, select {
    width: 100%; padding: 10px; font-size: 15px;
    border-radius: 8px; border: 1px solid #ccc; margin-top: 6px;
}
.btn {
    padding: 10px 16px; border-radius: 8px; border: none;
    background: #1a73e8; color: white; cursor: pointer;
    font-weight: bold; margin-top: 14px;
}
.btn-grey {
    background: #ccc; color: #000; text-decoration:none;
    padding: 10px 16px; border-radius: 8px;
}
.error { color:#b00020; margin-top: 10px; }
</style>
</head>

<body>
<div class="card">
    <h2>Tạo Task mới</h2>
    <div>Dự án: <b><?= e($project['name']) ?></b></div>

    <?php if ($error): ?>
        <div class="error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>Tiêu đề</label>
        <input type="text" name="title" required value="<?= isset($_POST['title']) ? e($_POST['title']) : '' ?>">

        <label>Nội dung</label>
        <textarea name="description" rows="5"><?= isset($_POST['description']) ? e($_POST['description']) : '' ?></textarea>

        

        <br>
        <a href="/task_management/pages/projects/detail.php?id=<?= urlencode($project_id) ?>" class="btn-grey">Hủy</a>
        <button class="btn">Tạo Task</button>
    </form>
</div>
</body>
</html>
