<?php
/**
 * Bố cục trang quản trị (giáo viên, cán bộ, quản trị).
 * Biến: $content, $title, $crumbs (mảng [nhãn => url]), $wide
 */
use App\Core\App;

$route = strtolower(trim((string) ($_GET['r'] ?? ''), '/'));
$module = explode('/', $route)[0] ?: 'dashboard';
$alias = ['monitor' => 'sessions', 'variants' => 'exams', 'roles' => 'users', 'home' => 'dashboard'];
$active = $alias[$module] ?? $module;

$running = 0;
try {
    $now = time();
    $running = (int) App::db()->value(
        "SELECT COUNT(*) FROM {exam_sessions} WHERE status <> 'closed' AND mode = 'exam' AND (start_at IS NULL OR start_at <= ?) AND (end_at IS NULL OR end_at > ?)",
        [$now, $now]
    );
} catch (\Throwable $e) {
}

$menu = [
    '' => [
        ['dashboard', 'Tổng quan', 'layout-dashboard', ['dashboard.view']],
    ],
    'Tổ chức thi' => [
        ['sessions', 'Ca thi', 'calendar-clock', ['sessions.manage', 'sessions.manage_all', 'sessions.proctor'], $running],
        ['exams', 'Đề thi & đáp án', 'file-text', ['exams.view', 'exams.manage', 'exams.manage_all']],
        ['subjects', 'Môn thi & định dạng', 'book-open', ['subjects.manage']],
    ],
    'Kết quả' => [
        ['results', 'Kết quả & chấm bài', 'clipboard-check', ['results.view', 'results.view_all']],
        ['stats', 'Thống kê & phân tích', 'chart-column', ['stats.view']],
    ],
    'Quản lý' => [
        ['students', 'Học sinh', 'graduation-cap', ['students.view', 'students.view_all', 'students.manage']],
        ['classes', 'Lớp học', 'school', ['classes.view', 'classes.manage']],
        ['teachers', 'Giáo viên', 'users', ['teachers.view', 'teachers.manage']],
        ['users', 'Tài khoản & phân quyền', 'shield-check', ['users.manage', 'roles.manage']],
        ['announcements', 'Thông báo', 'megaphone', ['announcements.manage']],
    ],
    'Hệ thống' => [
        ['settings', 'Cài đặt', 'settings', ['settings.manage']],
        ['backup', 'Sao lưu & phục hồi', 'database', ['backup.manage']],
        ['logs', 'Nhật ký hệ thống', 'scroll-text', ['logs.view']],
        ['system', 'Thông tin hệ thống', 'server', ['settings.manage']],
    ],
];
?>
<!doctype html>
<html lang="vi">
<head>
<?= \App\Core\View::partial('partials/head', ['title' => $title ?? '']) ?>
</head>
<body>
<div class="app">
  <aside class="sidebar" aria-label="Menu chính">
    <a class="brand" href="<?= e(url('dashboard')) ?>">
      <img src="<?= e(logo_url()) ?>" alt="">
      <span class="brand-text">
        <strong><?= e(setting('site_short_name')) ?></strong>
        <small><?= e(setting('org_name')) ?></small>
      </span>
    </a>
    <nav class="nav">
      <?php foreach ($menu as $group => $items):
          $visible = array_filter($items, static fn($it) => can(...$it[3]));
          if (!$visible) {
              continue;
          } ?>
        <div class="nav-group">
          <?php if ($group !== ''): ?><div class="nav-title"><?= e($group) ?></div><?php endif; ?>
          <?php foreach ($visible as $it): ?>
            <a href="<?= e(url($it[0])) ?>" class="<?= $active === $it[0] ? 'active' : '' ?>">
              <?= icon($it[2]) ?><span><?= e($it[1]) ?></span>
              <?php if (!empty($it[4])): ?><span class="nav-badge live" title="Ca thi đang diễn ra"><?= (int) $it[4] ?></span><?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot">
      <span>UTC+7 · Giờ Việt Nam</span>
      <span class="version-pill" title="Phiên bản phần mềm">v<?= e(TN_VERSION) ?></span>
    </div>
  </aside>
  <div class="sidebar-backdrop"></div>

  <div class="main">
    <header class="topbar">
      <button class="icon-btn menu-toggle" data-toggle-nav aria-label="Mở menu"><?= icon('menu') ?></button>
      <?php if (!empty($crumbs)): ?>
        <nav class="crumbs" aria-label="Đường dẫn">
          <a href="<?= e(url('dashboard')) ?>"><?= icon('house', 'sm') ?></a>
          <?php foreach ($crumbs as $label => $href): ?>
            <span class="sep">/</span>
            <?php if ($href): ?><a href="<?= e($href) ?>"><?= e($label) ?></a><?php else: ?><span class="cur"><?= e($label) ?></span><?php endif; ?>
          <?php endforeach; ?>
        </nav>
      <?php else: ?>
        <div class="topbar-title"><?= e($title ?? '') ?></div>
      <?php endif; ?>
      <div class="topbar-right">
        <div class="clock" id="tn-clock" title="Giờ máy chủ (UTC+7)"></div>
        <button class="icon-btn" data-theme-toggle title="Giao diện sáng / tối"><?= icon('sun') ?></button>
        <?= \App\Core\View::partial('partials/user_menu') ?>
      </div>
    </header>

    <main class="content<?= !empty($wide) ? ' wide' : '' ?>">
      <?= $content ?>
    </main>

    <footer class="footer">
      <div><?= render_footer_text((string) setting('footer_text', '')) ?></div>
      <div class="row gap-sm"><span class="text-faint">Giờ hiển thị: UTC+7</span><span class="version-pill">v<?= e(TN_VERSION) ?></span></div>
    </footer>
  </div>
</div>
<?= \App\Core\View::partial('partials/scripts') ?>
</body>
</html>
