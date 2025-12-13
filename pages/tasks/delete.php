<?php
session_start();

include __DIR__ . '/../../db.php';
include __DIR__ . '/../../includes/auth.php';

/* detect mysqli */
$db = null;
if (isset($mysqli) && $mysqli instanceof mysqli) $db = $mysqli;
elseif (isset($conn) && $conn instanceof mysqli) $db = $conn;
else die("Không tìm thấy kết nối MySQLi.");

/* chỉ POST */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Phương thức không hợp lệ.");
}

/* user hiện tại */
$currentUserId = (int)($_SESSION['user']['id'] ?? 0);
if ($currentUserId <= 0) {
    $_SESSION['flash'] = ['type'=>'error','msg'=>'Vui lòng đăng nhập.'];
    header('Location: /html/sign-in.html');
    exit;
}

/* input */
$task_id    = (int)($_POST['task_id'] ?? 0);
$project_id = (int)($_POST['project_id'] ?? 0);

if ($task_id <= 0 || $project_id <= 0) {
    $_SESSION['flash'] = ['type'=>'error','msg'=>'Dữ liệu không hợp lệ.'];
    header('Location: /task_management/pages/projects/detail.php?id=' . $project_id);
    exit;
}

/* ================= 1) kiểm tra task ================= */
$sql = "SELECT id, project_id FROM tasks WHERE id = $task_id LIMIT 1";
$res = mysqli_query($db, $sql);
$task = $res ? mysqli_fetch_assoc($res) : null;

if (!$task) {
    $_SESSION['flash'] = ['type'=>'error','msg'=>'Task không tồn tại.'];
    header('Location: /task_management/pages/projects/detail.php?id=' . $project_id);
    exit;
}
if ((int)$task['project_id'] !== $project_id) {
    $_SESSION['flash'] = ['type'=>'error','msg'=>'Task không thuộc project.'];
    header('Location: /task_management/pages/projects/detail.php?id=' . $project_id);
    exit;
}

/* ================= 2) kiểm tra quyền ================= */
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
    $_SESSION['flash'] = ['type'=>'error','msg'=>'Bạn không có quyền xóa task này.'];
    header('Location: /task_management/pages/projects/detail.php?id=' . $project_id);
    exit;
}

/* ================= 3) xóa task (transaction) ================= */

/* kiểm tra comments có task_id không */
$has_taskid_in_comments = false;
$chk = mysqli_query($db, "SHOW COLUMNS FROM comments LIKE 'task_id'");
if ($chk && mysqli_num_rows($chk) > 0) $has_taskid_in_comments = true;

/* transaction */
mysqli_begin_transaction($db);

try {
    if ($has_taskid_in_comments) {
        $sql = "DELETE FROM comments WHERE task_id = $task_id";
        if (!mysqli_query($db, $sql)) {
            throw new Exception("Lỗi xóa comment: " . mysqli_error($db));
        }
    }

    /* xóa task */
    $sql = "DELETE FROM tasks WHERE id = $task_id LIMIT 1";
    if (!mysqli_query($db, $sql)) {
        throw new Exception("Lỗi xóa task: " . mysqli_error($db));
    }
    if (mysqli_affected_rows($db) === 0) {
        throw new Exception("Task không còn tồn tại.");
    }

    mysqli_commit($db);

    $_SESSION['flash'] = ['type'=>'success','msg'=>'Xóa task thành công.'];
    header('Location: /task_management/pages/projects/detail.php?id=' . $project_id);
    exit;

} catch (Exception $e) {
    mysqli_rollback($db);
    $_SESSION['flash'] = ['type'=>'error','msg'=>'Xóa thất bại: ' . $e->getMessage()];
    header('Location: /task_management/pages/projects/detail.php?id=' . $project_id);
    exit;
}
