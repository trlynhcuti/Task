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

// Lấy project_id
$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($project_id <= 0) {
    die("Project ID không hợp lệ.");
}

// Lấy user hiện tại
$currentUserId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
if (!$currentUserId) {
    header('Location: /html/sign-in.html');
    exit;
}

/* Lấy project */
$project_id_q = (int)$project_id;
$sql = "SELECT id, name, description, owner_id, created_at, updated_at FROM projects WHERE id = $project_id_q LIMIT 1";
$res = mysqli_query($db, $sql);
if (!$res) {
    die("Lỗi truy vấn project: " . mysqli_error($db));
}
$project = mysqli_fetch_assoc($res);
if (!$project) die("Không tìm thấy project.");

/* Lấy members */
$sql = "SELECT pm.user_id, pm.permission, pm.added_at, u.name AS user_name
        FROM project_members pm
        JOIN users u ON u.id = pm.user_id
        WHERE pm.project_id = $project_id_q
        ORDER BY FIELD(pm.permission,'owner','editor','contributor','viewer'), u.name";
$res = mysqli_query($db, $sql);
if (!$res) {
    die("Lỗi truy vấn members: " . mysqli_error($db));
}
$members = [];
while ($row = mysqli_fetch_assoc($res)) {
    $members[] = $row;
}

/* Quyền của current user (xác định từ project_members nếu có) */
$currentPermission = null;
foreach ($members as $m) {
    if ((int)$m['user_id'] === (int)$currentUserId) {
        $currentPermission = $m['permission'];
        break;
    }
}

/* Nếu current user là owner của project thì đảm bảo quyền owner */
if ((int)$currentUserId === (int)$project['owner_id']) {
    $currentPermission = 'owner';

    // Nếu owner chưa có trong $members thì lấy tên owner và thêm vào để hiển thị
    $ownerPresent = false;
    foreach ($members as $m) {
        if ((int)$m['user_id'] === (int)$project['owner_id']) { $ownerPresent = true; break; }
    }
    if (!$ownerPresent) {
        $owner_id_q = (int)$project['owner_id'];
        $sql = "SELECT id, name FROM users WHERE id = $owner_id_q LIMIT 1";
        $res = mysqli_query($db, $sql);
        if ($res) {
            $ownerRow = mysqli_fetch_assoc($res);
            if ($ownerRow) {
                // thêm owner vào đầu mảng members
                array_unshift($members, [
                    'user_id' => $ownerRow['id'],
                    'permission' => 'owner',
                    'added_at' => null,
                    'user_name' => $ownerRow['name']
                ]);
            }
        }
    }
}

/* Lấy tasks */
$sql = "SELECT id, title, description, assigned_to, status, created_at, updated_at
        FROM tasks
        WHERE project_id = $project_id_q
        ORDER BY updated_at DESC, created_at DESC";
$res = mysqli_query($db, $sql);
if (!$res) {
    die("Lỗi truy vấn tasks: " . mysqli_error($db));
}
$tasks = [];
while ($row = mysqli_fetch_assoc($res)) {
    $tasks[] = $row;
}

$cur_user_q = (int)$currentUserId;
$sql = "
    SELECT c.id, c.task_id, c.user_id, c.content, c.created_at, u.name AS user_name
    FROM comments c
    JOIN users u ON u.id = c.user_id
    LEFT JOIN tasks t ON t.id = c.task_id
    LEFT JOIN project_members pm ON pm.user_id = c.user_id AND pm.project_id = $project_id_q
    WHERE (pm.permission IN ('owner','editor','contributor') OR c.user_id = $cur_user_q)
      AND (c.task_id IS NULL OR t.project_id = $project_id_q)
    ORDER BY c.created_at DESC
    LIMIT 300
";
$res = mysqli_query($db, $sql);
if (!$res) {
    die("Lỗi truy vấn comments: " . mysqli_error($db));
}
$comments = [];
while ($row = mysqli_fetch_assoc($res)) {
    $comments[] = $row;
}

/* Xử lý POST comment */
/* Người tạo project (owner) luôn được comment ngay cả khi không có record trong project_members */
$canComment = in_array($currentPermission, ['owner','editor','contributor'], true)
              || ((int)$currentUserId === (int)$project['owner_id']);
$errorMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment']) && $canComment) {
    $commentText = trim($_POST['comment']);
    $task_id = isset($_POST['task_id']) && is_numeric($_POST['task_id']) ? (int)$_POST['task_id'] : 0;

    // kiểm tra task tồn tại và thuộc project nếu có
    if ($task_id > 0) {
        $task_id_q = (int)$task_id;
        $sql = "SELECT id FROM tasks WHERE id = $task_id_q AND project_id = $project_id_q LIMIT 1";
        $chkRes = mysqli_query($db, $sql);
        if (!$chkRes) {
            $errorMsg = "Lỗi kiểm tra task: " . mysqli_error($db);
        } else {
            $exists = mysqli_fetch_assoc($chkRes);
            if (!$exists) $errorMsg = "Task không tồn tại hoặc không thuộc project này.";
        }
    }

    if (empty($errorMsg) && $commentText !== '') {
        $commentEsc = mysqli_real_escape_string($db, $commentText);

        // *** SỬA: luôn chèn project_id vào comments ***
        if ($task_id > 0) {
            $task_id_q = (int)$task_id;
            $sql = "INSERT INTO comments (task_id, project_id, user_id, content, created_at)
                    VALUES ($task_id_q, $project_id_q, $cur_user_q, '$commentEsc', NOW())";
        } else {
            $sql = "INSERT INTO comments (task_id, project_id, user_id, content, created_at)
                    VALUES (NULL, $project_id_q, $cur_user_q, '$commentEsc', NOW())";
        }

        $insRes = mysqli_query($db, $sql);
        if ($insRes) {
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        } else {
            $errorMsg = "Lưu bình luận thất bại: " . mysqli_error($db);
        }
    } elseif (empty($errorMsg)) {
        $errorMsg = "Bình luận rỗng.";
    }
}

/* Lấy task options cho dropdown */
$sql = "SELECT id, title FROM tasks WHERE project_id = $project_id_q ORDER BY updated_at DESC";
$res = mysqli_query($db, $sql);
if (!$res) {
    die("Lỗi truy vấn task options: " . mysqli_error($db));
}
$taskOptions = [];
while ($row = mysqli_fetch_assoc($res)) {
    $taskOptions[] = $row;
}

/* placeholder avatar */
function avatar_placeholder($name) {
    return '/task_management/assets/images/default-avatar.png';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Chi tiết dự án - <?=htmlspecialchars($project['name'])?></title>
  <link rel="stylesheet" href="/task_management/assets/css/index.css">
  <style>
    /* Tổng thể */
    body{
      margin-top: 60px;
    }
    .app-shell { 
        padding: 32px; 
        max-width: 1650px; 
        margin: auto;
    }

    /* GRID LỚN: 70% trái, 30% phải */
    .grid {
      display: grid;
      grid-template-columns: 68% 32%;
      grid-template-rows: auto 1fr;
      gap: 32px;
      min-height: calc(100vh - 70px);
      box-sizing: border-box;
    }

    /* Style chung cho tất cả box */
    .project-card, .task-list, .members-box, .comments-box {
        background:#fff;
        border-radius:18px;
        padding:28px;
        border:1px solid #d5d5d5;
        box-shadow: 0 6px 20px rgba(0,0,0,0.08);
    }

    /* --- Project Box (Top-left) --- */
    .project-card { 
        grid-column: 1 / 2;
        grid-row: 1 / 2;
    }

    .project-card h2 { 
        font-size:30px; 
        margin-bottom:14px;
        font-weight:700;
    }

    .project-card p {
        font-size:17px;
        line-height:1.6;
        margin-top:14px;
        color:#444;
    }

    /* Buttons */
    .btn-row { display:flex; gap:12px; margin-top:12px; flex-wrap:wrap; }

    .btn {
        padding:14px 20px;
        border-radius:12px;
        font-size:16px;
        font-weight:600;
        text-decoration:none;
        cursor:pointer;
    }
    .btn-outline { 
        border:2px solid #1a73e8; 
        color:#1a73e8;
        background:#fff;
    }
    .btn-outline:hover {
        background:#1a73e8;
        color:white;
    }
    .btn-primary {
        background:#1a73e8;
        color:white;
        border:none;
    }

    /* --- Members Box (Top-right, "to bản") --- */
    .members-box { 
        grid-column: 2 / 3;
        grid-row: 1 / 2;
        max-height: 500px;
        overflow:auto;
    }

    .members-box strong {
        font-size:22px;
        font-weight:700;
    }

    .member {
        display:flex;
        gap:18px;
        align-items:center;
        padding:16px 0;
        border-bottom:1px solid #e6e6e6;
        font-size:17px;
    }

    .member img {
        width:60px;
        height:60px;
        border-radius:50%;
        object-fit:cover;
        border:2px solid #ccc;
    }

    .member div {
        font-size:16px;
    }

    /* --- Tasks Box dưới Project (to bản) --- */
    .task-list { 
        grid-column: 1 / 2;
        grid-row: 2 / 3;
        min-height: 600px;
        overflow:auto;
    }

    .task-list strong {
        font-size:22px;
    }

    .task-item {
        padding:18px 0;
        border-bottom:1px solid #ececec;
        font-size:17px;
        display:flex;
        justify-content:space-between;
        gap:16px;
    }

    .task-title {
        font-size:20px;
        font-weight:700;
    }

    .task-meta {
        font-size:15px;
        color:#777;
    }

    /* --- Comments Box dưới Members (TO NHỮNG BOX CÒN LẠI) --- */
    .comments-box { 
        grid-column: 2 / 3;
        grid-row: 2 / 3;
        max-height: 520px;
        overflow:auto;
    }

    .comments-box strong {
        font-size:22px;
    }

    .comment {
        display:flex;
        gap:16px;
        padding:18px 0;
        border-bottom:1px dashed #ddd;
        font-size:16px;
    }

    .comment img {
        width:55px;
        height:55px;
        border-radius:50%;
        border:2px solid #ccc;
    }

    .comment .meta {
        font-size:14px;
        color:#777;
    }

    /* Comment form đẹp hơn */
    textarea {
        width:100%;
        min-height:110px;
        padding:14px;
        font-size:16px;
        border-radius:14px;
        border:1px solid #ccc;
    }

    select {
        padding:10px;
        border-radius:10px;
        font-size:15px;
    }

    @media (max-width:1200px) {
      .grid { 
          grid-template-columns: 1fr;
          grid-template-rows: auto auto auto auto;
      }
      .project-card, .members-box, .task-list, .comments-box {
          grid-column: 1 / -1;
          max-height:none;
      }
    }
  </style>


</head>
<body>
  <div class="app-shell">
    <div class="grid">

      <!-- PROJECT (top-left, now spans same width as tasks) -->
      <div class="project-card">
        <h2 style="margin:0 0 6px 0;"><?=htmlspecialchars($project['name'])?></h2>
        <div class="small-muted">Cập nhật: <?=htmlspecialchars(date('d/m/Y H:i', strtotime($project['updated_at'] ?? $project['created_at'])))?></div>
        <p style="margin-top:10px; color:#444;"><?=nl2br(htmlspecialchars($project['description'] ?? ''))?></p>

        <div class="btn-row">
          <a class="btn btn-outline" href="/task_management/pages/projects/add_member.php?project_id=<?=urlencode($project_id)?>">Thêm thành viên</a>
          <a class="btn btn-outline" href="/task_management/pages/tasks/create.php?project_id=<?=urlencode($project_id)?>">Tạo task</a>
          <a class="btn btn-outline" href="/task_management/pages/projects/manage_members.php?project_id=<?=urlencode($project_id)?>">Sửa thành viên</a>
        </div>
      </div>

      <!-- MEMBERS (top-right) -->
      <div class="members-box">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
          <strong>Thành viên</strong>
          <div class="small-muted"><?=count($members)?> thành viên</div>
        </div>

        <?php if (empty($members)): ?>
          <div class="small-muted">Chưa có thành viên.</div>
        <?php else: ?>
          <?php foreach ($members as $m): ?>
            <div class="member">
              <div>
                <div style="font-weight:600;"><?=htmlspecialchars($m['user_name'])?></div>
                <div class="small-muted"><?=htmlspecialchars($m['permission'])?></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- TASKS (spans left+center = 70% width) directly below project -->
      <div class="task-list">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
          <strong>Tasks</strong>
          <div class="small-muted"><?=count($tasks)?> task</div>
        </div>

        <?php if (empty($tasks)): ?>
          <div class="small-muted">Chưa có task nào trong project này.</div>
        <?php else: ?>
          <?php foreach ($tasks as $t): ?>
            <div class="task-item">
              <div style="flex:1;">
                <h3><?=htmlspecialchars($t['title'])?></h3>
                <div class="small-muted"><?=htmlspecialchars($t['status'])?> • <?=htmlspecialchars(date('d/m H:i', strtotime($t['updated_at'] ?? $t['created_at'])))?></div>
                <?php if (!empty($t['description'])): ?>
                  <div style="margin-top:6px; color:#555;"><?=nl2br(htmlspecialchars($t['description']))?></div>
                <?php endif; ?>
              </div>
              <div style="min-width:60px; text-align:right;">
                <a class="btn btn-outline" href="/task_management/pages/tasks/open.php?id=<?=urlencode($t['id'])?>">Mở</a>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- COMMENTS (right column under members) -->
      <div class="comments-box">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
          <strong>Bình luận</strong>
          <div class="small-muted"><?=count($comments)?> bình luận</div>
        </div>

        <?php if (empty($comments)): ?>
          <div class="small-muted">Chưa có bình luận nào.</div>
        <?php else: ?>
          <?php foreach ($comments as $c): ?>
            <div class="comment">
              <div style="flex:1;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                  <div style="font-weight:600;"><?=htmlspecialchars($c['user_name'])?></div>
                  <div class="small-muted"><?=htmlspecialchars(date('d/m/Y H:i', strtotime($c['created_at'])))?></div>
                </div>
                <div style="margin-top:6px;"><?=nl2br(htmlspecialchars($c['content']))?></div>
                <?php if (!empty($c['task_id'])): ?>
                  <div class="small-muted" style="margin-top:6px;">(Task ID: <?=htmlspecialchars($c['task_id'])?>)</div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($errorMsg)): ?>
          <div class="small-muted" style="color:#b00020; margin-top:8px;"><?=htmlspecialchars($errorMsg)?></div>
        <?php endif; ?>

        <?php if ($canComment): ?>
          <form method="post" style="margin-top:8px;">
            <textarea name="comment" placeholder="Viết bình luận..." required style="width:100%;min-height:70px;padding:8px;"></textarea>
            <div style="display:flex; gap:8px; margin-top:6px; align-items:center;">
              <select name="task_id" style="min-width:160px; padding:6px;">
                <option value="0">Bình luận cho toàn project</option>
                <?php foreach ($taskOptions as $opt): ?>
                  <option value="<?=htmlspecialchars($opt['id'])?>"><?=htmlspecialchars($opt['title'])?></option>
                <?php endforeach; ?>
              </select>
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
