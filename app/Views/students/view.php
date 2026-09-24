<?php
use App\Lib\Attempts;

use_charts();
$scored = array_values(array_filter(array_reverse($attempts), static fn($a) => $a['score'] !== null && $a['status'] !== 'in_progress'));
$avg = $scored ? array_sum(array_map(static fn($a) => (float) $a['score'], $scored)) / count($scored) : null;
?>
<div class="page-head">
  <div class="row gap-lg">
    <span class="avatar lg" style="background:<?= e(color_for($u['username'])) ?>"><?= e(initials($u['full_name'])) ?></span>
    <div>
      <h1><?= e($u['full_name']) ?></h1>
      <div class="sub"><?= $class ? 'Lớp ' . e($class['name']) . ' · ' : '' ?><?= $u['code'] ? 'Mã ' . e($u['code']) . ' · ' : '' ?>Tài khoản <span class="mono"><?= e($u['username']) ?></span> <?= $u['status'] === 'active' ? badge('Hoạt động', 'success') : badge('Đã khóa', 'danger') ?></div>
    </div>
  </div>
  <div class="actions">
    <?php if (can('students.password', 'students.manage')): ?><button class="btn" data-post="<?= e(url('students/password', ['id' => $u['id']])) ?>" data-fields='{"mode":"random6"}' data-confirm="Cấp mật khẩu mới cho học sinh này?"><?= icon('key-round') ?> Cấp lại mật khẩu</button><?php endif; ?>
    <?php if (can('students.manage')): ?><a class="btn btn-primary" href="<?= e(url('students/edit', ['id' => $u['id']])) ?>"><?= icon('square-pen') ?> Sửa</a><?php endif; ?>
  </div>
</div>

<div class="grid grid-4">
  <div class="stat"><div class="stat-icon"><?= icon('clipboard-list') ?></div><div><div class="stat-value"><?= count($attempts) ?></div><div class="stat-label">Lượt làm bài</div></div></div>
  <div class="stat"><div class="stat-icon success"><?= icon('award') ?></div><div><div class="stat-value"><?= $avg !== null ? e(fmt_num($avg, 2)) : '–' ?></div><div class="stat-label">Điểm trung bình</div></div></div>
  <div class="stat"><div class="stat-icon warning"><?= icon('trophy') ?></div><div><div class="stat-value"><?= $scored ? e(fmt_num(max(array_map(static fn($a) => (float) $a['score'], $scored)), 2)) : '–' ?></div><div class="stat-label">Điểm cao nhất</div></div></div>
  <div class="stat"><div class="stat-icon info"><?= icon('calendar') ?></div><div><div class="stat-value" style="font-size:1.05rem"><?= $u['last_login_at'] ? e(fmt_dt($u['last_login_at'])) : 'Chưa' ?></div><div class="stat-label">Đăng nhập gần nhất</div></div></div>
</div>

<?php if (count($scored) >= 2): ?>
<div class="card mt-3">
  <div class="card-head"><h3><?= icon('trending-up') ?> Tiến bộ qua các bài thi</h3></div>
  <div class="card-body"><div class="chart-box sm"><canvas id="trend"></canvas></div></div>
</div>
<?php \App\Core\View::push('scripts', '<script>TN.ready(function(){TNChart.line("trend",' . js_json(array_map(static fn($a) => fmt_dt($a['submitted_at'], 'd/m'), $scored)) . ',[{label:"Điểm",data:' . js_json(array_map(static fn($a) => (float) $a['score'], $scored)) . '}],{max:10});});</script>'); ?>
<?php endif; ?>

<div class="card mt-3">
  <div class="card-head"><h3><?= icon('history') ?> Lịch sử làm bài</h3></div>
  <?php if (!$attempts): ?>
    <div class="empty" style="padding:30px"><p>Học sinh chưa làm bài thi nào.</p></div>
  <?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Ca thi / đề</th><th>Mã đề</th><th>Bắt đầu</th><th>Thời gian</th><th>Trạng thái</th><th class="num">Điểm</th><th class="col-actions"></th></tr></thead>
    <tbody>
    <?php foreach ($attempts as $a): ?>
      <tr>
        <td><div class="person"><span class="subject-dot" style="background:<?= e($a['subject_color'] ?: '#2563eb') ?>"></span><div><div class="name"><?= e($a['session_name']) ?></div><div class="sub"><?= e($a['subject_name'] ?? '') ?> · <?= e($a['exam_title']) ?></div></div></div></td>
        <td class="mono"><?= e($a['variant_code']) ?></td>
        <td class="text-sm"><?= e(fmt_dt($a['started_at'])) ?></td>
        <td class="text-sm"><?= $a['submitted_at'] ? e(fmt_duration(max(0, (int) $a['submitted_at'] - (int) $a['started_at']))) : '—' ?></td>
        <td><?= Attempts::statusBadge($a) ?><?= (int) $a['violations'] > 0 ? ' ' . badge($a['violations'] . ' vi phạm', 'warning') : '' ?></td>
        <td class="num"><?= $a['score'] !== null ? '<span class="score-pill ' . score_class($a['score']) . '">' . e(fmt_score($a['score'])) . '</span>' : '<span class="text-faint">–</span>' ?></td>
        <td class="col-actions"><?php if (can('results.view', 'results.view_all')): ?><a class="btn btn-sm btn-ghost" href="<?= e(url('results/attempt', ['id' => $a['id']])) ?>"><?= icon('eye') ?> Chi tiết</a><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
