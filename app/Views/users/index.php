<?php $role = $_GET['role'] ?? ''; ?>
<div class="page-head">
  <div><h1>Tài khoản & phân quyền</h1><div class="sub">Quản lý mọi tài khoản trong hệ thống.</div></div>
  <div class="actions">
    <?php if (can('roles.manage')): ?><a class="btn" href="<?= e(url('roles')) ?>"><?= icon('shield-check') ?> Ma trận phân quyền</a><?php endif; ?>
    <a class="btn btn-primary" href="<?= e(url('users/edit')) ?>"><?= icon('user-plus') ?> Thêm tài khoản</a>
  </div>
</div>
<div class="row mb-2" style="gap:8px">
  <a class="chip<?= $role === '' ? ' active' : '' ?>" href="<?= e(query_with(['role' => null, 'page' => null])) ?>">Tất cả <span class="count"><?= array_sum(array_map('intval', $counts)) ?></span></a>
  <?php foreach ($roles as $k => $v): ?>
    <a class="chip<?= $role === $k ? ' active' : '' ?>" href="<?= e(query_with(['role' => $k, 'page' => null])) ?>"><?= e($v) ?> <span class="count"><?= (int) ($counts[$k] ?? 0) ?></span></a>
  <?php endforeach; ?>
</div>
<div class="card">
  <form class="table-toolbar" method="get" action="<?= e(base_uri() . 'index.php') ?>">
    <input type="hidden" name="r" value="users"><input type="hidden" name="role" value="<?= e($role) ?>">
    <div class="input-icon search"><?= icon('search') ?><input class="input" type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Tìm theo tên, tên đăng nhập, mã…" data-autosubmit></div>
    <select class="select" name="status" style="width:auto" data-autosubmit><option value="">Mọi trạng thái</option><option value="active"<?= selected($_GET['status'] ?? '', 'active') ?>>Hoạt động</option><option value="locked"<?= selected($_GET['status'] ?? '', 'locked') ?>>Đã khóa</option></select>
    <label class="check"><input type="checkbox" name="online" value="1"<?= checked(!empty($_GET['online'])) ?> data-autosubmit><span>Đang trực tuyến</span></label>
  </form>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Tài khoản</th><th>Vai trò</th><th class="hide-sm">Lớp / mã</th><th class="hide-sm">Hoạt động gần nhất</th><th>Trạng thái</th><th class="col-actions"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $on = $r['seen'] && (int) $r['seen'] >= time() - 600; ?>
      <tr>
        <td><div class="person"><span class="avatar sm" style="background:<?= e(color_for($r['username'])) ?>"><?= e(initials($r['full_name'])) ?></span><div><div class="name"><?= e($r['full_name']) ?></div><div class="sub mono"><?= e($r['username']) ?></div></div></div></td>
        <td><?= badge((string) $r['role_name'], $r['role'] === 'admin' ? 'danger' : ($r['kind'] === 'student' ? 'default' : 'primary')) ?></td>
        <td class="hide-sm text-sm"><?= e($r['class_name'] ?? '') ?><?= $r['code'] ? ' <span class="mono text-muted">' . e($r['code']) . '</span>' : '' ?></td>
        <td class="hide-sm text-sm"><?= $on ? '<span class="dot-live"></span> Đang trực tuyến' : ($r['seen'] ? e(fmt_ago($r['seen'])) : ($r['last_login_at'] ? e(fmt_ago($r['last_login_at'])) : '<span class="text-faint">Chưa đăng nhập</span>')) ?></td>
        <td><?= $r['status'] === 'active' ? badge('Hoạt động', 'success') : badge('Đã khóa', 'danger') ?></td>
        <td class="col-actions">
          <div class="dropdown">
            <button class="btn btn-ghost btn-icon btn-sm" data-dropdown><?= icon('ellipsis-vertical') ?></button>
            <div class="dropdown-menu">
              <a href="<?= e(url('users/edit', ['id' => $r['id']])) ?>"><?= icon('square-pen') ?> Sửa tài khoản</a>
              <button type="button" data-post="<?= e(url('users/logout-all', ['id' => $r['id']])) ?>" data-confirm="Đăng xuất <?= e($r['full_name']) ?> khỏi mọi thiết bị?"><?= icon('log-out') ?> Buộc đăng xuất</button>
              <hr>
              <button type="button" class="danger" data-post="<?= e(url('users/delete', ['id' => $r['id']])) ?>" data-confirm="Xóa vĩnh viễn tài khoản <?= e($r['username']) ?>?" data-danger><?= icon('trash-2') ?> Xóa</button>
            </div>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?= $pager->links() ?>
</div>
