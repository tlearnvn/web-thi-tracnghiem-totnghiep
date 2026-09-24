<?php
use App\Lib\Sessions;

$state = $_GET['state'] ?? '';
?>
<div class="page-head">
  <div><h1>Ca thi</h1><div class="sub">Lịch thi, giám sát trực tiếp và kết quả. Giờ hiển thị theo UTC+7.</div></div>
  <div class="actions">
    <?php if ($canCreate): ?>
      <a class="btn" href="<?= e(url('sessions/create', ['mode' => 'practice'])) ?>"><?= icon('target') ?> Tạo ca luyện tập</a>
      <a class="btn btn-primary" href="<?= e(url('sessions/create')) ?>"><?= icon('calendar-clock') ?> Tạo ca thi</a>
    <?php endif; ?>
  </div>
</div>
<div class="card">
  <form class="table-toolbar" method="get" action="<?= e(base_uri() . 'index.php') ?>">
    <input type="hidden" name="r" value="sessions">
    <div class="seg">
      <?php foreach (['' => 'Tất cả', 'running' => 'Đang diễn ra', 'upcoming' => 'Sắp tới', 'ended' => 'Đã kết thúc'] as $k => $v): ?><a class="<?= $state === $k ? 'active' : '' ?>" href="<?= e(query_with(['state' => $k, 'page' => null])) ?>"><?= e($v) ?></a><?php endforeach; ?>
    </div>
    <select class="select" name="mode" style="width:auto" data-autosubmit><option value="">Thi & luyện tập</option><?php foreach (Sessions::MODES as $k => $v): ?><option value="<?= e($k) ?>"<?= selected($_GET['mode'] ?? '', $k) ?>><?= e($v) ?></option><?php endforeach; ?></select>
    <div class="input-icon search"><?= icon('search') ?><input class="input" type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Tìm ca thi, đề thi…" data-autosubmit></div>
    <input type="hidden" name="state" value="<?= e($state) ?>">
  </form>
  <?php if (!$rows): ?>
    <div class="empty"><div class="empty-icon"><?= icon('calendar-clock') ?></div><h3>Chưa có ca thi</h3><p>Tạo ca thi từ một đề đã có mã đề và đáp án, chọn lớp dự thi và thời gian.</p><?php if ($canCreate): ?><a class="btn btn-primary" href="<?= e(url('sessions/create')) ?>"><?= icon('plus') ?> Tạo ca thi</a><?php endif; ?></div>
  <?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Ca thi</th><th>Thời gian</th><th>Đối tượng</th><th>Trạng thái</th><th style="min-width:170px">Tiến độ</th><th class="col-actions"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
        $st = Sessions::state($r);
        $tg = max(1, (int) $r['_targets']); ?>
      <tr>
        <td>
          <div class="person"><span class="subject-dot" style="background:<?= e($r['subject_color'] ?: '#2563eb') ?>;width:12px;height:12px"></span>
            <div style="min-width:0"><a class="row-link" href="<?= e(url('sessions/view', ['id' => $r['id']])) ?>"><?= e($r['name']) ?></a>
              <div class="sub"><?= e($r['subject_name'] ?? '') ?> · <?= e(str_limit($r['exam_title'], 48)) ?><?= $r['mode'] === 'practice' ? ' ' . badge('Luyện tập', 'purple') : '' ?><?= $r['access_code'] ? ' ' . badge('Có mã vào phòng', 'default', 'key-round') : '' ?></div></div></div>
        </td>
        <td class="text-sm nowrap"><?= $r['start_at'] ? e(fmt_dt($r['start_at'])) : 'Mở ngay' ?><br><span class="text-muted"><?= $r['end_at'] ? '→ ' . e(fmt_dt($r['end_at'])) : 'Không giới hạn' ?></span></td>
        <td class="text-sm"><?= $r['_classes'] ? e(implode(', ', $r['_classes'])) : '' ?><div class="text-muted"><?= (int) $r['_targets'] ?> học sinh</div></td>
        <td><?= Sessions::stateBadge($r) ?><?= (int) $r['released'] ? '<div class="mt-1">' . badge('Đã công bố điểm', 'success') . '</div>' : '' ?></td>
        <td>
          <div class="progress-label"><span><?= (int) $r['doing'] ?> đang làm · <?= (int) $r['done'] ?> đã nộp</span></div>
          <div class="progress"><span style="width:<?= min(100, round(((int) $r['done'] + (int) $r['doing']) * 100 / $tg)) ?>%"></span></div>
        </td>
        <td class="col-actions">
          <div class="table-actions">
            <?php if (in_array($st, ['running', 'paused', 'upcoming'], true)): ?><a class="btn btn-sm btn-soft" href="<?= e(url('monitor', ['id' => $r['id']])) ?>"><?= icon('monitor') ?> Giám sát</a><?php endif; ?>
            <a class="btn btn-sm btn-ghost" href="<?= e(url('results/session', ['id' => $r['id']])) ?>"><?= icon('clipboard-check') ?> Kết quả</a>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
  <?= $pager->links() ?>
</div>
