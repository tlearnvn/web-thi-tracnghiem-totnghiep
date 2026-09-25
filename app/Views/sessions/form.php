<?php
use App\Lib\ExamFormat;
use App\Lib\Seb;
use App\Lib\Sessions;

$isNew = (int) $s['id'] === 0;
$byGrade = [];
foreach ($classes as $c) {
    $byGrade[$c['grade'] ? 'Khối ' . $c['grade'] : 'Khác'][] = $c;
}
$sw = static function (string $key, string $title, string $desc) use ($opts): string {
    return '<div class="switch-row"><div><div class="t">' . e($title) . '</div><div class="d">' . e($desc) . '</div></div><label class="switch"><input type="checkbox" name="opt_' . $key . '" value="1"' . checked(!empty($opts[$key])) . '><span class="track"></span></label></div>';
};
?>
<div class="page-head"><div><h1><?= $isNew ? 'Tạo ca thi' : 'Sửa ca thi' ?></h1><div class="sub">Thời gian theo giờ Việt Nam (UTC+7).</div></div></div>
<form method="post" action="<?= e(url('sessions/save')) ?>" class="grid grid-sidebar" id="session-form">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
  <div class="stack" style="gap:20px">
    <div class="card">
      <div class="card-head"><h3><?= icon('file-text') ?> Đề thi & hình thức</h3></div>
      <div class="card-body">
        <div class="radio-cards mb-3">
          <label class="radio-card<?= $s['mode'] === 'exam' ? ' checked' : '' ?>"><input type="radio" name="mode" value="exam"<?= checked($s['mode'] === 'exam') ?>><span class="rc-icon"><?= icon('clipboard-check') ?></span><span><span class="rc-title">Thi / kiểm tra</span><span class="rc-desc">Làm 1 lần, khóa thiết bị, giám sát, công bố điểm theo cài đặt</span></span></label>
          <label class="radio-card<?= $s['mode'] === 'practice' ? ' checked' : '' ?>"><input type="radio" name="mode" value="practice"<?= checked($s['mode'] === 'practice') ?>><span class="rc-icon"><?= icon('target') ?></span><span><span class="rc-title">Luyện tập</span><span class="rc-desc">Làm nhiều lần, xem điểm & lời giải ngay sau khi nộp</span></span></label>
        </div>
        <div class="form-grid">
          <div class="field span-2"><label>Đề thi <span class="req">*</span></label>
            <select class="select" name="exam_id" id="exam-select" required><option value="">— Chọn đề thi —</option>
              <?php foreach ($exams as $x): $st = ExamFormat::normalizeStructure($x['structure']); ?><option value="<?= (int) $x['id'] ?>" data-duration="<?= (int) $x['duration'] ?>"<?= selected($s['exam_id'], $x['id']) ?>><?= e($x['title']) ?> · <?= e($x['subject_name'] ?? '') ?> · <?= e(ExamFormat::describe($st)) ?> · <?= (int) $x['variants'] ?> mã đề</option><?php endforeach; ?>
            </select></div>
          <div class="field span-2"><label>Tên ca thi <span class="req">*</span></label><input class="input" name="name" value="<?= e(old('name', $s['name'])) ?>" required placeholder="VD: Thi thử tốt nghiệp lần 1 – Toán – Ca sáng"></div>
          <div class="field"><label>Bắt đầu</label><input class="input" type="datetime-local" name="start_at" value="<?= e(input_datetime($s['start_at'])) ?>"><div class="help">Để trống: mở ngay khi tạo</div></div>
          <div class="field"><label>Kết thúc</label><input class="input" type="datetime-local" name="end_at" value="<?= e(input_datetime($s['end_at'])) ?>"><div class="help">Để trống: không giới hạn (luyện tập)</div></div>
          <div class="field"><label>Thời gian làm bài (phút)</label><input class="input" type="number" name="duration" id="duration" min="0" max="600" value="<?= e((string) $s['duration']) ?>" placeholder="Theo đề"><div class="help">0 = không giới hạn</div></div>
          <div class="field"><label>Phòng thi / địa điểm</label><input class="input" name="room" value="<?= e($s['room']) ?>" placeholder="VD: Phòng máy 1"></div>
          <div class="field"><label>Cho vào muộn tối đa (phút)</label><input class="input" type="number" name="late_join" min="0" value="<?= (int) $s['late_join'] ?>"><div class="help">0 = không giới hạn. Kỳ thi chính thức: 15 phút.</div></div>
          <div class="field"><label>Số lượt làm bài</label><input class="input" type="number" name="max_attempts" min="0" value="<?= (int) $s['max_attempts'] ?>"><div class="help">0 = không giới hạn (luyện tập)</div></div>
          <div class="field"><label>Mã vào phòng (tùy chọn)</label><div class="input-group"><input class="input mono" name="access_code" id="access-code" value="<?= e($s['access_code']) ?>" placeholder="Không yêu cầu"><button type="button" class="btn" id="gen-code"><?= icon('shuffle') ?></button></div><div class="help">Giám thị đọc mã tại phòng để học sinh bắt đầu.</div></div>
          <div class="field"><label>Chia mã đề</label><select class="select" name="variant_mode" id="variant-mode"><?php foreach (Sessions::VARIANT_MODES as $k => $v): ?><option value="<?= e($k) ?>"<?= selected($s['variant_mode'], $k) ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
          <div class="field" id="fixed-box"><label>Mã đề cố định</label><select class="select" name="fixed_variant_id" id="fixed-variant"><?php foreach ($variants as $v): ?><option value="<?= (int) $v['id'] ?>"<?= selected($s['fixed_variant_id'], $v['id']) ?>><?= e($v['code']) ?></option><?php endforeach; ?></select></div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h3><?= icon('users') ?> Đối tượng dự thi</h3><span class="hint" id="target-count"></span></div>
      <div class="card-body">
        <?php if (!$classes): ?><p class="text-muted">Chưa có lớp nào trong phạm vi của bạn.</p><?php endif; ?>
        <?php foreach ($byGrade as $g => $list): ?>
          <div class="section-title mt-0"><?= e($g) ?> <button type="button" class="btn btn-xs btn-ghost" data-toggle-grade><?= icon('square-check-big') ?> Chọn cả khối</button></div>
          <div class="row mb-2" style="gap:8px" data-grade>
            <?php foreach ($list as $c): ?><label class="chip"><input type="checkbox" name="classes[]" value="<?= (int) $c['id'] ?>"<?= in_array((int) $c['id'], $targetClasses, true) ? ' checked' : '' ?> style="accent-color:var(--primary)"> <?= e($c['name']) ?></label><?php endforeach; ?>
          </div>
        <?php endforeach; ?>
        <div class="field mt-2"><label>Thêm học sinh cụ thể (mã HS hoặc tên đăng nhập, mỗi dòng / dấu phẩy một người)</label><textarea class="textarea mono" name="extra_users" rows="3" placeholder="VD: thi lại, thi bù…"><?= e($extraUsers) ?></textarea></div>
        <div class="field mt-2"><label>Giám thị được phân công</label>
          <select class="select" name="proctors[]" multiple size="5"><?php foreach ($staff as $u): ?><option value="<?= (int) $u['id'] ?>"<?= in_array((int) $u['id'], $proctors, true) ? ' selected' : '' ?>><?= e($u['full_name']) ?></option><?php endforeach; ?></select>
          <div class="help">Giám thị xem được màn hình giám sát, cộng giờ, mở khóa thiết bị, thu bài. Giữ Ctrl để chọn nhiều.</div></div>
      </div>
    </div>
  </div>

  <div class="stack" style="gap:20px">
    <div class="card">
      <div class="card-head"><h3><?= icon('shield-check') ?> Chống gian lận & sự cố</h3></div>
      <div class="card-body" style="padding-top:6px">
        <?= $sw('device_lock', 'Khóa thiết bị', 'Mỗi bài chỉ làm trên 1 máy; đổi máy cần giám thị mở khóa') ?>
        <?= $sw('auto_reclaim', 'Tự nhận lại cùng máy', 'Trình duyệt bị đóng/xóa dữ liệu nhưng cùng IP & trình duyệt thì vào lại được ngay') ?>
        <?= $sw('track_focus', 'Ghi nhận rời màn hình', 'Chuyển tab, thu nhỏ cửa sổ… được tính là vi phạm') ?>
        <?= $sw('require_fullscreen', 'Bắt buộc toàn màn hình', 'Thoát toàn màn hình tính là vi phạm') ?>
        <?= $sw('watermark', 'Hình mờ trên đề', 'In họ tên, SBD lên trang đề để hạn chế chụp màn hình phát tán') ?>
        <?= $sw('protect_pdf', 'Chống tải tệp đề', 'Làm rối dữ liệu PDF, chặn chuột phải / in / lưu trang') ?>
        <div class="form-grid cols-1 mt-2">
          <div class="field"><label>Số lần vi phạm tối đa</label><input class="input" type="number" name="opt_max_violations" min="0" value="<?= (int) $opts['max_violations'] ?>"><div class="help">0 = không giới hạn</div></div>
          <div class="field"><label>Khi vượt quá</label><select class="select" name="opt_violation_action"><?php foreach (Sessions::VIOLATION_ACTIONS as $k => $v): ?><option value="<?= e($k) ?>"<?= selected($opts['violation_action'], $k) ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
          <div class="field span-2"><label>Tính giờ</label><select class="select" name="opt_time_policy"><?php foreach (Sessions::TIME_POLICIES as $k => $v): ?><option value="<?= e($k) ?>"<?= selected($opts['time_policy'], $k) ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label>Thời gian ân hạn (giây)</label><input class="input" type="number" name="opt_grace_seconds" min="15" max="1800" value="<?= (int) $opts['grace_seconds'] ?>"><div class="help">Nhận bài lưu muộn do mạng chậm</div></div>
          <div class="field"><label>Nộp bài sớm nhất sau (phút)</label><input class="input" type="number" name="opt_min_submit_minutes" min="0" value="<?= (int) $opts['min_submit_minutes'] ?>"></div>
        </div>
      </div>
    </div>
    <div class="card" id="seb-card">
      <div class="card-head"><h3><?= icon('lock') ?> Safe Exam Browser</h3></div>
      <div class="card-body">
        <div class="field"><label for="seb-mode">Bắt buộc làm bài bằng Safe Exam Browser</label>
          <select class="select" name="opt_seb" id="seb-mode"><?php foreach (Seb::MODES as $k => $v): ?><option value="<?= e($k) ?>"<?= selected($opts['seb'], $k) ?>><?= e($v) ?></option><?php endforeach; ?></select>
          <div class="help">SEB (miễn phí, cho Windows, macOS, iPad) khóa máy trong giờ thi: không mở được ứng dụng hay trang web khác, không sao chép, chụp màn hình. Học sinh mở bài bằng nút <b>Mở bằng Safe Exam Browser</b> ở phòng chờ. <a href="<?= e(Seb::DOWNLOAD_URL) ?>" target="_blank" rel="noopener">Tải SEB <?= icon('external-link', 'sm') ?></a></div></div>
        <div class="help mt-2" data-seb="config"><b>Khuyên dùng.</b> Hệ thống tự tạo tệp .seb cho ca thi và kiểm tra Config Key của SEB ở mọi thao tác làm bài.</div>
        <div class="field mt-2" data-seb="config"><label for="seb-quit">Mật khẩu thoát SEB <span class="text-muted">(tùy chọn)</span></label>
          <input class="input" type="password" id="seb-quit" name="seb_quit_password" autocomplete="new-password" placeholder="<?= $opts['seb_quit_hash'] ? 'Đã đặt – để trống nếu giữ nguyên' : 'Không đặt: học sinh tự thoát được' ?>">
          <div class="help">Có mật khẩu, học sinh chỉ thoát SEB giữa giờ khi giám thị nhập mật khẩu; nộp bài xong em bấm <b>Thoát Safe Exam Browser</b> – không cần mật khẩu.</div>
          <?php if ($opts['seb_quit_hash']): ?><label class="check mt-1"><input type="checkbox" name="seb_quit_clear" value="1"> <span>Bỏ mật khẩu thoát</span></label><?php endif; ?>
        </div>
        <div class="field mt-2" data-seb="keys"><label for="seb-keys">Config Key / Browser Exam Key được chấp nhận</label>
          <textarea class="textarea mono" id="seb-keys" name="opt_seb_keys" rows="3" placeholder="Mỗi dòng một khóa (64 ký tự)"><?= e($opts['seb_keys']) ?></textarea>
          <div class="help">Dùng khi nhà trường tự tạo tệp .seb bằng <i>SEB Config Tool</i>: bật <i>Use Browser Exam Key and Config Key</i> (thẻ Exam), đặt <i>Start URL</i> là địa chỉ phòng chờ của ca thi, rồi chép Config Key vào đây.</div>
        </div>
        <div class="help mt-2" data-seb="browser">Chỉ kiểm tra dấu hiệu "SEB" trong thông tin trình duyệt – học sinh rành máy tính có thể giả mạo. Dùng khi chế độ khuyên dùng báo sai khóa với phiên bản SEB của trường.</div>
        <?php if (!$isNew && $opts['seb'] !== 'off'): ?><div class="help mt-2">Đổi chế độ hoặc mật khẩu thoát khi ca thi đang diễn ra: em nào chưa vào phòng thi phải mở lại SEB bằng tệp cấu hình mới (em đang làm bài không bị ảnh hưởng).</div><?php endif; ?>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h3><?= icon('eye') ?> Điểm & xem lại bài</h3></div>
      <div class="card-body" style="padding-top:6px">
        <div class="field mt-2"><label>Cho học sinh xem điểm</label><select class="select" name="opt_show_score"><?php foreach (Sessions::SCORE_POLICIES as $k => $v): ?><option value="<?= e($k) ?>"<?= selected($opts['show_score'], $k) ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
        <div class="field mt-2"><label>Cho xem lại bài làm</label><select class="select" name="opt_allow_review"><?php foreach (Sessions::REVIEW_POLICIES as $k => $v): ?><option value="<?= e($k) ?>"<?= selected($opts['allow_review'], $k) ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
        <?= $sw('show_key', 'Hiện đáp án đúng khi xem lại', '') ?>
        <?= $sw('show_explanations', 'Hiện lời giải chi tiết', 'Lời giải từng câu nhập kèm đáp án') ?>
        <?= $sw('show_solution_pdf', 'Hiện PDF lời giải', 'Nếu mã đề có tệp lời giải') ?>
        <?= $sw('confirm_submit', 'Xác nhận khi nộp bài', 'Hiện danh sách câu chưa làm trước khi nộp') ?>
        <div class="field mt-2"><label>Nhiều lượt làm – tính điểm theo</label><select class="select" name="opt_result_policy"><option value="best"<?= selected($opts['result_policy'], 'best') ?>>Lượt cao nhất</option><option value="latest"<?= selected($opts['result_policy'], 'latest') ?>>Lượt gần nhất</option><option value="first"<?= selected($opts['result_policy'], 'first') ?>>Lượt đầu tiên</option></select></div>
      </div>
      <div class="card-foot"><a class="btn btn-ghost" href="<?= e($isNew ? url('sessions') : url('sessions/view', ['id' => $s['id']])) ?>">Hủy</a><button class="btn btn-primary"><?= icon('save') ?> <?= $isNew ? 'Tạo ca thi' : 'Lưu' ?></button></div>
    </div>
  </div>
</form>
<?php \App\Core\View::push('scripts', '<script>window.SESSION_VARIANTS_URL=' . js_json(url('sessions/variants')) . ';</script>'); ?>
<?php \App\Core\View::push('scripts', <<<'JS'
<script>
TN.ready(function () {
  var f = document.getElementById('session-form');
  var vm = document.getElementById('variant-mode'), fb = document.getElementById('fixed-box'), fv = document.getElementById('fixed-variant');
  function syncV() { fb.hidden = vm.value !== 'fixed'; }
  vm.addEventListener('change', syncV); syncV();
  f.querySelectorAll('input[name=mode]').forEach(function (r) { r.addEventListener('change', function () {
    f.querySelectorAll('.radio-card').forEach(function (c) { var i = c.querySelector('input'); c.classList.toggle('checked', i.checked); });
    if (r.checked && r.value === 'practice') { f.max_attempts.value = 0; f.late_join.value = 0; }
    if (r.checked && r.value === 'exam') { if (+f.max_attempts.value === 0) f.max_attempts.value = 1; }
  }); });
  document.getElementById('exam-select').addEventListener('change', function () {
    var id = this.value; if (!id) return;
    var opt = this.options[this.selectedIndex];
    if (!f.name.value) f.name.value = 'Ca thi – ' + opt.text.split(' · ')[0];
    TN.api(window.SESSION_VARIANTS_URL + '&exam_id=' + id).then(function (r) {
      fv.innerHTML = r.variants.map(function (v) { return '<option value="' + v.id + '">' + TN.esc(v.code) + '</option>'; }).join('');
      if (!f.duration.value) f.duration.placeholder = r.duration + ' (theo đề)';
    });
  });
  var sm = document.getElementById('seb-mode');
  function syncSeb() { f.querySelectorAll('[data-seb]').forEach(function (el) { el.hidden = el.getAttribute('data-seb') !== sm.value; }); }
  sm.addEventListener('change', syncSeb); syncSeb();
  document.getElementById('gen-code').onclick = function () { document.getElementById('access-code').value = String(Math.floor(100000 + Math.random() * 900000)); };
  function count() {
    var n = f.querySelectorAll('input[name="classes[]"]:checked').length;
    document.getElementById('target-count').textContent = n + ' lớp được chọn';
    f.querySelectorAll('.chip').forEach(function (c) { var i = c.querySelector('input'); if (i) c.classList.toggle('active', i.checked); });
  }
  f.addEventListener('change', count); count();
  f.querySelectorAll('[data-toggle-grade]').forEach(function (b) { b.onclick = function () {
    var box = b.parentNode.nextElementSibling, all = box.querySelectorAll('input'), on = [].some.call(all, function (i) { return !i.checked; });
    all.forEach(function (i) { i.checked = on; }); count();
  }; });
});
</script>
JS); ?>
