<?php
include __DIR__ . '/../db.php';

$error = "";
$success = "";
$email = "";

if (isset($_POST['submit'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if ($email === "" || $password === "") {
        $error = "Vui lòng nhập email và mật khẩu.";
    } else {

        $email_esc = mysqli_real_escape_string($conn, $email);
        $sql = "SELECT id, name, password_hash, role FROM users WHERE email = '$email_esc' LIMIT 1";
        $res = mysqli_query($conn, $sql);

        if ($res && mysqli_num_rows($res) === 1) {

            $row = mysqli_fetch_assoc($res);

            // Kiểm tra MD5 password
            if (md5($password) === $row['password_hash']) {

                session_start();
                $_SESSION['user'] = [
                    'id'   => $row['id'],
                    'name' => $row['name'],
                    'role' => $row['role']
                ];

                // Hiển thị thông báo và chuyển hướng
                $success = "Đăng nhập thành công! Đang chuyển hướng...";
                header("Refresh: 2; url=../index.php");
            } else {
                $error = "Email hoặc mật khẩu không đúng.";
            }
        } else {
            $error = "Email hoặc mật khẩu không đúng.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <title>Đăng nhập</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">

    <link rel="stylesheet" href="../assets/css/login.css">
</head>

<body>
    <div class="box">
        <h1>Đăng nhập</h1>

        <?php if ($success): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>


        <?php if ($error): ?>
            <div class="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <label>Email</label>
            <input type="email" name="email" required value="<?= htmlspecialchars($email) ?>">

            <label>Mật khẩu</label>
            <input type="password" name="password" required>

            <button class="btn" type="submit" name="submit">Đăng nhập</button>

            <div class="small-text">
                Chưa có tài khoản? <a href="register.php">Đăng ký</a>
            </div>
        </form>
    </div>
</body>

</html>