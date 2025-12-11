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
$currentUserId = $_SESSION['user']['id'] ?? null;
if (!$currentUserId) {
    header("Location: /html/sign-in.html");
    exit;
}

/* Get project_id */
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
if ($project_id <= 0) die("Project ID không hợp lệ.");

/* Load project */
$stmt = $db->prepare("SELECT id, name, owner_id FROM projects WHERE id = ?");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$project) die("Không tìm thấy project.");

/* Load project members to assign task */
$stmt = $db->prepare("
    SELECT pm.user_id, u.name
    FROM project_members pm
    JOIN users u ON u.id = pm.user_id
    WHERE pm.project_id = ?
    ORDER BY u.name
");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* Determine user permission */
$currentPermission = null;
foreach ($members as $m) {
    if ($m['user_id'] == $currentUserId) {
        $q = $db->prepare("SELECT permission FROM project_members WHERE project_id = ? AND user_id = ?");
        $q->bind_param("ii", $project_id, $currentUserId);
        $q->execute();
        $r = $q->get_result()->fetch_assoc();
        $q->close();
        if ($r) $currentPermission = $r['permission'];
        break;
    }
}

/* Owner always has permission */
if ($project['owner_id'] == $currentUserId) {
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
    $assigned_to = isset($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : 0;
    $status = $_POST['status'] ?? 'todo';

    if ($title === '') {
        $error = "Tiêu đề không được để trống.";
    } else {
        /* Validate assigned_to */
        if ($assigned_to > 0) {
            $chk = $db->prepare("SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ?");
            $chk->bind_param("ii", $project_id, $assigned_to);
            $chk->execute();
            if (!$chk->get_result()->fetch_assoc()) {
                $error = "Người được giao không phải thành viên project.";
            }
            $chk->close();
        }

        if ($error === "") {
            /* Insert task */
            if ($assigned_to > 0) {
                $sql = "INSERT INTO tasks (project_id, title, description, created_by, assigned_to, status, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("ississ", $project_id, $title, $description, $currentUserId, $assigned_to, $status);
            } else {
                $sql = "INSERT INTO tasks (project_id, title, description, created_by, assigned_to, status, created_at, updated_at)
                        VALUES (?, ?, ?, ?, NULL, ?, NOW(), NOW())";
                $stmt = $db->prepare($sql);
                $stmt->bind_param("issis", $project_id, $title, $description, $currentUserId, $status);
            }

            if ($stmt->execute()) {
                $stmt->close();
                header("Location: /task_management/pages/projects/detail.php?id=" . urlencode($project_id));
                exit;
            } else {
                $error = "Lỗi tạo task: " . $db->error;
                $stmt->close();
            }
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
    <div>Project: <b><?= e($project['name']) ?></b></div>

    <?php if ($error): ?>
        <div class="error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>Tiêu đề</label>
        <input type="text" name="title" required>

        <label>Mô tả</label>
        <textarea name="description" rows="5"></textarea>

        <label>Giao cho</label>
        <select name="assigned_to">
            <option value="0">— Không giao (NULL) —</option>
            <?php foreach ($members as $mem): ?>
                <option value="<?= e($mem['user_id']) ?>">
                    <?= e($mem['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Trạng thái</label>
        <select name="status" required>
            <option value="todo">Todo</option>
            <option value="in_progress">In Progress</option>
            <option value="done">Done</option>
            <option value="closed">Closed</option>
        </select>

        <br>
        <a href="/task_management/pages/projects/detail.php?id=<?= urlencode($project_id) ?>" class="btn-grey">Hủy</a>
        <button class="btn">Tạo Task</button>
    </form>
</div>
</body>
</html>
