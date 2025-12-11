<?php
session_start();
include __DIR__ . '/../../db.php';
include __DIR__ . '/../../includes/auth.php';
include __DIR__ . '/../../includes/header.php';

// detect mysqli connection
$db = null;
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $db = $mysqli;
} elseif (isset($conn) && $conn instanceof mysqli) {
    $db = $conn;
} else {
    die("Không tìm thấy kết nối MySQLi. Vui lòng kiểm tra db.php.");
}

// lấy project_id
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
if ($project_id <= 0) {
    die("Project ID không hợp lệ.");
}

// user hiện tại
$currentUserId = $_SESSION['user']['id'] ?? null;
if (!$currentUserId) {
    header('Location: /html/sign-in.html');
    exit;
}

// Lấy danh sách thành viên hiện tại để xác định quyền current user
$stmt = $db->prepare("SELECT pm.permission FROM project_members pm WHERE pm.project_id = ? AND pm.user_id = ?");
$stmt->bind_param('ii', $project_id, $currentUserId);
$stmt->execute();
$res = $stmt->get_result();
$myRow = $res->fetch_assoc();
$stmt->close();
$myPermission = $myRow['permission'] ?? null;

// Chỉ owner hoặc editor mới được thêm thành viên (bạn có thể điều chỉnh quy tắc)
if (!in_array($myPermission, ['owner','editor'], true)) {
    die("Bạn không có quyền thêm thành viên cho project này.");
}

// Xử lý POST
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $permission = isset($_POST['permission']) ? trim($_POST['permission']) : '';

    // validate permission
    $allowed = ['owner','editor','contributor','viewer'];
    if ($user_id <= 0) {
        $error = "Vui lòng chọn người dùng.";
    } elseif (!in_array($permission, $allowed, true)) {
        $error = "Quyền không hợp lệ.";
    } else {
        // kiểm tra user tồn tại
        $chk = $db->prepare("SELECT id, name FROM users WHERE id = ?");
        $chk->bind_param('i', $user_id);
        $chk->execute();
        $u = $chk->get_result()->fetch_assoc();
        $chk->close();

        if (!$u) {
            $error = "Người dùng không tồn tại.";
        } else {
            // kiểm tra đã là member chưa
            $chk2 = $db->prepare("SELECT id FROM project_members WHERE project_id = ? AND user_id = ?");
            $chk2->bind_param('ii', $project_id, $user_id);
            $chk2->execute();
            $exists = $chk2->get_result()->fetch_assoc();
            $chk2->close();

            if ($exists) {
                $error = "Người dùng đã là thành viên của project.";
            } else {
                // insert
                $ins = $db->prepare("INSERT INTO project_members (project_id, user_id, permission, added_by, added_at) VALUES (?, ?, ?, ?, NOW())");
                $ins->bind_param('iisi', $project_id, $user_id, $permission, $currentUserId);
                if ($ins->execute()) {
                    $success = "Thêm thành viên thành công.";
                    // redirect về detail để tránh submit lại (kèm message nếu cần)
                    header("Location: /task_management/pages/projects/detail.php?id=" . urlencode($project_id) . "&msg=" . urlencode($success));
                    exit;
                } else {
                    $error = "Lỗi khi thêm thành viên: " . $db->error;
                }
            }
        }
    }
}

// Lấy danh sách users chưa là member (để hiển thị trong select)
$sql = "SELECT u.id, u.name, u.email
        FROM users u
        WHERE u.id NOT IN (SELECT pm.user_id FROM project_members pm WHERE pm.project_id = ?)
        ORDER BY u.name";
$stmt = $db->prepare($sql);
$stmt->bind_param('i', $project_id);
$stmt->execute();
$availableUsers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Lấy project name để hiển thị
$stmt = $db->prepare("SELECT id, name FROM projects WHERE id = ?");
$stmt->bind_param('i', $project_id);
$stmt->execute();
$proj = $stmt->get_result()->fetch_assoc();
$stmt->close();
$projectName = $proj['name'] ?? ('#' . $project_id);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Thêm thành viên - <?=htmlspecialchars($projectName)?></title>
    <link rel="stylesheet" href="/task_management/assets/css/index.css">
    <style>
        
        .container { max-width:700px; margin:30px auto; padding:12px; }
        form { background:#fff; padding:16px; border:1px solid #e5e5e5; border-radius:6px; }
        label { display:block; margin-top:10px; font-weight:600; }
        select, button, textarea, input[type="text"] { width:100%; padding:8px; margin-top:6px; box-sizing:border-box; }
        .row { display:flex; gap:12px; }
        .col-2 { flex:1; }
        .btn { padding:8px 14px; background:#1976d2; color:#fff; text-decoration:none; border:none; border-radius:6px; cursor:pointer; }
        .muted { color:#666; font-size:13px; margin-top:6px; }
        .error { color:#b00020; margin-top:8px; }
        .success { color:green; margin-top:8px; }
        .back { display:inline-block; margin-bottom:10px; }
    </style>
</head>
<body>
<div class="container">
    <a class="back" href="/task_management/pages/projects/detail.php?id=<?=urlencode($project_id)?>">&larr; Quay lại dự án</a>
    <h2>Thêm thành viên cho: <?=htmlspecialchars($projectName)?></h2>

    <?php if ($error): ?>
        <div class="error"><?=htmlspecialchars($error)?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="success"><?=htmlspecialchars($success)?></div>
    <?php endif; ?>

    <form method="post" action="">
        <label for="user_id">Chọn người dùng</label>
        <?php if (empty($availableUsers)): ?>
            <div class="muted">Không có người dùng nào khả dụng để thêm (tất cả đã là thành viên).</div>
        <?php else: ?>
            <select name="user_id" id="user_id" required>
                <option value="">-- Chọn người dùng --</option>
                <?php foreach ($availableUsers as $u): ?>
                    <option value="<?=htmlspecialchars($u['id'])?>"><?=htmlspecialchars($u['name'] . ' — ' . $u['email'])?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <label for="permission">Quyền (permission)</label>
        <select name="permission" id="permission" required>
            <option value="contributor">contributor</option>
            <option value="editor">editor</option>
            <option value="viewer">viewer</option>
            <!-- Chỉ owner nên được set bởi người hiện tại nếu hiện tại là owner -->
            <?php if ($myPermission === 'owner'): ?>
                <option value="owner">owner</option>
            <?php endif; ?>
        </select>

        <div style="margin-top:12px;">
            <button class="btn" type="submit">Thêm thành viên</button>
            <a class="btn" href="/task_management/pages/projects/detail.php?id=<?=urlencode($project_id)?>" style="background:#6c757d; margin-left:8px;">Hủy</a>
        </div>
    </form>
</div>
</body>
</html>
