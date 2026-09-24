<?php
$isNew = (int) $u['id'] === 0;
$classOpts = '<option value="">— Lớp —</option>';
foreach ($classes as $c) {
    $classOpts .= '<option value="' . (int) $c['id'] . '">' . e($c['name']) . ($c['school_year'] ? ' · ' . e($c['school_year']) : '') . '</option>';
}
$subjectOpts = '<option value="">— Môn —</option>';
foreach ($subjects as $s) {
    $subjectOpts .= '<option value="' . (int) $s['id'] . '">' . e($s['name']) . '</option>';
}
$sel = static fn(string $opts, $v) => $v ? str_replace('value="' . (int) $v . '"', 'value="' . (int) $v . '" selected', $opts) : $opts;
?>
<div class="page-head"><div><h1><?= $isNew ? 'Thêm giáo viên' : 'Sửa giáo viên' ?></h1><div class="sub"><?= $isNew ? 'Tài khoản giáo viên, giám thị hoặc cán bộ quản lý.' : e($u['full_name']) ?></div></div></div>
<form method="post" action="<?= e(url('teachers/save')) ?>" class="grid grid-sidebar" autocomplete="off">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
  <div class="stack" style="gap:20px">
    <div class="card">
      <div class="card-head"><h3><?= icon('user') ?> Thông tin cá nhân</h3></div>
      <div class="card-body"><div class="form-grid">
        <div class="field span-2"><label>Họ và tên <span class="req">*</span></label><input class="input" name="full_name" value="<?= e($u['full_name']) ?>" required autofocus></div>
        <div class="field"><label>Mã giáo viên</label><input class="input mono" name="code" value="<?= e($u['code']) ?>"></div>
        <div class="field"><label>Môn giảng dạy chính</label><select class="select" name="subject_id"><?= $sel($subjectOpts, $u['subject_id']) ?></select></div>
        <div class="field"><label>Email</label><input class="input" type="email" name="email" value="<?= e($u['email']) ?>"></div>
        <div class="field"><label>Điện thoại</label><input class="input" name="phone" value="<?= e($u['phone']) ?>"></div>
        <div class="field"><label>Giới tính</label><select class="select" name="gender"><option value="">—</option><option<?= selected($u['gender'], 'Nam') ?>>Nam</option><option<?= selected($u['gender'], 'Nữ') ?>>Nữ</option></select></div>
        <div class="field"><label>Ghi chú</label><input class="input" name="note" value="<?= e($u['note']) ?>"></div>
      </div></div>
    </div>
    <div class="card">
      <div class="card-head"><h3><?= icon('school') ?> Chủ nhiệm & phân công giảng dạy</h3><button type="button" class="btn btn-sm btn-soft" id="add-assign"><?= icon('plus') ?> Thêm lớp dạy</button></div>
      <div class="card-body stack">
        <div class="field"><label>Lớp chủ nhiệm</label>
          <select class="select" name="homeroom[]" multiple size="4"><?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"<?= in_array((int) $c['id'], $homeroom, true) ? ' selected' : '' ?>><?= e($c['name']) ?> · <?= e($c['school_year']) ?></option><?php endforeach; ?></select>
          <div class="help">Giữ Ctrl để chọn nhiều lớp.</div></div>
        <div id="assign-list" class="stack-sm">
          <?php foreach ($assign as $a): ?>
            <div class="row nowrap assign-row"><select class="select" name="assign_class[]"><?= $sel($classOpts, $a['class_id']) ?></select><select class="select" name="assign_subject[]" style="max-width:220px"><?= $sel($subjectOpts, $a['subject_id']) ?></select><button type="button" class="btn btn-icon btn-ghost" data-remove><?= icon('x') ?></button></div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
  <div class="card">
    <div class="card-head"><h3><?= icon('shield-check') ?> Tài khoản & vai trò</h3></div>
    <div class="card-body stack">
      <div class="field"><label>Vai trò</label><select class="select" name="role"><?php foreach ($roles as $k => $v): ?><option value="<?= e($k) ?>"<?= selected($u['role'], $k) ?>><?= e($v) ?></option><?php endforeach; ?></select><div class="help">Quyền của từng vai trò chỉnh tại <a href="<?= e(url('roles')) ?>">Phân quyền</a>.</div></div>
      <div class="field"><label>Tên đăng nhập</label><input class="input mono" name="username" value="<?= e($u['username']) ?>" placeholder="Để trống: tự tạo từ họ tên"></div>
      <div class="field"><label><?= $isNew ? 'Mật khẩu' : 'Đặt mật khẩu mới' ?></label><div class="input-icon"><?= icon('lock') ?><input class="input" type="password" name="password" autocomplete="new-password" placeholder="<?= $isNew ? 'Để trống: tạo ngẫu nhiên' : 'Để trống nếu không đổi' ?>"><button type="button" class="toggle-pw"><?= icon('eye') ?></button></div></div>
      <label class="check"><input type="checkbox" name="must_change_password" value="1"<?= checked((int) $u['must_change_password']) ?>><span>Bắt đổi mật khẩu khi đăng nhập lần sau</span></label>
      <div class="field"><label>Trạng thái</label><select class="select" name="status"><option value="active"<?= selected($u['status'], 'active') ?>>Hoạt động</option><option value="locked"<?= selected($u['status'], 'locked') ?>>Khóa</option></select></div>
    </div>
    <div class="card-foot"><a class="btn btn-ghost" href="<?= e(url('teachers')) ?>">Hủy</a><button class="btn btn-primary"><?= icon('save') ?> Lưu</button></div>
  </div>
</form>
<template id="assign-tpl"><div class="row nowrap assign-row"><select class="select" name="assign_class[]"><?= $classOpts ?></select><select class="select" name="assign_subject[]" style="max-width:220px"><?= $sel($subjectOpts, $u['subject_id']) ?></select><button type="button" class="btn btn-icon btn-ghost" data-remove><?= icon('x') ?></button></div></template>
<?php \App\Core\View::push('scripts', '<script>TN.ready(function(){var l=document.getElementById("assign-list"),t=document.getElementById("assign-tpl");document.getElementById("add-assign").onclick=function(){l.appendChild(t.content.cloneNode(true));};l.addEventListener("click",function(e){var b=e.target.closest("[data-remove]");if(b)b.closest(".assign-row").remove();});});</script>'); ?>
