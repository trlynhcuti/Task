<?php
include __DIR__ . '/db.php';
include __DIR__ . '/includes/header.php';
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Dashboard — Task Manager</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/index.css">
</head>

<body>

    <div class="app-shell">
        <div class="app-card">

            <!-- LEFT -->
            <div class="left-pane">
                <div class="pane-header">
                    <div>
                        <h5 class="mb-0">Được chỉnh sửa gần đây</h5>
                    </div>

                    <!-- nút tạo project -->
                    <div>
                        <a href="pages/projects/create.php" class="btn btn-primary">Tạo dự án</a>
                    </div>
                </div>

                <!-- Recent list -->
                <div class="recent-list mt-2">
                    <!-- sample items: thay bằng loop PHP -->
                    <!-- <div class="project-item">
          <div>
            <a href="#" class="h6 mb-0">Website Marketing</a>
            <div class="meta">Cập nhật 2 giờ trước — Chủ sở hữu: Nam</div>
          </div>
          <div>
            <a class="btn btn-sm btn-outline-secondary" href="#">Mở</a>
          </div>
        </div>

        <div class="project-item">
          <div>
            <a href="#" class="h6 mb-0">App Mobile</a>
            <div class="meta">Cập nhật hôm qua — Chủ sở hữu: Lan</div>
          </div>
          <div>
            <a class="btn btn-sm btn-outline-secondary" href="#">Mở</a>
          </div>
        </div>

        <div class="project-item">
          <div>
            <a href="#" class="h6 mb-0">Thiết kế Logo</a>
            <div class="meta">Cập nhật 3 ngày trước — Chủ sở hữu: Bạn</div>
          </div>
          <div>
            <a class="btn btn-sm btn-outline-secondary" href="#">Mở</a>
          </div>
        </div> -->

                    <!-- thêm nhiều item mẫu để thấy scroll -->
                    <!-- <div class="project-item">
          <div>
            <a href="#" class="h6 mb-0">Chiến dịch Q4</a>
            <div class="meta">Cập nhật 5 ngày trước</div>
          </div>
          <div><a class="btn btn-sm btn-outline-secondary" href="#">Mở</a></div>
        </div>
        <div class="project-item">
          <div>
            <a href="#" class="h6 mb-0">Tài liệu hướng dẫn</a>
            <div class="meta">Cập nhật 1 tuần trước</div>
          </div>
          <div><a class="btn btn-sm btn-outline-secondary" href="#">Mở</a></div>
        </div> -->
                </div>
            </div>

            <!-- RIGHT -->
            <div class="right-pane">
                <!-- Thông báo hệ thống (chiếm nửa) -->
                <div class="right-card">
                    <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                        <strong>Thông báo hệ thống</strong>
                        <small class="text-muted">Mới nhất</small>
                    </div>
                    <div class="card-body">
                        <!-- sample notifications -->
                        <!-- <div class="mb-2">
            <div class="fw-bold">Tài khoản bị cảnh báo</div>
            <div class="small text-muted">Admin đã cảnh báo người dùng A — 2025-12-01</div>
          </div>
          <hr />
          <div class="mb-2">
            <div class="fw-bold">Bảo trì hệ thống</div>
            <div class="small text-muted">Hệ thống sẽ bảo trì 2025-12-10</div>
          </div>
          <hr />
          <div class="mb-2">
            <div class="fw-bold">Cập nhật chính sách</div>
            <div class="small text-muted">Chính sách mới đã được áp dụng</div>
          </div> -->
                    </div>
                </div>

                <!-- Thông báo của bạn (chiếm nửa) -->
                <div class="right-card">
                    <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                        <strong>Thông báo của bạn</strong>
                        <a href="#" class="small">Xem tất cả</a>
                    </div>
                    <div class="card-body">
                        <!-- <div class="mb-2">
            <div class="fw-bold">Bạn được thêm vào dự án X</div>
            <div class="small text-muted">Lan đã thêm bạn — 1 giờ trước</div>
          </div>
          <hr />
          <div class="mb-2">
            <div class="fw-bold">Bình luận mới trên Task Y</div>
            <div class="small text-muted">Minh: "Đã xong phần UI" — 3 giờ trước</div>
          </div>
          <hr />
          <div class="mb-2">
            <div class="fw-bold">Task Z được cập nhật</div>
            <div class="small text-muted">Bạn hoặc ai đó đã sửa trạng thái — hôm nay</div>
          </div> -->
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>