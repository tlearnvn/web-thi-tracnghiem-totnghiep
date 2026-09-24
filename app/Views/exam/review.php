<?php
/** Xem lại bài làm (dùng chung cho học sinh & giáo viên). Biến: $cfg, $a, $s, $exam, $variant, $asStudent, $title */
use App\Lib\Attempts;

use_katex();
$h = $cfg['header'];
$score = $cfg['score'];
$max = (float) $cfg['max'];
$items = $cfg['results']['items'];
$nOk = count(array_filter($items, static fn($it) => !empty($it['ok'])));
?>
<!doctype html>
<html lang="vi">
<head>
<?= \App\Core\View::partial('partials/head', ['title' => $title]) ?>
<link rel="stylesheet" href="<?= asset('css/exam.css') ?>">
</head>
<body class="<?= $asStudent ? 'exam-room' : '' ?>">
<div class="xr" id="xr" data-pane="sheet">
  <header class="xr-top">
    <a class="icon-btn" href="<?= e($cfg['back']) ?>" title="Quay lại"><?= icon('arrow-left') ?></a>
    <div class="xr-brand">
      <div class="xr-title">
        <strong>Xem lại: <?= e($h['exam']) ?></strong>
        <small><?= e($h['name']) ?> – <?= e($h['code']) ?> · Mã đề <b><?= e($h['variant']) ?></b> · <?= Attempts::STATUS[$a['status']][0] ?? '' ?> <?= e(fmt_dt($a['submitted_at'], 'H:i d/m/Y')) ?></small>
      </div>
    </div>
    <div class="xr-mid">
      <div class="prog-chip"><div class="prog-ring" style="--p:<?= $max > 0 && $score !== null ? round($score * 100 / $max) : 0 ?>" data-label="<?= e(fmt_num($score ?? 0, 1)) ?>"></div><span><b><?= e(fmt_score($score)) ?></b> / <?= e(fmt_num($max)) ?> điểm</span></div>
      <span class="save-chip"><?= icon('circle-check') ?> Đúng <?= $nOk ?>/<?= count($items) ?> câu</span>
    </div>
    <div class="xr-right-tools">
      <?php if ($cfg['solution']): ?>
        <div class="seg hide-sm" id="doc-switch"><button type="button" class="active" data-doc="exam"><?= icon('file-text', 'sm') ?> Đề thi</button><button type="button" data-doc="solution"><?= icon('lightbulb', 'sm') ?> Lời giải</button></div>
      <?php endif; ?>
      <button type="button" class="icon-btn" data-theme-toggle title="Giao diện sáng / tối"><?= icon('sun') ?></button>
    </div>
  </header>
  <nav class="xr-tabs">
    <button type="button" data-pane="pdf"><?= icon('file-text') ?> Đề thi</button>
    <button type="button" data-pane="sheet" class="active"><?= icon('clipboard-list') ?> Phiếu & lời giải</button>
  </nav>
  <div class="xr-body" id="xr-body">
    <div class="xr-left"><div id="pdf" style="height:100%"></div></div>
    <div class="xr-split" tabindex="0" role="separator" aria-orientation="vertical" aria-label="Kéo để đổi độ rộng 2 cột">
      <div class="xr-split-btns"><button type="button" data-collapse="left" title="Thu gọn đề thi"><?= icon('chevrons-left') ?></button><button type="button" data-collapse="right" title="Thu gọn phiếu"><?= icon('chevrons-right') ?></button></div>
    </div>
    <div class="xr-right">
      <div class="xr-nav">
        <div class="seg seg-sm" id="q-filter">
          <button type="button" class="active" data-f="all">Tất cả</button>
          <button type="button" data-f="bad"><?= icon('x', 'sm') ?> Chưa đúng</button>
          <button type="button" data-f="ok"><?= icon('check', 'sm') ?> Đúng</button>
        </div>
        <div class="qchips" id="qchips"></div>
      </div>
      <div class="xr-sheet-wrap">
        <div class="alert alert-info mb-3"><?= icon('lightbulb') ?><div>Ô <b class="text-success">viền xanh</b> là đáp án đúng; ô tô <b class="text-success">xanh</b> là em chọn đúng, ô tô <b class="text-danger">đỏ</b> là em chọn chưa đúng.<?= $cfg['explanations'] && (array) $cfg['explanations'] ? ' Bấm biểu tượng ' . icon('lightbulb', 'sm') . ' ở mỗi câu để xem lời giải chi tiết.' : '' ?></div></div>
        <div id="sheet"></div>
      </div>
    </div>
  </div>
</div>
<script>window.REVIEW_CFG = <?= js_json($cfg) ?>;</script>
<?= \App\Core\View::partial('partials/scripts') ?>
<script src="<?= asset('js/answersheet.js') ?>"></script>
<script src="<?= asset('js/split.js') ?>"></script>
<script>
TN.ready(function () {
  var C = window.REVIEW_CFG;
  TNSplit('#xr-body', { key: 'tn-split-review', def: 52, min: 22, max: 80 });
  document.querySelectorAll('.xr-tabs [data-pane]').forEach(function (b) {
    b.addEventListener('click', function () {
      document.getElementById('xr').dataset.pane = b.dataset.pane;
      document.querySelectorAll('.xr-tabs [data-pane]').forEach(function (x) { x.classList.toggle('active', x === b); });
    });
  });
  var sheet = new AnswerSheet('#sheet', { structure: C.structure, mode: 'review', answers: C.answers, results: C.results, keys: C.keys, explanations: C.explanations, header: Object.assign({ title: 'PHIẾU TRẢ LỜI – XEM LẠI' }, C.header) });
  AnswerSheet.math(document.getElementById('sheet'));
  var box = document.getElementById('qchips');
  box.innerHTML = sheet.ids().map(function (q) {
    var it = C.results.items[q] || {};
    var cls = it.graded === false ? 'partial' : (it.ok ? 'ok' : 'bad');
    return '<button type="button" class="qchip ' + cls + '" data-q="' + q + '" title="' + TN.esc(sheet.label(q)) + '">' + TN.esc(sheet.shortLabel(q)) + '</button>';
  }).join('');
  box.addEventListener('click', function (e) {
    var b = e.target.closest('[data-q]');
    if (!b) return;
    if (window.innerWidth <= 900) { document.getElementById('xr').dataset.pane = 'sheet'; }
    sheet.focusQuestion(b.dataset.q);
    if ((C.explanations || {})[b.dataset.q] && e.detail > 1) sheet.showExplanation(b.dataset.q);
  });
  document.getElementById('q-filter').addEventListener('click', function (e) {
    var b = e.target.closest('[data-f]');
    if (!b) return;
    this.querySelectorAll('[data-f]').forEach(function (x) { x.classList.toggle('active', x === b); });
    box.querySelectorAll('.qchip').forEach(function (c) {
      c.style.display = b.dataset.f === 'all' || c.classList.contains(b.dataset.f) ? '' : 'none';
    });
  });
});
</script>
<script type="module">
import { PdfViewer } from <?= js_json(asset('js/pdfviewer.js')) ?>;
const C = window.REVIEW_CFG;
const viewer = new PdfViewer(document.getElementById('pdf'), { watermark: C.watermark || '', protect: !!C.protected });
const headers = C.protected ? { 'X-Requested-With': 'tnexam' } : {};
const load = (doc) => {
  const url = doc === 'solution' ? C.solution : C.pdf;
  if (!url) { viewer.empty(doc === 'solution' ? 'Chưa có lời giải dạng PDF.' : 'Bài thi này dùng đề giấy – không có tệp PDF để xem lại.'); return; }
  viewer.open({ url, key: C.protected ? C.pdfKey : null, headers });
};
load('exam');
document.querySelectorAll('#doc-switch [data-doc]').forEach((b) => b.addEventListener('click', () => {
  document.querySelectorAll('#doc-switch [data-doc]').forEach((x) => x.classList.toggle('active', x === b));
  load(b.dataset.doc);
}));
</script>
</body>
</html>
