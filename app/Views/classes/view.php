<?php
$avgs = array_filter(array_map(static fn($s) => $s['avg_score'], $students), static fn($v) => $v !== null);
?>
<div class="page-head">
  <div>
    <div class="eyebrow"><?= icon('school', 'sm') ?> Năm học <?= e($c['school_year']) ?></div>
    <h1>Lớp <?= e($c['name']) ?> <?= $c['status'] !== 'active' ? badge('Lưu trữ') : '' ?></h1>
    <div class="sub">GVCN: <?= $homeroom ? e($homeroom['full_name']) : 'chưa phân công' ?><?= $c['description'] ? ' · ' . e($c['description']) : '' ?></div>
  </div>
  <div class="actions">
    <a class="btn" href="<?= e(url('students/export', ['class_id' => $c['id']])) ?>"><?= icon('file-down') ?> Xuất danh sách</a>
    <?php if (can('students.manage')): ?><a class="btn" href="<?= e(url('students/create', ['class_id' => $c['id']])) ?>"><?= icon('user-plus') ?> Thêm học sinh</a><?php endif; ?>
    <?php if (can('classes.manage')): ?><a class="btn btn-primary" href="<?= e(url('classes/edit', ['id' => $c['id']])) ?>"><?= icon('square-pen') ?> Sửa lớp</a><?php endif; ?>
  </div>
</div>
<div class="grid grid-4">
  <div class="stat"><div class="stat-icon"><?= icon('users') ?></div><div><div class="stat-value"><?= count($students) ?></div><div class="stat-label">Học sinh</div></div></div>
  <div class="stat"><div class="stat-icon purple"><?= icon('user-cog') ?></div><div><div class="stat-value"><?= count($teachers) ?></div><div class="stat-label">Phân công giảng dạy</div></div></div>
  <div class="stat"><div class="stat-icon info"><?= icon('calendar-clock') ?></div><div><div class="stat-value"><?= count($sessions) ?></div><div class="stat-label">Ca thi dành cho lớp</div></div></div>
  <div class="stat"><div class="stat-icon success"><?= icon('award') ?></div><div><div class="stat-value"><?= $avgs ? e(fmt_num(array_sum($avgs) / count($avgs), 2)) : '–' ?></div><div class="stat-label">Điểm TB của lớp</div></div></div>
</div>
<div class="grid grid-sidebar mt-3">
  <div class="card">
    <div class="card-head"><h3><?= icon('graduation-cap') ?> Danh sách học sinh</h3><a class="btn btn-sm btn-ghost" href="<?= e(url('students', ['class_id' => $c['id']])) ?>">Quản lý <?= icon('arrow-right', 'sm') ?></a></div>
    <div class="table-wrap"><table class="table compact">
      <thead><tr><th>#</th><th>Họ và tên</th><th>Mã HS</th><th>Ngày sinh</th><th class="center">Bài đã làm</th><th class="num">Điểm TB</th></tr></thead>
      <tbody>
      <?php foreach ($students as $i => $s): ?>
        <tr><td class="text-muted"><?= $i + 1 ?></td><td><a class="row-link" href="<?= e(url('students/view', ['id' => $s['id']])) ?>"><?= e($s['full_name']) ?></a></td><td class="mono text-sm"><?= e($s['code']) ?></td><td class="text-sm"><?= e(fmt_date($s['birthday'])) ?></td><td class="center"><?= (int) $s['done'] ?></td><td class="num"><?= $s['avg_score'] !== null ? '<span class="score-pill ' . score_class($s['avg_score']) . '">' . e(fmt_score($s['avg_score'])) . '</span>' : '–' ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$students): ?><tr><td colspan="6" class="text-center text-muted">Lớp chưa có học sinh.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
  <div class="stack" style="gap:20px">
    <div class="card">
      <div class="card-head"><h3><?= icon('user-cog') ?> Giáo viên</h3></div>
      <div class="card-body">
        <?php if (!$teachers && !$homeroom): ?><p class="text-muted mb-0">Chưa phân công giáo viên.</p><?php endif; ?>
        <ul class="list-plain">
          <?php if ($homeroom): ?><li><strong><?= e($homeroom['full_name']) ?></strong> <?= badge('Chủ nhiệm', 'warning') ?></li><?php endif; ?>
          <?php foreach ($teachers as $t): ?><li><strong><?= e($t['full_name']) ?></strong> <span class="text-muted text-sm">· <?= e($t['subject_name'] ?? 'Không rõ môn') ?></span></li><?php endforeach; ?>
        </ul>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h3><?= icon('calendar-clock') ?> Ca thi của lớp</h3></div>
      <div class="card-body">
        <?php if (!$sessions): ?><p class="text-muted mb-0">Chưa có ca thi nào dành cho lớp.</p><?php endif; ?>
        <ul class="list-plain">
          <?php foreach ($sessions as $s): ?>
            <li><a class="fw-600" href="<?= e(url('results/session', ['id' => $s['id']])) ?>"><?= e($s['name']) ?></a>
              <div class="text-sm text-muted"><?= e($s['subject_name'] ?? '') ?> · <?= (int) $s['n'] ?> bài<?= $s['avg_score'] !== null ? ' · TB ' . e(fmt_num($s['avg_score'], 2)) : '' ?></div></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
</div>
