<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
?>

<style>
  :root {
    --navbar-height: 56px;
    --bg-page: #f3f6fb;
    --card-bg: #ffffff;
    --muted: #94a3b8;
    --border: #eef2f6;
    --accent-500: #1976d2;
    --accent-400: #2f80ed;
    --text-900: #0f1724;
    font-family: "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
  }

  * {
    box-sizing: border-box;
  }

  .navbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: var(--navbar-height);
    background: #abc4ff;
    display: flex;
    align-items: center;
    padding: 0 20px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
    z-index: 9999;
  }

  .navbar-brand {
    font-size: 18px;
    font-weight: 700;
    color: #07122a;
    text-decoration: none;
    margin-right: 24px;
    flex: 0 0 auto;
  }

  .nav-left {
    display: flex;
    align-items: center;
    gap: 18px;
    flex: 1 1 auto;
    justify-content: flex-start;
  }

  .nav-right {
    display: flex;
    align-items: center;
    gap: 18px;
    flex: 0 0 auto;
    justify-content: flex-end;
  }

  .nav-link {
    text-decoration: none;
    color: #07122a;
    padding: 6px 10px;
    white-space: nowrap;
    border-radius: 6px;
  }

  .nav-link:hover {
    background: rgba(0, 0, 0, 0.06);
  }
</style>

<nav class="navbar">
  <a class="navbar-brand" href="/task_management/index.php">TaskManager</a>

  <div class="nav-left">
    <a class="nav-link" href="/task_management/index.php">Trang chủ</a>

    <?php if (isset($_SESSION['user']) && $_SESSION['user']['role'] == "user"): ?>
      <a class="nav-link" href="/task_management/pages/projects/list.php">Dự án của tôi</a>
      <a class="nav-link" href="/task_management/pages/projects/shared.php">Dự án được chia sẻ</a>
    <?php endif; ?>

    <?php if (isset($_SESSION['user']) && $_SESSION['user']['role'] == "admin"): ?>
      <a class="nav-link" href="/task_management/pages/admin/actions.php">Hoạt động</a>
      <a class="nav-link" href="/task_management/pages/admin/reports.php">Báo cáo</a>
      <a class="nav-link" href="/task_management/pages/admin/users.php">Quản lý người dùng</a>
    <?php endif; ?>
  </div>

  <div class="nav-right">
    <?php if (isset($_SESSION['user'])): ?>
      <span class="nav-link">Xin chào, <?= htmlspecialchars($_SESSION['user']['name']) ?></span>
      <a class="nav-link" href="/task_management/pages/logout.php">Đăng xuất</a>
    <?php else: ?>
      <a class="nav-link" href="/task_management/pages/login.php">Đăng nhập</a>
    <?php endif; ?>
  </div>
</nav>