<div class="page-head">
  <div><h1>Phân quyền theo vai trò</h1><div class="sub">Tích chọn quyền cho từng vai trò. Quản trị hệ thống luôn có toàn quyền.</div></div>
  <div class="actions"><button class="btn" type="button" id="new-role"><?= icon('plus') ?> Thêm vai trò</button><button class="btn btn-primary" form="roles-form"><?= icon('save') ?> Lưu phân quyền</button></div>
</div>
<form method="post" action="<?= e(url('roles/save')) ?>" id="roles-form" class="card">
  <?= csrf_field() ?>
  <div class="table-wrap">
    <table class="table perm-table compact">
      <thead>
        <tr>
          <th style="min-width:280px">Quyền</th>
          <?php foreach ($roles as $r): ?>
            <th style="min-width:110px;white-space:normal">
              <div style="text-transform:none;letter-spacing:0;font-size:13px;color:var(--text)"><?= e($r['name']) ?></div>
              <div class="text-xs" style="text-transform:none;letter-spacing:0"><?= (int) $r['users'] ?> người · <?= $r['kind'] === 'student' ? 'cổng HS' : 'quản trị' ?></div>
              <?php if (!(int) $r['is_system']): ?><button type="button" class="btn btn-xs btn-ghost text-danger" data-post="<?= e(url('roles/delete', ['id' => $r['id']])) ?>" data-confirm="Xóa vai trò <?= e($r['name']) ?>?" data-danger><?= icon('trash-2') ?></button><?php endif; ?>
            </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($groups as $g => $perms): ?>
        <tr class="group-row"><td colspan="<?= count($roles) + 1 ?>"><?= e($g) ?></td></tr>
        <?php foreach ($perms as $p => $label): ?>
          <tr>
            <td><div class="fw-600" style="font-size:13.5px"><?= e($label) ?></div><div class="text-xs text-muted mono"><?= e($p) ?></div></td>
            <?php foreach ($roles as $r): $isAdmin = in_array('*', $r['perms'], true); ?>
              <td><input type="checkbox" name="perm[<?= e($r['code']) ?>][<?= e($p) ?>]" value="1" <?= $isAdmin || in_array($p, $r['perms'], true) ? 'checked' : '' ?> <?= $isAdmin ? 'disabled' : '' ?> style="width:17px;height:17px;accent-color:var(--primary)"></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-foot"><span class="text-sm text-muted grow">Quyền "Xem học sinh các lớp được phân công" giới hạn theo lớp chủ nhiệm / lớp giảng dạy của giáo viên.</span><button class="btn btn-primary"><?= icon('save') ?> Lưu phân quyền</button></div>
</form>
<template id="role-tpl">
  <form method="post" action="<?= e(url('roles/create')) ?>" class="stack">
    <?= csrf_field() ?>
    <div class="field"><label>Tên vai trò</label><input class="input" name="name" required placeholder="VD: Tổ trưởng chuyên môn"></div>
    <div class="field"><label>Loại</label><select class="select" name="kind"><option value="staff">Cán bộ / giáo viên (trang quản trị)</option><option value="student">Người dự thi (cổng học sinh)</option></select></div>
    <div class="field"><label>Sao chép quyền từ</label><select class="select" name="copy_from"><option value="">— Không —</option><?php foreach ($roles as $r): if ($r['code'] === 'admin') continue; ?><option value="<?= e($r['code']) ?>"><?= e($r['name']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>Mô tả</label><input class="input" name="description"></div>
    <div class="row end"><button class="btn btn-primary"><?= icon('plus') ?> Tạo vai trò</button></div>
  </form>
</template>
<?php \App\Core\View::push('scripts', '<script>TN.ready(function(){document.getElementById("new-role").onclick=function(){TN.modal({title:"Thêm vai trò",icon:"shield-check",body:document.getElementById("role-tpl").content.cloneNode(true),foot:false});};});</script>'); ?>
