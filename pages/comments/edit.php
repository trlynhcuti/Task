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

/* comment id */
$comment_id = ($_SERVER['REQUEST_METHOD'] === 'POST')
    ? (int)($_POST['comment_id'] ?? 0)
    : (int)($_GET['id'] ?? 0);

if ($comment_id <= 0) {
    $_SESSION['flash'] = ['type'=>'error','msg'=>'Comment ID không hợp lệ.'];
    header('Location: /task_management/index.php');
    exit;
}

/* ================= 1) load comment ================= */
$sql = "
    SELECT id, project_id, user_id, content
    FROM comments
    WHERE id = $comment_id
    LIMIT 1
";
$res = mysqli_query($db, $sql);
$comment = $res ? mysqli_fetch_assoc($res) : null;

if (!$comment) {
    $_SESSION['flash'] = ['type'=>'error','msg'=>'Comment không tồn tại.'];
    header('Location: /task_management/index.php');
    exit;
}

$project_id = (int)$comment['project_id'];

/* ================= 2) load project name ================= */
$sql = "SELECT name FROM projects WHERE id = $project_id LIMIT 1";
$res = mysqli_query($db, $sql);
$project = $res ? mysqli_fetch_assoc($res) : null;
$projectName = $project['name'] ?? 'Không xác định';

/* ================= 3) check permission ================= */
/* chỉ người viết comment được sửa */
if ((int)$comment['user_id'] !== $currentUserId) {
    $_SESSION['flash'] = ['type'=>'error','msg'=>'Bạn không có quyền sửa comment này.'];
    header('Location: /task_management/pages/projects/detail.php?id=' . $project_id);
    exit;
}

/* ================= 4) handle POST ================= */
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $content = trim($_POST['content'] ?? '');

    if ($content === '') {
        $error = "Nội dung bình luận không được để trống.";
    } else {
        $contentEsc = mysqli_real_escape_string($db, $content);

        $sql = "
            UPDATE comments
            SET content = '$contentEsc'
            WHERE id = $comment_id
            LIMIT 1
        ";

        if (mysqli_query($db, $sql)) {
            $_SESSION['flash'] = ['type'=>'success','msg'=>'Cập nhật bình luận thành công.'];
            header('Location: /task_management/pages/projects/detail.php?id=' . $project_id);
            exit;
        } else {
            $error = "Lỗi khi cập nhật: " . mysqli_error($db);
        }
    }
}

/* giữ dữ liệu khi lỗi */
$form_content = $_POST['content'] ?? $comment['content'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sửa bình luận</title>
<link rel="stylesheet" href="/task_management/assets/css/index.css">
<style>
body { margin-top:60px; font-family: Arial, sans-serif; }
.container {
    max-width:700px;
    margin:30px auto;
    padding:22px;
    background:#fff;
    border-radius:12px;
    box-shadow:0 6px 20px rgba(0,0,0,0.06);
}
label { display:block; margin-top:12px; font-weight:700; }
textarea {
    width:100%;
    padding:12px;
    border-radius:8px;
    border:1px solid #ccc;
    margin-top:6px;
    min-height:120px;
    font-size:15px;
}
.btn {
    padding:10px 14px;
    border-radius:8px;
    background:#1a73e8;
    color:#fff;
    border:none;
    cursor:pointer;
    font-weight:700;
}
.btn-grey {
    background:#ccc;
    color:#000;
    text-decoration:none;
    padding:10px 14px;
    border-radius:8px;
}
.error { color:#b00020; margin-top:10px; }
</style>
</head>
<body>

<div class="container">
  <h2>Sửa bình luận</h2>

  <div style="margin-bottom:8px;">
    Dự án: <strong><?= e($projectName) ?></strong>
  </div>

  <?php if ($error): ?>
    <div class="error"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post">
    <input type="hidden" name="comment_id" value="<?= e($comment_id) ?>">

    <label>Nội dung bình luận</label>
    <textarea name="content" required><?= e($form_content) ?></textarea>

    <div style="margin-top:14px; display:flex; gap:10px;">
      <button class="btn" type="submit">Lưu</button>
      <a class="btn-grey" href="/task_management/pages/projects/detail.php?id=<?= $project_id ?>">Hủy</a>
    </div>
  </form>
</div>

</body>
</html>
