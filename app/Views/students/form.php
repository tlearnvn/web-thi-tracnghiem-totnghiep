<?php $isNew = (int) $u['id'] === 0; ?>
<div class="page-head">
  <div><h1><?= $isNew ? 'Thêm học sinh' : 'Sửa thông tin học sinh' ?></h1><div class="sub"><?= $isNew ? 'Tạo tài khoản để học sinh đăng nhập làm bài.' : e($u['full_name']) ?></div></div>
</div>
<form method="post" action="<?= e(url('students/save')) ?>" class="grid grid-sidebar" autocomplete="off">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
  <div class="card">
    <div class="card-head"><h3><?= icon('user') ?> Thông tin học sinh</h3></div>
    <div class="card-body">
      <div class="form-grid">
        <div class="field span-2"><label>Họ và tên <span class="req">*</span></label><input class="input" name="full_name" value="<?= e($u['full_name']) ?>" required autofocus></div>
        <div class="field"><label>Mã học sinh / Số báo danh</label><input class="input mono" name="code" value="<?= e($u['code']) ?>" placeholder="VD: 01000123"><div class="help">Hiển thị trên phiếu trả lời (ô Số báo danh).</div></div>
        <div class="field"><label>Lớp <?= \App\Core\Scope::classIds() !== null ? '<span class="req">*</span>' : '' ?></label>
          <select class="select" name="class_id"><option value="">— Chưa xếp lớp —</option>
            <?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($u['class_id'], $c['id']) ?>><?= e($c['name']) ?><?= $c['school_year'] ? ' · ' . e($c['school_year']) : '' ?></option><?php endforeach; ?>
          </select></div>
        <div class="field"><label>Ngày sinh</label><input class="input" type="date" name="birthday" value="<?= e($u['birthday']) ?>"></div>
        <div class="field"><label>Giới tính</label>
          <select class="select" name="gender"><option value="">—</option><option<?= selected($u['gender'], 'Nam') ?>>Nam</option><option<?= selected($u['gender'], 'Nữ') ?>>Nữ</option></select></div>
        <div class="field"><label>Email</label><input class="input" type="email" name="email" value="<?= e($u['email']) ?>"></div>
        <div class="field"><label>Điện thoại (học sinh / phụ huynh)</label><input class="input" name="phone" value="<?= e($u['phone']) ?>"></div>
        <div class="field span-2"><label>Ghi chú</label><input class="input" name="note" value="<?= e($u['note']) ?>"></div>
      </div>
    </div>
  </div>
  <div class="stack" style="gap:20px">
    <div class="card">
      <div class="card-head"><h3><?= icon('key-round') ?> Tài khoản đăng nhập</h3></div>
      <div class="card-body stack">
        <div class="field"><label>Tên đăng nhập</label><input class="input mono" name="username" value="<?= e($u['username']) ?>" placeholder="Để trống: dùng mã học sinh"><div class="help">Chữ không dấu, số, dấu chấm, gạch dưới.</div></div>
        <div class="field"><label><?= $isNew ? 'Mật khẩu' : 'Đặt mật khẩu mới' ?></label>
          <div class="input-icon"><?= icon('lock') ?><input class="input" type="password" name="password" autocomplete="new-password" placeholder="<?= $isNew ? 'Để trống: tạo ngẫu nhiên 6 số' : 'Để trống nếu không đổi' ?>"><button type="button" class="toggle-pw"><?= icon('eye') ?></button></div></div>
        <label class="check"><input type="checkbox" name="must_change_password" value="1"<?= checked((int) $u['must_change_password']) ?>><span>Bắt đổi mật khẩu khi đăng nhập lần đầu</span></label>
        <div class="field"><label>Trạng thái</label>
          <select class="select" name="status"><option value="active"<?= selected($u['status'], 'active') ?>>Đang hoạt động</option><option value="locked"<?= selected($u['status'], 'locked') ?>>Khóa (không cho đăng nhập)</option></select></div>
      </div>
      <div class="card-foot">
        <?php if ($isNew): ?><button class="btn" name="add_another" value="1"><?= icon('plus') ?> Lưu & thêm tiếp</button><?php endif; ?>
        <button class="btn btn-primary" type="submit"><?= icon('save') ?> Lưu</button>
      </div>
    </div>
  </div>
</form>
