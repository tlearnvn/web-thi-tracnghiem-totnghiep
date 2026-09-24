<?php
use App\Lib\Scoring;
use App\Lib\Sessions;
use App\Lib\Stats;

use_charts();
$n = (int) $desc['n'];
$scale = $max ?: 10;
$pass = 0;
$good = 0;
foreach ($hist as $b) {
    if ($b['x'] * 10 / $scale >= 5) {
        $pass += $b['n'];
    }
    if ($b['x'] * 10 / $scale >= 8) {
        $good += $b['n'];
    }
}
$alphas = array_values(array_filter(array_map(static fn($v) => $v['alpha'], $items), static fn($x) => $x !== null));
$alpha = $alphas ? array_sum($alphas) / count($alphas) : null;
$partNames = ['p1' => 'Phần I', 'p2' => 'Phần II', 'p3' => 'Phần III', 'essay' => 'Tự luận'];
$q = static fn(array $extra) => e(query_with($extra));
$pColor = static fn(float $p): string => $p >= 0.8 ? '#16a34a' : ($p >= 0.6 ? '#0891b2' : ($p >= 0.4 ? '#2563eb' : ($p >= 0.2 ? '#f59e0b' : '#dc2626')));
?>
<div class="page-head">
  <div>
    <div class="eyebrow"><?= icon('chart-column', 'sm') ?> Thống kê & phân tích</div>
    <h1><?= e($s['name']) ?></h1>
    <div class="sub row gap-sm"><?= Sessions::stateBadge($s) ?><span><?= e($exam['title']) ?> · thang <?= e(fmt_num($max)) ?> điểm</span></div>
  </div>
  <div class="actions">
    <?php if (count($classes) > 1): ?>
      <select class="select" style="width:auto" onchange="location.href=this.value">
        <option value="<?= $q(['class' => null]) ?>">Tất cả lớp</option>
        <?php foreach ($classes as $c): ?><option value="<?= $q(['class' => $c['id']]) ?>"<?= selected($classId, $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
    <?php endif; ?>
    <a class="btn" href="<?= e(url('results/session', ['id' => $s['id'], 'class' => $classId])) ?>"><?= icon('table') ?> Bảng điểm</a>
    <?php if (can('results.export')): ?><a class="btn btn-primary" href="<?= e(url('results/export', ['id' => $s['id'], 'class' => $classId])) ?>"><?= icon('file-spreadsheet') ?> Xuất Excel</a><?php endif; ?>
  </div>
</div>

<?php if (!$n): ?>
  <div class="card"><div class="empty"><div class="empty-icon"><?= icon('chart-column') ?></div><h3>Chưa có bài nộp</h3><p>Thống kê sẽ có khi học sinh nộp bài.</p></div></div>
<?php else: ?>

<div class="grid grid-4 mb-3">
  <div class="stat"><div class="stat-icon"><?= icon('users') ?></div><div><div class="stat-value"><?= $n ?><span class="text-muted" style="font-size:15px">/<?= (int) $targets ?></span></div><div class="stat-label">Bài đã nộp / dự thi</div></div></div>
  <div class="stat"><div class="stat-icon info"><?= icon('sigma') ?></div><div><div class="stat-value"><?= e(fmt_num($desc['mean'], 2)) ?></div><div class="stat-label">Điểm TB · trung vị <?= e(fmt_num($desc['median'], 2)) ?> · ĐLC <?= e(fmt_num($desc['sd'], 2)) ?></div></div></div>
  <div class="stat"><div class="stat-icon success"><?= icon('trending-up') ?></div><div><div class="stat-value"><?= e(fmt_percent($pass / $n, 0)) ?></div><div class="stat-label">Đạt từ 5 điểm · <?= e(fmt_percent($good / $n, 0)) ?> từ 8 điểm</div></div></div>
  <div class="stat"><div class="stat-icon purple"><?= icon('gauge') ?></div><div><div class="stat-value"><?= $alpha !== null ? e(fmt_num($alpha, 2)) : '–' ?></div><div class="stat-label" title="Hệ số Cronbach α – độ tin cậy (nhất quán nội tại) của đề">Độ tin cậy của đề: <?= e(Stats::alphaLabel($alpha)) ?></div></div></div>
</div>

<div class="grid grid-2-1 mb-3">
  <div class="card">
    <div class="card-head"><h3><?= icon('chart-column') ?> Phổ điểm</h3><span class="hint">cao nhất <?= e(fmt_num($desc['max'], 2)) ?> · thấp nhất <?= e(fmt_num($desc['min'], 2)) ?> · điểm nhiều nhất <?= e(fmt_num($desc['mode'], 2)) ?></span></div>
    <div class="card-body"><div class="chart-box"><canvas id="c-hist"></canvas></div></div>
  </div>
  <div class="card">
    <div class="card-head"><h3><?= icon('chart-pie') ?> Xếp loại</h3></div>
    <div class="card-body"><div class="chart-box"><canvas id="c-cls"></canvas></div></div>
  </div>
</div>

<div class="grid grid-2 mb-3">
  <div class="card">
    <div class="card-head"><h3><?= icon('school') ?> So sánh theo lớp</h3></div>
    <div class="table-wrap"><table class="table table-sm">
      <thead><tr><th>Lớp</th><th class="text-right">Số bài</th><th class="text-right">TB</th><th class="text-right">ĐLC</th><th class="text-right">Cao</th><th class="text-right">Thấp</th><th class="text-right">≥ 5</th><th class="text-right">≥ 8</th></tr></thead>
      <tbody><?php foreach ($byClass as $c): ?>
        <tr><td class="fw-600"><?= e($c['name']) ?></td><td class="text-right num"><?= (int) $c['n'] ?></td><td class="text-right"><span class="score-pill <?= score_class($c['mean'], $scale) ?>"><?= e(fmt_num($c['mean'], 2)) ?></span></td><td class="text-right num"><?= e(fmt_num($c['sd'], 2)) ?></td><td class="text-right num"><?= e(fmt_num($c['max'], 2)) ?></td><td class="text-right num"><?= e(fmt_num($c['min'], 2)) ?></td><td class="text-right num"><?= e(fmt_percent($c['pass'], 0)) ?></td><td class="text-right num"><?= e(fmt_percent($c['good'], 0)) ?></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <?php if (count($byClass) > 1): ?><div class="card-body"><div class="chart-box sm"><canvas id="c-class"></canvas></div></div><?php endif; ?>
  </div>
  <div class="card">
    <div class="card-head"><h3><?= icon('layers') ?> Tỉ lệ điểm đạt được theo phần</h3></div>
    <div class="card-body part-bars">
      <?php foreach ($parts as $k => $p): $w = round($p['ratio'] * 100, 1); ?>
        <div class="part-bar">
          <div class="pb-top"><span><?= e($partNames[$k] ?? $k) ?> <span class="text-muted text-sm">· <?= e(\App\Lib\ExamFormat::partLong(['p1' => 1, 'p2' => 2, 'p3' => 3, 'essay' => 4][$k])) ?></span></span><b><?= e(fmt_num($p['avg'], 2)) ?> / <?= e(fmt_num($p['max'], 2)) ?> (<?= e(fmt_num($w, 0)) ?>%)</b></div>
          <div class="progress"><span style="width:<?= $w ?>%;background:<?= e($pColor($p['ratio'])) ?>"></span></div>
        </div>
      <?php endforeach; ?>
      <?php if ($levels): ?>
        <div class="divider-text">Theo mức độ nhận thức</div>
        <?php foreach ($levels as $l): ?>
          <div class="part-bar"><div class="pb-top"><span><?= e($l['tag']) ?> <span class="text-muted text-sm">· <?= (int) $l['n'] ?> câu</span></span><b><?= e(fmt_num($l['p'] * 100, 0)) ?>% làm đúng</b></div><div class="progress progress-sm"><span style="width:<?= round($l['p'] * 100, 1) ?>%;background:<?= e($pColor($l['p'])) ?>"></span></div></div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card mb-3" data-tab-scope>
  <div class="card-head">
    <h3><?= icon('scan-line') ?> Phân tích câu hỏi</h3>
    <div class="row gap-sm">
      <?php if (count($items) > 1): ?><div class="tabs" data-tabs><?php foreach ($items as $i => $v): ?><a class="tab<?= $i === 0 ? ' active' : '' ?>" data-tab="v<?= (int) $v['variant']['id'] ?>" href="#">Mã <?= e($v['variant']['code']) ?> <span class="text-muted">(<?= (int) $v['n'] ?>)</span></a><?php endforeach; ?></div><?php endif; ?>
      <label class="check text-sm"><input type="checkbox" id="only-issue"> Chỉ câu cần xem lại</label>
    </div>
  </div>
  <div class="card-body text-sm text-muted" style="padding-bottom:0">
    <b>p</b> – độ khó (tỉ lệ làm đúng; Phần II tính theo tỉ lệ ý đúng) · <b>D</b> – độ phân biệt: chênh lệch tỉ lệ đúng giữa 27% học sinh điểm cao nhất và 27% thấp nhất (tốt khi D ≥ 0,3; âm là dấu hiệu sai đáp án) · <b>r</b> – tương quan điểm câu với tổng điểm.
  </div>
  <?php foreach ($items as $i => $v): ?>
    <div class="tab-panel<?= $i === 0 ? ' active' : '' ?>" data-panel="v<?= (int) $v['variant']['id'] ?>">
      <div class="card-body text-sm" style="padding-bottom:0">Mã đề <b><?= e($v['variant']['code']) ?></b>: <?= (int) $v['n'] ?> bài · Cronbach α = <b><?= $v['alpha'] !== null ? e(fmt_num($v['alpha'], 2)) : '–' ?></b> (<?= e(Stats::alphaLabel($v['alpha'])) ?>)</div>
      <div class="table-wrap"><table class="table table-sm table-items">
        <thead><tr><th>Câu</th><th>Đáp án</th><th style="min-width:150px">Độ khó p</th><th class="text-right">D</th><th class="text-right">r</th><th style="min-width:230px">Phân bố lựa chọn</th><th>Nhận xét</th></tr></thead>
        <tbody>
        <?php foreach ($v['items'] as $it):
            $issue = (bool) array_filter($it['flags'], static fn($f) => in_array($f[1], ['danger', 'warning'], true));
            $dl = Stats::difficultyLabel($it['p']); ?>
          <tr<?= $issue ? ' data-issue' : '' ?>>
            <td class="nowrap"><b><?= e(str_replace(['Phần ', ' – Câu '], ['', '.'], $it['label'])) ?></b><?php if ($it['level'] || $it['topic']): ?><div class="text-xs text-muted"><?= e(trim($it['level'] . ' · ' . $it['topic'], ' ·')) ?></div><?php endif; ?></td>
            <td class="mono"><?= e(str_replace('|', ' / ', $it['part'] === 'p2' ? str_replace('D', 'Đ', (string) $it['key']) : (string) $it['key'])) ?></td>
            <td>
              <div class="row nowrap" style="gap:8px"><div class="progress progress-sm grow"><span style="width:<?= round($it['p'] * 100, 1) ?>%;background:<?= e($pColor($it['p'])) ?>"></span></div><b class="num" style="min-width:34px;text-align:right"><?= e(fmt_num($it['p'], 2)) ?></b></div>
              <div class="text-xs text-muted"><?= e($dl[0]) ?></div>
            </td>
            <td class="text-right num <?= $it['d'] !== null && $it['d'] < 0 ? 'text-danger fw-700' : ($it['d'] !== null && $it['d'] < 0.2 ? 'text-warning' : '') ?>"><?= $it['d'] !== null ? e(fmt_num($it['d'], 2)) : '–' ?></td>
            <td class="text-right num"><?= $it['rpb'] !== null ? e(fmt_num($it['rpb'], 2)) : '–' ?></td>
            <td>
              <?php if ($it['part'] === 'p1'):
                  $tot = max(1, array_sum($it['options'])); ?>
                <div class="dist">
                  <?php foreach (['A', 'B', 'C', 'D', '–'] as $op):
                      $cnt = (int) ($it['options'][$op] ?? 0);
                      if (!$cnt) {
                          continue;
                      }
                      $isKey = $op !== '–' && strpos((string) $it['key'], $op) !== false; ?>
                    <span class="<?= $isKey ? 'k' : ($op === '–' ? 'b' : '') ?>" style="flex:<?= $cnt ?>" title="<?= e($op === '–' ? 'Bỏ trống' : 'Phương án ' . $op) ?>: <?= $cnt ?> (<?= round($cnt * 100 / $tot) ?>%)"><?= e($op) ?> <?= round($cnt * 100 / $tot) ?>%</span>
                  <?php endforeach; ?>
                </div>
              <?php elseif ($it['part'] === 'p2'): ?>
                <div class="subdist"><?php foreach (($it['subs'] ?? []) as $j => $r2): ?><span title="Ý <?= ['a', 'b', 'c', 'd'][$j] ?>: <?= round($r2 * 100) ?>% đúng"><i style="height:<?= max(4, round($r2 * 100)) ?>%;background:<?= e($pColor($r2)) ?>"></i><b><?= ['a', 'b', 'c', 'd'][$j] ?></b></span><?php endforeach; ?><em class="text-xs text-muted">đủ 4 ý: <?= round(($it['full'] ?? 0) * 100) ?>%</em></div>
              <?php else: ?>
                <div class="text-xs"><?php $parts3 = []; foreach ($it['options'] as $val => $cnt) { $parts3[] = '<span class="' . (str_replace(',', '.', (string) $val) === str_replace(',', '.', (string) $it['key']) ? 'text-success fw-700' : '') . '">' . e((string) $val) . '</span> <span class="text-muted">×' . (int) $cnt . '</span>'; } echo implode(' · ', $parts3); ?></div>
              <?php endif; ?>
            </td>
            <td><?php foreach ($it['flags'] as $f): ?><?= badge($f[0], $f[1]) ?> <?php endforeach; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="grid grid-2 mb-3">
  <div class="card">
    <div class="card-head"><h3><?= icon('trophy') ?> Học sinh điểm cao nhất</h3></div>
    <div class="card-body"><ol class="rank-list"><?php foreach ($top as $a): ?><li><a href="<?= e(url('results/attempt', ['id' => $a['id']])) ?>"><?= e($a['full_name']) ?></a><span class="text-muted text-sm"><?= e((string) $a['class_name']) ?></span><span class="score-pill <?= score_class($a['score'], $scale) ?>"><?= e(fmt_score($a['score'])) ?></span></li><?php endforeach; ?></ol></div>
  </div>
  <div class="card">
    <div class="card-head"><h3><?= icon('heart-handshake') ?> Học sinh cần hỗ trợ thêm</h3><span class="hint">dưới 5 điểm (thang 10)</span></div>
    <div class="card-body"><?php if (!$weak): ?><div class="text-muted text-sm">Không có học sinh dưới 5 điểm. 🎉</div><?php else: ?><ol class="rank-list"><?php foreach ($weak as $a): ?><li><a href="<?= e(url('results/attempt', ['id' => $a['id']])) ?>"><?= e($a['full_name']) ?></a><span class="text-muted text-sm"><?= e((string) $a['class_name']) ?></span><span class="score-pill <?= score_class($a['score'], $scale) ?>"><?= e(fmt_score($a['score'])) ?></span></li><?php endforeach; ?></ol><?php endif; ?></div>
  </div>
</div>
<?php if ($topics): ?>
  <div class="card mb-3">
    <div class="card-head"><h3><?= icon('bookmark') ?> Theo chủ đề / nội dung</h3><span class="hint">tỉ lệ làm đúng trung bình</span></div>
    <div class="card-body part-bars cols-2">
      <?php foreach ($topics as $t): ?><div class="part-bar"><div class="pb-top"><span><?= e($t['tag']) ?> <span class="text-muted text-sm">· <?= (int) $t['n'] ?> câu</span></span><b><?= e(fmt_num($t['p'] * 100, 0)) ?>%</b></div><div class="progress progress-sm"><span style="width:<?= round($t['p'] * 100, 1) ?>%;background:<?= e($pColor($t['p'])) ?>"></span></div></div><?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>
<?php
$clsColors = ['#16a34a', '#2563eb', '#f59e0b', '#f97316', '#dc2626'];
\App\Core\View::push('scripts', '<script>TN.ready(function () {
  TNChart.histogram("c-hist", ' . js_json(array_map(static fn($b) => fmt_num($b['x'], 2), $hist)) . ', ' . js_json(array_column($hist, 'n')) . ', { max: ' . (float) $scale . ', xTitle: "Mức điểm" });
  TNChart.doughnut("c-cls", ' . js_json(array_keys($cls)) . ', ' . js_json(array_values($cls)) . ', ' . js_json($clsColors) . ');
  if (document.getElementById("c-class")) TNChart.bar("c-class", ' . js_json(array_column($byClass, 'name')) . ', [{ label: "Điểm TB", data: ' . js_json(array_map(static fn($c) => round((float) $c['mean'], 2), $byClass)) . ' }, { label: "Tỉ lệ ≥ 5 (×10)", color: "#16a34a", data: ' . js_json(array_map(static fn($c) => round($c['pass'] * 10, 2), $byClass)) . ' }], { max: ' . (float) $scale . ' });
  var cb = document.getElementById("only-issue");
  if (cb) cb.addEventListener("change", function () { document.querySelectorAll(".table-items tbody tr").forEach(function (tr) { tr.hidden = cb.checked && !tr.hasAttribute("data-issue"); }); });
});</script>');
endif; ?>
