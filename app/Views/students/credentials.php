<div class="page-head">
  <div><h1>Tài khoản vừa cấp</h1><div class="sub">Danh sách chỉ lưu tạm trong phiên đăng nhập của bạn tối đa 2 giờ – hãy tải về hoặc in ngay.</div></div>
  <?php if ($creds): ?>
  <div class="actions">
    <a class="btn" href="<?= e(url('students/credentials-excel')) ?>"><?= icon('file-down') ?> Tải Excel</a>
    <a class="btn btn-primary" href="<?= e(url('students/slips')) ?>" target="_blank"><?= icon('printer') ?> In phiếu tài khoản</a>
    <button class="btn btn-danger-soft" data-post="<?= e(url('students/clear-credentials')) ?>" data-confirm="Xóa danh sách mật khẩu tạm khỏi phiên làm việc?"><?= icon('trash-2') ?> Xóa danh sách tạm</button>
  </div>
  <?php endif; ?>
</div>
<?php if (!$creds): ?>
  <div class="card"><div class="empty"><div class="empty-icon"><?= icon('key-round') ?></div><h3>Không có danh sách tài khoản tạm</h3><p>Danh sách chỉ xuất hiện ngay sau khi nhập học sinh từ Excel, thêm học sinh hoặc cấp lại mật khẩu.</p><a class="btn" href="<?= e(url('students')) ?>">Về danh sách học sinh</a></div></div>
<?php else: ?>
  <div class="card">
    <div class="card-head"><h3><?= icon('key-round') ?> <?= e($c = $creds['title']) ?></h3><span class="hint"><?= count($creds['rows']) ?> tài khoản · tạo lúc <?= e(fmt_dt($creds['at'], 'H:i d/m/Y')) ?></span></div>
    <div class="table-wrap"><table class="table compact">
      <thead><tr><th>#</th><th>Lớp</th><th>Mã</th><th>Họ và tên</th><th>Tên đăng nhập</th><th>Mật khẩu</th></tr></thead>
      <tbody>
      <?php foreach ($creds['rows'] as $i => $r): ?>
        <tr><td class="text-muted"><?= $i + 1 ?></td><td><?= e($r['class']) ?></td><td class="mono"><?= e($r['code']) ?></td><td class="fw-600"><?= e($r['name']) ?></td><td class="mono"><?= e($r['username']) ?></td><td><span class="copy-box" style="display:inline-flex"><?= e($r['password']) ?> <button class="btn btn-xs btn-ghost" data-copy="<?= e($r['password']) ?>"><?= icon('copy') ?></button></span></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
<?php endif; ?>
