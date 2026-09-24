<?php
use App\Controllers\AnnouncementsController;

$v = static fn(string $k) => old($k, $a[$k] ?? '');
?>
<div class="page-head"><div><h1><?= $a['id'] ? 'Sửa thông báo' : 'Tạo thông báo' ?></h1></div></div>
<form class="grid grid-sidebar" method="post" action="<?= e(url('announcements/save')) ?>">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
  <div class="card">
    <div class="card-body">
      <div class="field"><label for="title">Tiêu đề <span class="text-danger">*</span></label><input class="input input-lg" id="title" name="title" required maxlength="255" value="<?= e($v('title')) ?>" placeholder="VD: Lịch thi thử tốt nghiệp lần 2"></div>
      <div class="field"><label for="body">Nội dung</label><textarea class="textarea" id="body" name="body" rows="9" maxlength="5000" placeholder="Nội dung chi tiết…"><?= e($v('body')) ?></textarea><div class="help">Xuống dòng được giữ nguyên khi hiển thị.</div></div>
    </div>
  </div>
  <div class="stack" style="gap:20px">
    <div class="card">
      <div class="card-head"><h3><?= icon('users') ?> Gửi tới</h3></div>
      <div class="card-body">
        <div class="field"><select class="select" name="audience" id="audience"><?php foreach (AnnouncementsController::AUDIENCES as $k => $t): ?><option value="<?= e($k) ?>"<?= selected($v('audience'), $k) ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
        <div class="field" id="class-field"><label>Lớp</label><select class="select" name="class_id"><option value="">– Chọn lớp –</option><?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"<?= selected($v('class_id'), $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
        <div class="switch-row"><div><div class="fw-600">Ghim lên đầu</div><div class="help">Hiển thị nổi bật (màu vàng).</div></div><label class="switch"><input type="checkbox" name="is_pinned" value="1"<?= checked((int) $v('is_pinned') === 1) ?>><span></span></label></div>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h3><?= icon('calendar-clock') ?> Thời gian hiển thị</h3><span class="hint">UTC+7</span></div>
      <div class="card-body">
        <div class="field"><label>Từ lúc</label><input class="input" type="datetime-local" name="starts_at" value="<?= e(old('starts_at', input_datetime($a['starts_at']))) ?>"><div class="help">Để trống = hiển thị ngay.</div></div>
        <div class="field mb-0"><label>Đến lúc</label><input class="input" type="datetime-local" name="ends_at" value="<?= e(old('ends_at', input_datetime($a['ends_at']))) ?>"><div class="help">Để trống = không hết hạn.</div></div>
      </div>
    </div>
    <div class="row"><button class="btn btn-primary" type="submit"><?= icon('save') ?> Lưu thông báo</button><a class="btn btn-ghost" href="<?= e(url('announcements')) ?>">Hủy</a></div>
  </div>
</form>
<?php \App\Core\View::push('scripts', '<script>TN.ready(function () { var s = document.getElementById("audience"), f = document.getElementById("class-field"); function t() { f.hidden = s.value !== "class"; } s.addEventListener("change", t); t(); });</script>'); ?>
