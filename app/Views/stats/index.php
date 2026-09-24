<?php
use App\Lib\Sessions;

use_charts();
$withData = array_values(array_filter($rows, static fn($r) => (int) $r['n'] > 0));
$trend = array_reverse(array_slice(array_values(array_filter($withData, static fn($r) => $r['mode'] === 'exam')), 0, 12));
?>
<div class="page-head">
  <div><h1>Thống kê & phân tích</h1><div class="sub">Phổ điểm, xếp loại, so sánh lớp và phân tích chất lượng từng câu hỏi (độ khó, độ phân biệt, phương án nhiễu).</div></div>
</div>
<?php if (!$withData): ?>
  <div class="card"><div class="empty"><div class="empty-icon"><?= icon('chart-column') ?></div><h3>Chưa có dữ liệu thống kê</h3><p>Số liệu sẽ xuất hiện khi học sinh nộp bài trong các ca thi.</p></div></div>
<?php else: ?>
<div class="grid grid-2 mb-3">
  <div class="card">
    <div class="card-head"><h3><?= icon('chart-column') ?> Điểm trung bình các ca thi gần đây</h3><span class="hint">quy về thang 10</span></div>
    <div class="card-body"><div class="chart-box"><canvas id="c-trend"></canvas></div></div>
  </div>
  <div class="card">
    <div class="card-head"><h3><?= icon('book-open') ?> Điểm trung bình theo môn</h3><span class="hint">các ca thi chính thức</span></div>
    <div class="card-body"><div class="chart-box"><canvas id="c-subject"></canvas></div></div>
  </div>
</div>
<?php
\App\Core\View::push('scripts', '<script>TN.ready(function () {
  TNChart.bar("c-trend", ' . js_json(array_map(static fn($r) => mb_strimwidth($r['name'], 0, 26, '…'), $trend)) . ', [{ label: "Điểm TB", data: ' . js_json(array_map(static fn($r) => round((float) $r['avg_score'] * 10 / $r['_max'], 2), $trend)) . ' }, { label: "Cao nhất", color: "#16a34a", data: ' . js_json(array_map(static fn($r) => round((float) $r['max_score'] * 10 / $r['_max'], 2), $trend)) . ' }], { max: 10 });
  TNChart.bar("c-subject", ' . js_json(array_keys($bySubject)) . ', [{ label: "Điểm TB", data: ' . js_json(array_map(static fn($v) => round($v['sum'] / max(1, $v['n']), 2), array_values($bySubject))) . ', backgroundColor: ' . js_json(array_map(static fn($v) => $v['color'], array_values($bySubject))) . ' }], { max: 10, horizontal: true });
});</script>');
endif; ?>
<div class="card">
  <div class="card-head"><h3><?= icon('list') ?> Chọn ca thi để phân tích</h3></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Ca thi</th><th>Ngày</th><th class="text-right">Số bài</th><th class="text-right">TB</th><th class="text-right">Cao nhất</th><th class="text-right">Thấp nhất</th><th class="col-actions"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><div class="person"><span class="subject-dot" style="background:<?= e($r['subject_color'] ?: '#2563eb') ?>"></span><div><a class="row-link" href="<?= e(url('stats/session', ['id' => $r['id']])) ?>"><?= e($r['name']) ?></a><div class="sub"><?= e($r['subject_name'] ?? '') ?> · <?= e(str_limit($r['exam_title'], 50)) ?> <?= Sessions::stateBadge($r) ?><?= $r['mode'] === 'practice' ? ' ' . badge('Luyện tập', 'purple') : '' ?></div></div></div></td>
        <td class="text-sm nowrap"><?= e(fmt_dt($r['start_at'] ?: $r['created_at'], 'd/m/Y')) ?></td>
        <td class="text-right num"><?= (int) $r['n'] ?></td>
        <td class="text-right"><?= $r['avg_score'] !== null ? '<span class="score-pill ' . score_class($r['avg_score'], $r['_max']) . '">' . e(fmt_score($r['avg_score'])) . '</span>' : '–' ?></td>
        <td class="text-right num"><?= $r['max_score'] !== null ? e(fmt_score($r['max_score'])) : '–' ?></td>
        <td class="text-right num"><?= $r['min_score'] !== null ? e(fmt_score($r['min_score'])) : '–' ?></td>
        <td class="col-actions"><?php if ((int) $r['n']): ?><a class="btn btn-sm btn-soft" href="<?= e(url('stats/session', ['id' => $r['id']])) ?>"><?= icon('chart-column') ?> Phân tích</a><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7"><div class="empty" style="padding:30px"><p>Chưa có ca thi nào.</p></div></td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
