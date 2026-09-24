<?php
use App\Controllers\ExamsController;

$isNew = (int) $e['id'] === 0;
?>
<div class="page-head"><div><h1><?= $isNew ? 'Tạo đề thi mới' : 'Sửa đề thi' ?></h1><div class="sub"><?= $isNew ? 'Chọn môn để áp dụng sẵn định dạng đề thi tốt nghiệp từ năm 2025, có thể điều chỉnh tùy ý.' : e($e['title']) ?></div></div></div>
<form method="post" action="<?= e(url('exams/save')) ?>" class="grid grid-sidebar" id="exam-form">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $e['id'] ?>">
  <div>
    <div class="card mb-3">
      <div class="card-head"><h3><?= icon('file-text') ?> Thông tin đề</h3></div>
      <div class="card-body">
        <div class="form-grid">
          <div class="field span-2"><label>Tên đề thi <span class="req">*</span></label><input class="input" name="title" value="<?= e($e['title']) ?>" required placeholder="VD: Đề thi thử tốt nghiệp lần 1 – Môn Toán" autofocus></div>
          <div class="field"><label>Môn thi</label>
            <select class="select" name="subject_id" id="subject-select"><option value="">— Không chọn —</option><?php foreach ($subjects as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected($e['subject_id'], $s['id']) ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label>Thời gian làm bài (phút)</label><input class="input" type="number" name="duration" id="duration" min="0" max="600" value="<?= (int) $e['duration'] ?>"><div class="help">0 = không giới hạn (luyện tập)</div></div>
          <div class="field"><label>Khối</label><select class="select" name="grade"><option value="">—</option><?php foreach ([12, 11, 10, 9, 8, 7, 6] as $g): ?><option value="<?= $g ?>"<?= selected($e['grade'], $g) ?>>Khối <?= $g ?></option><?php endforeach; ?></select></div>
          <div class="field"><label>Trạng thái</label><select class="select" name="status"><?php foreach (ExamsController::STATUSES as $k => $v): ?><option value="<?= e($k) ?>"<?= selected($e['status'], $k) ?>><?= e($v[0]) ?></option><?php endforeach; ?></select></div>
          <?php if ($isNew): ?><div class="field span-2"><label>Các mã đề</label><input class="input mono" name="codes" value="<?= e($codes) ?>" placeholder="VD: 0101, 0102, 0103, 0104"><div class="help">Cách nhau bởi dấu phẩy. Có thể thêm / bớt mã đề sau. Nhập đáp án từ Excel cũng tự tạo mã đề còn thiếu.</div></div><?php endif; ?>
          <div class="field span-2"><label>Mô tả / ghi chú</label><textarea class="textarea" name="description" rows="2"><?= e($e['description']) ?></textarea></div>
          <label class="switch span-2"><input type="checkbox" name="is_shared" value="1"<?= checked((int) $e['is_shared']) ?>><span class="track"></span><span>Chia sẻ đề cho giáo viên khác<small class="help" style="display:block">Giáo viên khác xem được đề và dùng để tạo ca thi (không sửa được).</small></span></label>
        </div>
      </div>
    </div>
    <?= \App\Core\View::partial('partials/format_editor', ['structure' => $structure, 'scoring' => $scoring, 'locked' => $locked]) ?>
  </div>
  <div class="stack" style="gap:20px">
    <div class="card" style="position:sticky;top:84px">
      <div class="card-body stack">
        <div class="callout text-sm">Định dạng 2025: <b>Toán</b> 12 TN + 4 Đ/S + 6 TLN (90 phút) · <b>Lí, Hóa, Sinh, Địa</b> 18 + 4 + 6 · <b>Sử, GDKT&PL, Tin, Công nghệ</b> 24 + 4 · <b>Ngoại ngữ</b> 40 câu · <b>Ngữ văn</b> tự luận 120 phút.</div>
        <?php if ($locked): ?><label class="check"><input type="checkbox" name="rescore" value="1" checked><span>Chấm lại các bài đã nộp theo cách tính điểm mới</span></label><?php endif; ?>
      </div>
      <div class="card-foot"><a class="btn btn-ghost" href="<?= e($isNew ? url('exams') : url('exams/view', ['id' => $e['id']])) ?>">Hủy</a><button class="btn btn-primary"><?= icon('save') ?> <?= $isNew ? 'Tạo đề' : 'Lưu' ?></button></div>
    </div>
  </div>
</form>
<?php \App\Core\View::push('scripts', '<script>TN.ready(function(){var S=' . js_json($subjects) . ',sel=document.getElementById("subject-select"),isNew=' . ($isNew ? 'true' : 'false') . ';sel.addEventListener("change",function(){var s=S.filter(function(x){return String(x.id)===sel.value;})[0];if(!s)return;var go=function(){window.TNFormat.apply(s.structure,s.scoring);document.getElementById("duration").value=s.duration;TN.toast("Đã áp dụng định dạng môn "+s.name,"success");};if(isNew)go();else TN.confirm({message:"Áp dụng cấu trúc & cách tính điểm mặc định của môn "+s.name+"?"}).then(function(ok){if(ok)go();});});});</script>'); ?>
