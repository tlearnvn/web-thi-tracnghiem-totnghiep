<?php
$open = array_values(array_filter($rows, static fn($s) => !in_array($s['_state'], ['ended', 'closed'], true)));
$closed = array_values(array_filter($rows, static fn($s) => in_array($s['_state'], ['ended', 'closed'], true)));
?>
<div class="page-head">
  <div>
    <div class="eyebrow"><?= icon('target', 'sm') ?> Tự ôn luyện</div>
    <h1>Luyện tập</h1>
    <div class="sub">Làm đề theo đúng định dạng thi tốt nghiệp từ năm 2025. Được làm lại nhiều lần, xem điểm và lời giải ngay sau khi nộp.</div>
  </div>
</div>
<?php if (!$rows): ?>
  <div class="card"><div class="empty"><div class="empty-icon"><?= icon('target') ?></div><h3>Chưa có đề luyện tập</h3><p>Thầy cô sẽ giao đề luyện tập cho lớp của em. Hãy quay lại sau nhé!</p></div></div>
<?php else: ?>
  <?php if ($open): ?>
    <div class="tile-grid"><?php foreach ($open as $s) { echo \App\Core\View::partial('student/tile', ['s' => $s, 'group' => 'practice']); } ?></div>
  <?php endif; ?>
  <?php if ($closed): ?>
    <h2 class="section-title"><?= icon('archive', 'sm') ?> Đã đóng</h2>
    <div class="tile-grid"><?php foreach ($closed as $s) { echo \App\Core\View::partial('student/tile', ['s' => $s, 'group' => 'practice']); } ?></div>
  <?php endif; ?>
<?php endif; ?>
