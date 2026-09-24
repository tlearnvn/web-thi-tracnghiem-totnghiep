<?php
use App\Lib\ExamFormat;

use_katex();
\App\Core\View::push('styles', '<link rel="stylesheet" href="' . asset('css/exam.css') . '">');
\App\Core\View::push('scripts', '<script src="' . asset('js/answersheet.js') . '"></script><script src="' . asset('js/split.js') . '"></script>');
$cfg = [
    'variantId' => (int) $v['id'],
    'examId' => (int) $e['id'],
    'structure' => $e['_structure'],
    'answers' => $answers,
    'details' => (object) $details,
    'canManage' => $canManage,
    'hasPdf' => (bool) $v['pdf_file_id'],
    'hasSolution' => (bool) $v['solution_file_id'],
    'pdfUrl' => url('files/pdf', ['variant_id' => $v['id']]),
    'solUrl' => url('files/pdf', ['variant_id' => $v['id'], 'kind' => 'solution']),
    'saveUrl' => url('variants/save-key'),
    'header' => ['title' => 'ĐÁP ÁN – MÃ ĐỀ ' . $v['code'], 'exam' => $e['title'], 'subject' => $subject, 'variant' => $v['code'], 'date' => date('d/m/Y')],
];
?>
<div class="xr-body key-layout" id="key-body">
  <div class="xr-left">
    <div id="pdf" style="height:100%"></div>
  </div>
  <div class="xr-split" tabindex="0" role="separator" aria-orientation="vertical" aria-label="Kéo để đổi độ rộng 2 cột">
    <div class="xr-split-btns"><button type="button" data-collapse="left" title="Thu gọn đề"><?= icon('chevrons-left') ?></button><button type="button" data-collapse="right" title="Thu gọn phiếu"><?= icon('chevrons-right') ?></button></div>
  </div>
  <div class="key-right">
    <div class="key-tools">
      <a class="btn btn-sm btn-ghost" href="<?= e(url('exams/view', ['id' => $e['id']])) ?>"><?= icon('arrow-left') ?> Đề thi</a>
      <select class="select select-sm" style="width:auto" onchange="location.href=this.value">
        <?php foreach ($others as $o): ?><option value="<?= e(url('variants/key', ['id' => $o['id']])) ?>"<?= selected($o['id'], $v['id']) ?>>Mã đề <?= e($o['code']) ?><?= $o['pdf_file_id'] ? '' : ' (chưa có PDF)' ?></option><?php endforeach; ?>
      </select>
      <?php if ($v['solution_file_id']): ?><div class="seg"><button type="button" class="active" data-doc="exam">Đề thi</button><button type="button" data-doc="solution">Lời giải</button></div><?php endif; ?>
      <span class="grow"></span>
      <?php if ($canManage): ?>
        <span class="save-chip" id="save-state"><?= icon('circle-check') ?> Đã lưu</span>
        <button type="button" class="btn btn-sm" id="quick-btn"><?= icon('keyboard') ?> Nhập nhanh</button>
        <button type="button" class="btn btn-sm btn-primary" id="save-btn"><?= icon('save') ?> Lưu đáp án</button>
      <?php else: ?>
        <span class="badge badge-info"><?= icon('eye') ?> Chế độ xem (đề được chia sẻ)</span>
      <?php endif; ?>
    </div>
    <div class="xr-sheet-wrap">
      <?php if ($canManage): ?><div class="alert alert-info mb-3"><?= icon('lightbulb') ?><div>Bấm ô tròn để chọn đáp án đúng. Biểu tượng <?= icon('square-pen', 'sm') ?> ở mỗi câu để nhập <b>lời giải, điểm riêng, mức độ, chủ đề</b> hoặc <b>hủy câu</b>. Phím tắt: <kbd>Ctrl</kbd>+<kbd>S</kbd> để lưu.</div></div><?php endif; ?>
      <div id="sheet"></div>
    </div>
  </div>
</div>
<script>window.KEY_CFG = <?= js_json($cfg) ?>;</script>
<?php \App\Core\View::push('scripts', '<script type="module">import { PdfViewer } from ' . js_json(asset('js/pdfviewer.js')) . ';
const C = window.KEY_CFG;
const viewer = new PdfViewer(document.getElementById("pdf"), { protect: false });
const load = (doc) => { if (doc === "solution") viewer.open({ url: C.solUrl }); else if (C.hasPdf) viewer.open({ url: C.pdfUrl }); else viewer.empty("Mã đề này chưa có tệp PDF. Tải PDF ở trang đề thi."); };
load("exam");
document.querySelectorAll("[data-doc]").forEach((b) => b.addEventListener("click", () => { document.querySelectorAll("[data-doc]").forEach((x) => x.classList.toggle("active", x === b)); load(b.dataset.doc); }));
</script>'); ?>
<?php \App\Core\View::push('scripts', <<<'JS'
<script>
TN.ready(function () {
  var C = window.KEY_CFG, details = C.details || {}, dirty = false;
  TNSplit('#key-body', { key: 'tn-split-key', def: 52 });
  var chip = document.getElementById('save-state');
  function setDirty(v) {
    dirty = v;
    if (!chip) return;
    chip.className = 'save-chip' + (v ? ' pending' : '');
    chip.innerHTML = TN.icon(v ? 'pencil' : 'circle-check') + (v ? ' Chưa lưu' : ' Đã lưu');
  }
  var sheet = new AnswerSheet('#sheet', {
    structure: C.structure, mode: 'key', answers: C.answers, keys: details, header: C.header, readOnly: !C.canManage,
    onChange: function () { setDirty(true); },
    onDetail: openDetail
  });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  var partNo = { p1: 1, p2: 2, p3: 3, e: 4 };
  function collect() {
    var a = sheet.getAnswers(), out = [];
    sheet.ids().forEach(function (q) {
      var p = q.split('.'), det = details[q] || {};
      out.push({ part: partNo[p[0]], num: +p[1], answer: p[0] === 'e' ? '' : (a[p[0]][p[1]] || ''), points: det.points, level: det.level, topic: det.topic, origin: det.origin, explanation: det.explanation, is_void: det.is_void ? 1 : 0 });
    });
    return out;
  }
  function save() {
    if (!C.canManage) return;
    var btn = document.getElementById('save-btn');
    TN.busy(btn, true);
    TN.api(C.saveUrl, { data: { variant_id: C.variantId, keys: collect() } }).then(function (r) {
      TN.busy(btn, false); setDirty(false); TN.toast(r.message, 'success');
    }).catch(function (e) { TN.busy(btn, false); TN.toast(e.message, 'error', 'Chưa lưu được'); });
  }
  var sb = document.getElementById('save-btn');
  if (sb) sb.onclick = save;
  document.addEventListener('keydown', function (e) { if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); save(); } });

  function openDetail(qid) {
    var det = details[qid] || {}, part = qid.split('.')[0];
    var ans = part === 'e' ? '' : (sheet.value(qid) || '');
    var levels = ['', 'Biết', 'Hiểu', 'Vận dụng', 'Vận dụng cao'];
    var body = document.createElement('div');
    body.className = 'stack';
    body.innerHTML =
      (part !== 'e' ? '<div class="field"><label>Đáp án</label><input class="input mono" data-f="answer" value="' + TN.esc(ans) + '"><div class="help">' +
        (part === 'p1' ? 'A, B, C, D – nhiều đáp án đúng: A|C' : part === 'p2' ? '4 ký tự cho a, b, c, d: DSDD (Đ = đúng, S = sai, * = hủy ý)' : 'Số với dấu phẩy thập phân, nhiều đáp án cách nhau |') + '</div></div>' : '') +
      '<div class="form-grid"><div class="field"><label>Điểm riêng của câu</label><input class="input" data-f="points" value="' + (det.points != null ? String(det.points).replace('.', ',') : '') + '" placeholder="Theo cách tính chung"></div>' +
      '<div class="field"><label>Mức độ</label><select class="select" data-f="level">' + levels.map(function (l) { return '<option' + (l === (det.level || '') ? ' selected' : '') + '>' + l + '</option>'; }).join('') + '</select></div>' +
      '<div class="field"><label>Chủ đề / nội dung</label><input class="input" data-f="topic" value="' + TN.esc(det.topic || '') + '"></div>' +
      '<div class="field"><label>Câu gốc</label><input class="input" data-f="origin" value="' + TN.esc(det.origin || '') + '" placeholder="Số câu trong đề gốc"></div></div>' +
      '<label class="check"><input type="checkbox" data-f="is_void"' + (det.is_void ? ' checked' : '') + '><span><b>Hủy câu</b> – cho điểm tối đa với mọi thí sinh (câu hỏi lỗi)</span></label>' +
      '<div class="field"><label>Lời giải chi tiết</label><textarea class="textarea" data-f="explanation" rows="9" placeholder="Hỗ trợ **đậm**, *nghiêng*, - danh sách, công thức $x^2$ …">' + TN.esc(det.explanation || '') + '</textarea></div>' +
      '<div class="field"><label>Xem trước</label><div class="md-body card card-body" data-preview style="min-height:60px"></div></div>' +
      (C.canManage ? '<div class="row end"><button class="btn btn-primary" data-apply>' + TN.icon('check') + ' Áp dụng</button></div>' : '');
    var dr = TN.drawer({ title: sheet.label(qid), body: body });
    var ta = body.querySelector('[data-f=explanation]'), pv = body.querySelector('[data-preview]');
    var render = TN.debounce(function () { pv.innerHTML = AnswerSheet.md(ta.value); AnswerSheet.math(pv); }, 250);
    ta.addEventListener('input', render); render();
    if (!C.canManage) body.querySelectorAll('[data-f]').forEach(function (i) { i.disabled = true; });
    var ap = body.querySelector('[data-apply]');
    if (ap) ap.onclick = function () {
      var g = function (f) { var el = body.querySelector('[data-f=' + f + ']'); return el ? (el.type === 'checkbox' ? el.checked : el.value.trim()) : null; };
      var pts = g('points');
      details[qid] = { points: pts === '' ? null : parseFloat(pts.replace(',', '.')), level: g('level') || null, topic: g('topic') || null, origin: g('origin') || null, explanation: g('explanation') || null, is_void: g('is_void') ? 1 : 0 };
      if (part !== 'e') {
        var a = g('answer').toUpperCase();
        if (part === 'p2') a = a.replace(/Đ/g, 'D').replace(/[^DS*_]/g, '');
        if (part === 'p3') a = g('answer').replace(/\./g, ',');
        sheet.set(qid, a);
      }
      sheet.o.keys = details;
      sheet.refresh(qid);
      setDirty(true);
      dr.close();
    };
  }

  var qb = document.getElementById('quick-btn');
  if (qb) qb.onclick = function () {
    var s = C.structure, body = document.createElement('div');
    body.className = 'stack';
    body.innerHTML = (s.p1 ? '<div class="field"><label>Phần I (' + s.p1 + ' câu) – gõ liền các chữ cái</label><input class="input mono" data-q="p1" placeholder="VD: ABCDABCDABCD"></div>' : '') +
      (s.p2 ? '<div class="field"><label>Phần II (' + s.p2 + ' câu) – mỗi câu 4 ký tự Đ/S, cách nhau dấu cách</label><input class="input mono" data-q="p2" placeholder="VD: ĐSĐĐ SSĐĐ ĐĐĐS SĐSĐ"></div>' : '') +
      (s.p3 ? '<div class="field"><label>Phần III (' + s.p3 + ' câu) – cách nhau dấu chấm phẩy</label><input class="input mono" data-q="p3" placeholder="VD: -1,5; 12; 0,25; 3; 2,5; 100"></div>' : '') +
      '<div class="row end"><button class="btn btn-primary" data-go>' + TN.icon('check') + ' Điền vào phiếu</button></div>';
    var m = TN.modal({ title: 'Nhập nhanh đáp án', icon: 'keyboard', body: body, foot: false });
    body.querySelector('[data-go]').onclick = function () {
      var v1 = body.querySelector('[data-q=p1]'), v2 = body.querySelector('[data-q=p2]'), v3 = body.querySelector('[data-q=p3]');
      if (v1 && v1.value.trim()) v1.value.toUpperCase().replace(/[^ABCD]/g, '').split('').slice(0, s.p1).forEach(function (c, i) { sheet.set('p1.' + (i + 1), c); });
      if (v2 && v2.value.trim()) v2.value.toUpperCase().replace(/Đ/g, 'D').split(/[\s,;]+/).filter(Boolean).slice(0, s.p2).forEach(function (c, i) { c = c.replace(/[^DS*]/g, ''); if (c.length === 4) sheet.set('p2.' + (i + 1), c); });
      if (v3 && v3.value.trim()) v3.value.split(/\s*;\s*/).filter(function (x) { return x.trim() !== ''; }).slice(0, s.p3).forEach(function (c, i) { sheet.set('p3.' + (i + 1), c.trim().replace(/\./g, ',')); });
      m.close();
      TN.toast('Đã điền vào phiếu – kiểm tra rồi bấm Lưu đáp án.', 'success');
    };
  };
});
</script>
JS); ?>
