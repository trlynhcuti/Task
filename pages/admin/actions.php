<?php
session_start();
include __DIR__ . '/../../db.php';
include __DIR__ . '/../../includes/header.php';

$db = null;
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $db = $mysqli;
} elseif (isset($conn) && $conn instanceof mysqli) {
    $db = $conn;
} else {
    die("Không tìm thấy kết nối MySQLi.");
}

// Chỉ admin mới được xem
if (empty($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    die("Bạn cần đăng nhập với quyền admin để truy cập trang này.");
}

// Lấy danh sách action
$sql = "
    SELECT 
        a.id,
        a.admin_id,
        admin.name AS admin_name,
        a.action_type,
        a.target_user_id,
        user.name AS target_user_name,
        a.message,
        a.created_at
    FROM admin_actions a
    LEFT JOIN users admin ON a.admin_id = admin.id
    LEFT JOIN users user ON a.target_user_id = user.id
    ORDER BY a.created_at DESC
";

$result = mysqli_query($db, $sql);
$actions = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $actions[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lịch sử hành động của Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        td, th { vertical-align: middle !important; }
    </style>
</head>
<body class="">
<div class="container">
    <h1 class="mb-4">Lịch sử hành động của Admin</h1>

    <table class="table table-bordered table-striped">
        <thead class="table-light">
            <tr>
                <th>ID</th>
                <th>Admin thực hiện</th>
                <th>Loại hành động</th>
                <th>ID User bị tác động</th>
                <th>Nội dung</th>
                <th>Thời gian</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($actions)): ?>
            <tr><td colspan="6" class="text-center">Không có hành động nào.</td></tr>
        <?php else: ?>
            <?php foreach ($actions as $a): ?>
                <tr>
                    <td><?= $a['id'] ?></td>
                    <td><?= htmlspecialchars($a['admin_name'] ?? "Admin ID {$a['admin_id']}") ?></td>
                    <td>
                        <?php
                            switch ($a['action_type']) {
                                case 'warn': echo "Cảnh báo"; break;
                                case 'delete_account': echo "Xóa tài khoản"; break;
                                case 'note': echo "Ghi chú"; break;
                                default: echo ucfirst($a['action_type']);
                            }
                        ?>
                    </td>
                    <td>
                        <?= $a['target_user_id'] ?>
                        <?php if (!empty($a['target_user_name'])): ?>
                            (<?= htmlspecialchars($a['target_user_name']) ?>)
                        <?php endif; ?>
                    </td>
                    <td><?= nl2br(htmlspecialchars($a['message'])) ?></td>
                    <td><?= $a['created_at'] ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
