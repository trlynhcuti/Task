<?php
// admin_reports.php
session_start();
include __DIR__ . '../../../db.php';
include __DIR__ . '/../../includes/header.php';

$db = null;
if (isset($mysqli) && $mysqli instanceof mysqli) {
    $db = $mysqli;
} elseif (isset($conn) && $conn instanceof mysqli) {
    $db = $conn;
} else {
    die("Không tìm thấy kết nối MySQLi. Vui lòng kiểm tra db.php.");
}

// check admin
if (empty($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin') {
    die("Bạn cần đăng nhập với quyền admin để truy cập trang này.");
}

$flash_success = '';
$flash_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'update_status' && isset($_POST['report_id'], $_POST['status'])) {
        $rid = intval($_POST['report_id']);
        $status = $_POST['status'];
        // allow only specific statuses
        $allowed = ['open', 'reviewed', 'closed'];
        if (!in_array($status, $allowed, true)) {
            $flash_error = "Trạng thái không hợp lệ.";
        } else {
            $stmt = $db->prepare("UPDATE reports SET status = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('si', $status, $rid);
                if ($stmt->execute()) {
                    $flash_success = "Cập nhật trạng thái thành công.";
                } else {
                    $flash_error = "Lỗi khi cập nhật: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $flash_error = "Lỗi chuẩn bị truy vấn: " . $db->error;
            }
        }
    } elseif ($action === 'delete_report' && isset($_POST['report_id'])) {
        $rid = intval($_POST['report_id']);
        $stmt = $db->prepare("DELETE FROM reports WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $rid);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $flash_success = "Đã xóa báo cáo.";
                } else {
                    $flash_error = "Báo cáo không tồn tại hoặc đã bị xóa.";
                }
            } else {
                $flash_error = "Lỗi xóa báo cáo: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $flash_error = "Lỗi chuẩn bị truy vấn: " . $db->error;
        }
    } else {
        $flash_error = "Hành động không hợp lệ.";
    }
}

$reports = [];
$sql = "
    SELECT r.id, r.reporter_id, r.reported_user_id, r.project_id, r.task_id, r.reason, r.status, r.created_at,
           rep.name AS reporter_name, rpt.name AS reported_name
    FROM reports r
    LEFT JOIN users rep ON r.reporter_id = rep.id
    LEFT JOIN users rpt ON r.reported_user_id = rpt.id
    ORDER BY r.created_at DESC
";
$res = mysqli_query($db, $sql);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $reports[] = $row;
    }
    mysqli_free_result($res);
} else {
    $flash_error = "Lỗi khi truy vấn báo cáo: " . mysqli_error($db);
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Quản lý báo cáo người dùng</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {margin: 0px; background:#f7f9fc; padding: 0;}
    .card-header { display:flex; align-items:center; justify-content:space-between; }
    .reason-trunc { max-width:420px; display:inline-block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; vertical-align:middle; }
    td, th { vertical-align: middle !important; }
  </style>
</head>
<body class="">
  <div class="container">
    <div class="card shadow-sm">
      <div class="card-header">
        <h4 class="mb-0">Danh sách báo cáo người dùng</h4>
        <small class="text-muted">Tổng: <?= count($reports) ?></small>
      </div>
      <div class="card-body">
        <?php if ($flash_success): ?>
          <div class="alert alert-success"><?= htmlspecialchars($flash_success) ?></div>
        <?php endif; ?>
        <?php if ($flash_error): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($flash_error) ?></div>
        <?php endif; ?>

        <div class="table-responsive">
        <table class="table table-hover table-bordered align-middle">
          <thead class="table-light">
            <tr>
              <th style="width:60px">#</th>
              <th>Reporter</th>
              <th>Reported</th>
              <th>Project</th>
              <th>Task</th>
              <th>Reason</th>
              <th style="width:120px">Status</th>
              <th style="width:150px">Created At</th>
              <th style="width:200px">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($reports)): ?>
              <tr><td colspan="9" class="text-center">Chưa có báo cáo.</td></tr>
            <?php else: ?>
              <?php foreach ($reports as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['id']) ?></td>
                  <td>
                    <?= htmlspecialchars($r['reporter_name'] ?? ('ID '.$r['reporter_id'])) ?>
                    <div class="small text-muted">#<?= htmlspecialchars($r['reporter_id']) ?></div>
                  </td>
                  <td>
                    <?= htmlspecialchars($r['reported_name'] ?? ('ID '.$r['reported_user_id'])) ?>
                    <div class="small text-muted">#<?= htmlspecialchars($r['reported_user_id']) ?></div>
                  </td>
                  <td>
                    <span class="text-muted">#<?= htmlspecialchars($r['project_id'] ?: '-') ?></span>
                  </td>
                  <td>
                    <span class="text-muted">#<?= htmlspecialchars($r['task_id'] ?: '-') ?></span>
                  </td>
                  <td>
                    <span class="reason-trunc"><?= htmlspecialchars($r['reason']) ?></span>
                    <button class="btn btn-link btn-sm p-0 ms-2" type="button"
                            onclick="openReasonModal(<?= htmlspecialchars(json_encode($r['id'], JSON_HEX_APOS|JSON_HEX_QUOT)) ?>, <?= htmlspecialchars(json_encode($r['reason'], JSON_HEX_APOS|JSON_HEX_QUOT)) ?>)">
                      Xem
                    </button>
                  </td>
                  <td>
                    <?php
                      $s = $r['status'] ?? 'open';
                      $badge = 'secondary';
                      if ($s === 'open') $badge = 'warning';
                      if ($s === 'reviewed') $badge = 'info';
                      if ($s === 'closed') $badge = 'success';
                    ?>
                    <span class="badge bg-<?= $badge ?>"><?= htmlspecialchars(ucfirst($s)) ?></span>
                  </td>
                  <td>
                    <?= htmlspecialchars($r['created_at']) ?>
                  </td>
                  <td>
                    <div class="d-flex gap-2">
                      <!-- Update status form -->
                      <form method="post" class="d-inline">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="report_id" value="<?= htmlspecialchars($r['id']) ?>">
                        <select name="status" class="form-select form-select-sm" style="width:140px; display:inline-block;">
                          <option value="open" <?= ($r['status']=='open')? 'selected':'' ?>>Open</option>
                          <option value="reviewed" <?= ($r['status']=='reviewed')? 'selected':'' ?>>Reviewed</option>
                          <option value="closed" <?= ($r['status']=='closed')? 'selected':'' ?>>Closed</option>
                        </select>
                        <button type="submit" class="btn btn-sm btn-outline-primary ms-1">Cập nhật</button>
                      </form>

                      <!-- Delete -->
                      <form method="post" class="d-inline" onsubmit="return confirm('Bạn có muốn xóa báo cáo #<?= htmlspecialchars($r['id']) ?>?')">
                        <input type="hidden" name="action" value="delete_report">
                        <input type="hidden" name="report_id" value="<?= htmlspecialchars($r['id']) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Xóa</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
        </div>

      </div>
    </div>
  </div>

  <!-- Modal: show full reason -->
  <div class="modal fade" id="reasonModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Chi tiết báo cáo <span id="reasonModalId"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <pre id="reasonModalContent" style="white-space:pre-wrap;"></pre>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const reasonModal = new bootstrap.Modal(document.getElementById('reasonModal'));
    function openReasonModal(id, reason) {
      document.getElementById('reasonModalId').textContent = '#' + id;
      document.getElementById('reasonModalContent').textContent = reason || '';
      reasonModal.show();
    }
  </script>
</body>
</html>
