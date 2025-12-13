<?php
session_start();

include __DIR__ . '/../../db.php';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/auth.php';

/* detect mysqli */
$db = null;
if (isset($mysqli) && $mysqli instanceof mysqli) $db = $mysqli;
elseif (isset($conn) && $conn instanceof mysqli) $db = $conn;
else die("Không tìm thấy kết nối MySQLi.");

function e($v) {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

/* current user */
$currentUserId = (int)($_SESSION['user']['id'] ?? 0);
if ($currentUserId <= 0) {
    $_SESSION['flash'] = ['type'=>'error','msg'=>'Vui lòng đăng nhập.'];
    header('Location: /html/sign-in.html');
    exit;
}

/* task id */
$task_id = ($_SERVER['REQUEST_METHOD'] === 'POST')
    ? (int)($_POST['task_id'] ?? 0)
    : (int)($_GET['id'] ?? 0);

if ($task_id <= 0) {
    $_SESSION['flash'] = ['type'=>'error','msg'=>'Task ID không hợp lệ.'];
    header('Location: /task_management/pages/projects/index.php');
    exit;
}

/* ================= 1) load task ================= */
$sql = "
    SELECT id, project_id, title, description
    FROM tasks
    WHERE id = $task_id
    LIMIT 1
";
$res = mysqli_query($db, $sql);
$task = $res ? mysqli_fetch_assoc($res) : null;

if (!$task) {
    $_SESSION['flash'] = ['type'=>'error','msg'=>'Task không tồn tại.'];
    header('Location: /task_management/pages/projects/index.php');
    exit;
}

$project_id = (int)$task['project_id'];

/* ================= 2) check permission ================= */
$hasPermission = false;

/* owner */
$sql = "SELECT owner_id FROM projects WHERE id = $project_id LIMIT 1";
$res = mysqli_query($db, $sql);
$proj = $res ? mysqli_fetch_assoc($res) : null;
if ($proj && (int)$proj['owner_id'] === $currentUserId) {
    $hasPermission = true;
}

/* editor */
if (!$hasPermission) {
    $sql = "
        SELECT permission
        FROM project_members
        WHERE project_id = $project_id
          AND user_id = $currentUserId
        LIMIT 1
    ";
    $res = mysqli_query($db, $sql);
    $pm = $res ? mysqli_fetch_assoc($res) : null;
    if ($pm && in_array($pm['permission'], ['owner','editor'], true)) {
        $hasPermission = true;
    }
}

if (!$hasPermission) {
    $_SESSION['flash'] = ['type'=>'error','msg'=>'Bạn không có quyền chỉnh sửa task này.'];
    header('Location: /task_management/pages/projects/detail.php?id=' . $project_id);
    exit;
}

/* ================= 3) handle POST ================= */
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($title === '') {
        $error = "Tiêu đề không được để trống.";
    } else {
        $titleEsc = mysqli_real_escape_string($db, $title);
        $descEsc  = mysqli_real_escape_string($db, $description);

        $sql = "
            UPDATE tasks
            SET title = '$titleEsc',
                description = '$descEsc',
                updated_at = NOW()
            WHERE id = $task_id
            LIMIT 1
        ";

        if (mysqli_query($db, $sql)) {
            $_SESSION['flash'] = ['type'=>'success','msg'=>'Cập nhật task thành công.'];
            header('Location: /task_management/pages/projects/detail.php?id=' . $project_id);
            exit;
        } else {
            $error = "Lỗi khi cập nhật: " . mysqli_error($db);
        }
    }
}

/* giữ dữ liệu khi lỗi */
$form_title = $_POST['title'] ?? $task['title'];
$form_description = $_POST['description'] ?? $task['description'];

?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Chỉnh sửa Task - <?= e($task['title']) ?></title>
<link rel="stylesheet" href="/task_management/assets/css/index.css">
<style>
body { margin-top:60px; font-family: Arial, sans-serif; }
.container { max-width:800px; margin:30px auto; padding:22px; background:#fff; border-radius:12px; box-shadow:0 6px 20px rgba(0,0,0,0.06); }
label { display:block; margin-top:12px; font-weight:700; }
input[type="text"], textarea { width:100%; padding:10px; border-radius:8px; border:1px solid #ccc; margin-top:6px; }
.btn { padding:10px 14px; border-radius:8px; background:#1a73e8; color:#fff; border:none; cursor:pointer; font-weight:700; }
.btn-grey { background:#ccc; color:#000; text-decoration:none; padding:10px 14px; border-radius:8px; }
.error { color:#b00020; margin-top:10px; }
</style>
</head>
<body>

<div class="container">
  <h2>Chỉnh sửa Task</h2>
  <div style="margin-bottom:8px;">Project ID: <strong><?= e($project_id) ?></strong></div>

  <?php if ($error): ?>
    <div class="error"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post">
    <input type="hidden" name="task_id" value="<?= e($task_id) ?>">

    <label>Tiêu đề</label>
    <input type="text" name="title" value="<?= e($form_title) ?>" required>

    <label>Mô tả</label>
    <textarea name="description" rows="8"><?= e($form_description) ?></textarea>

    <div style="margin-top:14px; display:flex; gap:10px;">
      <button class="btn" type="submit">Lưu</button>
      <a class="btn-grey" href="/task_management/pages/projects/detail.php?id=<?= $project_id ?>">Hủy</a>
    </div>
  </form>
</div>

</body>
</html>
