<?php
// manage_users.php
session_start();
include __DIR__ . '/../../db.php';
include __DIR__ . '/../../includes/header.php';

$db = null;
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $db = $mysqli;
} elseif (isset($conn) && $conn instanceof mysqli) {
    $db = $conn;
} else {
    die("Không tìm thấy kết nối MySQLi. Vui lòng kiểm tra db.php.");
}

if (empty($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    die("Bạn cần đăng nhập với quyền admin để truy cập trang này.");
}
$admin_id = intval($_SESSION['user']['id']);

$flash_success = '';
$flash_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {

    $target_user_id = intval($_POST['user_id'] ?? 0);
    $message_text = trim($_POST['message'] ?? '');

    if ($target_user_id <= 0) {
        $flash_error = "ID người dùng không hợp lệ.";
    } else {

        $sql_check = "SELECT id, name, email, role FROM users WHERE id = {$target_user_id} LIMIT 1";
        $res_check = mysqli_query($db, $sql_check);

        if (!$res_check) {
            $flash_error = "Lỗi truy vấn: " . mysqli_error($db);
        } elseif (mysqli_num_rows($res_check) === 0) {
            $flash_error = "Không tìm thấy người dùng.";
        } else {

            $userRow = mysqli_fetch_assoc($res_check);

            if ($userRow['role'] !== 'user') {
                $flash_error = "Chỉ được xóa tài khoản role = user.";
            } else {

                $now = date('Y-m-d H:i:s');
                if ($message_text === '') {
                    $message_text = "Admin (id={$admin_id}) xóa user id={$target_user_id}";
                }

                $msg = mysqli_real_escape_string($db, $message_text);

                mysqli_begin_transaction($db);

                $sql_log = "INSERT INTO admin_actions (admin_id, action_type, target_user_id, message, created_at)
                            VALUES ({$admin_id}, 'delete_account', {$target_user_id}, '{$msg}', '{$now}')";

                if (!mysqli_query($db, $sql_log)) {
                    mysqli_rollback($db);
                    $flash_error = "Lỗi ghi admin_action: " . mysqli_error($db);
                } else {

                    $sql_delete = "DELETE FROM users WHERE id = {$target_user_id} LIMIT 1";
                    mysqli_query($db, $sql_delete);

                    if (mysqli_affected_rows($db) > 0) {
                        mysqli_commit($db);
                        $flash_success = "Xóa user thành công!";
                    } else {
                        mysqli_rollback($db);
                        $flash_error = "Không thể xóa user.";
                    }
                }
            }
        }
        if ($res_check) mysqli_free_result($res_check);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'warn_user') {

    $target_user_id = intval($_POST['user_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $message_text = trim($_POST['message'] ?? '');

    if ($target_user_id <= 0) {
        $flash_error = "ID người dùng không hợp lệ.";
    } elseif ($title === '') {
        $flash_error = "Tiêu đề cảnh báo không được rỗng.";
    } else {

        $sql_check = "SELECT id, name, email, role FROM users WHERE id = {$target_user_id} LIMIT 1";
        $res_check = mysqli_query($db, $sql_check);

        if (!$res_check) {
            $flash_error = "Lỗi truy vấn: " . mysqli_error($db);
        } elseif (mysqli_num_rows($res_check) === 0) {
            $flash_error = "Không tìm thấy người dùng.";
        } else {

            $userRow = mysqli_fetch_assoc($res_check);

            $now = date('Y-m-d H:i:s');
            $title_esc = mysqli_real_escape_string($db, $title);
            $msg_esc = mysqli_real_escape_string($db, $message_text);

            mysqli_begin_transaction($db);

            $sql_notif = "INSERT INTO notifications (user_id, type, title, message, is_read, created_at)
                          VALUES ({$target_user_id}, 'personal', '{$title_esc}', '{$msg_esc}', 0, '{$now}')";

            if (!mysqli_query($db, $sql_notif)) {
                mysqli_rollback($db);
                $flash_error = "Lỗi khi tạo notification: " . mysqli_error($db);
            } else {

                $log_msg = "Cảnh báo user id={$target_user_id}: {$message_text}";
                $log_msg_esc = mysqli_real_escape_string($db, $log_msg);

                $sql_log = "INSERT INTO admin_actions (admin_id, action_type, target_user_id, message, created_at)
                            VALUES ({$admin_id}, 'warn', {$target_user_id}, '{$log_msg_esc}', '{$now}')";

                if (!mysqli_query($db, $sql_log)) {
                    mysqli_rollback($db);
                    $flash_error = "Lỗi ghi admin_action: " . mysqli_error($db);
                } else {
                    mysqli_commit($db);
                    $flash_success = "Đã gửi cảnh báo cho user ID {$target_user_id}.";
                }
            }
        }
        if ($res_check) mysqli_free_result($res_check);
    }
}

$users = [];
$listSql = "SELECT id, name, email, created_at FROM users WHERE role = 'user' ORDER BY created_at DESC";
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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản lý người dùng</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    </style>
</head>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        document.querySelectorAll('.btn-warn').forEach(function(btn) {
            btn.addEventListener('click', function() {

                var userId = this.dataset.userId;
                var userName = this.dataset.userName || ('ID ' + userId);

                var defaultTitle = "Cảnh cáo vi phạm quy tắc";
                var title = prompt("Tiêu đề cảnh báo cho " + userName + ":", defaultTitle);
                if (title === null) return;

                var defaultMsg = "Bạn đã vi phạm nội quy. Vui lòng kiểm tra lại.";
                var message = prompt("Nội dung cảnh báo:", defaultMsg);
                if (message === null) return;

                var form = document.createElement('form');
                form.method = 'post';
                form.style.display = 'none';

                var addHidden = function(name, value) {
                    var i = document.createElement('input');
                    i.type = 'hidden';
                    i.name = name;
                    i.value = value;
                    form.appendChild(i);
                };

                addHidden('action', 'warn_user');
                addHidden('user_id', userId);
                addHidden('title', title);
                addHidden('message', message);

                document.body.appendChild(form);
                form.submit();
            });
        });

        if (document.querySelectorAll('.btn-warn').length === 0) {
            console.log('Không tìm thấy nút .btn-warn — kiểm tra phần render PHP.');
        }
    });
</script>

<body class="">
    <div class="container">
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

                                <button type="button"
                                    class="btn btn-sm btn-primary btn-warn"
                                    data-user-id="<?= $u['id'] ?>"
                                    data-user-name="<?= htmlspecialchars($u['name'], ENT_QUOTES) ?>">
                                    Cảnh báo
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>

            </tbody>
        </table>

    </div>
</body>

</html>