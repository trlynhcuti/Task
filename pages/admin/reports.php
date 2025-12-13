<?php
// admin_reports.php
session_start();

include __DIR__ . '/../../db.php';
include __DIR__ . '/../../includes/header.php';

/* detect mysqli connection */
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

$flash_success = '';
$flash_error   = '';

/* ================= HANDLE POST ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    /* UPDATE STATUS */
    if ($action === 'update_status' && isset($_POST['report_id'], $_POST['status'])) {
        $rid = (int) $_POST['report_id'];
        $status = $_POST['status'];

        // whitelist status
        $allowed = ['open', 'reviewed', 'closed'];
        if (!in_array($status, $allowed, true)) {
            $flash_error = "Trạng thái không hợp lệ.";
        } else {
            $sql = "
                UPDATE reports 
                SET status = '$status'
                WHERE id = $rid
            ";

            if (mysqli_query($db, $sql)) {
                if (mysqli_affected_rows($db) > 0) {
                    $flash_success = "Cập nhật trạng thái thành công.";
                } else {
                    $flash_error = "Không có thay đổi hoặc báo cáo không tồn tại.";
                }
            } else {
                $flash_error = "Lỗi cập nhật: " . mysqli_error($db);
            }
        }
    }

    /* DELETE REPORT */
    elseif ($action === 'delete_report' && isset($_POST['report_id'])) {
        $rid = (int) $_POST['report_id'];

        $sql = "DELETE FROM reports WHERE id = $rid LIMIT 1";

        if (mysqli_query($db, $sql)) {
            if (mysqli_affected_rows($db) > 0) {
                $flash_success = "Đã xóa báo cáo.";
            } else {
                $flash_error = "Báo cáo không tồn tại hoặc đã bị xóa.";
            }
        } else {
            $flash_error = "Lỗi xóa báo cáo: " . mysqli_error($db);
        }
    } 
    else {
        $flash_error = "Hành động không hợp lệ.";
    }
}

/* ================= LOAD REPORTS ================= */
$reports = [];

$sql = "
    SELECT r.id, r.reporter_id, r.reported_user_id,
           r.project_id, r.task_id, r.reason,
           r.status, r.created_at,
           rep.name AS reporter_name,
           rpt.name AS reported_name
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
    $flash_error = "Lỗi truy vấn báo cáo: " . mysqli_error($db);
}
?>

<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Quản lý báo cáo</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body { background:#f7f9fc; }
.card-header { display:flex; justify-content:space-between; align-items:center; }
.reason-trunc {
    max-width:420px;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}
td, th { vertical-align: middle !important; }
</style>
</head>

<body>
<div class="container mt-4">
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
<table class="table table-bordered table-hover">
<thead class="table-light">
<tr>
    <th>#</th>
    <th>Reporter</th>
    <th>Reported</th>
    <th>Project</th>
    <th>Task</th>
    <th>Reason</th>
    <th>Status</th>
    <th>Created At</th>
    <th>Actions</th>
</tr>
</thead>

<tbody>
<?php if (empty($reports)): ?>
<tr>
    <td colspan="9" class="text-center">Chưa có báo cáo</td>
</tr>
<?php else: ?>
<?php foreach ($reports as $r): ?>
<tr>
    <td><?= $r['id'] ?></td>

    <td>
        <?= htmlspecialchars($r['reporter_name'] ?? 'ID '.$r['reporter_id']) ?>
        <div class="small text-muted">#<?= $r['reporter_id'] ?></div>
    </td>

    <td>
        <?= htmlspecialchars($r['reported_name'] ?? 'ID '.$r['reported_user_id']) ?>
        <div class="small text-muted">#<?= $r['reported_user_id'] ?></div>
    </td>

    <td>#<?= $r['project_id'] ?: '-' ?></td>
    <td>#<?= $r['task_id'] ?: '-' ?></td>

    <td>
        <span class="reason-trunc"><?= htmlspecialchars($r['reason']) ?></span>
        <button class="btn btn-link btn-sm p-0 ms-2"
            onclick="openReasonModal(<?= (int)$r['id'] ?>, <?= json_encode($r['reason']) ?>)">
            Xem
        </button>
    </td>

    <td>
        <?php
        $badge = 'secondary';
        if ($r['status'] === 'open') $badge = 'warning';
        if ($r['status'] === 'reviewed') $badge = 'info';
        if ($r['status'] === 'closed') $badge = 'success';
        ?>
        <span class="badge bg-<?= $badge ?>">
            <?= ucfirst($r['status']) ?>
        </span>
    </td>

    <td><?= $r['created_at'] ?></td>

    <td>
        <div class="d-flex gap-2">
            <form method="post">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                <select name="status" class="form-select form-select-sm d-inline" style="width:130px">
                    <option value="open" <?= $r['status']=='open'?'selected':'' ?>>Open</option>
                    <option value="reviewed" <?= $r['status']=='reviewed'?'selected':'' ?>>Reviewed</option>
                    <option value="closed" <?= $r['status']=='closed'?'selected':'' ?>>Closed</option>
                </select>
                <button class="btn btn-sm btn-outline-primary ms-1">OK</button>
            </form>

            <form method="post"
                onsubmit="return confirm('Xóa báo cáo #<?= $r['id'] ?> ?')">
                <input type="hidden" name="action" value="delete_report">
                <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                <button class="btn btn-sm btn-outline-danger">Xóa</button>
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

<!-- MODAL -->
<div class="modal fade" id="reasonModal" tabindex="-1">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
    <h5 class="modal-title">Chi tiết báo cáo <span id="modalId"></span></h5>
    <button class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <pre id="modalContent" style="white-space:pre-wrap;"></pre>
</div>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const modal = new bootstrap.Modal(document.getElementById('reasonModal'));
function openReasonModal(id, reason) {
    document.getElementById('modalId').textContent = '#' + id;
    document.getElementById('modalContent').textContent = reason || '';
    modal.show();
}
</script>
</body>
</html>
