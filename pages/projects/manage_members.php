<?php
// /task_management/pages/projects/manage_members.php
session_start();

include __DIR__ . '/../../db.php';
include __DIR__ . '/../../includes/auth.php';
include __DIR__ . '/../../includes/header.php';

// detect mysqli connection
$db = null;
if (isset($mysqli) && $mysqli instanceof mysqli) $db = $mysqli;
elseif (isset($conn) && $conn instanceof mysqli) $db = $conn;
else {
    http_response_code(500);
    echo "Không tìm thấy kết nối MySQLi. Vui lòng kiểm tra db.php.";
    exit;
}

/* Helper for flash */
function flash($type, $msg) {
    $_SESSION['flash'] = ['type'=>$type, 'msg'=>$msg];
}

/* require project_id */
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
if ($project_id <= 0) {
    flash('error', 'Project ID không hợp lệ.');
    header('Location: /task_management/pages/projects/index.php');
    exit;
}

/* current user */
$currentUserId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
if ($currentUserId <= 0) {
    flash('error', 'Vui lòng đăng nhập.');
    header('Location: /html/sign-in.html');
    exit;
}

/* load project and check owner */
$stmt = $db->prepare("SELECT id, name, owner_id FROM projects WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $project_id);
$stmt->execute();
$res = $stmt->get_result();
$project = $res->fetch_assoc();
$stmt->close();

if (!$project) {
    flash('error', 'Project không tồn tại.');
    header('Location: /task_management/pages/projects/index.php');
    exit;
}

$isOwner = ((int)$project['owner_id'] === $currentUserId);
if (!$isOwner) {
    flash('error', 'Chỉ chủ sở hữu (owner) mới được sửa thành viên.');
    header('Location: /task_management/pages/projects/detail.php?id=' . urlencode($project_id));
    exit;
}

/* Handle POST actions: change_role | remove_member */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'change_role') {
        $member_id = isset($_POST['member_id']) ? (int)$_POST['member_id'] : 0;
        $new_role = $_POST['new_role'] ?? '';

        // validate
        $allowed = ['editor','contributor','viewer'];
        if ($member_id <= 0 || !in_array($new_role, $allowed, true)) {
            flash('error', 'Dữ liệu thay đổi quyền không hợp lệ.');
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }

        // Prevent changing owner role
        if ($member_id === (int)$project['owner_id']) {
            flash('error', 'Không thể thay đổi quyền của chủ sở hữu project.');
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }

        // ensure member exists in project
        $stmt = $db->prepare("SELECT user_id FROM project_members WHERE project_id = ? AND user_id = ? LIMIT 1");
        $stmt->bind_param('ii', $project_id, $member_id);
        $stmt->execute();
        $r = $stmt->get_result();
        $exists = $r->fetch_assoc();
        $stmt->close();
        if (!$exists) {
            flash('error', 'Thành viên không tồn tại trong project.');
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }

        // update permission
        $stmt = $db->prepare("UPDATE project_members SET permission = ? WHERE project_id = ? AND user_id = ? LIMIT 1");
        if (!$stmt) {
            flash('error', 'Lỗi DB: ' . $db->error);
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }
        $stmt->bind_param('sii', $new_role, $project_id, $member_id);
        if ($stmt->execute()) {
            flash('success', 'Cập nhật quyền thành công.');
        } else {
            flash('error', 'Cập nhật quyền thất bại: ' . $stmt->error);
        }
        $stmt->close();
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    if ($action === 'remove_member') {
        $member_id = isset($_POST['member_id']) ? (int)$_POST['member_id'] : 0;
        if ($member_id <= 0) {
            flash('error', 'Thành viên không hợp lệ.');
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }

        // Prevent removing owner
        if ($member_id === (int)$project['owner_id']) {
            flash('error', 'Không thể xóa chủ sở hữu project.');
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }

        // ensure member exists
        $stmt = $db->prepare("SELECT user_id FROM project_members WHERE project_id = ? AND user_id = ? LIMIT 1");
        $stmt->bind_param('ii', $project_id, $member_id);
        $stmt->execute();
        $r = $stmt->get_result();
        $exists = $r->fetch_assoc();
        $stmt->close();
        if (!$exists) {
            flash('error', 'Thành viên không tồn tại trong project.');
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }

        // proceed delete (and optionally delete related rows e.g. member settings)
        $stmt = $db->prepare("DELETE FROM project_members WHERE project_id = ? AND user_id = ? LIMIT 1");
        if (!$stmt) {
            flash('error', 'Lỗi DB: ' . $db->error);
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }
        $stmt->bind_param('ii', $project_id, $member_id);
        if ($stmt->execute()) {
            flash('success', 'Xóa thành viên thành công.');
        } else {
            flash('error', 'Xóa thành viên thất bại: ' . $stmt->error);
        }
        $stmt->close();
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    // unknown action
    flash('error', 'Hành động không hợp lệ.');
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

$sql = "
    SELECT pm.user_id, pm.permission, pm.added_at, u.name AS user_name, u.email, COALESCE(u.role, '') AS role
    FROM project_members pm
    JOIN users u ON u.id = pm.user_id
    WHERE pm.project_id = ?
    ORDER BY FIELD(pm.permission,'owner','editor','contributor'), u.name
";
$stmt = $db->prepare($sql);
$stmt->bind_param('i', $project_id);
$stmt->execute();
$res = $stmt->get_result();
$members = [];
while ($row = $res->fetch_assoc()) $members[] = $row;
$stmt->close();

/* Build visible members by filtering out admin via PHP foreach + if */
$visible_members = [];
foreach ($members as $m) {
    // if role exactly 'admin' -> skip
    if (isset($m['role']) && $m['role'] === 'admin') {
        continue;
    }
    $visible_members[] = $m;
}

/* optional: show flash */
$flash = $_SESSION['flash'] ?? null;
if ($flash) unset($_SESSION['flash']);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Quản lý thành viên - <?=htmlspecialchars($project['name'])?></title>
<link rel="stylesheet" href="/task_management/assets/css/index.css">
<style>
.container { max-width:980px; margin:40px auto; padding:22px; background:#fff; border-radius:12px; box-shadow:0 6px 20px rgba(0,0,0,0.06); }
.table { width:100%; border-collapse:collapse; margin-top:12px; }
.table th, .table td { padding:10px 8px; border-bottom:1px solid #eee; text-align:left; vertical-align:middle; }
.select-role { padding:6px 8px; border-radius:6px; }
.btn { padding:8px 12px; border-radius:8px; border:1px solid #1a73e8; background:#fff; color:#1a73e8; cursor:pointer; text-decoration:none; }
.btn-danger { background:#d9534f; color:#fff; border:none; }
.btn-primary { background:#1a73e8; color:#fff; border:none; }
.form-inline { display:inline-block; margin:0; }
.flash-success { background:#e6ffed; border:1px solid #a6e6b8; padding:8px 12px; color:#0a7a2a; border-radius:8px; display:inline-block; }
.flash-error { background:#ffecec; border:1px solid #f1a6a6; padding:8px 12px; color:#b00020; border-radius:8px; display:inline-block; }
.note { font-size:13px; color:#666; margin-top:8px; }
</style>
<script>
function confirmRemove() {
    return confirm('Bạn có chắc muốn xóa thành viên này khỏi project?');
}
</script>
</head>
<body>
<div class="container">
  <h2>Quản lý thành viên — <?=htmlspecialchars($project['name'])?></h2>
  <?php if ($flash): ?>
    <div class="<?= $flash['type'] === 'success' ? 'flash-success' : 'flash-error' ?>"><?=htmlspecialchars($flash['msg'])?></div>
  <?php endif; ?>

  <table class="table">
    <thead>
      <tr>
        <th>Người dùng</th>
        <th>Quyền hiện tại</th>
        <th>Hành động</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($visible_members)): ?>
        <tr><td colspan="3">Chưa có thành viên để hiển thị (ngoại trừ admin).</td></tr>
      <?php else: ?>
        <?php foreach ($visible_members as $m): 
            $uid = (int)$m['user_id'];
            $permission = $m['permission'];
            $isOwnerRow = ($uid === (int)$project['owner_id']);
        ?>
        <tr>
          <td>
            <strong><?=htmlspecialchars($m['user_name'])?></strong>
            <?php if (!empty($m['email'])): ?>
              <div style="font-size:13px;color:#666;"><?=htmlspecialchars($m['email'])?></div>
            <?php endif; ?>
          </td>
          <td><?=htmlspecialchars($permission)?> <?= $isOwnerRow ? '<span style="font-weight:700;color:#1a73e8;">(Owner)</span>' : '' ?></td>
          <td>
            <?php if ($isOwnerRow): ?>
              <em style="color:#666;">Không thể thay đổi</em>
            <?php else: ?>
              <!-- change role form -->
              <form class="form-inline" method="post" onsubmit="return true;">
                <input type="hidden" name="action" value="change_role">
                <input type="hidden" name="member_id" value="<?=htmlspecialchars($uid)?>">
                <select name="new_role" class="select-role" required>
                  <option value="editor" <?= $permission === 'editor' ? 'selected' : '' ?>>Editor</option>
                  <option value="contributor" <?= $permission === 'contributor' ? 'selected' : '' ?>>Contributor</option>
                </select>
                <button type="submit" class="btn btn-primary">Cập nhật</button>
              </form>

              <!-- remove member form -->
              <form class="form-inline" method="post" style="margin-left:8px;" onsubmit="return confirmRemove();">
                <input type="hidden" name="action" value="remove_member">
                <input type="hidden" name="member_id" value="<?=htmlspecialchars($uid)?>">
                <button type="submit" class="btn btn-danger">Xóa</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <p style="margin-top:18px;">
    <a href="/task_management/pages/projects/detail.php?id=<?=urlencode($project_id)?>" class="btn">Quay lại dự án</a>
  </p>
</div>
</body>
</html>
