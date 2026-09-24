<?php
use App\Controllers\ExamsController;
use App\Lib\ExamFormat;

$own = $_GET['own'] ?? '';
?>
<div class="page-head">
  <div><h1>Đề thi & đáp án</h1><div class="sub">Mỗi đề gồm một hoặc nhiều mã đề; mỗi mã đề có tệp PDF, đáp án và lời giải riêng.</div></div>
  <div class="actions"><?php if ($canCreate): ?><a class="btn btn-primary" href="<?= e(url('exams/create')) ?>"><?= icon('file-plus') ?> Tạo đề thi</a><?php endif; ?></div>
</div>
<div class="card mb-3">
  <form class="table-toolbar" method="get" action="<?= e(base_uri() . 'index.php') ?>" style="border-bottom:0">
    <input type="hidden" name="r" value="exams">
    <div class="input-icon search"><?= icon('search') ?><input class="input" type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Tìm theo tên đề…" data-autosubmit></div>
    <div class="seg">
      <?php foreach (['' => 'Tất cả', 'mine' => 'Của tôi', 'shared' => 'Được chia sẻ'] as $k => $v): ?><a class="<?= $own === $k ? 'active' : '' ?>" href="<?= e(query_with(['own' => $k])) ?>"><?= e($v) ?></a><?php endforeach; ?>
    </div>
    <select class="select" name="subject_id" style="width:auto" data-autosubmit><option value="">Mọi môn</option><?php foreach ($subjects as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected($_GET['subject_id'] ?? '', $s['id']) ?>><?= e($s['name']) ?></option><?php endforeach; ?></select>
    <select class="select" name="status" style="width:auto" data-autosubmit><option value="">Đang dùng</option><?php foreach (ExamsController::STATUSES as $k => $v): ?><option value="<?= e($k) ?>"<?= selected($_GET['status'] ?? '', $k) ?>><?= e($v[0]) ?></option><?php endforeach; ?><option value="all"<?= selected($_GET['status'] ?? '', 'all') ?>>Tất cả</option></select>
    <input type="hidden" name="own" value="<?= e($own) ?>">
  </form>
</div>
<?php if (!$rows): ?>
  <div class="card"><div class="empty"><div class="empty-icon"><?= icon('file-text') ?></div><h3>Chưa có đề thi</h3><p>Tạo đề thi, tải tệp PDF đề của từng mã đề và nhập đáp án (từ Excel / JSON hoặc tô trực tiếp trên phiếu).</p><?php if ($canCreate): ?><a class="btn btn-primary" href="<?= e(url('exams/create')) ?>"><?= icon('file-plus') ?> Tạo đề thi đầu tiên</a><?php endif; ?></div></div>
<?php else: ?>
<div class="tile-grid">
  <?php foreach ($rows as $i => $r):
      $st = ExamFormat::normalizeStructure($r['structure']);
      $expected = max(1, ($st['p1'] + $st['p2'] + $st['p3']) * max(1, (int) $r['variants']));
      $keyPct = min(100, round((int) $r['keys_filled'] * 100 / $expected));
      $stt = ExamsController::STATUSES[$r['status']] ?? ['', 'default']; ?>
    <a class="tile rise" style="--tile-color:<?= e($r['subject_color'] ?: '#2563eb') ?>;animation-delay:<?= min(12, $i) * 30 ?>ms;color:inherit" href="<?= e(url('exams/view', ['id' => $r['id']])) ?>">
      <div class="tile-head">
        <div class="tile-icon"><?= e(mb_substr((string) ($r['subject_short'] ?: $r['subject_name'] ?: 'Đề'), 0, 4)) ?></div>
        <div class="grow" style="min-width:0">
          <div class="tile-title"><?= e($r['title']) ?></div>
          <div class="tile-sub"><?= e($r['subject_name'] ?? 'Chưa chọn môn') ?> · <?= (int) $r['duration'] ?> phút</div>
        </div>
      </div>
      <div class="tile-meta">
        <span><?= icon('layout-grid') ?><?= e(ExamFormat::describe($st)) ?></span>
      </div>
      <div class="tile-meta">
        <span><?= icon('files') ?><?= (int) $r['variants'] ?> mã đề</span>
        <span class="<?= (int) $r['with_pdf'] < (int) $r['variants'] ? 'text-warning' : 'text-success' ?>"><?= icon('file-check') ?><?= (int) $r['with_pdf'] ?>/<?= (int) $r['variants'] ?> có PDF</span>
        <span><?= icon('calendar-clock') ?><?= (int) $r['sessions'] ?> ca thi</span>
      </div>
      <div>
        <div class="progress-label"><span>Đáp án</span><span><?= $keyPct ?>%</span></div>
        <div class="progress progress-sm <?= $keyPct >= 100 ? 'success' : ($keyPct > 0 ? '' : 'warning') ?>"><span style="width:<?= $keyPct ?>%"></span></div>
      </div>
      <div class="tile-foot">
        <span class="text-sm text-muted"><?= icon('user', 'sm') ?> <?= e($r['owner_name'] ?? '—') ?></span>
        <span class="row gap-sm"><?= (int) $r['is_shared'] ? badge('Chia sẻ', 'purple', 'users') : '' ?><?= badge($stt[0], $stt[1]) ?></span>
      </div>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>
