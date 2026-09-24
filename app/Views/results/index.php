<?php
use App\Lib\Sessions;
?>
<div class="page-head">
  <div><h1>Kết quả & chấm bài</h1><div class="sub">Bảng điểm từng ca thi, chấm tự luận, chấm lại, xuất Excel và in phiếu trả lời.</div></div>
  <div class="actions"><?php if (can('stats.view')): ?><a class="btn" href="<?= e(url('stats')) ?>"><?= icon('chart-column') ?> Thống kê & phân tích</a><?php endif; ?></div>
</div>
<div class="card">
  <form class="table-toolbar" method="get" action="<?= e(base_uri() . 'index.php') ?>">
    <input type="hidden" name="r" value="results">
    <div class="input-icon search"><?= icon('search') ?><input class="input" type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Tìm ca thi, đề thi…" data-autosubmit></div>
    <select class="select" name="subject" style="width:auto" data-autosubmit><option value="">Tất cả môn</option><?php foreach ($subjects as $sb): ?><option value="<?= (int) $sb['id'] ?>"<?= selected($_GET['subject'] ?? '', $sb['id']) ?>><?= e($sb['name']) ?></option><?php endforeach; ?></select>
    <select class="select" name="mode" style="width:auto" data-autosubmit><option value="">Thi & luyện tập</option><?php foreach (Sessions::MODES as $k => $v): ?><option value="<?= e($k) ?>"<?= selected($_GET['mode'] ?? '', $k) ?>><?= e($v) ?></option><?php endforeach; ?></select>
  </form>
  <?php if (!$rows): ?>
    <div class="empty"><div class="empty-icon"><?= icon('clipboard-check') ?></div><h3>Chưa có kết quả</h3><p>Kết quả sẽ xuất hiện khi học sinh nộp bài trong các ca thi bạn quản lý.</p></div>
  <?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Ca thi</th><th>Thời gian</th><th style="min-width:160px">Đã nộp</th><th class="text-right">Điểm TB</th><th class="text-right">Cao nhất</th><th class="col-actions"></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
        $tg = max(1, (int) $r['_targets']);
        $max = (float) $r['_max'] ?: 10; ?>
      <tr>
        <td>
          <div class="person"><span class="subject-dot" style="background:<?= e($r['subject_color'] ?: '#2563eb') ?>;width:12px;height:12px"></span>
            <div style="min-width:0"><a class="row-link" href="<?= e(url('results/session', ['id' => $r['id']])) ?>"><?= e($r['name']) ?></a>
              <div class="sub"><?= e($r['subject_name'] ?? '') ?> · <?= e(str_limit($r['exam_title'], 50)) ?> <?= Sessions::stateBadge($r) ?><?= $r['mode'] === 'practice' ? ' ' . badge('Luyện tập', 'purple') : '' ?><?= (int) $r['pending'] ? ' ' . badge((int) $r['pending'] . ' bài chờ chấm', 'warning', 'pen-line') : '' ?><?= (int) $r['released'] ? ' ' . badge('Đã công bố', 'success') : '' ?></div></div></div>
        </td>
        <td class="text-sm nowrap"><?= $r['start_at'] ? e(fmt_dt($r['start_at'], 'H:i d/m/Y')) : e(fmt_dt($r['created_at'], 'd/m/Y')) ?></td>
        <td>
          <div class="progress-label"><span><?= (int) $r['done'] ?>/<?= (int) $r['_targets'] ?> học sinh<?= (int) $r['doing'] ? ' · ' . (int) $r['doing'] . ' đang làm' : '' ?></span></div>
          <div class="progress"><span style="width:<?= min(100, round((int) $r['done'] * 100 / $tg)) ?>%"></span></div>
        </td>
        <td class="text-right"><?= $r['avg_score'] !== null ? '<span class="score-pill ' . score_class($r['avg_score'], $max) . '">' . e(fmt_score($r['avg_score'])) . '</span>' : '<span class="text-muted">–</span>' ?></td>
        <td class="text-right num"><?= $r['max_score'] !== null ? e(fmt_score($r['max_score'])) : '–' ?></td>
        <td class="col-actions">
          <div class="table-actions">
            <a class="btn btn-sm btn-soft" href="<?= e(url('results/session', ['id' => $r['id']])) ?>"><?= icon('table') ?> Bảng điểm</a>
            <?php if (can('stats.view')): ?><a class="btn btn-sm btn-ghost" href="<?= e(url('stats/session', ['id' => $r['id']])) ?>" title="Thống kê"><?= icon('chart-column') ?></a><?php endif; ?>
            <?php if (can('results.export')): ?><a class="btn btn-sm btn-ghost" href="<?= e(url('results/export', ['id' => $r['id']])) ?>" title="Xuất Excel"><?= icon('file-spreadsheet') ?></a><?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
  <?= $pager->links() ?>
</div>
