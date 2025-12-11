<?php
session_start();
include __DIR__ . '/../../db.php';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/auth.php';
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tạo dự án mới</title>

    <link rel="stylesheet" href="../../assets/css/create_project.css">

    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
    </style>

</head>

<body>
    <div class="box">
        <h1>Tạo dự án mới</h1>

        <form method="post">
            <label for="name">Tên dự án</label>
            <input type="text" name="name" required>

            <label for="description">Mô tả</label>
            <input type="text" name="description">

            <button type="submit" name="submit" class="btn">Tạo</button>
        </form>

        <?php
        if (isset($_POST['submit'])) {
            $id   = $_SESSION['user']['id'];
            $name = $_POST['name'] ?? "";
            $des  = $_POST['description'] ?? "";

            $sql = "INSERT INTO projects (name, description, owner_id, created_at, updated_at)
                VALUES ('$name', '$des', '$id', NOW(), NOW())";

            $res = mysqli_query($conn, $sql);

            if ($res) {
                echo "<div class='success'>Tạo dự án thành công!</div>";
                header("Refresh: 1; url=/task_management/index.php");
            } else {
                echo "<div class='alert'>Tạo dự án thất bại!</div>";
            }
        }
        ?>
    </div>

</body>

</html>