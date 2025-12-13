<?php
session_start();

include __DIR__ . '/../../db.php';
include __DIR__ . '/../../includes/auth.php';
include __DIR__ . '/../../includes/header.php';

/* detect mysqli connection */
$db = null;
if (isset($mysqli) && $mysqli instanceof mysqli) $db = $mysqli;
elseif (isset($conn) && $conn instanceof mysqli) $db = $conn;
else die("Không tìm thấy kết nối MySQLi.");

/* flash helper */
function flash($type, $msg)
{
  $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

/* project_id */
$project_id = (int)($_GET['project_id'] ?? 0);
if ($project_id <= 0) {
  flash('error', 'Project ID không hợp lệ.');
  header('Location: /task_management/pages/projects/index.php');
  exit;
}

/* current user */
$currentUserId = (int)($_SESSION['user']['id'] ?? 0);
if ($currentUserId <= 0) {
  flash('error', 'Vui lòng đăng nhập.');
  header('Location: /html/sign-in.html');
  exit;
}

/* load project */
$sql = "SELECT id, name, owner_id FROM projects WHERE id = $project_id LIMIT 1";
$res = mysqli_query($db, $sql);
$project = mysqli_fetch_assoc($res);
mysqli_free_result($res);

if (!$project) {
  flash('error', 'Project không tồn tại.');
  header('Location: /task_management/pages/projects/index.php');
  exit;
}

/* check owner */
if ((int)$project['owner_id'] !== $currentUserId) {
  flash('error', 'Chỉ owner mới được quản lý thành viên.');
  header('Location: /task_management/pages/projects/detail.php?id=' . $project_id);
  exit;
}

/* ================= HANDLE POST ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  /* CHANGE ROLE */
  if ($action === 'change_role') {
    $member_id = (int)($_POST['member_id'] ?? 0);
    $new_role  = $_POST['new_role'] ?? '';

    $allowed = ['editor', 'contributor', 'viewer'];
    if ($member_id <= 0 || !in_array($new_role, $allowed, true)) {
      flash('error', 'Dữ liệu không hợp lệ.');
      header('Location: ' . $_SERVER['REQUEST_URI']);
      exit;
    }

    if ($member_id === (int)$project['owner_id']) {
      flash('error', 'Không thể đổi quyền owner.');
      header('Location: ' . $_SERVER['REQUEST_URI']);
      exit;
    }

    $sql = "
            UPDATE project_members
            SET permission = '$new_role'
            WHERE project_id = $project_id
              AND user_id = $member_id
            LIMIT 1
        ";

    if (mysqli_query($db, $sql) && mysqli_affected_rows($db) > 0) {
      flash('success', 'Cập nhật quyền thành công.');
    } else {
      flash('error', 'Không thể cập nhật quyền.');
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
  }

  /* REMOVE MEMBER */
  if ($action === 'remove_member') {
    $member_id = (int)($_POST['member_id'] ?? 0);

    if ($member_id <= 0) {
      flash('error', 'Thành viên không hợp lệ.');
      header('Location: ' . $_SERVER['REQUEST_URI']);
      exit;
    }

    if ($member_id === (int)$project['owner_id']) {
      flash('error', 'Không thể xóa owner.');
      header('Location: ' . $_SERVER['REQUEST_URI']);
      exit;
    }

    $sql = "
            DELETE FROM project_members
            WHERE project_id = $project_id
              AND user_id = $member_id
            LIMIT 1
        ";

    if (mysqli_query($db, $sql) && mysqli_affected_rows($db) > 0) {
      flash('success', 'Đã xóa thành viên khỏi project.');
    } else {
      flash('error', 'Không thể xóa thành viên.');
    }

    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
  }

  flash('error', 'Hành động không hợp lệ.');
  header('Location: ' . $_SERVER['REQUEST_URI']);
  exit;
}

/* ================= LOAD MEMBERS ================= */
$sql = "
    SELECT pm.user_id, pm.permission, pm.added_at,
           u.name AS user_name, u.email, u.role
    FROM project_members pm
    JOIN users u ON u.id = pm.user_id
    WHERE pm.project_id = $project_id
    ORDER BY FIELD(pm.permission,'owner','editor','contributor','viewer'), u.name
";
$res = mysqli_query($db, $sql);
$members = [];
while ($row = mysqli_fetch_assoc($res)) {
  if ($row['role'] !== 'admin') {
    $members[] = $row;
  }
}
mysqli_free_result($res);

/* flash */
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="utf-8">
  <title>Quản lý thành viên - <?= htmlspecialchars($project['name']) ?></title>
  <link rel="stylesheet" href="/task_management/assets/css/index.css">
  <style>
    .container {
      max-width: 980px;
      margin: 40px auto;
      padding: 22px;
      background: #fff;
      border-radius: 12px
    }

    .table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 14px
    }

    .table th,
    .table td {
      padding: 10px;
      border-bottom: 1px solid #eee
    }

    .btn {
      padding: 8px 12px;
      border-radius: 8px;
      border: 1px solid #1a73e8;
      background: #fff;
      color: #1a73e8
    }

    .btn-primary {
      background: #1a73e8;
      color: #fff;
      border: none
    }

    .btn-danger {
      background: #d9534f;
      color: #fff;
      border: none
    }

    .select-role {
      padding: 6px 8px;
      border-radius: 6px
    }

    .flash-success {
      background: #e6ffed;
      border: 1px solid #a6e6b8;
      padding: 8px 12px
    }

    .flash-error {
      background: #ffecec;
      border: 1px solid #f1a6a6;
      padding: 8px 12px
    }
  </style>
  <script>
    function confirmRemove() {
      return confirm('Bạn có chắc muốn xóa thành viên này?');
    }
  </script>
</head>

<body>
  <div class="container">
    <h2>Quản lý thành viên — <?= htmlspecialchars($project['name']) ?></h2>

    <?php if ($flash): ?>
      <div class="<?= $flash['type'] === 'success' ? 'flash-success' : 'flash-error' ?>">
        <?= htmlspecialchars($flash['msg']) ?>
      </div>
    <?php endif; ?>

    <table class="table">
      <thead>
        <tr>
          <th>Người dùng</th>
          <th>Quyền</th>
          <th>Hành động</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($members)): ?>
          <tr>
            <td colspan="3">Chưa có thành viên.</td>
          </tr>
          <?php else: foreach ($members as $m):
            $uid = (int)$m['user_id'];
            $isOwner = ($uid === (int)$project['owner_id']);
          ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($m['user_name']) ?></strong>
                <div style="font-size:13px;color:#666"><?= htmlspecialchars($m['email']) ?></div>
              </td>
              <td>
                <?= htmlspecialchars($m['permission']) ?>
                <?= $isOwner ? '<strong style="color:#1a73e8">(Owner)</strong>' : '' ?>
              </td>
              <td>
                <?php if ($isOwner): ?>
                  <em>Không thể thay đổi</em>
                <?php else: ?>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="action" value="change_role">
                    <input type="hidden" name="member_id" value="<?= $uid ?>">
                    <select name="new_role" class="select-role">
                      <option value="editor" <?= $m['permission'] == 'editor' ? 'selected' : '' ?>>Editor</option>
                      <option value="contributor" <?= $m['permission'] == 'contributor' ? 'selected' : '' ?>>Contributor</option>
                      <option value="viewer" <?= $m['permission'] == 'viewer' ? 'selected' : '' ?>>Viewer</option>
                    </select>
                    <button class="btn btn-primary">OK</button>
                  </form>

                  <form method="post" style="display:inline" onsubmit="return confirmRemove();">
                    <input type="hidden" name="action" value="remove_member">
                    <input type="hidden" name="member_id" value="<?= $uid ?>">
                    <button class="btn btn-danger">Xóa</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
        <?php endforeach;
        endif; ?>
      </tbody>
    </table>

    <p style="margin-top:18px">
      <a class="btn" href="/task_management/pages/projects/detail.php?id=<?= $project_id ?>">Quay lại</a>
    </p>
  </div>
</body>

</html>