<?php
include __DIR__ . '/../db.php';

$error = "";
$email = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email == "" || $password == "") {
        $error = "Vui lòng nhập email và mật khẩu.";
    } else {
        $email_esc = mysqli_real_escape_string($conn, $email);
        $sql = "SELECT id, name, password_hash, role FROM users WHERE email = '$email_esc' LIMIT 1";
        $res = mysqli_query($conn, $sql);

        if ($res && mysqli_num_rows($res) == 1) {
            $row = mysqli_fetch_assoc($res);
            if (password_verify($password, $row['password_hash'])) {
                session_start();
                $_SESSION['user'] = ['id' => $row['id'], 'name' => $row['name'], 'role' => $row['role']];
                header("Location: ../index.php");
                exit;
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
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Đăng nhập</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f2f2f2;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center
        }

        .box {
            width: 100%;
            max-width: 500px;
            padding: 30px;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 4px 15px rgba(0, 0, 0, .1)
        }
    </style>
</head>

<body>
    <div class="box">
        <h4 class="text-center mb-3">Đăng nhập</h4>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($email) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Mật khẩu</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button class="btn btn-primary w-100">Đăng nhập</button>
            <div class="text-center mt-3"><small>Chưa có tài khoản? <a href="register.php">Đăng ký</a></small></div>
        </form>
    </div>
</body>

</html>