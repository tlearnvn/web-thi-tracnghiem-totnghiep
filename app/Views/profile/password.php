<div class="page-head"><div><h1>Đổi mật khẩu</h1><div class="sub">Mật khẩu mới có hiệu lực ngay, các phiên đăng nhập khác sẽ bị đăng xuất.</div></div></div>
<div style="max-width:520px">
  <?php if ($forced): ?>
    <div class="alert alert-warning mb-3"><?= icon('shield-alert') ?><div><div class="alert-title">Cần đổi mật khẩu lần đầu</div>Để bảo vệ tài khoản, bạn cần đặt mật khẩu mới trước khi sử dụng hệ thống.</div></div>
  <?php endif; ?>
  <form class="card" method="post" action="<?= e(url('profile/password')) ?>" autocomplete="off">
    <?= csrf_field() ?>
    <div class="card-body stack">
      <div class="field"><label>Mật khẩu hiện tại</label><div class="input-icon"><?= icon('lock') ?><input class="input" type="password" name="current_password" required autocomplete="current-password"><button type="button" class="toggle-pw"><?= icon('eye') ?></button></div></div>
      <div class="field"><label>Mật khẩu mới</label><div class="input-icon"><?= icon('key-round') ?><input class="input" type="password" name="new_password" required minlength="<?= (int) $min ?>" autocomplete="new-password"><button type="button" class="toggle-pw"><?= icon('eye') ?></button></div><div class="help">Tối thiểu <?= (int) $min ?> ký tự. Nên kết hợp chữ, số và ký tự đặc biệt.</div></div>
      <div class="field"><label>Nhập lại mật khẩu mới</label><div class="input-icon"><?= icon('key-round') ?><input class="input" type="password" name="new_password_confirm" required autocomplete="new-password"></div></div>
    </div>
    <div class="card-foot"><button class="btn btn-primary"><?= icon('save') ?> Đổi mật khẩu</button></div>
  </form>
</div>
