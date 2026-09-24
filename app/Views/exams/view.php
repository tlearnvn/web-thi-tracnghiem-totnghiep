<?php
use App\Controllers\ExamsController;
use App\Lib\ExamFormat;
use App\Lib\Scoring;
use App\Lib\Sessions;

$st = $e['_structure'];
$sc = $e['_scoring'];
$stt = ExamsController::STATUSES[$e['status']] ?? ['', 'default'];
$color = $subject['color'] ?? '#2563eb';
?>
<div class="page-head">
  <div class="row top gap-lg">
    <div class="tile-icon" style="--tile-color:<?= e($color) ?>;width:56px;height:56px;border-radius:16px;font-size:14px"><?= e(mb_substr((string) ($subject['short_name'] ?? $subject['name'] ?? 'Đề'), 0, 4)) ?></div>
    <div>
      <div class="eyebrow"><?= e($subject['name'] ?? 'Chưa chọn môn') ?> · <?= (int) $e['duration'] ?> phút<?= $e['grade'] ? ' · Khối ' . (int) $e['grade'] : '' ?></div>
      <h1><?= e($e['title']) ?></h1>
      <div class="sub row gap-sm"><?= badge($stt[0], $stt[1]) ?><?= (int) $e['is_shared'] ? badge('Chia sẻ', 'purple', 'users') : '' ?><span>Người tạo: <?= e($owner['full_name'] ?? '—') ?></span></div>
    </div>
  </div>
  <div class="actions">
    <?php if (can('sessions.manage', 'sessions.manage_all')): ?><a class="btn btn-primary" href="<?= e(url('sessions/create', ['exam_id' => $e['id']])) ?>"><?= icon('calendar-clock') ?> Tạo ca thi với đề này</a><?php endif; ?>
    <div class="dropdown">
      <button class="btn" data-dropdown><?= icon('ellipsis') ?> Thao tác</button>
      <div class="dropdown-menu">
        <?php if ($canManage): ?><a href="<?= e(url('exams/edit', ['id' => $e['id']])) ?>"><?= icon('square-pen') ?> Sửa thông tin & cấu trúc</a><?php endif; ?>
        <?php if (can('exams.manage', 'exams.manage_all')): ?><button type="button" data-post="<?= e(url('exams/duplicate', ['id' => $e['id']])) ?>" data-confirm="Tạo bản sao của đề (kèm mã đề, PDF, đáp án)?"><?= icon('copy') ?> Nhân bản đề</button><?php endif; ?>
        <a href="<?= e(url('exams/key-export', ['id' => $e['id']])) ?>"><?= icon('file-down') ?> Tải đáp án hiện có (Excel)</a>
        <?php if ($canManage): ?><hr><button type="button" class="danger" data-post="<?= e(url('exams/delete', ['id' => $e['id']])) ?>" data-confirm="Xóa đề thi này cùng toàn bộ mã đề, tệp PDF và đáp án?" data-danger><?= icon('trash-2') ?> Xóa đề thi</button><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="mini-stats mb-3">
  <div class="mini-stat"><div class="v"><?= (int) $st['p1'] ?></div><div class="l">Phần I – trắc nghiệm</div></div>
  <div class="mini-stat"><div class="v"><?= (int) $st['p2'] ?></div><div class="l">Phần II – đúng/sai</div></div>
  <div class="mini-stat"><div class="v"><?= (int) $st['p3'] ?></div><div class="l">Phần III – trả lời ngắn</div></div>
  <div class="mini-stat"><div class="v"><?= count($st['essay']) ?></div><div class="l">Tự luận</div></div>
  <div class="mini-stat"><div class="v"><?= e(fmt_num(Scoring::maxScore($st, $sc))) ?></div><div class="l">Điểm tối đa</div></div>
</div>
<div class="callout mb-3 text-sm"><?= icon('calculator', 'sm') ?> <b><?= e(Scoring::SCHEMES[$sc['scheme']]) ?>:</b> <?= e(Scoring::describe($sc, $st)) ?></div>

<div class="card">
  <div class="card-head">
    <h3><?= icon('files') ?> Mã đề (<?= count($variants) ?>)</h3>
    <?php if ($canManage): ?>
    <div class="row gap-sm">
      <form class="input-group" id="add-variant" style="width:auto"><input class="input input-sm mono" name="code" placeholder="Mã đề mới" style="width:130px"><button class="btn btn-sm btn-soft"><?= icon('plus') ?> Thêm</button></form>
      <label class="btn btn-sm"><?= icon('file-up') ?> Tải nhiều PDF<input type="file" id="bulk-pdf" accept="application/pdf,.pdf" multiple hidden></label>
    </div>
    <?php endif; ?>
  </div>
  <?php if (!$variants): ?>
    <div class="empty" style="padding:30px"><p>Chưa có mã đề. Thêm mã đề hoặc nhập đáp án từ Excel để tạo tự động.</p></div>
  <?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Mã đề</th><th>Tệp đề (PDF)</th><th class="hide-sm">Lời giải (PDF)</th><th>Đáp án</th><th class="center hide-sm">Bài làm</th><th class="col-actions"></th></tr></thead>
    <tbody>
    <?php foreach ($variants as $v):
        $pct = $expected ? min(100, round((int) $v['answered'] * 100 / $expected)) : 100;
        $meta = json_dec($v['pdf_meta'], []); ?>
      <tr data-variant="<?= (int) $v['id'] ?>">
        <td><span class="badge badge-lg badge-primary mono"><?= e($v['code']) ?></span></td>
        <td>
          <?php if ($v['pdf_file_id']): ?>
            <div class="person"><span class="stat-icon danger" style="width:34px;height:34px;border-radius:10px"><?= icon('file-text', 'sm') ?></span><div><div class="name text-sm"><?= e(str_limit($v['pdf_name'], 36)) ?></div><div class="sub"><?= e(fmt_bytes($v['pdf_size'])) ?><?= !empty($meta['pages']) ? ' · ' . (int) $meta['pages'] . ' trang' : '' ?></div></div></div>
          <?php else: ?><span class="badge badge-warning"><?= icon('triangle-alert') ?> Chưa có PDF</span><?php endif; ?>
          <div class="upload-progress" hidden><div class="progress progress-sm"><span style="width:0%"></span></div></div>
        </td>
        <td class="hide-sm"><?php if ($v['solution_file_id']): ?><span class="text-sm"><?= icon('file-check', 'sm') ?> <?= e(str_limit($v['sol_name'], 28)) ?></span><?php else: ?><span class="text-faint text-sm">—</span><?php endif; ?></td>
        <td style="min-width:150px">
          <div class="progress-label"><span><?= (int) $v['answered'] ?>/<?= (int) $expected ?> câu<?= (int) $v['explained'] ? ' · ' . (int) $v['explained'] . ' lời giải' : '' ?></span></div>
          <div class="progress progress-sm <?= $pct >= 100 ? 'success' : 'warning' ?>"><span style="width:<?= $pct ?>%"></span></div>
        </td>
        <td class="center hide-sm"><?= (int) $v['attempts'] ?></td>
        <td class="col-actions">
          <div class="table-actions">
            <a class="btn btn-sm btn-soft" href="<?= e(url('variants/key', ['id' => $v['id']])) ?>"><?= icon($canManage ? 'square-pen' : 'eye') ?> <?= $canManage ? 'Đề & đáp án' : 'Xem' ?></a>
            <?php if ($canManage): ?>
            <div class="dropdown">
              <button class="btn btn-sm btn-ghost btn-icon" data-dropdown><?= icon('ellipsis-vertical') ?></button>
              <div class="dropdown-menu">
                <button type="button" data-upload="exam" data-id="<?= (int) $v['id'] ?>"><?= icon('file-up') ?> <?= $v['pdf_file_id'] ? 'Thay tệp PDF đề' : 'Tải tệp PDF đề' ?></button>
                <button type="button" data-upload="solution" data-id="<?= (int) $v['id'] ?>"><?= icon('file-check') ?> <?= $v['solution_file_id'] ? 'Thay PDF lời giải' : 'Tải PDF lời giải' ?></button>
                <?php if ($v['solution_file_id']): ?><button type="button" data-post="<?= e(url('variants/remove-file', ['id' => $v['id']])) ?>" data-fields='{"kind":"solution"}' data-confirm="Gỡ PDF lời giải của mã đề <?= e($v['code']) ?>?"><?= icon('x') ?> Gỡ PDF lời giải</button><?php endif; ?>
                <hr>
                <button type="button" class="danger" data-post="<?= e(url('variants/delete', ['id' => $v['id']])) ?>" data-confirm="Xóa mã đề <?= e($v['code']) ?>? <?= (int) $v['attempts'] ? 'Mã đề đã có ' . (int) $v['attempts'] . ' bài làm – KHÔNG thể xóa.' : '' ?>" data-danger><?= icon('trash-2') ?> Xóa mã đề</button>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>

<?php if ($canManage): ?>
<div class="grid grid-sidebar mt-3">
  <div class="card" id="key-import">
    <div class="card-head"><h3><?= icon('file-spreadsheet') ?> Nhập đáp án từ Excel / JSON</h3><div class="row gap-sm"><a class="btn btn-sm" href="<?= e(url('exams/key-template', ['id' => $e['id']])) ?>"><?= icon('download') ?> Mẫu Excel</a><a class="btn btn-sm" href="<?= e(url('exams/key-template', ['id' => $e['id'], 'format' => 'json'])) ?>"><?= icon('braces') ?> Mẫu JSON</a></div></div>
    <form class="card-body" id="key-form" onsubmit="return false">
      <?= csrf_field() ?>
      <label class="dropzone">
        <input type="file" name="file" accept=".xlsx,.csv,.json,application/json">
        <div class="dz-icon"><?= icon('cloud-upload') ?></div>
        <div class="dz-title">Chọn tệp đáp án (.xlsx, .csv, .json)</div>
        <div class="dz-hint">Nhận dạng tự động 3 kiểu bảng: mỗi dòng một câu · mỗi dòng một mã đề · mỗi cột một mã đề (phần mềm trộn đề). Có thể kèm cột <b>Lời giải</b>, <b>Mức độ</b>, <b>Chủ đề</b>, <b>Điểm</b>.</div>
      </label>
      <div class="form-grid mt-3">
        <div class="field"><label>Cách ghi</label><select class="select" name="mode"><option value="replace">Thay toàn bộ đáp án của mã đề trong tệp</option><option value="merge">Chỉ cập nhật các câu có trong tệp (vd: bổ sung lời giải)</option></select></div>
        <div class="field"><label>Tệp không có cột Mã đề → gán cho</label><select class="select" name="variant_id"><?php foreach ($variants as $v): ?><option value="<?= (int) $v['id'] ?>"><?= e($v['code']) ?></option><?php endforeach; ?></select></div>
        <label class="check span-2"><input type="checkbox" name="create_missing" value="1" checked><span>Tự tạo mã đề chưa có trong đề thi</span></label>
      </div>
      <div class="row end mt-2"><button type="button" class="btn btn-primary" id="key-preview"><?= icon('eye') ?> Xem trước</button></div>
      <div id="key-output" class="mt-3"></div>
    </form>
  </div>
  <div class="card">
    <div class="card-head"><h3><?= icon('lightbulb') ?> Quy ước đáp án</h3></div>
    <div class="card-body text-sm stack-sm">
      <div><b>Phần I:</b> A, B, C, D. Nhiều đáp án đúng: <code>A|C</code>. Hủy câu (cho điểm tất cả): <code>*</code></div>
      <div><b>Phần II:</b> 4 ký tự cho ý a–d: <code>ĐSĐĐ</code> (hoặc DSDD, TFTT, 1011). Ý hủy: <code>*</code></div>
      <div><b>Phần III:</b> số, dấu phẩy thập phân: <code>-1,5</code>. Nhiều đáp án: <code>0,5|0,50</code></div>
      <div><b>Lời giải:</b> văn bản, hỗ trợ <code>**đậm**</code>, danh sách <code>- ý</code>, công thức <code>$x^2$</code></div>
      <div class="alert alert-info mt-1"><?= icon('rotate-ccw') ?><div>Nhập lại đáp án sau khi thi xong sẽ <b>tự chấm lại</b> các bài đã nộp của mã đề đó.</div></div>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="card mt-3">
  <div class="card-head"><h3><?= icon('calendar-clock') ?> Ca thi sử dụng đề này</h3></div>
  <?php if (!$sessions): ?><div class="card-body text-muted">Chưa có ca thi nào.</div><?php else: ?>
  <div class="table-wrap"><table class="table compact">
    <thead><tr><th>Ca thi</th><th>Hình thức</th><th>Thời gian</th><th>Trạng thái</th><th class="center">Đã nộp</th><th class="col-actions"></th></tr></thead>
    <tbody><?php foreach ($sessions as $s): ?>
      <tr><td><a class="row-link" href="<?= e(url('sessions/view', ['id' => $s['id']])) ?>"><?= e($s['name']) ?></a></td><td><?= e(Sessions::MODES[$s['mode']] ?? $s['mode']) ?></td><td class="text-sm"><?= e(fmt_dt($s['start_at'])) ?><?= $s['end_at'] ? ' → ' . e(fmt_dt($s['end_at'], 'H:i d/m')) : '' ?></td><td><?= Sessions::stateBadge($s) ?></td><td class="center"><?= (int) $s['done'] ?></td><td class="col-actions"><a class="btn btn-sm btn-ghost" href="<?= e(url('results/session', ['id' => $s['id']])) ?>"><?= icon('clipboard-check') ?> Kết quả</a></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>

<input type="file" id="single-pdf" accept="application/pdf,.pdf" hidden>
<?php \App\Core\View::push('scripts', '<script>window.EXAM_ID=' . (int) $e['id'] . ';</script>'); ?>
<?php \App\Core\View::push('scripts', <<<'JS'
<script>
TN.ready(function () {
  var examId = window.EXAM_ID;
  // Thêm mã đề
  var av = document.getElementById('add-variant');
  if (av) av.addEventListener('submit', function (e) {
    e.preventDefault();
    var code = av.code.value.trim(); if (!code) return;
    TN.api(TN.url('variants/create'), { data: { exam_id: examId, code: code } }).then(function () { location.reload(); }).catch(function (x) { TN.toast(x.message, 'error'); });
  });
  // Tải PDF cho từng mã đề (theo từng khúc 512 KB – không bị giới hạn upload của hosting)
  var single = document.getElementById('single-pdf'), target = null;
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-upload]'); if (!b) return;
    target = { id: b.dataset.id, kind: b.dataset.upload }; single.value = ''; single.click();
  });
  function upload(file, attach, row) {
    if (!/\.pdf$/i.test(file.name) && file.type !== 'application/pdf') { TN.toast('Chỉ nhận tệp PDF: ' + file.name, 'error'); return Promise.resolve(); }
    var bar = row ? row.querySelector('.upload-progress') : null;
    if (bar) bar.hidden = false;
    return TN.upload(file, { purpose: attach.kind === 'solution' ? 'solution_pdf' : 'exam_pdf', attach: attach, onProgress: function (p) { if (bar) bar.querySelector('span').style.width = Math.round(p * 100) + '%'; } })
      .then(function (r) { TN.toast('Đã tải lên ' + file.name + (r.pages ? ' (' + r.pages + ' trang)' : ''), 'success'); });
  }
  single.addEventListener('change', function () {
    var f = single.files[0]; if (!f || !target) return;
    var row = document.querySelector('tr[data-variant="' + target.id + '"]');
    upload(f, { type: 'variant', variant_id: target.id, kind: target.kind }, row).then(function () { setTimeout(function () { location.reload(); }, 600); }).catch(function (x) { TN.toast(x.message, 'error'); });
  });
  var bulk = document.getElementById('bulk-pdf');
  if (bulk) bulk.addEventListener('change', function () {
    var files = [].slice.call(bulk.files);
    var jobs = files.map(function (f) {
      var m = f.name.replace(/\.pdf$/i, '').match(/(\d{3,4})(?!.*\d{3,4})/);
      return { file: f, code: m ? m[1] : null };
    });
    var bad = jobs.filter(function (j) { return !j.code; });
    var msg = 'Tải ' + files.length + ' tệp PDF. Mã đề lấy từ tên tệp:\n' + jobs.map(function (j) { return '• ' + j.file.name + ' → ' + (j.code || 'KHÔNG RÕ – bỏ qua'); }).join('\n');
    TN.confirm({ title: 'Tải nhiều tệp PDF đề', message: msg, ok: 'Tải lên' }).then(function (ok) {
      if (!ok) return;
      var i = 0;
      (function next() {
        if (i >= jobs.length) { setTimeout(function () { location.reload(); }, 600); return; }
        var j = jobs[i++]; if (!j.code) return next();
        upload(j.file, { type: 'variant', exam_id: examId, code: j.code, kind: 'exam' }, null).then(next).catch(function (x) { TN.toast(j.file.name + ': ' + x.message, 'error'); next(); });
      })();
    });
  });
  // Nhập đáp án
  var kf = document.getElementById('key-form');
  if (!kf) return;
  var out = document.getElementById('key-output');
  var fileInput = kf.querySelector('[name=file]');
  function send(commit) {
    var f = fileInput.files[0]; if (!f) { TN.toast('Chọn tệp đáp án trước.', 'warning'); return; }
    var fd = new FormData(kf); fd.set('commit', commit ? '1' : '0');
    var btn = commit ? out.querySelector('[data-commit]') : document.getElementById('key-preview');
    TN.busy(btn, true);
    TN.api(TN.url('exams/import-key', { id: examId }), { form: fd, timeout: 180000 }).then(function (r) {
      TN.busy(btn, false);
      var act = { update: ['Ghi vào mã đề', 'info'], create: ['Tạo mã đề mới', 'success'], skip: ['Bỏ qua (chưa có mã đề)', 'default'] };
      var h = '';
      if (r.committed) h += '<div class="alert alert-success mb-2">' + TN.icon('circle-check') + '<div><div class="alert-title">Đã nhập đáp án</div>' + (r.rescored ? 'Đã chấm lại ' + r.rescored + ' bài làm theo đáp án mới.' : 'Có thể mở từng mã đề để kiểm tra trên phiếu.') + '</div></div>';
      h += '<div class="text-sm text-muted mb-2">Định dạng nhận dạng: <b>' + TN.esc(r.format || '') + '</b></div>';
      if (r.errors.length) h += '<div class="alert alert-danger mb-2">' + TN.icon('circle-x') + '<div><div class="alert-title">Lỗi cần sửa trong tệp (' + r.errors.length + ')</div><ul>' + r.errors.slice(0, 30).map(function (x) { return '<li>' + TN.esc(x) + '</li>'; }).join('') + '</ul></div></div>';
      if (r.warnings.length) h += '<div class="alert alert-warning mb-2">' + TN.icon('triangle-alert') + '<div><div class="alert-title">Lưu ý (' + r.warnings.length + ')</div><ul>' + r.warnings.slice(0, 30).map(function (x) { return '<li>' + TN.esc(x) + '</li>'; }).join('') + '</ul></div></div>';
      h += '<div class="table-wrap"><table class="table compact"><thead><tr><th>Mã đề trong tệp</th><th>Mã đề của đề thi</th><th>Hành động</th><th class="center">Đáp án</th><th class="center">Lời giải</th></tr></thead><tbody>' +
        r.plan.map(function (p) { var a = act[p.action]; return '<tr><td class="mono fw-700">' + TN.esc(p.code) + '</td><td class="mono">' + TN.esc(p.variant || '—') + '</td><td><span class="badge badge-' + a[1] + '">' + a[0] + '</span></td><td class="center">' + p.answered + '/' + r.expected + '</td><td class="center">' + p.explained + '</td></tr>'; }).join('') + '</tbody></table></div>';
      if (!r.committed) h += '<div class="row end mt-2"><button type="button" class="btn btn-primary" data-commit ' + (r.errors.length ? 'disabled title="Sửa lỗi trong tệp trước"' : '') + '>' + TN.icon('upload') + ' Xác nhận nhập đáp án</button></div>';
      out.innerHTML = h;
      var c = out.querySelector('[data-commit]');
      if (c) c.onclick = function () { send(true); };
      if (r.committed) setTimeout(function () { location.reload(); }, 1800);
    }).catch(function (x) { TN.busy(btn, false); out.innerHTML = '<div class="alert alert-danger">' + TN.icon('circle-x') + '<div>' + TN.esc(x.message) + '</div></div>'; });
  }
  document.getElementById('key-preview').onclick = function () { send(false); };
  fileInput.addEventListener('change', function () { if (fileInput.files[0]) send(false); });
});
</script>
JS); ?>
