<?php
session_start();
include __DIR__ . '/../../db.php';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/auth.php';

/* detect mysqli connection */
$db = null;
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $db = $mysqli;
} elseif (isset($conn) && $conn instanceof mysqli) {
    $db = $conn;
} else {
    die("Không tìm thấy kết nối MySQLi.");
}

/* current user */
$currentUserId = (int)($_SESSION['user']['id'] ?? 0);
if ($currentUserId <= 0) {
    header("Location: /html/sign-in.html");
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Tạo dự án mới</title>
    <link rel="stylesheet" href="../../assets/css/create_project.css">
    <style>
        body {
            display:flex;
            justify-content:center;
            align-items:center;
            height:100vh;
        }
        .alert { color:#b00020; margin-top:10px; }
        .success { color:green; margin-top:10px; }
    </style>
</head>
<body>

<div class="box">
    <h1>Tạo dự án mới</h1>

    <form method="post">
        <label>Tên dự án</label>
        <input type="text" name="name" required>

        <label>Mô tả</label>
        <input type="text" name="description">

        <button type="submit" name="submit" class="btn">Tạo</button>
    </form>

<?php
if (isset($_POST['submit'])) {

    $name = trim($_POST['name'] ?? '');
    $des  = trim($_POST['description'] ?? '');

    if ($name === '') {
        echo "<div class='alert'>Tên dự án không được rỗng.</div>";
    } else {

        /* escape dữ liệu */
        $nameEsc = mysqli_real_escape_string($db, $name);
        $desEsc  = mysqli_real_escape_string($db, $des);

        /* insert project */
        $sql = "
            INSERT INTO projects (name, description, owner_id, created_at, updated_at)
            VALUES ('$nameEsc', '$desEsc', $currentUserId, NOW(), NOW())
        ";

        if (mysqli_query($db, $sql)) {

            $project_id = mysqli_insert_id($db);

            /* thêm owner vào project_members */
            $sql2 = "
                INSERT INTO project_members (project_id, user_id, permission, added_by, added_at)
                VALUES ($project_id, $currentUserId, 'owner', $currentUserId, NOW())
            ";
            mysqli_query($db, $sql2);

            echo "<div class='success'>Tạo dự án thành công!</div>";
            header("Refresh: 1; url=/task_management/index.php");
            exit;

        } else {
            echo "<div class='alert'>Tạo dự án thất bại: " . mysqli_error($db) . "</div>";
        }
    }
}
?>

</div>

</body>
</html>
