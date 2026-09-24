<?php
use App\Core\Auth;

$u = Auth::user();
$role = Auth::roleInfo();
?>
<div class="dropdown">
  <button class="user-chip" data-dropdown aria-haspopup="true">
    <span class="avatar sm" style="background:<?= e(color_for($u['username'])) ?>"><?= e(initials($u['full_name'])) ?></span>
    <span class="meta"><strong><?= e($u['full_name']) ?></strong><small><?= e($role['name'] ?? '') ?></small></span>
    <?= icon('chevron-down', 'sm') ?>
  </button>
  <div class="dropdown-menu">
    <div class="dropdown-head">
      <strong><?= e($u['full_name']) ?></strong>
      <small><?= e($u['username']) ?><?= $u['code'] ? ' · ' . e($u['code']) : '' ?></small>
    </div>
    <a href="<?= e(url('profile')) ?>"><?= icon('user') ?> Hồ sơ cá nhân</a>
    <a href="<?= e(url('profile/password')) ?>"><?= icon('key-round') ?> Đổi mật khẩu</a>
    <button type="button" data-theme-toggle><?= icon('moon') ?> Giao diện sáng / tối</button>
    <hr>
    <a href="#" class="danger" data-post="<?= e(url('logout')) ?>"><?= icon('log-out') ?> Đăng xuất</a>
  </div>
</div>
