<?php
use App\Lib\Attempts;
use App\Lib\Scoring;
use App\Lib\Sessions;

$struct = $exam['_structure'];
$hasEssay = !empty($struct['essay']);
$state = Sessions::state($s);
$acc = $s['_access'];
$scale = $max ?: 10;
$pass = 0;
foreach ($rows as $r) {
    if ($r['a'] && $r['a']['score'] !== null && (float) $r['a']['score'] * 10 / $scale >= 5) {
        $pass++;
    }
}
$pending = count(array_filter($rows, static fn($r) => $r['a'] && $r['a']['grading_status'] === 'pending'));
$firstPending = null;
foreach ($rows as $r) {
    if ($r['a'] && $r['a']['grading_status'] === 'pending') {
        $firstPending = (int) $r['a']['id'];
        break;
    }
}
$q = static fn(array $extra) => e(query_with($extra));
?>
<div class="page-head">
  <div>
    <div class="eyebrow"><?= icon('clipboard-check', 'sm') ?> Bảng điểm</div>
    <h1><?= e($s['name']) ?></h1>
    <div class="sub row gap-sm"><?= Sessions::stateBadge($s) ?><?= (int) $s['released'] ? badge('Đã công bố điểm', 'success', 'badge-check') : badge('Chưa công bố điểm', 'default') ?><span><?= e($exam['title']) ?> · Thang <?= e(fmt_num($max)) ?> điểm</span></div>
  </div>
  <div class="actions">
    <?php if ($acc['proctor'] && in_array($state, ['running', 'paused'], true)): ?><a class="btn" href="<?= e(url('monitor', ['id' => $s['id']])) ?>"><?= icon('monitor') ?> Giám sát</a><?php endif; ?>
    <?php if (can('stats.view')): ?><a class="btn" href="<?= e(url('stats/session', ['id' => $s['id']])) ?>"><?= icon('chart-column') ?> Thống kê</a><?php endif; ?>
    <?php if (can('results.export')): ?><a class="btn btn-primary" href="<?= e(url('results/export', ['id' => $s['id'], 'class' => $classId])) ?>"><?= icon('file-spreadsheet') ?> Xuất Excel</a><?php endif; ?>
    <div class="dropdown">
      <button class="btn" data-dropdown><?= icon('ellipsis') ?></button>
      <div class="dropdown-menu">
        <a href="<?= e(url('results/print', ['id' => $s['id'], 'class' => $classId])) ?>" target="_blank"><?= icon('printer') ?> In phiếu trả lời (bản lưu)</a>
        <a href="<?= e(url('results/print', ['id' => $s['id'], 'class' => $classId, 'keys' => 1])) ?>" target="_blank"><?= icon('printer') ?> In phiếu có chấm đúng/sai</a>
        <?php if ($canGrade): ?>
          <hr>
          <button type="button" data-post="<?= e(url('results/rescore', ['id' => $s['id']])) ?>" data-confirm="Chấm lại TẤT CẢ bài làm theo đáp án và cách tính điểm hiện tại? (dùng sau khi sửa đáp án / hủy câu)"><?= icon('rotate-ccw') ?> Chấm lại toàn bộ</button>
          <?php $ctl = url('sessions/control', ['id' => $s['id']]); ?>
          <?php if ((int) $s['released']): ?>
            <button type="button" data-post="<?= e($ctl) ?>" data-fields='{"action":"unrelease"}'><?= icon('eye-off') ?> Ẩn điểm với học sinh</button>
          <?php else: ?>
            <button type="button" data-post="<?= e($ctl) ?>" data-fields='{"action":"release"}' data-confirm="Công bố điểm cho học sinh?"><?= icon('badge-check') ?> Công bố điểm</button>
          <?php endif; ?>
        <?php endif; ?>
        <hr>
        <a href="<?= e(url('sessions/view', ['id' => $s['id']])) ?>"><?= icon('info') ?> Thông tin ca thi</a>
      </div>
    </div>
  </div>
</div>

<div class="mini-stats mb-3">
  <div class="mini-stat"><div class="v"><?= (int) $desc['n'] ?><span class="text-muted text-sm">/<?= count($rows) ?></span></div><div class="l">Bài đã nộp</div></div>
  <div class="mini-stat"><div class="v"><?= $desc['mean'] !== null ? e(fmt_num($desc['mean'], 2)) : '–' ?></div><div class="l">Điểm trung bình</div></div>
  <div class="mini-stat"><div class="v"><?= $desc['median'] !== null ? e(fmt_num($desc['median'], 2)) : '–' ?></div><div class="l">Trung vị</div></div>
  <div class="mini-stat"><div class="v text-success"><?= $desc['max'] !== null ? e(fmt_num($desc['max'], 2)) : '–' ?></div><div class="l">Cao nhất</div></div>
  <div class="mini-stat"><div class="v text-danger"><?= $desc['min'] !== null ? e(fmt_num($desc['min'], 2)) : '–' ?></div><div class="l">Thấp nhất</div></div>
  <div class="mini-stat"><div class="v"><?= $desc['n'] ? e(fmt_percent($pass / $desc['n'], 0)) : '–' ?></div><div class="l">Đạt từ 5 điểm</div></div>
</div>

<?php if ($pending && $canGrade): ?>
  <div class="alert alert-warning mb-3"><?= icon('pen-line') ?><div class="grow"><b><?= $pending ?> bài</b> có phần tự luận đang chờ chấm.</div><a class="btn btn-sm" href="<?= e(url('results/attempt', ['id' => $firstPending])) ?>"><?= icon('pen-line') ?> Chấm ngay</a></div>
<?php endif; ?>

<div class="card">
  <div class="table-toolbar">
    <div class="seg">
      <?php foreach (['' => 'Tất cả', 'done' => 'Đã nộp', 'absent' => 'Vắng / chưa làm', 'doing' => 'Đang làm', 'pending' => 'Chờ chấm'] as $k => $v): ?>
        <a class="<?= $filter === $k ? 'active' : '' ?>" href="<?= $q(['f' => $k ?: null]) ?>"><?= e($v) ?></a>
      <?php endforeach; ?>
    </div>
    <?php if (count($classes) > 1): ?>
      <select class="select" style="width:auto" onchange="location.href=this.value">
        <option value="<?= $q(['class' => null]) ?>">Tất cả lớp</option>
        <?php foreach ($classes as $c): ?><option value="<?= $q(['class' => $c['id']]) ?>"<?= selected($classId, $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
    <?php endif; ?>
    <div class="seg">
      <a class="<?= ($_GET['sort'] ?? '') !== 'score' ? 'active' : '' ?>" href="<?= $q(['sort' => null]) ?>"><?= icon('list-ordered', 'sm') ?> Theo danh sách</a>
      <a class="<?= ($_GET['sort'] ?? '') === 'score' ? 'active' : '' ?>" href="<?= $q(['sort' => 'score']) ?>"><?= icon('trophy', 'sm') ?> Theo điểm</a>
    </div>
  </div>
  <div class="bulk-bar" id="bulk">
    <span><b data-count>0</b> bài được chọn</span>
    <button type="button" class="btn btn-sm" id="bulk-print"><?= icon('printer') ?> In phiếu</button>
    <?php if ($canGrade): ?><button type="button" class="btn btn-sm" id="bulk-rescore"><?= icon('rotate-ccw') ?> Chấm lại</button><?php endif; ?>
  </div>
  <div class="table-wrap"><table class="table">
    <thead><tr>
      <th style="width:34px"><input type="checkbox" data-check-all="#bulk" aria-label="Chọn tất cả"></th>
      <th>#</th><th>Học sinh</th><th>Mã đề</th><th>Trạng thái</th><th class="hide-sm">Thời gian làm</th>
      <?php if ($struct['p1']): ?><th class="text-right">P.I</th><?php endif; ?>
      <?php if ($struct['p2']): ?><th class="text-right">P.II</th><?php endif; ?>
      <?php if ($struct['p3']): ?><th class="text-right">P.III</th><?php endif; ?>
      <?php if ($hasEssay): ?><th class="text-right">TL</th><?php endif; ?>
      <th class="text-right">Tổng</th><th class="text-center" title="Số lần rời màn hình">Rời MH</th><th class="col-actions"></th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $i => $r):
        $u = $r['u'];
        $a = $r['a'];
        $d = $a ? json_dec($a['score_detail'], []) : [];
        $p = $d['parts'] ?? [];
        $used = $a && $a['submitted_at'] ? max(0, (int) $a['submitted_at'] - (int) $a['started_at'] - (int) $a['paused_total'] - (int) $a['hold_sec']) : 0; ?>
      <tr>
        <td><?php if ($a): ?><input type="checkbox" data-check value="<?= (int) $a['id'] ?>"><?php endif; ?></td>
        <td class="text-muted"><?= $i + 1 ?></td>
        <td>
          <div class="person"><div style="min-width:0">
            <?php if ($a): ?><a class="row-link" href="<?= e(url('results/attempt', ['id' => $a['id']])) ?>"><?= e($u['full_name']) ?></a><?php else: ?><span class="fw-600"><?= e($u['full_name']) ?></span><?php endif; ?>
            <div class="sub"><?= e($u['code'] ?: $u['username']) ?><?= !empty($u['class_name']) ? ' · ' . e($u['class_name']) : '' ?><?= !empty($r['extra']) ? ' · <span class="text-warning">ngoài danh sách</span>' : '' ?></div>
          </div></div>
        </td>
        <td class="mono"><?= $a ? e((string) $a['variant_code']) : '' ?></td>
        <td>
          <?php if ($a): ?>
            <?= Attempts::statusBadge($a) ?><?= $a['grading_status'] === 'pending' ? ' ' . badge('Chờ chấm TL', 'warning') : '' ?><?= (int) $a['_count'] > 1 ? '<div class="text-xs text-muted">lần ' . (int) $a['attempt_no'] . '/' . (int) $a['_count'] . '</div>' : '' ?>
            <div class="text-xs text-muted"><?= e(fmt_dt($a['submitted_at'], 'H:i d/m')) ?></div>
          <?php elseif ($r['doing']): ?>
            <?= badge('Đang làm', 'primary') ?><div class="text-xs text-muted"><?= (int) $r['doing']['answered'] ?> câu</div>
          <?php else: ?>
            <?= badge('Vắng / chưa làm', 'default') ?>
          <?php endif; ?>
        </td>
        <td class="hide-sm text-sm"><?= $used ? e(fmt_duration($used)) : '' ?></td>
        <?php foreach (['p1', 'p2', 'p3'] as $pp): if (!$struct[$pp]) { continue; } ?>
          <td class="text-right num text-sm"><?= isset($p[$pp]['score']) ? e(fmt_num($p[$pp]['score'], 2)) : '' ?></td>
        <?php endforeach; ?>
        <?php if ($hasEssay): ?><td class="text-right num text-sm"><?= $a ? ($a['grading_status'] === 'pending' ? '<span class="text-warning">?</span>' : e(fmt_num($p['essay']['score'] ?? 0, 2))) : '' ?></td><?php endif; ?>
        <td class="text-right"><?= $a && $a['score'] !== null ? '<span class="score-pill ' . score_class($a['score'], $scale) . '">' . e(fmt_score($a['score'])) . '</span>' : '' ?></td>
        <td class="text-center"><?= $a && (int) $a['violations'] ? '<span class="badge badge-warning">' . (int) $a['violations'] . '</span>' : '' ?></td>
        <td class="col-actions"><?php if ($a): ?><div class="table-actions"><a class="btn btn-sm btn-ghost" href="<?= e(url('results/review', ['id' => $a['id']])) ?>" target="_blank" title="Xem lại bài như học sinh"><?= icon('eye') ?></a><a class="btn btn-sm btn-ghost" href="<?= e(url('results/attempt', ['id' => $a['id']])) ?>" title="Chi tiết"><?= icon('chevron-right') ?></a></div><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="14"><div class="empty" style="padding:30px"><p>Không có học sinh phù hợp bộ lọc.</p></div></td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<?php \App\Core\View::push('scripts', '<script>
TN.ready(function () {
  var printUrl = ' . js_json(url('results/print', ['id' => $s['id']])) . ';
  var b = document.getElementById("bulk-print");
  if (b) b.addEventListener("click", function () { var ids = TN.checkedIds(); if (!ids.length) return; window.open(printUrl + "&" + ids.map(function (i) { return "aids[]=" + i; }).join("&"), "_blank"); });
  var r = document.getElementById("bulk-rescore");
  if (r) r.addEventListener("click", function () { var ids = TN.checkedIds(); if (!ids.length) return; TN.confirm({ message: "Chấm lại " + ids.length + " bài đã chọn?" }).then(function (ok) { if (ok) TN.post(' . js_json(url('results/rescore', ['id' => $s['id']])) . ', { "aids[]": ids }); }); });
});
</script>'); ?>
