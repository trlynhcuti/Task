<?php
// /task_management/pages/tasks/delete.php
session_start();

include __DIR__ . '/../../db.php';
include __DIR__ . '/../../includes/auth.php'; // nếu file này có check session/role khác, giữ như cũ

// Kết nối mysqli
$db = null;
if (isset($mysqli) && $mysqli instanceof mysqli) $db = $mysqli;
elseif (isset($conn) && $conn instanceof mysqli) $db = $conn;
else {
    http_response_code(500);
    echo "Không tìm thấy kết nối MySQLi. Vui lòng kiểm tra db.php.";
    exit;
}

// Chỉ chấp nhận POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Phương thức không hợp lệ.";
    exit;
}

// Lấy current user
$currentUserId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
if ($currentUserId <= 0) {
    // chưa đăng nhập
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Vui lòng đăng nhập để thực hiện hành động này.'];
    header('Location: /html/sign-in.html');
    exit;
}

// Lấy input
$task_id = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
$project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;

if ($task_id <= 0 || $project_id <= 0) {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Dữ liệu không hợp lệ.'];
    header('Location: /task_management/pages/projects/detail.php?id=' . urlencode($project_id ?: 0));
    exit;
}

// --- 1) Kiểm tra task tồn tại và thuộc project ---
$stmt = $db->prepare("SELECT id, project_id, created_by FROM tasks WHERE id = ? LIMIT 1");
if (!$stmt) {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Lỗi chuẩn bị truy vấn: ' . $db->error];
    header('Location: /task_management/pages/projects/detail.php?id=' . urlencode($project_id));
    exit;
}
$stmt->bind_param('i', $task_id);
$stmt->execute();
$res = $stmt->get_result();
$taskRow = $res->fetch_assoc();
$stmt->close();

if (!$taskRow) {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Task không tồn tại.'];
    header('Location: /task_management/pages/projects/detail.php?id=' . urlencode($project_id));
    exit;
}
if ((int)$taskRow['project_id'] !== $project_id) {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Task không thuộc project được cung cấp.'];
    header('Location: /task_management/pages/projects/detail.php?id=' . urlencode($project_id));
    exit;
}

// --- 2) Kiểm tra quyền: owner hoặc editor của project ---
$hasPermission = false;

// 2a. Kiểm tra nếu user là owner của project
$stmt = $db->prepare("SELECT owner_id FROM projects WHERE id = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param('i', $project_id);
    $stmt->execute();
    $r = $stmt->get_result();
    $proj = $r->fetch_assoc();
    $stmt->close();
    if ($proj && (int)$proj['owner_id'] === $currentUserId) {
        $hasPermission = true;
    }
}

// 2b. Nếu chưa phải owner, kiểm tra project_members.permission
if (!$hasPermission) {
    $stmt = $db->prepare("SELECT permission FROM project_members WHERE project_id = ? AND user_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('ii', $project_id, $currentUserId);
        $stmt->execute();
        $r = $stmt->get_result();
        $pm = $r->fetch_assoc();
        $stmt->close();
        if ($pm && in_array($pm['permission'], ['owner','editor'], true)) {
            $hasPermission = true;
        }
    }
}

if (!$hasPermission) {
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Bạn không có quyền xóa task này.'];
    header('Location: /task_management/pages/projects/detail.php?id=' . urlencode($project_id));
    exit;
}

// --- 3) Xóa task an toàn (transaction) ---
// Một số hệ thống có các bảng liên quan (comments, attachments, ...). Ở đây:
// - Nếu bảng comments có cột task_id => xóa các comments liên quan trước.
// - Sau đó xóa task ở tasks.
//
// Kiểm tra xem comments có column 'task_id' không
$has_taskid_in_comments = false;
$chk = $db->query("SHOW COLUMNS FROM `comments` LIKE 'task_id'");
if ($chk && $chk->num_rows > 0) $has_taskid_in_comments = true;

// Bắt đầu transaction (nếu DB hỗ trợ InnoDB)
$useTransaction = true;
if ($useTransaction) {
    $db->begin_transaction();
}

$ok = true;
$errmsg = '';

try {
    if ($has_taskid_in_comments) {
        $stmt = $db->prepare("DELETE FROM comments WHERE task_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $task_id);
            if (!$stmt->execute()) {
                throw new Exception("Lỗi xóa comment: " . $stmt->error);
            }
            $stmt->close();
        } else {
            throw new Exception("Lỗi chuẩn bị xóa comment: " . $db->error);
        }
    }

    // Nếu bạn có bảng attachments hoặc task_items, xóa ở đây tương tự...
    // Ví dụ: DELETE FROM task_attachments WHERE task_id = ?

    // Xóa task chính
    $stmt = $db->prepare("DELETE FROM tasks WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $task_id);
        if (!$stmt->execute()) {
            throw new Exception("Lỗi xóa task: " . $stmt->error);
        }
        $affected = $stmt->affected_rows;
        $stmt->close();
        if ($affected === 0) {
            throw new Exception("Không có dòng nào bị xóa (task có thể đã bị xóa trước đó).");
        }
    } else {
        throw new Exception("Lỗi chuẩn bị xóa task: " . $db->error);
    }

    if ($useTransaction) $db->commit();

    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Xóa task thành công.'];
    header('Location: /task_management/pages/projects/detail.php?id=' . urlencode($project_id));
    exit;

} catch (Exception $e) {
    if ($useTransaction) $db->rollback();
    $errmsg = $e->getMessage();
    $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Xóa thất bại: ' . $errmsg];
    header('Location: /task_management/pages/projects/detail.php?id=' . urlencode($project_id));
    exit;
}
