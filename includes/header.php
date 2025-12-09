<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Management</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

<nav class="navbar navbar-expand-lg navbar-light px-3 fixed-top" style="background-color: #abc4ff;">
    <a class="navbar-brand" href="/task-management/index.php">TaskManager</a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
        <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navMenu">

        <ul class="navbar-nav me-auto">

            <li class="nav-item">
                <a class="nav-link" href="/task_management/index.php">Trang chủ</a>
            </li>

            <?php   
                if($_SESSION['user']['role'] == "user"){
            ?>
                <li class="nav-item">
                    <a class="nav-link" href="/Task_Management/pages/my_projects.php">Dự án của tôi</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="/Task_Management/pages/shared_projects.php">Dự án được chia sẻ</a>
                </li>
            <?php } ?>
            <?php 
                if($_SESSION['user']['role'] == "admin"){
            ?>
                <li class="nav-item">
                    <a class="nav-link" href="/task_management/pages/admin/actions.php">Hoạt động</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="/task_management/pages/admin/reports.php">Báo cáo</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/task_management/pages/admin/users.php">Quản lý người dùng</a>
                </li>
            <?php } ?>

        </ul>

        <ul class="navbar-nav ms-auto">

            <?php if (isset($_SESSION['user'])): ?>
                <!-- Nếu đã đăng nhập -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        Xin chào, <?= htmlspecialchars($_SESSION['user']['name']) ?>
                    </a>

                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="/task-management/pages/logout.php">Đăng xuất</a>
                        </li>
                    </ul>
                </li>

            <?php else: ?>
                <!-- Nếu chưa đăng nhập -->
                <li class="nav-item">
                    <a class="nav-link" href="/task-management/pages/login.php">Đăng nhập</a>
                </li>
            <?php endif; ?>

        </ul>

    </div>
</nav>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
