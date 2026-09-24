<?php
use App\Lib\Text;

$classLabel = $u['class_id'] ? (string) \App\Core\App::db()->value('SELECT name FROM {classes} WHERE id = ?', [(int) $u['class_id']]) : '';

$tile = static fn(array $s, string $group): string => \App\Core\View::partial('student/tile', ['s' => $s, 'group' => $group]);
$nNow = count($groups['now']);
$nUp = count($groups['upcoming']);
$nDone = count($groups['done']);
?>
<section class="hello rise">
  <div>
    <div class="eyebrow"><?= icon('calendar', 'sm') ?> <?= e(weekday_vi()) ?>, <?= e(date('d/m/Y')) ?></div>
    <h1><?= e(greeting()) ?>, <?= e(Text::givenName($u['full_name'])) ?>!</h1>
    <p><?= e($u['full_name']) ?><?= $classLabel !== '' ? ' · Lớp ' . e($classLabel) : '' ?><?= $u['code'] ? ' · SBD ' . e($u['code']) : '' ?>. Chúc em bình tĩnh, tự tin và làm bài thật tốt!</p>
  </div>
  <div class="hello-stats">
    <a href="#now"><b><?= $nNow ?></b><span>Đang mở</span></a>
    <a href="#upcoming"><b><?= $nUp ?></b><span>Sắp diễn ra</span></a>
    <a href="#done"><b><?= $nDone ?></b><span>Đã hoàn thành</span></a>
    <a href="<?= e(url('student/practice')) ?>"><b><?= (int) $practiceCount ?></b><span>Bài luyện tập</span></a>
  </div>
</section>

<?php if ($announcements): ?>
  <div class="card mb-3 rise rise-1">
    <div class="card-head"><h3><?= icon('megaphone') ?> Thông báo</h3></div>
    <div class="card-body">
      <?php foreach ($announcements as $an): ?>
        <div class="announcement<?= (int) $an['is_pinned'] ? ' pinned' : '' ?>">
          <h4><?= (int) $an['is_pinned'] ? icon('pin', 'sm') : icon('bell', 'sm') ?> <?= e($an['title']) ?></h4>
          <?php if ($an['body']): ?><p><?= e($an['body']) ?></p><?php endif; ?>
          <small><?= e(fmt_dt($an['created_at'])) ?></small>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<h2 class="section-title" id="now"><?= icon('circle-play', 'sm') ?> Bài thi đang mở</h2>
<?php if (!$groups['now']): ?>
  <div class="card"><div class="empty" style="padding:34px 20px"><div class="empty-icon"><?= icon('coffee') ?></div><h3>Hiện chưa có bài thi nào đang mở</h3><p>Khi đến giờ thi, bài thi sẽ xuất hiện tại đây. Trang tự cập nhật khi ca thi bắt đầu.</p></div></div>
<?php else: ?>
  <div class="tile-grid"><?php foreach ($groups['now'] as $s) { echo $tile($s, 'now'); } ?></div>
<?php endif; ?>

<?php if ($groups['upcoming']): ?>
  <h2 class="section-title" id="upcoming"><?= icon('calendar-clock', 'sm') ?> Lịch thi sắp tới</h2>
  <div class="tile-grid"><?php foreach ($groups['upcoming'] as $s) { echo $tile($s, 'upcoming'); } ?></div>
<?php endif; ?>

<h2 class="section-title" id="done"><?= icon('history', 'sm') ?> Đã kết thúc</h2>
<?php if (!$groups['done']): ?>
  <div class="card"><div class="empty" style="padding:30px 20px"><div class="empty-icon"><?= icon('clipboard-list') ?></div><h3>Chưa có bài thi nào</h3><p>Các bài thi đã làm sẽ được lưu tại đây cùng kết quả.</p></div></div>
<?php else: ?>
  <div class="tile-grid"><?php foreach (array_slice($groups['done'], 0, 12) as $s) { echo $tile($s, 'done'); } ?></div>
  <?php if (count($groups['done']) > 12): ?><div class="text-center mt-3"><a class="btn" href="<?= e(url('student/history')) ?>"><?= icon('history') ?> Xem toàn bộ lịch sử</a></div><?php endif; ?>
<?php endif; ?>
