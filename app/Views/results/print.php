<?php
/** In phiếu trả lời (bản lưu) – mỗi học sinh một trang A4. */
\App\Core\View::push('scripts', '<script src="' . asset('js/answersheet.js') . '"></script>');
?>
<link rel="stylesheet" href="<?= asset('css/exam.css') ?>">
<style>
body { background: var(--bg); }
.print-bar { position: sticky; top: 0; z-index: 10; display: flex; gap: 10px; align-items: center; padding: 12px 20px; background: var(--surface); border-bottom: 1px solid var(--border); }
.print-meta { display: flex; justify-content: space-between; gap: 12px; align-items: center; margin: 0 0 10px; padding: 10px 14px; border: 1.5px solid #d9387a; border-radius: 12px; font-size: 13px; background: #fff; color: #111; }
.print-meta .sc { font-size: 22px; font-weight: 800; }
.print-sheet { background: #fff; margin: 18px auto; box-shadow: var(--shadow); border-radius: 8px; }
.print-sheet .sh-flag { display: none; }
@media print { .print-sheet { box-shadow: none; margin: 0 auto; border-radius: 0; } .print-bar { display: none; } body { background: #fff; } [data-theme="dark"] .sheet { --s-paper: #fff; --s-ink: #151a2d; --s-bg: #fff4f8; } }
</style>
<div class="print-bar no-print">
  <a class="btn btn-ghost" href="<?= e(url('results/session', ['id' => $s['id']])) ?>"><?= icon('arrow-left') ?> Bảng điểm</a>
  <b><?= e($s['name']) ?></b><span class="text-muted">· <?= count($sheets) ?> phiếu<?= $withKey ? ' · có chấm đúng/sai' : '' ?></span>
  <span class="grow"></span>
  <button class="btn btn-primary" type="button" onclick="window.print()"><?= icon('printer') ?> In / lưu PDF</button>
</div>
<?php if (!$sheets): ?>
  <div class="empty"><div class="empty-icon"><?= icon('printer') ?></div><h3>Không có bài làm để in</h3></div>
<?php endif; ?>
<?php foreach ($sheets as $i => $sh): ?>
  <div class="print-sheet<?= $i < count($sheets) - 1 ? ' page-break' : '' ?>">
    <div class="print-meta">
      <div><b><?= e($sh['header']['name']) ?></b> · <?= e($sh['header']['code']) ?> · Lớp <?= e($sh['header']['className']) ?><br><span style="color:#555"><?= e($sh['status']) ?> lúc <?= e($sh['submitted']) ?> · Mã bài #<?= (int) $sh['aid'] ?></span></div>
      <div class="text-right"><span style="color:#555">Điểm</span> <span class="sc"><?= $sh['score'] !== null ? e(fmt_score($sh['score'])) : '–' ?></span><span style="color:#555"> / <?= e(fmt_num($sh['max'])) ?></span></div>
    </div>
    <div data-sheet="<?= $i ?>"></div>
  </div>
<?php endforeach; ?>
<script>window.PRINT_SHEETS = <?= js_json(['structure' => $exam['_structure'], 'sheets' => $sheets, 'withKey' => $withKey]) ?>;</script>
<?php \App\Core\View::push('scripts', <<<'JS'
<script>
TN.ready(function () {
  document.body.classList.add('printing-sheet');
  document.documentElement.setAttribute('data-theme', 'light');
  var P = window.PRINT_SHEETS;
  P.sheets.forEach(function (sh, i) {
    var el = document.querySelector('[data-sheet="' + i + '"]');
    new AnswerSheet(el, { structure: P.structure, mode: P.withKey ? 'review' : 'print', answers: sh.answers, results: sh.results, header: sh.header });
  });
});
</script>
JS); ?>
