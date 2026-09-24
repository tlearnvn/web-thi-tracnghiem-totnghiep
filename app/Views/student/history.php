<?php
use App\Lib\Attempts;

$done = array_values(array_filter($rows, static fn($r) => $r['status'] !== 'in_progress'));
$scored = array_values(array_filter($done, static fn($r) => $r['_show'] && $r['score'] !== null));
$avg = $scored ? array_sum(array_map(static fn($r) => (float) $r['score'], $scored)) / count($scored) : null;
$best = $scored ? max(array_map(static fn($r) => (float) $r['score'], $scored)) : null;
?>
<div class="page-head">
  <div>
    <div class="eyebrow"><?= icon('history', 'sm') ?> Hồ sơ học tập</div>
    <h1>Kết quả & lịch sử làm bài</h1>
    <div class="sub">Tất cả các bài thi và bài luyện tập em đã làm.</div>
  </div>
</div>

<div class="grid grid-3 mb-3">
  <div class="stat rise"><div class="stat-icon"><?= icon('clipboard-check') ?></div><div><div class="stat-value" data-count-to="<?= count($done) ?>">0</div><div class="stat-label">Bài đã nộp</div></div></div>
  <div class="stat rise rise-1"><div class="stat-icon success"><?= icon('chart-line') ?></div><div><div class="stat-value"><?= $avg !== null ? e(fmt_score($avg)) : '–' ?></div><div class="stat-label">Điểm trung bình (bài có điểm)</div></div></div>
  <div class="stat rise rise-2"><div class="stat-icon warning"><?= icon('trophy') ?></div><div><div class="stat-value"><?= $best !== null ? e(fmt_score($best)) : '–' ?></div><div class="stat-label">Điểm cao nhất</div></div></div>
</div>

<div class="card">
  <?php if (!$rows): ?>
    <div class="empty"><div class="empty-icon"><?= icon('history') ?></div><h3>Chưa có bài làm nào</h3><p>Các bài thi, bài luyện tập em làm sẽ được lưu lại tại đây.</p></div>
  <?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Bài thi</th><th>Ngày làm</th><th>Trạng thái</th><th class="text-right">Điểm</th><th class="col-actions"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
        $mx = (float) (json_dec($r['score_detail'], [])['max'] ?? 10); ?>
      <tr>
        <td>
          <div class="person"><span class="subject-dot" style="background:<?= e($r['subject_color'] ?: '#2563eb') ?>"></span>
            <div style="min-width:0"><div class="fw-600"><?= e($r['session_name']) ?></div>
              <div class="sub"><?= e($r['subject_name'] ?? '') ?> · <?= e(str_limit($r['exam_title'], 50)) ?><?= $r['mode'] === 'practice' ? ' ' . badge('Luyện tập', 'purple') : '' ?><?= (int) $r['attempt_no'] > 1 ? ' · lần ' . (int) $r['attempt_no'] : '' ?></div></div></div>
        </td>
        <td class="text-sm nowrap"><?= e(fmt_dt($r['started_at'], 'H:i d/m/Y')) ?></td>
        <td><?= Attempts::statusBadge($r) ?></td>
        <td class="text-right"><?php if ($r['status'] === 'in_progress'): ?><span class="text-muted">–</span><?php elseif ($r['_show'] && $r['score'] !== null): ?><span class="score-pill <?= score_class($r['score'], $mx ?: 10) ?>"><?= e(fmt_score($r['score'])) ?></span><?php else: ?><span class="text-muted text-sm">Chưa công bố</span><?php endif; ?></td>
        <td class="col-actions">
          <div class="table-actions">
            <?php if ($r['status'] === 'in_progress'): ?>
              <a class="btn btn-sm btn-primary" href="<?= e(url('exam/room', ['aid' => $r['id']])) ?>"><?= icon('pencil-line') ?> Làm tiếp</a>
            <?php else: ?>
              <?php if ($r['_review']): ?><a class="btn btn-sm btn-soft" href="<?= e(url('student/review', ['aid' => $r['id']])) ?>"><?= icon('eye') ?> Xem lại</a><?php endif; ?>
              <a class="btn btn-sm btn-ghost" href="<?= e(url('student/result', ['aid' => $r['id']])) ?>"><?= icon('chevron-right') ?></a>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
