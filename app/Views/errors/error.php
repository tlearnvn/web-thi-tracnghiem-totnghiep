<?php
$icons = [403 => 'shield-alert', 404 => 'search', 419 => 'clock', 401 => 'log-in', 503 => 'settings'];
$home = \App\Core\Auth::check() ? (\App\Core\Auth::isStudent() ? url('student') : url('dashboard')) : url('login');
?>
<div class="bare">
  <div class="bare-card rise">
    <div class="bare-code"><?= (int) $status ?></div>
    <h2 class="mt-2"><?= e($title ?? 'Đã xảy ra lỗi') ?></h2>
    <p class="text-muted"><?= e($message) ?></p>
    <?php if (!empty($ref)): ?>
      <p class="text-sm text-muted">Mã tra cứu lỗi: <code><?= e($ref) ?></code> – quản trị viên xem chi tiết tại <em>Nhật ký hệ thống</em>.</p>
    <?php endif; ?>
    <div class="row" style="justify-content:center;margin-top:20px">
      <a class="btn" href="javascript:history.back()"><?= icon('arrow-left') ?> Quay lại</a>
      <a class="btn btn-primary" href="<?= e($home) ?>"><?= icon($icons[$status] ?? 'house') ?> Về trang chính</a>
    </div>
  </div>
</div>
