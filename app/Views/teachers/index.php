<div class="page-head">
  <div><h1>Giáo viên & cán bộ</h1><div class="sub"><?= number_format($pager->total, 0, ',', '.') ?> tài khoản giáo viên, giám thị, cán bộ quản lý</div></div>
  <div class="actions">
    <a class="btn" href="<?= e(url('teachers/export')) ?>"><?= icon('file-down') ?> Xuất Excel</a>
    <?php if ($canManage): ?>
      <a class="btn" href="<?= e(url('teachers/import')) ?>"><?= icon('file-spreadsheet') ?> Nhập từ Excel</a>
      <a class="btn btn-primary" href="<?= e(url('teachers/create')) ?>"><?= icon('user-plus') ?> Thêm giáo viên</a>
    <?php endif; ?>
  </div>
</div>
<div class="card">
  <form class="table-toolbar" method="get" action="<?= e(base_uri() . 'index.php') ?>">
    <input type="hidden" name="r" value="teachers">
    <div class="input-icon search"><?= icon('search') ?><input class="input" type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Tìm theo tên, mã, tên đăng nhập…" data-autosubmit></div>
    <select class="select" name="role" style="width:auto" data-autosubmit><option value="">Mọi vai trò</option><?php foreach ($roles as $k => $v): ?><option value="<?= e($k) ?>"<?= selected($_GET['role'] ?? '', $k) ?>><?= e($v) ?></option><?php endforeach; ?></select>
    <select class="select" name="subject_id" style="width:auto" data-autosubmit><option value="">Mọi môn</option><?php foreach ($subjects as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected($_GET['subject_id'] ?? '', $s['id']) ?>><?= e($s['name']) ?></option><?php endforeach; ?></select>
  </form>
  <?php if (!$rows): ?>
    <div class="empty"><div class="empty-icon"><?= icon('users') ?></div><h3>Chưa có giáo viên</h3><p>Thêm giáo viên để giao việc soạn đề, tổ chức ca thi, chấm bài.</p></div>
  <?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Giáo viên</th><th>Vai trò</th><th>Môn</th><th class="hide-sm">Chủ nhiệm</th><th class="hide-sm">Lớp giảng dạy</th><th class="hide-sm">Đăng nhập</th><th class="col-actions"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $id = (int) $r['id']; ?>
      <tr>
        <td><div class="person"><span class="avatar sm" style="background:<?= e(color_for($r['username'])) ?>"><?= e(initials($r['full_name'])) ?></span><div><div class="name"><?= e($r['full_name']) ?><?= $r['status'] !== 'active' ? ' ' . badge('Khóa', 'danger') : '' ?></div><div class="sub mono"><?= e($r['username']) ?><?= $r['code'] ? ' · ' . e($r['code']) : '' ?></div></div></div></td>
        <td><?= badge((string) $r['role_name'], $r['role'] === 'admin' ? 'danger' : ($r['role'] === 'manager' ? 'purple' : ($r['role'] === 'proctor' ? 'info' : 'primary'))) ?></td>
        <td><?= e($r['subject_name'] ?? '—') ?></td>
        <td class="hide-sm"><?= isset($homeroom[$id]) ? e(implode(', ', $homeroom[$id])) : '<span class="text-faint">—</span>' ?></td>
        <td class="hide-sm text-sm"><?= isset($teach[$id]) ? e(implode(', ', array_unique($teach[$id]))) : '<span class="text-faint">—</span>' ?></td>
        <td class="hide-sm text-sm text-muted"><?= $r['last_login_at'] ? e(fmt_ago($r['last_login_at'])) : 'Chưa' ?></td>
        <td class="col-actions">
          <?php if ($canManage): ?>
          <div class="dropdown">
            <button class="btn btn-ghost btn-icon btn-sm" data-dropdown><?= icon('ellipsis-vertical') ?></button>
            <div class="dropdown-menu">
              <a href="<?= e(url('teachers/edit', ['id' => $id])) ?>"><?= icon('square-pen') ?> Sửa & phân công</a>
              <button type="button" data-post="<?= e(url('teachers/password', ['id' => $id])) ?>" data-confirm="Cấp mật khẩu mới cho <?= e($r['full_name']) ?>? Giáo viên sẽ phải đổi mật khẩu khi đăng nhập."><?= icon('key-round') ?> Cấp lại mật khẩu</button>
              <hr>
              <button type="button" class="danger" data-post="<?= e(url('teachers/delete', ['id' => $id])) ?>" data-confirm="Xóa tài khoản <?= e($r['full_name']) ?>? Đề thi và ca thi của giáo viên sẽ chuyển sang tài khoản của bạn." data-danger><?= icon('trash-2') ?> Xóa tài khoản</button>
            </div>
          </div>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
  <?= $pager->links() ?>
</div>
