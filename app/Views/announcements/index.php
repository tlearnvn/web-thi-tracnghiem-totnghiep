<?php
use App\Controllers\AnnouncementsController;

$now = time();
?>
<div class="page-head">
  <div><h1>Thông báo</h1><div class="sub">Thông báo hiện ở trang chủ của học sinh (theo lớp) và bảng điều khiển của giáo viên.</div></div>
  <div class="actions"><a class="btn btn-primary" href="<?= e(url('announcements/create')) ?>"><?= icon('plus') ?> Tạo thông báo</a></div>
</div>
<div class="card">
  <?php if (!$rows): ?>
    <div class="empty"><div class="empty-icon"><?= icon('megaphone') ?></div><h3>Chưa có thông báo</h3><p>Đăng lịch thi, hướng dẫn làm bài, nhắc nhở… cho học sinh và giáo viên.</p><a class="btn btn-primary" href="<?= e(url('announcements/create')) ?>"><?= icon('plus') ?> Tạo thông báo</a></div>
  <?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Thông báo</th><th>Gửi tới</th><th>Hiển thị</th><th>Người đăng</th><th class="col-actions"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $a):
        $live = (!$a['starts_at'] || (int) $a['starts_at'] <= $now) && (!$a['ends_at'] || (int) $a['ends_at'] >= $now); ?>
      <tr>
        <td style="max-width:520px"><div class="fw-600"><?= (int) $a['is_pinned'] ? icon('pin', 'sm') . ' ' : '' ?><?= e($a['title']) ?></div><div class="text-sm text-muted truncate"><?= e(str_limit((string) $a['body'], 140)) ?></div></td>
        <td class="text-sm"><?= e(AnnouncementsController::AUDIENCES[$a['audience']] ?? $a['audience']) ?><?= $a['class_name'] ? ': <b>' . e($a['class_name']) . '</b>' : '' ?></td>
        <td class="text-sm"><?= $live ? badge('Đang hiển thị', 'success') : ((int) $a['starts_at'] > $now ? badge('Chưa đến lúc', 'info') : badge('Đã hết hạn', 'default')) ?><div class="text-xs text-muted mt-1"><?= $a['starts_at'] ? e(fmt_dt($a['starts_at'], 'H:i d/m')) : 'ngay' ?> → <?= $a['ends_at'] ? e(fmt_dt($a['ends_at'], 'H:i d/m')) : 'không hạn' ?></div></td>
        <td class="text-sm"><?= e($a['author'] ?? '') ?><div class="text-xs text-muted"><?= e(fmt_dt($a['created_at'])) ?></div></td>
        <td class="col-actions"><div class="table-actions">
          <a class="btn btn-sm btn-ghost" href="<?= e(url('announcements/edit', ['id' => $a['id']])) ?>"><?= icon('square-pen') ?></a>
          <button class="btn btn-sm btn-ghost" data-post="<?= e(url('announcements/delete', ['id' => $a['id']])) ?>" data-confirm="Xóa thông báo này?" data-danger><?= icon('trash-2') ?></button>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
  <?= $pager->links() ?>
</div>
