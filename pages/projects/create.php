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

$currentUserId = $_SESSION['user']['id'];
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

            $name = trim($_POST['name'] ?? "");
            $des  = trim($_POST['description'] ?? "");

            // Insert project (dùng prepared statement)
            $stmt = $db->prepare("
                INSERT INTO projects (name, description, owner_id, created_at, updated_at)
                VALUES (?, ?, ?, NOW(), NOW())
            ");
            $stmt->bind_param("ssi", $name, $des, $currentUserId);

            if ($stmt->execute()) {

                // Lấy project_id vừa tạo
                $project_id = $stmt->insert_id;
                $stmt->close();

                // Thêm người tạo vào project_members với permission = 'owner'
                $stmt2 = $db->prepare("
                    INSERT INTO project_members (project_id, user_id, permission, added_by, added_at)
                    VALUES (?, ?, 'owner', ?, NOW())
                ");
                $stmt2->bind_param("iii", $project_id, $currentUserId, $currentUserId);
                $stmt2->execute();
                $stmt2->close();

                echo "<div class='success'>Tạo dự án thành công!</div>";
                header("Refresh: 1; url=/task_management/index.php");
                exit;

            } else {
                echo "<div class='alert'>Tạo dự án thất bại: " . $db->error . "</div>";
            }
        }
        ?>
    </div>

</body>

</html>
