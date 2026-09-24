<?php $isNew = (int) $u['id'] === 0; ?>
<div class="page-head"><div><h1><?= $isNew ? 'Thêm tài khoản' : 'Sửa tài khoản' ?></h1><div class="sub">Dùng trang <a href="<?= e(url('students')) ?>">Học sinh</a> / <a href="<?= e(url('teachers')) ?>">Giáo viên</a> để có đầy đủ thông tin chuyên biệt.</div></div></div>
<form method="post" action="<?= e(url('users/save')) ?>" class="card" style="max-width:860px" autocomplete="off">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
  <div class="card-body">
    <div class="form-grid">
      <div class="field span-2"><label>Họ và tên <span class="req">*</span></label><input class="input" name="full_name" value="<?= e($u['full_name']) ?>" required></div>
      <div class="field"><label>Tên đăng nhập <span class="req">*</span></label><input class="input mono" name="username" value="<?= e($u['username']) ?>" required></div>
      <div class="field"><label>Vai trò</label><select class="select" name="role"><?php foreach ($roles as $r): ?><option value="<?= e($r['code']) ?>"<?= selected($u['role'], $r['code']) ?>><?= e($r['name']) ?><?= $r['kind'] === 'student' ? ' (cổng học sinh)' : '' ?></option><?php endforeach; ?></select></div>
      <div class="field"><label><?= $isNew ? 'Mật khẩu' : 'Mật khẩu mới' ?> <?= $isNew ? '<span class="req">*</span>' : '' ?></label><div class="input-icon"><?= icon('lock') ?><input class="input" type="password" name="password" autocomplete="new-password" placeholder="<?= $isNew ? '' : 'Để trống nếu không đổi' ?>"><button type="button" class="toggle-pw"><?= icon('eye') ?></button></div></div>
      <div class="field"><label>Mã</label><input class="input mono" name="code" value="<?= e($u['code']) ?>"></div>
      <div class="field"><label>Lớp (với học sinh)</label><select class="select" name="class_id"><option value="">—</option><?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($u['class_id'], $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Trạng thái</label><select class="select" name="status"><option value="active"<?= selected($u['status'], 'active') ?>>Hoạt động</option><option value="locked"<?= selected($u['status'], 'locked') ?>>Khóa</option></select></div>
      <div class="field"><label>Email</label><input class="input" type="email" name="email" value="<?= e($u['email']) ?>"></div>
      <div class="field"><label>Điện thoại</label><input class="input" name="phone" value="<?= e($u['phone']) ?>"></div>
      <div class="field span-2"><label>Ghi chú</label><input class="input" name="note" value="<?= e($u['note']) ?>"></div>
      <label class="check span-2"><input type="checkbox" name="must_change_password" value="1"<?= checked((int) $u['must_change_password']) ?>><span>Bắt đổi mật khẩu khi đăng nhập lần sau</span></label>
    </div>
  </div>
  <div class="card-foot"><a class="btn btn-ghost" href="<?= e(url('users')) ?>">Hủy</a><button class="btn btn-primary"><?= icon('save') ?> Lưu tài khoản</button></div>
</form>
