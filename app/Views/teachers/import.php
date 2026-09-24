<?php
use App\Lib\PeopleImporter;

\App\Core\View::push('scripts', '<script src="' . asset('js/import.js') . '"></script>');
\App\Core\View::push('scripts', '<script>TN.ready(function(){TNImport({form:"#import-form",output:"#import-output",url:' . js_json(url('teachers/import-run')) . ',labels:' . js_json(PeopleImporter::FIELD_LABELS) . ',columns:[["name","Họ và tên","fw-600"],["code","Mã","mono"],["subject","Môn"],["homeroom","Chủ nhiệm"],["classes","Lớp dạy"],["username","Tên đăng nhập","mono"]]});});</script>');
?>
<div class="page-head">
  <div><h1>Nhập giáo viên từ Excel</h1><div class="sub">Tạo nhanh tài khoản, gán môn, lớp chủ nhiệm và lớp giảng dạy.</div></div>
  <div class="actions"><a class="btn" href="<?= e(url('teachers/template')) ?>"><?= icon('download') ?> Tải tệp mẫu</a></div>
</div>
<form id="import-form" class="grid grid-sidebar" onsubmit="return false">
  <?= csrf_field() ?>
  <div class="card">
    <div class="card-head"><h3><?= icon('file-spreadsheet') ?> Chọn tệp danh sách</h3></div>
    <div class="card-body">
      <label class="dropzone">
        <input type="file" name="file" accept=".xlsx,.csv">
        <div class="dz-icon"><?= icon('cloud-upload') ?></div>
        <div class="dz-title">Kéo thả tệp Excel vào đây hoặc bấm để chọn</div>
        <div class="dz-hint">Cột nhận dạng: Mã GV, Họ và tên, Tên đăng nhập, Mật khẩu, Email, Điện thoại, Môn, Lớp chủ nhiệm, Lớp giảng dạy (cách nhau dấu phẩy), Vai trò.</div>
      </label>
      <div class="field mt-2" hidden><label>Sheet</label><select class="select" name="sheet"><option value="0">Sheet 1</option></select></div>
    </div>
  </div>
  <div class="card">
    <div class="card-head"><h3><?= icon('sliders-horizontal') ?> Tùy chọn</h3></div>
    <div class="card-body stack">
      <div class="field"><label>Vai trò mặc định (khi tệp không có cột Vai trò)</label>
        <select class="select" name="default_role"><?php foreach ($roles as $k => $v): ?><option value="<?= e($k) ?>"<?= selected('teacher', $k) ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
      <p class="text-sm text-muted mb-0">Mật khẩu để trống sẽ được tạo ngẫu nhiên 8 ký tự; giáo viên phải đổi mật khẩu khi đăng nhập lần đầu. Lớp chưa có sẽ được tạo mới theo năm học <?= e(setting('default_school_year')) ?>.</p>
    </div>
    <div class="card-foot"><button type="button" class="btn btn-primary btn-block" data-preview><?= icon('eye') ?> Xem trước dữ liệu</button></div>
  </div>
</form>
<div id="import-output" class="mt-3"></div>
