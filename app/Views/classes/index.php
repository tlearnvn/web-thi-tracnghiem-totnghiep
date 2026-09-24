<?php $status = $_GET['status'] ?? 'active'; ?>
<div class="page-head">
  <div><h1>Lớp học</h1><div class="sub"><?= count($rows) ?> lớp · năm học mặc định <?= e(setting('default_school_year')) ?></div></div>
  <div class="actions">
    <?php if ($canManage): ?>
      <a class="btn btn-primary" href="<?= e(url('classes/create')) ?>"><?= icon('plus') ?> Thêm lớp</a>
    <?php endif; ?>
  </div>
</div>
<div class="card">
  <form class="table-toolbar" method="get" action="<?= e(base_uri() . 'index.php') ?>">
    <input type="hidden" name="r" value="classes">
    <div class="seg">
      <?php foreach (['active' => 'Đang học', 'archived' => 'Đã lưu trữ', 'all' => 'Tất cả'] as $k => $v): ?>
        <a class="<?= $status === $k ? 'active' : '' ?>" href="<?= e(query_with(['status' => $k])) ?>"><?= e($v) ?></a>
      <?php endforeach; ?>
    </div>
    <select class="select" name="year" style="width:auto" data-autosubmit>
      <option value="">Mọi năm học</option>
      <?php foreach ($years as $y): ?><option<?= selected($_GET['year'] ?? '', $y) ?>><?= e($y) ?></option><?php endforeach; ?>
    </select>
    <input type="hidden" name="status" value="<?= e($status) ?>">
  </form>
  <form method="post" action="<?= e(url('classes/promote')) ?>" id="promote-form">
    <?= csrf_field() ?>
    <?php if ($canManage): ?>
    <div class="bulk-bar" id="class-bulk">
      <span>Đã chọn <strong data-count>0</strong> lớp</span>
      <span class="text-sm">Lên lớp sang năm học</span>
      <input class="input input-sm" name="new_year" placeholder="VD: <?= e((string) ((int) substr((string) setting('default_school_year'), 0, 4) + 1) . '-' . ((int) substr((string) setting('default_school_year'), 0, 4) + 2)) ?>" style="width:130px">
      <button class="btn btn-sm btn-primary" data-confirm="Đổi tên lớp lên khối trên (10A1 → 11A1) và chuyển năm học? Lớp 12 sẽ được lưu trữ."><?= icon('arrow-right') ?> Thực hiện</button>
    </div>
    <?php endif; ?>
    <?php if (!$rows): ?>
      <div class="empty"><div class="empty-icon"><?= icon('school') ?></div><h3>Chưa có lớp nào</h3><p>Tạo lớp thủ công hoặc để hệ thống tự tạo lớp khi nhập danh sách học sinh từ Excel.</p>
        <?php if ($canManage): ?><a class="btn btn-primary" href="<?= e(url('classes/create')) ?>"><?= icon('plus') ?> Thêm lớp</a><?php endif; ?></div>
    <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><?php if ($canManage): ?><th class="col-check"><input type="checkbox" data-check-all="#class-bulk"></th><?php endif; ?><th>Lớp</th><th>Khối</th><th>Năm học</th><th>GV chủ nhiệm</th><th class="center">Sĩ số</th><th class="center">GV bộ môn</th><th class="col-actions"></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <?php if ($canManage): ?><td class="col-check"><input type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" data-check></td><?php endif; ?>
          <td><a class="row-link" href="<?= e(url('classes/view', ['id' => $r['id']])) ?>"><?= e($r['name']) ?></a><?= $r['status'] !== 'active' ? ' ' . badge('Lưu trữ') : '' ?><?php if ($r['description']): ?><div class="text-sm text-muted"><?= e($r['description']) ?></div><?php endif; ?></td>
          <td><?= $r['grade'] ? 'Khối ' . (int) $r['grade'] : '—' ?></td>
          <td><?= e($r['school_year']) ?></td>
          <td><?= $r['homeroom_name'] ? e($r['homeroom_name']) : '<span class="text-faint">Chưa phân công</span>' ?></td>
          <td class="center"><a href="<?= e(url('students', ['class_id' => $r['id']])) ?>" class="badge badge-primary"><?= (int) $r['students'] ?> HS</a></td>
          <td class="center"><?= (int) $r['teachers'] ?></td>
          <td class="col-actions">
            <div class="table-actions">
              <a class="btn btn-sm btn-ghost" href="<?= e(url('classes/view', ['id' => $r['id']])) ?>" title="Xem"><?= icon('eye') ?></a>
              <?php if ($canManage): ?>
                <a class="btn btn-sm btn-ghost" href="<?= e(url('classes/edit', ['id' => $r['id']])) ?>" title="Sửa"><?= icon('square-pen') ?></a>
                <button type="button" class="btn btn-sm btn-ghost text-danger" title="Xóa" data-post="<?= e(url('classes/delete', ['id' => $r['id']])) ?>" data-fields='{"students":"detach"}' data-confirm="Xóa lớp <?= e($r['name']) ?>? Học sinh của lớp sẽ được giữ lại ở trạng thái chưa xếp lớp." data-danger><?= icon('trash-2') ?></button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </form>
</div>
