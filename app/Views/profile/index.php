<?php use App\Core\Auth; ?>
<div class="page-head">
  <div><h1>Hồ sơ cá nhân</h1><div class="sub">Thông tin tài khoản của bạn trên hệ thống.</div></div>
  <div class="actions"><a class="btn" href="<?= e(url('profile/password')) ?>"><?= icon('key-round') ?> Đổi mật khẩu</a></div>
</div>
<div class="grid grid-sidebar-l">
  <div class="card">
    <div class="card-body text-center">
      <div class="avatar xl" style="margin:0 auto 12px;background:<?= e(color_for($u['username'])) ?>"><?= e(initials($u['full_name'])) ?></div>
      <h2 class="mb-1"><?= e($u['full_name']) ?></h2>
      <div class="text-muted"><?= e($role['name'] ?? '') ?></div>
      <div class="kv-list mt-3" style="text-align:left">
        <div class="kv"><span>Tên đăng nhập</span><span class="mono"><?= e($u['username']) ?></span></div>
        <?php if ($u['code']): ?><div class="kv"><span><?= Auth::isStudent() ? 'Mã HS / SBD' : 'Mã' ?></span><span class="mono"><?= e($u['code']) ?></span></div><?php endif; ?>
        <?php if ($class): ?><div class="kv"><span>Lớp</span><span><?= e($class['name']) ?></span></div><?php endif; ?>
        <?php if ($u['birthday']): ?><div class="kv"><span>Ngày sinh</span><span><?= e(fmt_date($u['birthday'])) ?></span></div><?php endif; ?>
        <div class="kv"><span>Đăng nhập gần nhất</span><span><?= e(fmt_dt($u['last_login_at'])) ?></span></div>
      </div>
    </div>
  </div>
  <div class="stack" style="gap:20px">
    <form class="card" method="post" action="<?= e(url('profile/save')) ?>">
      <?= csrf_field() ?>
      <div class="card-head"><h3><?= icon('user') ?> Thông tin liên hệ</h3></div>
      <div class="card-body">
        <div class="form-grid">
          <div class="field span-2"><label>Họ và tên</label><input class="input" name="full_name" value="<?= e($u['full_name']) ?>" <?= Auth::isStudent() ? 'readonly title="Liên hệ giáo viên để sửa họ tên"' : '' ?>></div>
          <div class="field"><label>Email</label><input class="input" type="email" name="email" value="<?= e($u['email']) ?>"></div>
          <div class="field"><label>Số điện thoại</label><input class="input" name="phone" value="<?= e($u['phone']) ?>"></div>
        </div>
      </div>
      <div class="card-foot"><button class="btn btn-primary"><?= icon('save') ?> Lưu thay đổi</button></div>
    </form>
    <div class="card">
      <div class="card-head"><h3><?= icon('laptop') ?> Các phiên đăng nhập</h3><span class="hint">Phiên lưu trong CSDL, tự hết hạn sau <?= (int) setting('session_lifetime_hours', 12) ?> giờ không hoạt động</span></div>
      <div class="table-wrap"><table class="table compact">
        <thead><tr><th>Thiết bị</th><th>IP</th><th>Bắt đầu</th><th>Hoạt động</th></tr></thead>
        <tbody>
        <?php foreach ($sessions as $s): ?>
          <tr><td><?= e(describe_ua($s['user_agent'])) ?><?= $s['id'] === session_id() ? ' ' . badge('Phiên này', 'success') : '' ?></td><td class="mono text-sm"><?= e($s['ip']) ?></td><td class="text-sm"><?= e(fmt_dt($s['created_at'])) ?></td><td class="text-sm"><?= e(fmt_ago($s['last_activity'])) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</div>
