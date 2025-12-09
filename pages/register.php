<?php
include __DIR__ . '/../db.php';

$errors = "";
$name = "";
$email = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm'];

    if ($name == "" || $email == "" || $password == "" || $confirm == "") {
        $errors = "Vui lòng nhập đầy đủ thông tin!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors = "Email không hợp lệ!";
    } elseif ($password != $confirm) {
        $errors = "Mật khẩu nhập lại không khớp!";
    } else {

        // Escape chống lỗi SQL
        $name_esc = mysqli_real_escape_string($conn, $name);
        $email_esc = mysqli_real_escape_string($conn, $email);

        // Kiểm tra trùng email
        $sql = "SELECT id FROM users WHERE email = '$email_esc'";
        $res = mysqli_query($conn, $sql);

        if (mysqli_num_rows($res) > 0) {
            $errors = "Email đã được sử dụng!";
        } else {
            // Hash mật khẩu & lưu DB
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $sql = "INSERT INTO users (name, email, password_hash, role, created_at)
                    VALUES ('$name_esc', '$email_esc', '$hash', 'user', NOW())";

            mysqli_query($conn, $sql);

            header("Location: login.php");
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #f2f2f2;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .register-box {
            width: 100%;
            max-width: 500px;
            padding: 40px;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>

    <div class="register-box">
        <h3 class="text-center mb-4">Đăng ký tài khoản</h3>

        <?php if ($errors != ""): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($errors) ?></div>
        <?php endif; ?>

        <form method="POST">

            <div class="mb-3">
                <label class="form-label">Tên người dùng</label>
                <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($name) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($email) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Mật khẩu</label>
                <input type="password" name="password" class="form-control" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Nhập lại mật khẩu</label>
                <input type="password" name="confirm" class="form-control" required>
            </div>

            <button class="btn btn-primary w-100 mt-2">Tạo tài khoản</button>

            <div class="text-center mt-3">
                <small>Đã có tài khoản? <a href="login.php">Đăng nhập</a></small>
            </div>
        </form>
    </div>

</body>

</html>