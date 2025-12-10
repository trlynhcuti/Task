<?php
session_start();
include __DIR__ . '/../../db.php';
include __DIR__ . '/../../includes/auth.php';
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tạo dự án mới</title>
</head>

<body>
    <div>
        <form method="post">
            <label for="">Tên dự án</label>
            <input type="text" name="name" id="" required>
            <label for="">Mô tả</label>
            <input type="text" name="description" id="">
            <input type="submit" name="submit" id="" value="Tạo">
        </form>
        <?php
        if (isset($_POST['submit'])) {
            $id = $_SESSION['user']['id'];
            $name = $_POST['name'] ?? "";
            $des = $_POST['description'] ?? "";

            $sql = "INSERT INTO `projects` (`name`, `description`, `owner_id`, `created_at`, `updated_at`)
                    VALUES('$name', '$des', '$id', NOW(), NOW())";
            $res = mysqli_query($conn, $sql);

            if ($res) {
                echo "Tạo dự án thành công!";   
                header("Refresh: 2, url=''");
            } else {
                echo "Tạo dự án thất bại!";
            }
        }
        ?>
    </div>
</body>

</html>