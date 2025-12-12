<?php
session_start();
include __DIR__ . '/../../db.php';
include __DIR__ . '/../../includes/auth.php';
include __DIR__ . '/../../includes/header.php';

/* Detect mysqli connection */
$db = null;
if (isset($mysqli) && $mysqli instanceof mysqli) $db = $mysqli;
elseif (isset($conn) && $conn instanceof mysqli) $db = $conn;
else die("Không tìm thấy kết nối MySQL.");

/* Lấy project_id */
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
if ($project_id <= 0) die("Project ID không hợp lệ.");

/* Lấy user hiện tại */
$currentUserId = $_SESSION['user']['id'] ?? null;
if (!$currentUserId) {
    header("Location: /html/sign-in.html");
    exit;
}
$currentUserId = (int)$currentUserId;

/* Lấy quyền current user */
$sql = "SELECT permission FROM project_members WHERE project_id = $project_id AND user_id = $currentUserId LIMIT 1";
$res = mysqli_query($db, $sql);
$row = $res ? mysqli_fetch_assoc($res) : null;
$myPermission = $row['permission'] ?? null;

/* Chỉ owner + editor được thêm thành viên */
if (!in_array($myPermission, ['owner', 'editor'], true)) {
    die("Bạn không có quyền thêm thành viên cho project này.");
}

$error = "";
$success = "";

/* Xử lý POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $permission = isset($_POST['permission']) ? trim($_POST['permission']) : "";

    $allowed = ['owner', 'editor', 'contributor', 'viewer'];

    if ($user_id <= 0) {
        $error = "Vui lòng chọn người dùng.";
    } elseif (!in_array($permission, $allowed, true)) {
        $error = "Quyền không hợp lệ.";
    } else {

        /* Kiểm tra user tồn tại */
        $sql = "SELECT id FROM users WHERE id = $user_id LIMIT 1";
        $res = mysqli_query($db, $sql);
        if (!$res || !mysqli_fetch_assoc($res)) {
            $error = "Người dùng không tồn tại.";
        } else {

            /* Kiểm tra đã là thành viên hay chưa */
            $sql = "SELECT id FROM project_members WHERE project_id = $project_id AND user_id = $user_id LIMIT 1";
            $res = mysqli_query($db, $sql);
            if ($res && mysqli_fetch_assoc($res)) {
                $error = "Người dùng đã là thành viên.";
            } else {

                /* INSERT thành viên mới */
                $permEsc = mysqli_real_escape_string($db, $permission);

                $sql = "INSERT INTO project_members (project_id, user_id, permission, added_by, added_at)
                        VALUES ($project_id, $user_id, '$permEsc', $currentUserId, NOW())";

                if (mysqli_query($db, $sql)) {
                    $success = "Thêm thành viên thành công.";
                    header("Location: /task_management/pages/projects/detail.php?id=" . urlencode($project_id));
                    exit;
                } else {
                    $error = "Lỗi khi thêm thành viên: " . mysqli_error($db);
                }
            }
        }
    }
}

/* Lấy danh sách user chưa là thành viên */
$sql = "
    SELECT u.id, u.name, u.email
    FROM users u
    WHERE u.id NOT IN (
        SELECT pm.user_id FROM project_members pm WHERE pm.project_id = $project_id
    )
    ORDER BY u.name
";
$res = mysqli_query($db, $sql);
$availableUsers = [];
while ($row = mysqli_fetch_assoc($res)) $availableUsers[] = $row;

/* Lấy tên project */
$sql = "SELECT name FROM projects WHERE id = $project_id LIMIT 1";
$res = mysqli_query($db, $sql);
$proj = $res ? mysqli_fetch_assoc($res) : null;
$projectName = $proj['name'] ?? ("#" . $project_id);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thêm thành viên - <?= htmlspecialchars($projectName) ?></title>
    <link rel="stylesheet" href="/task_management/assets/css/index.css">
    <style>
        .container { max-width:700px; margin:30px auto; padding:12px; }
        form { background:#fff; padding:16px; border:1px solid #ddd; border-radius:6px; }
        label { margin-top:10px; font-weight:600; display:block; }
        select, input, button { width:100%; padding:8px; margin-top:6px; }
        .btn { background:#1976d2; color:#fff; border:none; padding:8px; border-radius:6px; cursor:pointer; }
        .btn-secondary { background:#777; }
        .muted { font-size:13px; color:#666; }
        .error { color:#b00020; margin-top:8px; }
        .success { color:green; margin-top:8px; }
    </style>
</head>
<body>
<div class="container">
    <h2>Thêm thành viên cho dự án: <?= htmlspecialchars($projectName) ?></h2>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>Chọn người dùng</label>

        <?php if (empty($availableUsers)): ?>
            <div class="muted">Không còn ai để thêm (tất cả đều là thành viên).</div>
        <?php else: ?>
        <select name="user_id" required>
            <option value="">-- Chọn người dùng --</option>
            <?php foreach ($availableUsers as $u): ?>
                <option value="<?= $u['id'] ?>">
                    <?= htmlspecialchars($u['name'] . " — " . $u['email']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>

        <label>Quyền</label>
        <select name="permission" required>
            <option value="contributor">contributor</option>
            <option value="editor">editor</option>
            <option value="viewer">viewer</option>
            <?php if ($myPermission === 'owner'): ?>
                <option value="owner">owner</option>
            <?php endif; ?>
        </select>

        <button type="submit" class="btn" style="margin-top:14px;">Thêm thành viên</button>
        <a href="/task_management/pages/projects/detail.php?id=<?= $project_id ?>" class="btn btn-secondary" style="text-decoration:none; display:inline-block; text-align:center; margin-top:8px;">Hủy</a>
    </form>
</div>
</body>
</html>
