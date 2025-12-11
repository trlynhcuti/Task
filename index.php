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

            // Lấy dự án của user
            $sql = "
            SELECT p.*, u.name AS owner_name
            FROM projects p
            LEFT JOIN users u ON u.id = p.owner_id
            WHERE p.owner_id = $userId
            ORDER BY p.updated_at DESC";

            $res = mysqli_query($conn, $sql);

            if ($res && mysqli_num_rows($res) > 0) {

              while ($row = mysqli_fetch_assoc($res)) {
                $pid     = $row['id'];
                $title   = htmlspecialchars($row['name']);
                $desc    = htmlspecialchars($row['description']);
                $owner   = htmlspecialchars($row['owner_name']);
                $updated = date("d/m/Y H:i", strtotime($row['updated_at']));

                $openUrl = "./pages/projects/open.php?id=" . $pid;
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
              }
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