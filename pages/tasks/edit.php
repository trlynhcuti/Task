<?php
// /task_management/pages/tasks/edit.php
session_start();

include __DIR__ . '/../../db.php';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/auth.php';

// detect mysqli connection
$db = null;
if (isset($mysqli) && $mysqli instanceof mysqli) $db = $mysqli;
elseif (isset($conn) && $conn instanceof mysqli) $db = $conn;
else {
    http_response_code(500);
    echo "Không tìm thấy kết nối MySQLi. Vui lòng kiểm tra db.php.";
    exit;
}

/* helper */
function e($v) { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

/* get current user */
$currentUserId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
if ($currentUserId <= 0) {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Vui lòng đăng nhập.'];
    header('Location: /html/sign-in.html');
    exit;
}

/* get task id (from GET for form display, from POST when submitting) */
$task_id = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $task_id = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
} else {
    $task_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
}
if ($task_id <= 0) {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Task ID không hợp lệ.'];
    header('Location: /task_management/pages/projects/index.php');
    exit;
}

/* --- 1) Load task and its project --- */
$stmt = $db->prepare("SELECT id, project_id, title, description, created_by, created_at, updated_at FROM tasks WHERE id = ? LIMIT 1");
if (!$stmt) {
    $_SESSION['flash'] = ['type'=>'error','msg'=>'Lỗi DB: '.$db->error];
    header('Location: /task_management/pages/projects/index.php');
    exit;
}
$stmt->bind_param('i', $task_id);
$stmt->execute();
$res = $stmt->get_result();
$task = $res->fetch_assoc();
$stmt->close();

if (!$task) {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Task không tồn tại.'];
    header('Location: /task_management/pages/projects/index.php');
    exit;
}
$project_id = (int)$task['project_id'];

/* --- 2) Check permission: owner or editor of the project --- */
/* check project owner */
$hasPermission = false;
$stmt = $db->prepare("SELECT owner_id FROM projects WHERE id = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param('i', $project_id);
    $stmt->execute();
    $r = $stmt->get_result();
    $proj = $r->fetch_assoc();
    $stmt->close();
    if ($proj && (int)$proj['owner_id'] === $currentUserId) {
        $hasPermission = true;
    }
}

/* if not owner, check project_members permission */
if (!$hasPermission) {
    $stmt = $db->prepare("SELECT permission FROM project_members WHERE project_id = ? AND user_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('ii', $project_id, $currentUserId);
        $stmt->execute();
        $r = $stmt->get_result();
        $pm = $r->fetch_assoc();
        $stmt->close();
        if ($pm && in_array($pm['permission'], ['owner','editor'], true)) {
            $hasPermission = true;
        }
    }
}

if (!$hasPermission) {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Bạn không có quyền chỉnh sửa task này.'];
    header('Location: /task_management/pages/projects/detail.php?id=' . urlencode($project_id));
    exit;
}

/* --- 3) Handle POST (update) --- */
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($title === '') {
        $error = "Tiêu đề không được để trống.";
    } else {
        // use prepared statement to update
        $stmt = $db->prepare("UPDATE tasks SET title = ?, description = ?, updated_at = NOW() WHERE id = ? LIMIT 1");
        if (!$stmt) {
            $error = "Lỗi chuẩn bị câu lệnh: " . $db->error;
        } else {
            $stmt->bind_param('ssi', $title, $description, $task_id);
            if ($stmt->execute()) {
                $stmt->close();
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Cập nhật task thành công.'];
                header('Location: /task_management/pages/projects/detail.php?id=' . urlencode($project_id));
                exit;
            } else {
                $error = "Lỗi khi cập nhật: " . $stmt->error;
                $stmt->close();
            }
        }
    }
}

/* If GET (no POST) we already have $task loaded above; if there was POST but error, keep posted values for form */
$form_title = isset($_POST['title']) ? $_POST['title'] : $task['title'];
$form_description = isset($_POST['description']) ? $_POST['description'] : $task['description'];

?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Chỉnh sửa Task - <?= e($task['title']) ?></title>
  <link rel="stylesheet" href="/task_management/assets/css/index.css">
  <style>
    body { margin-top:60px; font-family: Arial, sans-serif; }
    .container { max-width:800px; margin: 30px auto; padding:22px; background:#fff; border-radius:12px; box-shadow:0 6px 20px rgba(0,0,0,0.06); }
    label { display:block; margin-top:12px; font-weight:700; }
    input[type="text"], textarea { width:100%; padding:10px; border-radius:8px; border:1px solid #ccc; margin-top:6px; font-size:15px; }
    .btn { display:inline-block; padding:10px 14px; border-radius:8px; background:#1a73e8; color:#fff; text-decoration:none; border:none; cursor:pointer; font-weight:700; }
    .btn-grey { background:#ccc; color:#000; text-decoration:none; padding:10px 14px; border-radius:8px; display:inline-block; }
    .error { color:#b00020; margin-top:10px; }
  </style>
</head>
<body>
  <div class="container">
    <h2>Chỉnh sửa Task</h2>
    <div style="margin-bottom:8px;">Project ID: <strong><?= e($project_id) ?></strong></div>

    <?php if (!empty($error)): ?>
      <div class="error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="task_id" value="<?= e($task_id) ?>">
      <label>Tiêu đề</label>
      <input type="text" name="title" value="<?= e($form_title) ?>" required>

      <label>Mô tả</label>
      <textarea name="description" rows="8"><?= e($form_description) ?></textarea>

      <div style="margin-top:14px; display:flex; gap:10px; align-items:center;">
        <button class="btn" type="submit">Lưu</button>
        <a class="btn-grey" href="/task_management/pages/projects/detail.php?id=<?= urlencode($project_id) ?>">Hủy</a>
      </div>
    </form>
  </div>
</body>
</html>
