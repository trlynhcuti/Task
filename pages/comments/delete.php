<?php
session_start();
include __DIR__ . '/../../db.php';
include __DIR__ . '/../../includes/auth.php';

$db = $mysqli ?? $conn ?? null;
if (!$db) die("DB error");

$comment_id = (int)($_POST['comment_id'] ?? 0);
$project_id = (int)($_POST['project_id'] ?? 0);
$user_id    = (int)($_SESSION['user']['id'] ?? 0);

if ($comment_id <= 0 || $project_id <= 0 || $user_id <= 0) {
    die("Dữ liệu không hợp lệ");
}

/* Lấy comment */
$sql = "
    SELECT c.user_id, p.owner_id
    FROM comments c
    JOIN projects p ON p.id = c.project_id
    WHERE c.id = $comment_id AND c.project_id = $project_id
    LIMIT 1
";
$res = mysqli_query($db, $sql);
$row = mysqli_fetch_assoc($res);

if (!$row) die("Comment không tồn tại");

/* Quyền: người viết OR owner project */
if ($row['user_id'] != $user_id && $row['owner_id'] != $user_id) {
    die("Bạn không có quyền xóa comment này");
}

/* Xóa */
mysqli_query($db, "DELETE FROM comments WHERE id = $comment_id LIMIT 1");

header("Location: /task_management/pages/projects/detail.php?id=$project_id");
exit;
