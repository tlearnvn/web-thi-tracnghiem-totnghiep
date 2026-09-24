<div class="bare">
  <div class="bare-card rise">
    <div class="empty-icon" style="width:96px;height:96px;border-radius:30px"><?= icon('settings', 'ic-lg') ?></div>
    <h2><?= e(setting('site_name')) ?></h2>
    <p class="text-muted"><?= e(setting('maintenance_message')) ?></p>
    <a class="btn" href="<?= e(url('login')) ?>"><?= icon('log-in') ?> Đăng nhập quản trị</a>
  </div>
</div>
