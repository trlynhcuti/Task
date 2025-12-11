<?php
include __DIR__ . '/../db.php';

$errors = "";
$success = "";
$name = "";
$email = "";

if (isset($_POST['submit'])) {

    // Lấy dữ liệu theo kiểu cơ bản
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirm'];

    // Validate cơ bản
    if ($name === "" || $email === "" || $password === "" || $confirm === "") {
        $errors = "Vui lòng nhập đầy đủ thông tin!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors = "Email không hợp lệ!";
    } elseif ($password !== $confirm) {
        $errors = "Mật khẩu nhập lại không khớp!";
    } else {

        // Escape
        $name_esc  = mysqli_real_escape_string($conn, $name);
        $email_esc = mysqli_real_escape_string($conn, $email);

        // Kiểm tra email tồn tại
        $sql_email = "SELECT id FROM users WHERE email = '$email_esc' LIMIT 1";
        $res_email = mysqli_query($conn, $sql_email);

        if ($res_email && mysqli_num_rows($res_email) > 0) {
            $errors = "Email đã được sử dụng!";
        }else {

            // Hash bằng MD5
            $hash = md5($password);

            $sql_insert = "INSERT INTO users (name, email, password_hash, role, created_at)
                    VALUES ('$name_esc', '$email_esc', '$hash', 'user', NOW())";

            if (mysqli_query($conn, $sql_insert)) {
                $success = "Đăng ký thành công! Bạn sẽ được chuyển sang trang đăng nhập.";
                header("Refresh: 2; url=login.php");
            } else {
                $errors = "Lỗi hệ thống, không thể tạo tài khoản.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <title>Đăng ký</title>

    <link rel="stylesheet" href="../assets/css/register.css">
</head>

<body>

    <div class="register-box">
        <h1>Đăng ký tài khoản</h1>

        <?php if ($success != ""): ?>
            <div class="success" style="color: green;"><?= $success ?></div>
        <?php endif; ?>

        <?php if ($errors != ""): ?>
            <div class="alert" style="color: red;"><?= htmlspecialchars($errors) ?></div>
        <?php endif; ?>

        <form method="POST">

            <label>Tên người dùng</label>
            <input type="text" name="name" value="<?= htmlspecialchars($name) ?>">

            <label>Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($email) ?>">

            <label>Mật khẩu</label>
            <input type="password" name="password">

            <label>Nhập lại mật khẩu</label>
            <input type="password" name="confirm">

            <button class="btn" name="submit" type="submit">Tạo tài khoản</button>

            <div class="small-text">
                Đã có tài khoản? <a href="login.php">Đăng nhập</a>
            </div>
        </form>
    </div>

</body>

</html>