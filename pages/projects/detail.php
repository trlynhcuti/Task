<?php
session_start();
include __DIR__ . '/../../db.php';
include __DIR__ . '/../../includes/auth.php';
include __DIR__ . '/../../includes/header.php';

/* Detect mysqli connection */
$db = null;
if (isset($mysqli) && $mysqli instanceof mysqli) $db = $mysqli;
elseif (isset($conn) && $conn instanceof mysqli) $db = $conn;
else die("Không tìm thấy kết nối MySQLi. Vui lòng kiểm tra db.php.");

/* Get project_id from GET */
$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($project_id <= 0) {
  die("Project ID không hợp lệ.");
}

/* Current user */
$currentUserId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
if (!$currentUserId) {
  header('Location: /html/sign-in.html');
  exit;
}
$cur_user_q = $currentUserId;

/* Load project */
$project_id_q = (int)$project_id;
$sql = "SELECT id, name, description, owner_id, created_at, updated_at FROM projects WHERE id = $project_id_q LIMIT 1";
$res = mysqli_query($db, $sql);
if (!$res) die("Lỗi truy vấn project: " . mysqli_error($db));
$project = mysqli_fetch_assoc($res);
if (!$project) die("Không tìm thấy project.");

/* --- Membership check: allow owner OR any member listed in project_members --- */
$currentPermission = null;
if ((int)$project['owner_id'] === $currentUserId) {
  // owner
  $currentPermission = 'owner';
} else {
  $checkSql = "SELECT permission FROM project_members WHERE project_id = $project_id_q AND user_id = $currentUserId LIMIT 1";
  $checkRes = mysqli_query($db, $checkSql);
  if ($checkRes && ($checkRow = mysqli_fetch_assoc($checkRes))) {
    $currentPermission = $checkRow['permission'] ?? 'viewer';
  } else {
    // Không phải owner và không phải member => không cho truy cập
    header("Location: /task_management/index.php?msg=no_access");
    exit;
  }
}
/* --- end membership check --- */

/* Load project members (for display) */
$sql = "
    SELECT pm.user_id, pm.permission, pm.added_at, u.name AS user_name
    FROM project_members pm
    JOIN users u ON u.id = pm.user_id
    WHERE pm.project_id = $project_id_q
    ORDER BY FIELD(pm.permission,'owner','editor','contributor','viewer'), u.name
";
$res = mysqli_query($db, $sql);
if (!$res) die("Lỗi truy vấn members: " . mysqli_error($db));
$members = [];
while ($row = mysqli_fetch_assoc($res)) $members[] = $row;

/* If owner isn't in members list (some setups don't insert owner into project_members), ensure owner's presence for display */
$ownerPresent = false;
foreach ($members as $m) {
  if ((int)$m['user_id'] === (int)$project['owner_id']) {
    $ownerPresent = true;
    break;
  }
}
if (!$ownerPresent) {
  $owner_id_q = (int)$project['owner_id'];
  $r = mysqli_query($db, "SELECT id, name FROM users WHERE id = $owner_id_q LIMIT 1");
  if ($r) {
    $ownerRow = mysqli_fetch_assoc($r);
    if ($ownerRow) {
      array_unshift($members, [
        'user_id' => $ownerRow['id'],
        'permission' => 'owner',
        'added_at' => null,
        'user_name' => $ownerRow['name']
      ]);
    }
  }
}

/* Is current user the owner? (boolean for UI) */
$isOwner = ((int)$currentUserId === (int)$project['owner_id']);

/* Load tasks (no assigned_to / status) — join users to get creator name */
$sql = "
    SELECT t.id, t.title, t.description, t.created_at, t.updated_at, t.created_by,
           u.name AS creator_name
    FROM tasks t
    LEFT JOIN users u ON u.id = t.created_by
    WHERE t.project_id = $project_id_q
    ORDER BY t.updated_at DESC, t.created_at DESC
";
$res = mysqli_query($db, $sql);
if (!$res) die("Lỗi truy vấn tasks: " . mysqli_error($db));
$tasks = [];
while ($row = mysqli_fetch_assoc($res)) $tasks[] = $row;

/* Load comments — simplified: comments tied to project via project_id (no task_id) */
$sql = "
    SELECT c.id, c.user_id, c.content, c.created_at, u.name AS user_name
    FROM comments c
    JOIN users u ON u.id = c.user_id
    WHERE c.project_id = $project_id_q
    ORDER BY c.created_at DESC
    LIMIT 300
";
$res = mysqli_query($db, $sql);
if (!$res) die("Lỗi truy vấn comments: " . mysqli_error($db));
$comments = [];
while ($row = mysqli_fetch_assoc($res)) $comments[] = $row;

/* Handle POST comment — insert only (project_id, user_id, content, created_at) */
/* only users with permission 'contributor' (or editor/owner) are allowed to comment */
$canComment = in_array($currentPermission, ['owner', 'editor', 'contributor'], true);

$errorMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment']) && $canComment) {
  $commentText = trim($_POST['comment']);
  if ($commentText === '') {
    $errorMsg = "Bình luận rỗng.";
  } else {
    $commentEsc = mysqli_real_escape_string($db, $commentText);
    $sql = "
            INSERT INTO comments (project_id, user_id, content, created_at)
            VALUES ($project_id_q, $cur_user_q, '$commentEsc', NOW())
        ";
    if (mysqli_query($db, $sql)) {
      header("Location: " . $_SERVER['REQUEST_URI']);
      exit;
    } else {
      $errorMsg = "Lỗi lưu bình luận: " . mysqli_error($db);
    }
  }
}

/* Helper for avatar placeholder */
function avatar_placeholder($name)
{
  return '/task_management/assets/images/default-avatar.png';
}

/* Permission for editing/deleting tasks: only owner or editor */
$canModifyTasks = in_array($currentPermission, ['owner', 'editor'], true);
?>
<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Chi tiết dự án - <?= htmlspecialchars($project['name']) ?></title>
  <!-- <link rel="stylesheet" href="/task_management/assets/css/index.css"> -->
  <style>
    body {
      margin-top: 60px;
    }

    .app-shell {
      padding: 32px;
      max-width: 1650px;
      margin: auto;
    }

    .grid {
      display: grid;
      grid-template-columns: 68% 32%;
      grid-template-rows: auto 1fr;
      gap: 32px;
      min-height: calc(100vh - 70px);
      box-sizing: border-box;
    }

    .project-card,
    .task-list,
    .members-box,
    .comments-box {
      background: #fff;
      border-radius: 18px;
      padding: 28px;
      border: 1px solid #d5d5d5;
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
    }

    .btn-row {
      display: flex;
      gap: 12px;
      margin-top: 12px;
      flex-wrap: wrap;
    }

    .btn {
      padding: 10px 14px;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 600;
      text-decoration: none;
      cursor: pointer;
      border: 2px solid #1a73e8;
      color: #1a73e8;
      background: #fff;
    }

    .btn-primary {
      background: #1a73e8;
      color: #fff;
      border: none;
      padding: 10px 14px;
    }

    .btn-danger {
      background: #d9534f;
      color: #fff;
      border: none;
      padding: 10px 12px;
      border-radius: 8px;
      cursor: pointer;
    }

    .member {
      display: flex;
      gap: 18px;
      align-items: center;
      padding: 16px 0;
      border-bottom: 1px solid #e6e6e6;
      font-size: 17px;
    }

    .task-item {
      padding: 18px 0;
      border-bottom: 1px solid #ececec;
      font-size: 17px;
      display: flex;
      justify-content: space-between;
      gap: 16px;
      align-items: center;
    }

    textarea {
      width: 100%;
      min-height: 110px;
      padding: 14px;
      font-size: 16px;
      border-radius: 14px;
      border: 1px solid #ccc;
    }

    .task-meta {
      font-size: 14px;
      color: #777;
      margin-top: 6px;
    }

    form.inline {
      display: inline-block;
      margin: 0;
    }

    @media (max-width:1200px) {
      .grid {
        grid-template-columns: 1fr;
        grid-template-rows: auto auto auto auto;
      }

      .project-card,
      .members-box,
      .task-list,
      .comments-box {
        grid-column: 1 / -1;
        max-height: none;
      }
    }
  </style>
  <script>
    function confirmDeleteTask(form) {
      if (confirm('Bạn có chắc muốn xóa task này? Hành động không thể hoàn tác.')) {
        return true;
      }
      return false;
    }
  </script>
</head>

<body>
  <div class="app-shell">
    <div class="grid">

      <!-- PROJECT -->
      <div class="project-card">
        <h2 style="margin:0 0 6px 0;"><?= htmlspecialchars($project['name']) ?></h2>
        <p style="margin-top:10px; color:#444;">Mô tả: <?= nl2br(htmlspecialchars($project['description'] ?? '')) ?></p>
        <div class="small-muted">Cập nhật: <?= htmlspecialchars(date('d/m/Y H:i', strtotime($project['updated_at'] ?? $project['created_at']))) ?></div>

        <div class="btn-row">
          <?php if ($isOwner): ?>
            <a class="btn" href="/task_management/pages/projects/add_member.php?project_id=<?= urlencode($project_id) ?>">Thêm thành viên</a>
            <a class="btn" href="/task_management/pages/tasks/create.php?project_id=<?= urlencode($project_id) ?>">Tạo task</a>
            <a class="btn" href="/task_management/pages/projects/manage_members.php?project_id=<?= urlencode($project_id) ?>">Sửa thành viên</a>
          <?php endif; ?>
        </div>
      </div>

      <!-- MEMBERS -->
      <div class="members-box">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
          <strong>Thành viên</strong>
          <div class="small-muted"><?= count($members) ?> thành viên</div>
        </div>

        <?php if (empty($members)): ?>
          <div class="small-muted">Chưa có thành viên.</div>
        <?php else: ?>
          <?php foreach ($members as $m): ?>
            <div class="member">
              <div>
                <div style="font-weight:600;"><?= htmlspecialchars($m['user_name']) ?></div>
                <div class="small-muted"><?= htmlspecialchars($m['permission']) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- TASKS -->
      <div class="task-list">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
          <strong>Tasks</strong>
          <div class="small-muted"><?= count($tasks) ?> task</div>
        </div>

        <?php if (empty($tasks)): ?>
          <div class="small-muted">Chưa có task nào trong project này.</div>
        <?php else: ?>
          <?php foreach ($tasks as $t): ?>
            <div class="task-item">
              <div style="flex:1;">
                <h3 style="margin:0;"><?= htmlspecialchars($t['title']) ?></h3>
                <div class="task-meta">
                  Người tạo: <?= htmlspecialchars($t['creator_name'] ?? '') ?>
                </div>
                <?php if (!empty($t['description'])): ?>
                  <div style="margin-top:6px; color:#555;">Nội dung: <?= nl2br(htmlspecialchars($t['description'])) ?></div>
                <?php endif; ?>
                <?= htmlspecialchars(date('d/m H:i', strtotime($t['updated_at'] ?? $t['created_at']))) ?>
              </div>

              <div style="min-width:150px; text-align:right; display:flex; gap:8px; align-items:center; justify-content:flex-end; flex-direction: column; ">
                <?php if ($canModifyTasks): ?>
                  <!-- Edit button -->
                  <a class="btn" href="/task_management/pages/tasks/edit.php?id=<?= urlencode($t['id']) ?>">Sửa</a>

                  <!-- Delete form (POST) -->
                  <form class="inline" method="post" action="/task_management/pages/tasks/delete.php" onsubmit="return confirmDeleteTask(this);">
                    <input type="hidden" name="task_id" value="<?= htmlspecialchars($t['id']) ?>">
                    <input type="hidden" name="project_id" value="<?= htmlspecialchars($project_id_q) ?>">
                    <button type="submit" class="btn-danger">Xóa</button>
                  </form>
                <?php else: ?>
                  <span class="small-muted">Không có quyền</span>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- COMMENTS -->
      <div class="comments-box">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
          <strong>Bình luận</strong>
          <div class="small-muted"><?= count($comments) ?> bình luận</div>
        </div>

        <?php if (empty($comments)): ?>
          <div class="small-muted">Chưa có bình luận nào.</div>
        <?php else: ?>
          <?php foreach ($comments as $c): ?>
            <?php
            $isCommentOwner = ($c['user_id'] == $currentUserId);
            ?>

            <div class="comment" style="border-bottom:1px dashed #ddd;padding:10px 0;">
              <div style="display:flex;justify-content:space-between;">
                <strong><?= htmlspecialchars($c['user_name']) ?></strong>
                <small><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></small>
              </div>

              <div><?= nl2br(htmlspecialchars($c['content'])) ?></div>

              <div style="margin-top:6px;">
                <?php if ($isCommentOwner): ?>
                  <a class="btn" href="/task_management/pages/comments/edit.php?id=<?= $c['id'] ?>">Sửa</a>
                <?php endif; ?>

                <?php if ($isCommentOwner || $isOwner): ?>
                  <form class="inline" method="post"
                    action="/task_management/pages/comments/delete.php"
                    onsubmit="return confirm('Xóa comment này?');">
                    <input type="hidden" name="comment_id" value="<?= $c['id'] ?>">
                    <input type="hidden" name="project_id" value="<?= $project_id_q ?>">
                    <button class="btn-danger">Xóa</button>
                  </form>
                <?php endif; ?>
              </div>
            </div>

          <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($errorMsg)): ?>
          <div class="small-muted" style="color:#b00020; margin-top:8px;"><?= htmlspecialchars($errorMsg) ?></div>
        <?php endif; ?>

        <?php if ($canComment): ?>
          <form method="post" style="margin-top:8px;">
            <textarea name="comment" placeholder="Viết bình luận..." required style="width:100%;min-height:70px;padding:8px;"></textarea>
            <div style="display:flex; gap:8px; margin-top:6px; align-items:center;">
              <button class="btn btn-primary" type="submit">Gửi</button>
            </div>
          </form>
        <?php else: ?>
          <div class="small-muted" style="margin-top:8px;">Bạn không có quyền viết bình luận trong dự án này.</div>
        <?php endif; ?>

      </div>

    </div>
  </div>
</body>

</html>