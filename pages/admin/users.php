<?php
// manage_users.php
session_start();
include __DIR__ . '/../../db.php';
include __DIR__ . '/../../includes/header.php';

/* detect mysqli */
$db = null;
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $db = $mysqli;
} elseif (isset($conn) && $conn instanceof mysqli) {
    $db = $conn;
} else {
    die("Không tìm thấy kết nối MySQLi. Vui lòng kiểm tra db.php.");
}

/* check admin */
if (empty($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    die("Bạn cần đăng nhập với quyền admin để truy cập trang này.");
}
$admin_id = (int)$_SESSION['user']['id'];

$flash_success = '';
$flash_error = '';

/* ================= DELETE USER ================= */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'delete_user'
) {

    $target_user_id = (int)($_POST['user_id'] ?? 0);
    $message_text   = trim($_POST['message'] ?? '');

    if ($target_user_id <= 0) {
        $flash_error = "ID người dùng không hợp lệ.";
    } else {

        /* check user tồn tại + role */
        $sql_check = "
            SELECT id, name, email, role
            FROM users
            WHERE id = {$target_user_id}
            LIMIT 1
        ";
        $res_check = mysqli_query($db, $sql_check);

        if (!$res_check) {
            $flash_error = "Lỗi truy vấn: " . mysqli_error($db);
        } elseif (mysqli_num_rows($res_check) === 0) {
            $flash_error = "Không tìm thấy người dùng.";
        } else {

            $userRow = mysqli_fetch_assoc($res_check);

            if ($userRow['role'] !== 'user') {
                $flash_error = "Chỉ được xóa tài khoản có role = user.";
            } else {

                $now = date('Y-m-d H:i:s');

                if ($message_text === '') {
                    $message_text = "Admin (id={$admin_id}) xóa user id={$target_user_id}";
                }

                $msg_esc = mysqli_real_escape_string($db, $message_text);

                mysqli_begin_transaction($db);

                /* ghi log admin_actions */
                $sql_log = "
                    INSERT INTO admin_actions
                        (admin_id, action_type, target_user_id, message, created_at)
                    VALUES
                        ({$admin_id}, 'delete', {$target_user_id}, '{$msg_esc}', '{$now}')
                ";

                if (!mysqli_query($db, $sql_log)) {
                    mysqli_rollback($db);
                    $flash_error = "Lỗi ghi admin_actions: " . mysqli_error($db);
                } else {

                    /* DELETE USER – FK CASCADE xử lý phần còn lại */
                    $sql_delete = "
                        DELETE FROM users
                        WHERE id = {$target_user_id}
                        LIMIT 1
                    ";

                    if (!mysqli_query($db, $sql_delete)) {
                        mysqli_rollback($db);
                        $flash_error = "Lỗi xóa user: " . mysqli_error($db);
                    } elseif (mysqli_affected_rows($db) === 0) {
                        mysqli_rollback($db);
                        $flash_error = "Không thể xóa user.";
                    } else {
                        mysqli_commit($db);
                        $flash_success = "Đã xóa user và toàn bộ dữ liệu liên quan.";
                    }
                }
            }
        }

        if ($res_check) {
            mysqli_free_result($res_check);
        }
    }
}

/* ================= LIST USERS ================= */
$users = [];
$listSql = "
    SELECT id, name, email, created_at
    FROM users
    WHERE role = 'user'
    ORDER BY created_at DESC
";
$res = mysqli_query($db, $listSql);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $users[] = $row;
    }
    mysqli_free_result($res);
}
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Quản lý người dùng</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
<div class="container mt-4">

    <h1 class="mb-4">Danh sách tài khoản</h1>

    <?php if ($flash_success): ?>
        <div class="alert alert-success"><?= $flash_success ?></div>
    <?php endif; ?>

    <?php if ($flash_error): ?>
        <div class="alert alert-danger"><?= $flash_error ?></div>
    <?php endif; ?>

    <table class="table table-striped table-bordered">
        <thead class="table-light">
        <tr>
            <th>ID</th>
            <th>Tên</th>
            <th>Email</th>
            <th>Ngày tạo</th>
            <th>Hành động</th>
        </tr>
        </thead>
        <tbody>

        <?php if (empty($users)): ?>
            <tr>
                <td colspan="5" class="text-center">Không có người dùng nào.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><?= htmlspecialchars($u['name']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= htmlspecialchars($u['created_at']) ?></td>
                    <td>
                        <form method="post" class="d-inline"
                              onsubmit="return confirm('Bạn có chắc chắn muốn xóa user ID <?= $u['id'] ?>?');">
                            <input type="hidden" name="action" value="delete_user">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <input type="hidden" name="message" value="Xóa tài khoản bởi admin id=<?= $admin_id ?>">
                            <button class="btn btn-sm btn-danger">Xóa</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>

        </tbody>
    </table>

</div>
</body>
</html>
