<?php
include __DIR__ . '/db.php';
include __DIR__ . '/includes/header.php';
?>

<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Trang chủ</title>

  <link rel="stylesheet" href="/task_management/assets/css/index.css">
</head>

<body>

  <div class="app-shell">
    <div class="app-card">

      <!-- LEFT -->
      <div class="left-pane">
        <div class="pane-header">
          <div>
            <h1 class="title">Dự án của tôi</h1>
          </div>

          <!-- nút tạo project: thêm class btn-primary -->
          <div>
            <a href="./pages/projects/create.php" class="btn-primary">Tạo dự án</a>
          </div>
        </div>


        <!-- Recent list -->
        <div class="recent-list">
          <?php
          if (!isset($_SESSION['user'])) {
            echo "<div class='small'>Vui lòng đăng nhập để xem dự án.</div>";
          } else {
            $userId = (int) $_SESSION['user']['id'];

            // Lấy tất cả project mà user là owner OR project được share (project_members)
            // Không thay đổi HTML/CSS — chỉ thay logic truy vấn
            $user_q = $userId;

            $sql = "
    SELECT DISTINCT p.*, u.name AS owner_name, pm.permission AS my_permission
    FROM projects p
    LEFT JOIN users u ON u.id = p.owner_id
    LEFT JOIN project_members pm ON pm.project_id = p.id AND pm.user_id = $user_q
    WHERE p.owner_id = $user_q
       OR p.id IN (SELECT project_id FROM project_members WHERE user_id = $user_q)
    ORDER BY p.updated_at DESC
  ";

            $res = mysqli_query($conn, $sql);

            if ($res && mysqli_num_rows($res) > 0) {

              while ($row = mysqli_fetch_assoc($res)) {
                $pid     = $row['id'];
                $title   = htmlspecialchars($row['name']);
                $desc    = htmlspecialchars($row['description']);
                $owner   = htmlspecialchars($row['owner_name']);
                $updated = $row['updated_at'] ? date("d/m/Y H:i", strtotime($row['updated_at'])) : '';

                $openUrl = "/task_management/pages/projects/detail.php?id=" . urlencode($pid);

          ?>
                <div class="project-item">
                  <div class="project-info">
                    <a class="project-title" href="<?= $openUrl ?>"><?= $title ?></a>
                    <div class="meta">Cập nhật <?= $updated ?> — Chủ sở hữu: <?= $owner ?></div>
                    <?php if ($desc != ""): ?>
                      <div class="small"><?= $desc ?></div>
                    <?php endif; ?>
                  </div>
                  <div>
                    <a class="btn-outline" href="<?= $openUrl ?>">Mở</a>
                  </div>
                </div>
          <?php
              } // end while
            } else {
              echo "<div class='small'>Bạn chưa có dự án nào.</div>";
            }
          }
          ?>

        </div>

      </div>

      <!-- RIGHT -->
      <div class="right-pane">
        <!-- Thông báo hệ thống (chiếm nửa) -->
        <div class="right-card">
          <div class="card-header">
            <strong>Thông báo hệ thống</strong>
          </div>
          <div class="card-body">
            <!-- nội dung thông báo sẽ show ở đây -->
          </div>
        </div>

        <!-- Thông báo của bạn (chiếm nửa) -->
        <div class="right-card">
          <div class="card-header">
            <strong>Thông báo của bạn</strong>
          </div>
          <div class="card-body">
            <!-- nội dung thông báo của user -->
          </div>
        </div>
      </div>

    </div>
  </div>

</body>

</html>