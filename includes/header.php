<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<link rel="stylesheet" href="../assets/css/header.css">

<nav class="navbar">
  <a class="navbar-brand" href="./index.php">TaskManager</a>

  <div class="nav-left">
    <a class="nav-link" href="./index.php">Trang chủ</a>

    <?php if(isset($_SESSION['user']) && $_SESSION['user']['role'] == "user"): ?>
      <a class="nav-link" href="./pages/projects/list.php">Dự án của tôi</a>
      <a class="nav-link" href="./pages/projects/shared.php">Dự án được chia sẻ</a>
    <?php endif; ?>

    <?php if(isset($_SESSION['user']) && $_SESSION['user']['role'] == "admin"): ?>
      <a class="nav-link" href="./pages/admin/actions.php">Hoạt động</a>
      <a class="nav-link" href="./pages/admin/reports.php">Báo cáo</a>
      <a class="nav-link" href="./pages/admin/users.php">Quản lý người dùng</a>
    <?php endif; ?>
  </div>

  <div class="nav-right">
    <?php if (isset($_SESSION['user'])): ?>
      <span class="nav-link">Xin chào, <?= htmlspecialchars($_SESSION['user']['name']) ?></span>
      <a class="nav-link" href="./pages/logout.php">Đăng xuất</a>
    <?php else: ?>
      <a class="nav-link" href="./pages/login.php">Đăng nhập</a>
    <?php endif; ?>
  </div>
</nav>
