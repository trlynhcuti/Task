<?php
session_start();
include __DIR__ . '/../../db.php';
include __DIR__ . '/../../includes/auth.php';

$id = (int) $_GET['id'];
$user = $_SESSION['user']['id'];

// Kiểm tra quyền sở hữu
$check = mysqli_query($conn, "SELECT id FROM projects WHERE id = $id AND owner_id = $user");

if (!$check || mysqli_num_rows($check) == 0) {
    header("Location: ../../index.php");
    exit;
}

// update `updated_at`
mysqli_query($conn, "UPDATE projects SET updated_at = NOW() WHERE id = $id");

// chuyển đến trang chi tiết dự án
header("Location: detail.php?id=" . $id);
exit;
