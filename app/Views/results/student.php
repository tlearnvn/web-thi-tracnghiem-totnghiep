<?php
use App\Core\Scope;
use App\Lib\Attempts;

$done = array_values(array_filter($rows, static fn($r) => $r['status'] !== 'in_progress' && $r['score'] !== null));
$avg = $done ? array_sum(array_map(static fn($r) => (float) $r['score'], $done)) / count($done) : null;
$chart = array_reverse(array_slice(array_values(array_filter($done, static fn($r) => $r['mode'] === 'exam')), 0, 20));
if (count($chart) >= 2) {
    use_charts();
}
?>
<div class="page-head">
  <div>
    <div class="eyebrow"><?= icon('graduation-cap', 'sm') ?> <?= e($u['class_name'] ?? '') ?> · <?= e($u['code'] ?: $u['username']) ?></div>
    <h1><?= e($u['full_name']) ?></h1>
    <div class="sub"><?= count($rows) ?> bài làm · điểm trung bình <?= $avg !== null ? '<b>' . e(fmt_num($avg, 2)) . '</b>' : '–' ?></div>
  </div>
  <div class="actions"><a class="btn" href="<?= e(url('students/view', ['id' => $u['id']])) ?>"><?= icon('user') ?> Hồ sơ học sinh</a></div>
</div>
<?php if (count($chart) >= 2): ?>
  <div class="card mb-3">
    <div class="card-head"><h3><?= icon('chart-line') ?> Tiến trình điểm các bài thi</h3></div>
    <div class="card-body"><div class="chart-box sm"><canvas id="trend"></canvas></div></div>
  </div>
  <?php \App\Core\View::push('scripts', '<script>TN.ready(function () { TNChart.line("trend", ' . js_json(array_map(static fn($r) => fmt_dt($r['submitted_at'], 'd/m') . ' ' . str_limit($r['session_name'], 22), $chart)) . ', [{ label: "Điểm", data: ' . js_json(array_map(static fn($r) => round((float) $r['score'], 2), $chart)) . ' }], { max: 10 }); });</script>'); ?>
<?php endif; ?>
<div class="card">
  <?php if (!$rows): ?>
    <div class="empty"><div class="empty-icon"><?= icon('history') ?></div><h3>Học sinh chưa làm bài nào</h3></div>
  <?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Ca thi</th><th>Ngày</th><th>Trạng thái</th><th class="text-right">Điểm</th><th class="col-actions"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
        $acc = Scope::sessionAccess(['id' => $r['session_id'], 'created_by' => $r['s_owner']]);
        $mx = (float) (json_dec($r['score_detail'], [])['max'] ?? 10); ?>
      <tr>
        <td><div class="person"><span class="subject-dot" style="background:<?= e($r['subject_color'] ?: '#2563eb') ?>"></span><div><div class="fw-600"><?= e($r['session_name']) ?></div><div class="sub"><?= e($r['subject_name'] ?? '') ?> · <?= e(str_limit($r['exam_title'], 50)) ?><?= $r['mode'] === 'practice' ? ' ' . badge('Luyện tập', 'purple') : '' ?></div></div></div></td>
        <td class="text-sm nowrap"><?= e(fmt_dt($r['started_at'], 'H:i d/m/Y')) ?></td>
        <td><?= Attempts::statusBadge($r) ?></td>
        <td class="text-right"><?= $r['score'] !== null ? '<span class="score-pill ' . score_class($r['score'], $mx ?: 10) . '">' . e(fmt_score($r['score'])) . '</span>' : '–' ?></td>
        <td class="col-actions"><?php if ($acc['results']): ?><a class="btn btn-sm btn-ghost" href="<?= e(url('results/attempt', ['id' => $r['id']])) ?>"><?= icon('chevron-right') ?></a><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
