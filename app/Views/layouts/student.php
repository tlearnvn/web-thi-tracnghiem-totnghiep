<?php
/** Bố cục cổng học sinh. Biến: $content, $title */
$route = strtolower(trim((string) ($_GET['r'] ?? ''), '/'));
$nav = [
    ['student', 'Bài thi của em', 'clipboard-list', ['student', 'student/index', 'home/index', '', 'student/lobby', 'student/result']],
    ['student/practice', 'Luyện tập', 'target', ['student/practice']],
    ['student/history', 'Kết quả & lịch sử', 'history', ['student/history', 'student/review']],
];
?>
<!doctype html>
<html lang="vi">
<head>
<?= \App\Core\View::partial('partials/head', ['title' => $title ?? '']) ?>
<style>
.s-top{position:sticky;top:0;z-index:40;background:rgba(255,255,255,.86);backdrop-filter:saturate(180%) blur(12px);-webkit-backdrop-filter:saturate(180%) blur(12px);border-bottom:1px solid var(--border)}
[data-theme="dark"] .s-top{background:rgba(17,24,39,.86)}
.s-top-in{max-width:1240px;margin:0 auto;height:66px;display:flex;align-items:center;gap:18px;padding:0 20px}
.s-brand{display:flex;align-items:center;gap:11px;color:var(--text);min-width:0}
.s-brand img{width:40px;height:40px;object-fit:contain;border-radius:10px}
.s-brand strong{display:block;font-size:14px;text-transform:uppercase;letter-spacing:.02em;white-space:nowrap}
.s-brand small{display:block;font-size:12px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:260px}
.s-nav{display:flex;gap:4px;margin-left:14px}
.s-nav a{display:flex;align-items:center;gap:8px;padding:8px 14px;border-radius:10px;color:var(--text-2);font-weight:600;font-size:14px}
.s-nav a:hover{background:var(--surface-3);color:var(--text)}
.s-nav a.active{background:var(--primary-soft);color:var(--primary)}
.s-main{max-width:1240px;margin:0 auto;padding:28px 20px 48px}
.s-foot{border-top:1px solid var(--border);background:var(--surface);color:var(--muted);font-size:12.5px}
.s-foot-in{max-width:1240px;margin:0 auto;padding:18px 20px;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}
.s-tabbar{display:none}
@media (max-width:860px){.s-nav{display:none}.s-brand small{max-width:150px}.s-tabbar{display:flex;position:fixed;bottom:0;left:0;right:0;z-index:50;background:var(--surface);border-top:1px solid var(--border);padding:6px 6px calc(6px + env(safe-area-inset-bottom))}.s-tabbar a{flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;font-size:11px;font-weight:600;color:var(--muted);padding:6px;border-radius:10px}.s-tabbar a.active{color:var(--primary);background:var(--primary-soft)}.s-main{padding-bottom:90px}}
</style>
</head>
<body>
<header class="s-top">
  <div class="s-top-in">
    <a class="s-brand" href="<?= e(url('student')) ?>">
      <img src="<?= e(logo_url()) ?>" alt="">
      <span><strong><?= e(setting('site_short_name')) ?></strong><small><?= e(setting('org_name')) ?></small></span>
    </a>
    <nav class="s-nav">
      <?php foreach ($nav as $n): ?>
        <a href="<?= e(url($n[0])) ?>" class="<?= in_array($route, $n[3], true) ? 'active' : '' ?>"><?= icon($n[2]) ?><?= e($n[1]) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="topbar-right">
      <div class="clock" id="tn-clock" title="Giờ máy chủ (UTC+7)"></div>
      <button class="icon-btn" data-theme-toggle title="Giao diện sáng / tối"><?= icon('sun') ?></button>
      <?= \App\Core\View::partial('partials/user_menu') ?>
    </div>
  </div>
</header>
<main class="s-main"><?= $content ?></main>
<footer class="s-foot"><div class="s-foot-in"><div><?= render_footer_text((string) setting('footer_text', '')) ?></div><span class="version-pill">v<?= e(TN_VERSION) ?></span></div></footer>
<nav class="s-tabbar">
  <?php foreach ($nav as $n): ?>
    <a href="<?= e(url($n[0])) ?>" class="<?= in_array($route, $n[3], true) ? 'active' : '' ?>"><?= icon($n[2]) ?><?= e($n[1]) ?></a>
  <?php endforeach; ?>
</nav>
<?= \App\Core\View::partial('partials/scripts') ?>
</body>
</html>
