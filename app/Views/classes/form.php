<?php
$isNew = (int) $c['id'] === 0;
$teacherOpts = '<option value="">— Chọn giáo viên —</option>';
foreach ($teachers as $t) {
    $teacherOpts .= '<option value="' . (int) $t['id'] . '">' . e($t['full_name']) . ($t['code'] ? ' (' . e($t['code']) . ')' : '') . '</option>';
}
$subjectOpts = '<option value="">— Môn —</option>';
foreach ($subjects as $s) {
    $subjectOpts .= '<option value="' . (int) $s['id'] . '">' . e($s['name']) . '</option>';
}
?>
<div class="page-head"><div><h1><?= $isNew ? 'Thêm lớp học' : 'Sửa lớp ' . e($c['name']) ?></h1></div></div>
<form method="post" action="<?= e(url('classes/save')) ?>" class="grid grid-sidebar">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
  <div class="stack" style="gap:20px">
    <div class="card">
      <div class="card-head"><h3><?= icon('school') ?> Thông tin lớp</h3></div>
      <div class="card-body">
        <div class="form-grid">
          <div class="field"><label>Tên lớp <span class="req">*</span></label><input class="input" name="name" value="<?= e($c['name']) ?>" required placeholder="VD: 12A1" autofocus></div>
          <div class="field"><label>Khối</label><select class="select" name="grade"><?php foreach ([10, 11, 12, 9, 8, 7, 6] as $g): ?><option value="<?= $g ?>"<?= selected($c['grade'], $g) ?>>Khối <?= $g ?></option><?php endforeach; ?></select></div>
          <div class="field"><label>Năm học</label><input class="input" name="school_year" value="<?= e($c['school_year']) ?>" placeholder="2025-2026"></div>
          <div class="field"><label>Giáo viên chủ nhiệm</label><select class="select" name="homeroom_teacher_id"><?= str_replace('value="' . (int) $c['homeroom_teacher_id'] . '"', 'value="' . (int) $c['homeroom_teacher_id'] . '" selected', $teacherOpts) ?></select><div class="help">GVCN xem được học sinh, kết quả của lớp.</div></div>
          <div class="field span-2"><label>Mô tả</label><input class="input" name="description" value="<?= e($c['description']) ?>" placeholder="VD: Lớp chuyên Toán"></div>
          <div class="field"><label>Trạng thái</label><select class="select" name="status"><option value="active"<?= selected($c['status'], 'active') ?>>Đang học</option><option value="archived"<?= selected($c['status'], 'archived') ?>>Lưu trữ</option></select></div>
        </div>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h3><?= icon('users') ?> Phân công giáo viên bộ môn</h3><button type="button" class="btn btn-sm btn-soft" id="add-assign"><?= icon('plus') ?> Thêm phân công</button></div>
      <div class="card-body">
        <p class="text-sm text-muted">Giáo viên được phân công xem được học sinh và kết quả thi của lớp này (theo quyền "lớp được phân công").</p>
        <div id="assign-list" class="stack-sm">
          <?php foreach ($assign as $a): ?>
            <div class="row nowrap assign-row">
              <select class="select" name="assign_user[]"><?= str_replace('value="' . (int) $a['user_id'] . '"', 'value="' . (int) $a['user_id'] . '" selected', $teacherOpts) ?></select>
              <select class="select" name="assign_subject[]" style="max-width:220px"><?= str_replace('value="' . (int) $a['subject_id'] . '"', 'value="' . (int) $a['subject_id'] . '" selected', $subjectOpts) ?></select>
              <button type="button" class="btn btn-icon btn-ghost" data-remove><?= icon('x') ?></button>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
  <div>
    <div class="card">
      <div class="card-body">
        <p class="text-sm text-muted">Mẹo: tên lớp bắt đầu bằng số khối (10, 11, 12) giúp chức năng <b>Lên lớp</b> đổi tên tự động khi sang năm học mới.</p>
      </div>
      <div class="card-foot"><a class="btn btn-ghost" href="<?= e(url('classes')) ?>">Hủy</a><button class="btn btn-primary"><?= icon('save') ?> Lưu lớp</button></div>
    </div>
  </div>
</form>
<template id="assign-tpl"><div class="row nowrap assign-row"><select class="select" name="assign_user[]"><?= $teacherOpts ?></select><select class="select" name="assign_subject[]" style="max-width:220px"><?= $subjectOpts ?></select><button type="button" class="btn btn-icon btn-ghost" data-remove><?= icon('x') ?></button></div></template>
<?php \App\Core\View::push('scripts', '<script>TN.ready(function(){var l=document.getElementById("assign-list"),t=document.getElementById("assign-tpl");document.getElementById("add-assign").onclick=function(){l.appendChild(t.content.cloneNode(true));};l.addEventListener("click",function(e){var b=e.target.closest("[data-remove]");if(b)b.closest(".assign-row").remove();});});</script>'); ?>
