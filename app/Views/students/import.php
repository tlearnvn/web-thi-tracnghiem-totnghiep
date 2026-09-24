<?php
use App\Lib\PeopleImporter;
use App\Lib\Users;

\App\Core\View::push('scripts', '<script src="' . asset('js/import.js') . '"></script>');
\App\Core\View::push('scripts', '<script>TN.ready(function(){TNImport({form:"#import-form",output:"#import-output",url:' . js_json(url('students/import-run')) . ',labels:' . js_json(PeopleImporter::FIELD_LABELS) . ',columns:[["name","Họ và tên","fw-600"],["code","Mã HS","mono"],["class","Lớp"],["birthday","Ngày sinh"],["gender","Giới tính"],["username","Tên đăng nhập","mono"]]});
var pm=document.querySelector("[name=password_mode]"),pf=document.getElementById("pw-fixed");function s(){pf.hidden=pm.value!=="fixed";}pm.addEventListener("change",s);s();});</script>');
?>
<div class="page-head">
  <div><h1>Nhập học sinh từ Excel</h1><div class="sub">Hỗ trợ tệp .xlsx / .csv, kể cả tệp xuất từ phần mềm quản lý trường học. Xem trước trước khi ghi.</div></div>
  <div class="actions"><a class="btn" href="<?= e(url('students/template')) ?>"><?= icon('download') ?> Tải tệp mẫu</a></div>
</div>

<form id="import-form" class="grid grid-sidebar" onsubmit="return false">
  <?= csrf_field() ?>
  <div class="card">
    <div class="card-head"><h3><?= icon('file-spreadsheet') ?> 1. Chọn tệp danh sách</h3></div>
    <div class="card-body">
      <label class="dropzone">
        <input type="file" name="file" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv">
        <div class="dz-icon"><?= icon('cloud-upload') ?></div>
        <div class="dz-title">Kéo thả tệp Excel vào đây hoặc bấm để chọn</div>
        <div class="dz-hint">Cần có cột <b>Họ và tên</b> (hoặc Họ đệm + Tên). Các cột khác tự nhận dạng: Mã HS, Ngày sinh, Giới tính, Lớp, Tên đăng nhập, Mật khẩu, Email, Điện thoại.</div>
      </label>
      <div class="field mt-2" hidden><label>Sheet</label><select class="select" name="sheet"><option value="0">Sheet 1</option></select></div>
      <div class="alert alert-info mt-3"><?= icon('lightbulb') ?><div>
        <div class="alert-title">Mẹo</div>
        <ul>
          <li>Hệ thống tự tìm dòng tiêu đề trong 20 dòng đầu – không cần xóa phần tiêu đề trường/lớp phía trên.</li>
          <li>Học sinh đã có (trùng <b>Mã HS</b> hoặc <b>Tên đăng nhập</b>) sẽ được cập nhật thay vì tạo trùng.</li>
          <li>Mật khẩu được tạo sẵn sẽ hiển thị <b>một lần</b> sau khi nhập để tải Excel hoặc in phiếu phát cho học sinh.</li>
        </ul>
      </div></div>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h3><?= icon('sliders-horizontal') ?> 2. Tùy chọn</h3></div>
    <div class="card-body stack">
      <div class="field"><label>Lớp mặc định (khi tệp không có cột Lớp)</label>
        <select class="select" name="default_class"><option value="">— Không —</option><?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
      <?php if (!$restricted): ?>
        <label class="check"><input type="checkbox" name="create_classes" value="1" checked><span>Tự tạo lớp mới nếu chưa có<small>Năm học: <input class="input input-sm" name="school_year" value="<?= e($year) ?>" style="width:120px;display:inline-block;margin-left:4px"></small></span></label>
      <?php endif; ?>
      <div class="field"><label>Khi học sinh đã có trong hệ thống</label>
        <select class="select" name="on_duplicate"><option value="update">Cập nhật thông tin (họ tên, lớp, ngày sinh…)</option><option value="skip">Bỏ qua, giữ nguyên</option></select></div>
      <div class="field"><label>Tên đăng nhập (khi tệp không có cột này)</label>
        <select class="select" name="username_mode"><option value="code">Dùng Mã học sinh</option><option value="name">Tạo từ họ tên (vd: annv, annv2…)</option></select></div>
      <div class="field"><label>Mật khẩu (khi tệp không có cột này)</label>
        <select class="select" name="password_mode"><?php foreach (Users::PASSWORD_MODES as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select>
        <input class="input mt-1" id="pw-fixed" name="password_fixed" placeholder="Nhập mật khẩu chung" hidden></div>
      <label class="check"><input type="checkbox" name="must_change" value="1"><span>Bắt học sinh đổi mật khẩu khi đăng nhập lần đầu</span></label>
    </div>
    <div class="card-foot"><button type="button" class="btn btn-primary btn-block" data-preview><?= icon('eye') ?> Xem trước dữ liệu</button></div>
  </div>
</form>
<div id="import-output" class="mt-3"></div>
