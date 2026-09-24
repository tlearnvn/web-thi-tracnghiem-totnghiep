<?php
use App\Lib\Attempts;
use App\Lib\ExamFormat;
use App\Lib\Scoring;

use_katex();
\App\Core\View::push('scripts', '<script src="' . asset('js/answersheet.js') . '"></script>');
$struct = $exam['_structure'];
$max = (float) $r['max'] ?: 10;
$score = $a['score'] !== null ? (float) $a['score'] : null;
$running = $a['status'] === 'in_progress';
$used = $a['submitted_at'] ? max(0, (int) $a['submitted_at'] - (int) $a['started_at'] - (int) $a['paused_total'] - (int) $a['hold_sec']) : 0;
$sub = ['a', 'b', 'c', 'd'];
$fmtP2 = static function (?string $v) use ($sub): string {
    $v = str_pad((string) $v, 4, '_');
    $out = [];
    for ($j = 0; $j < 4; $j++) {
        $out[] = $sub[$j] . ')' . ($v[$j] === 'D' ? 'Đ' : ($v[$j] === 'S' ? 'S' : ($v[$j] === '*' ? '*' : '–')));
    }
    return implode(' ', $out);
};
$act = url('monitor/act', ['id' => $s['id']]);
$evLabel = static function (array $ev): array {
    $info = Attempts::EVENTS[$ev['type']] ?? [$ev['type'], 'circle'];
    $d = json_dec($ev['data'], []);
    $detail = '';
    if ($ev['type'] === 'answer' && is_array($d)) {
        $parts = [];
        foreach ($d as $q => $v) {
            $parts[] = str_replace(['p1.', 'p2.', 'p3.', 'e.'], ['I.', 'II.', 'III.', 'TL.'], (string) $q) . ' → ' . ($v === '' ? 'bỏ chọn' : (string) $v);
        }
        $detail = implode(', ', $parts);
    } elseif (is_array($d)) {
        foreach (['detail', 'device', 'minutes', 'score', 'reason', 'extra', 'paused', 'session_pause', 'total'] as $k) {
            if (isset($d[$k]) && $d[$k] !== '' && $d[$k] !== false) {
                $val = $d[$k];
                if (in_array($k, ['paused', 'session_pause'], true)) {
                    $val = fmt_duration((int) $val);
                } elseif ($k === 'minutes' || $k === 'extra') {
                    $val = ((int) $val > 0 ? '+' : '') . (int) $val . ' phút';
                } elseif ($k === 'score' || $k === 'total') {
                    $val = 'điểm ' . fmt_score($val);
                } elseif ($k === 'reason') {
                    $val = Attempts::REASONS[$val] ?? $val;
                }
                $detail .= ($detail !== '' ? ' · ' : '') . $val;
            }
        }
        if (!empty($d['counted'])) {
            $detail .= ' · lần ' . (int) ($d['n'] ?? 0);
        }
    }
    $level = in_array($ev['type'], ['leave', 'fullscreen_exit', 'device_blocked', 'violation_lock', 'multi_tab', 'print', 'copy', 'offline'], true) ? 'warning'
        : (in_array($ev['type'], ['submit', 'timeout', 'force_submit', 'grade'], true) ? 'success' : ($ev['type'] === 'answer' ? 'muted' : ''));
    return [$info[0], $info[1], $detail, $level];
};
$answerEvents = count(array_filter($events, static fn($e) => $e['type'] === 'answer'));
?>
<div class="page-head">
  <div>
    <div class="eyebrow"><?= icon('user', 'sm') ?> <?= e($u['class_name'] ?? '') ?> · <?= e($u['code'] ?: $u['username']) ?></div>
    <h1><?= e($u['full_name']) ?></h1>
    <div class="sub row gap-sm"><?= Attempts::statusBadge($a) ?><?= $a['grading_status'] === 'pending' ? badge('Chờ chấm tự luận', 'warning', 'pen-line') : ($a['grading_status'] === 'graded' ? badge('Đã chấm tự luận', 'success', 'check') : '') ?><span><?= e($s['name']) ?> · Mã đề <b class="mono"><?= e($variant['code'] ?? '') ?></b> · Lần <?= (int) $a['attempt_no'] ?></span></div>
  </div>
  <div class="actions">
    <?php if (!$running): ?><a class="btn" href="<?= e(url('results/review', ['id' => $a['id']])) ?>" target="_blank"><?= icon('eye') ?> Xem lại bài</a><?php endif; ?>
    <?php if (!$running): ?><a class="btn" href="<?= e(url('results/print', ['id' => $s['id'], 'aids[]' => $a['id'], 'keys' => 1])) ?>" target="_blank"><?= icon('printer') ?> In phiếu</a><?php endif; ?>
    <div class="dropdown">
      <button class="btn" data-dropdown><?= icon('ellipsis') ?></button>
      <div class="dropdown-menu">
        <?php if ($canGrade && !$running): ?><button type="button" data-post="<?= e(url('results/rescore', ['aid' => $a['id']])) ?>"><?= icon('rotate-ccw') ?> Chấm lại bài này</button><?php endif; ?>
        <?php if ($canProctor): ?>
          <?php if ($running): ?>
            <a href="<?= e(url('monitor', ['id' => $s['id']])) ?>"><?= icon('monitor') ?> Mở bảng giám sát</a>
            <button type="button" class="danger" data-post="<?= e($act) ?>" data-fields='<?= e(json_encode(['action' => 'force_submit', 'aid' => (int) $a['id']])) ?>' data-confirm="Thu bài ngay?"><?= icon('hourglass') ?> Thu bài</button>
          <?php elseif ($a['status'] !== 'voided'): ?>
            <button type="button" id="btn-reopen"><?= icon('lock-open') ?> Mở lại bài để làm tiếp…</button>
            <button type="button" class="danger" data-post="<?= e($act) ?>" data-fields='<?= e(json_encode(['action' => 'void', 'aid' => (int) $a['id']])) ?>' data-confirm="Hủy bài làm này để học sinh thi lại từ đầu?" data-danger><?= icon('rotate-ccw') ?> Hủy bài & cho thi lại</button>
          <?php endif; ?>
        <?php endif; ?>
        <hr><a href="<?= e(url('results/session', ['id' => $s['id']])) ?>"><?= icon('table') ?> Bảng điểm ca thi</a>
        <a href="<?= e(url('results/student', ['uid' => $u['id']])) ?>"><?= icon('history') ?> Tất cả kết quả của học sinh</a>
      </div>
    </div>
  </div>
</div>

<div class="grid grid-sidebar">
  <div class="stack" style="gap:20px">
    <div class="card">
      <div class="card-body row" style="gap:24px;align-items:center">
        <div class="text-center" style="min-width:140px">
          <div class="text-muted text-sm">Tổng điểm</div>
          <div style="font-size:46px;font-weight:800;line-height:1.1" class="num <?= $score !== null ? 'text-primary' : 'text-muted' ?>"><?= $score !== null ? e(fmt_score($score)) : ($running ? '…' : '–') ?></div>
          <div class="text-muted text-sm">/ <?= e(fmt_num($max)) ?> · <?= e(Scoring::classify($score, $max)) ?></div>
        </div>
        <div class="grow" style="min-width:260px">
          <div class="part-bars">
            <?php foreach (['p1' => 'Phần I', 'p2' => 'Phần II', 'p3' => 'Phần III', 'essay' => 'Tự luận'] as $k => $label):
                $p = $r['parts'][$k];
                if (!$p['total']) {
                    continue;
                }
                $w = $p['max'] > 0 ? max(0, min(100, $p['score'] * 100 / $p['max'])) : 0;
                $info = $k === 'p1' ? 'đúng ' . $p['correct'] . '/' . $p['total'] . ' câu' : ($k === 'p2' ? 'đúng ' . $p['correct_items'] . '/' . $p['total_items'] . ' ý · ' . $p['full'] . ' câu đủ 4 ý' : ($k === 'p3' ? 'đúng ' . $p['correct'] . '/' . $p['total'] . ' câu' : 'đã chấm ' . $p['graded'] . '/' . $p['total'])); ?>
              <div class="part-bar"><div class="pb-top"><span><?= e($label) ?> <span class="text-muted text-sm">· <?= e($info) ?></span></span><b><?= e(fmt_num($p['score'], 2)) ?> / <?= e(fmt_num($p['max'], 2)) ?></b></div><div class="progress progress-sm"><span style="width:<?= round($w, 1) ?>%"></span></div></div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php if ($running): ?><div class="card-foot text-sm text-muted"><?= icon('info', 'sm') ?> Học sinh đang làm bài – điểm hiển thị khi nộp. Số câu đã làm: <?= (int) $a['answered'] ?>.</div><?php endif; ?>
    </div>

    <?php if (!empty($struct['essay'])): ?>
      <form class="card card-accent" method="post" action="<?= e(url('results/grade', ['id' => $a['id']])) ?>">
        <?= csrf_field() ?>
        <div class="card-head"><h3><?= icon('pen-line') ?> Chấm tự luận</h3><?php if ($grader): ?><span class="hint">Chấm bởi <?= e($grader) ?> lúc <?= e(fmt_dt($a['graded_at'])) ?></span><?php endif; ?></div>
        <div class="card-body stack">
          <?php foreach ($struct['essay'] as $i => $es):
              $n = $i + 1;
              $k = $keys['e.' . $n] ?? null;
              $emax = ($k && $k['points'] !== null && $k['points'] !== '') ? (float) $k['points'] : (float) $es['points'];
              $text = (string) ($answers['e'][$n] ?? $answers['e'][(string) $n] ?? ''); ?>
            <div class="es-grade">
              <div class="row between mb-1"><b><?= e($es['label']) ?></b><span class="text-muted text-sm">Tối đa <?= e(fmt_num($emax)) ?> điểm</span></div>
              <div class="es-answer"><?= $text !== '' ? nl2br(e($text)) : '<span class="text-muted">(Học sinh bỏ trống)</span>' ?></div>
              <?php if ($k && $k['explanation']): ?><details class="mt-1"><summary class="text-sm text-primary" style="cursor:pointer">Hướng dẫn chấm / đáp án</summary><div class="md-body text-sm mt-1" data-md><?= e($k['explanation']) ?></div></details><?php endif; ?>
              <div class="row mt-2"><label class="text-sm fw-600" for="es<?= $n ?>">Điểm</label><input class="input" id="es<?= $n ?>" name="essay[<?= $n ?>]" style="width:110px" inputmode="decimal" value="<?= isset($essayScores[$n]) ? e(fmt_num($essayScores[$n], 2)) : '' ?>" placeholder="0 – <?= e(fmt_num($emax)) ?>" <?= $canGrade && !$running ? '' : 'disabled' ?>><span class="text-muted text-sm"><?= mb_strlen($text) ?> ký tự</span></div>
            </div>
          <?php endforeach; ?>
          <div class="field mb-0"><label>Nhận xét / ghi chú của giáo viên</label><input class="input" name="note" maxlength="255" value="<?= e($a['note'] ?? '') ?>" <?= $canGrade && !$running ? '' : 'disabled' ?>></div>
        </div>
        <?php if ($canGrade && !$running): ?>
          <div class="card-foot row end">
            <button class="btn" type="submit"><?= icon('save') ?> Lưu điểm</button>
            <?php if ($nextPending): ?><button class="btn btn-primary" type="submit" name="next" value="<?= $nextPending ?>"><?= icon('arrow-right') ?> Lưu & chấm bài tiếp</button><?php endif; ?>
          </div>
        <?php endif; ?>
      </form>
    <?php endif; ?>

    <div class="card">
      <div class="card-head"><h3><?= icon('list-checks') ?> Bài làm từng câu</h3><span class="hint">Mã đề <?= e($variant['code'] ?? '') ?></span></div>
      <div class="table-wrap"><table class="table table-sm">
        <thead><tr><th>Câu</th><th>Đáp án</th><th>Học sinh chọn</th><th class="text-center">Kết quả</th><th class="text-right">Điểm</th><th class="hide-sm">Mức độ / chủ đề</th></tr></thead>
        <tbody>
        <?php foreach (ExamFormat::questionIds($struct) as $qid):
            if (strpos($qid, 'e.') === 0) {
                continue;
            }
            $it = $r['items'][$qid] ?? null;
            $part = explode('.', $qid)[0];
            $k = $keys[$qid] ?? null;
            $given = $it['given'] ?? '';
            $key = (string) ($it['key'] ?? '');
            if ($part === 'p2') {
                $gTxt = trim((string) $given, '_') === '' ? '' : $fmtP2($given);
                $kTxt = $key !== '' ? $fmtP2($key) : '';
            } else {
                $gTxt = (string) $given;
                $kTxt = str_replace('|', ' hoặc ', $key);
            } ?>
          <tr>
            <td class="nowrap fw-600"><?= e(str_replace(['p1.', 'p2.', 'p3.'], ['I.', 'II.', 'III.'], $qid)) ?></td>
            <td class="mono text-sm"><?= $kTxt !== '' ? e($kTxt) : '<span class="text-danger">chưa có</span>' ?><?= !empty($it['void']) ? ' ' . badge('hủy', 'default') : '' ?></td>
            <td class="mono text-sm"><?= $gTxt !== '' ? e($gTxt) : '<span class="text-muted">bỏ trống</span>' ?><?= $part === 'p3' && isset($it['valid']) && !$it['valid'] && $gTxt !== '' ? '<div class="text-xs text-danger">' . e($it['note']) . '</div>' : '' ?></td>
            <td class="text-center"><?php if ($part === 'p2'): ?><span class="badge <?= ($it['k'] ?? 0) === 4 ? 'badge-success' : (($it['k'] ?? 0) >= 2 ? 'badge-warning' : 'badge-danger') ?>"><?= (int) ($it['k'] ?? 0) ?>/4 ý</span><?php elseif ($it && $it['ok']): ?><span class="text-success"><?= icon('circle-check') ?></span><?php elseif ($gTxt === ''): ?><span class="text-faint"><?= icon('circle-minus') ?></span><?php else: ?><span class="text-danger"><?= icon('circle-x') ?></span><?php endif; ?></td>
            <td class="text-right num text-sm"><?= $it ? e(fmt_num($it['pts'], 2)) : '' ?></td>
            <td class="hide-sm text-xs text-muted"><?= e(trim(($k['level'] ?? '') . ' · ' . ($k['topic'] ?? ''), ' ·')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>

  <div class="stack" style="gap:20px">
    <div class="card">
      <div class="card-head"><h3><?= icon('clock') ?> Thời gian & thiết bị</h3></div>
      <div class="card-body kv-list">
        <div class="kv"><span>Bắt đầu</span><span><?= e(fmt_dt($a['started_at'], 'H:i:s d/m/Y')) ?></span></div>
        <div class="kv"><span>Nộp bài</span><span><?= $a['submitted_at'] ? e(fmt_dt($a['submitted_at'], 'H:i:s d/m/Y')) : '–' ?></span></div>
        <div class="kv"><span>Hình thức nộp</span><span><?= e(Attempts::REASONS[$a['submit_reason']] ?? '–') ?></span></div>
        <div class="kv"><span>Thời gian làm</span><span><?= $used ? e(fmt_duration($used)) : '–' ?> / <?= (int) $a['duration_sec'] ? e(fmt_duration((int) $a['duration_sec'])) : '∞' ?></span></div>
        <?php if ((int) $a['extra_sec']): ?><div class="kv"><span>Được cộng giờ</span><span><?= (int) $a['extra_sec'] > 0 ? '+' : '' ?><?= (int) round((int) $a['extra_sec'] / 60) ?> phút</span></div><?php endif; ?>
        <?php if ((int) $a['paused_total'] || (int) $a['hold_sec']): ?><div class="kv"><span>Tạm dừng</span><span><?= e(fmt_duration((int) $a['paused_total'] + (int) $a['hold_sec'])) ?></span></div><?php endif; ?>
        <div class="kv"><span>Số câu đã làm</span><span><?= (int) $a['answered'] ?> / <?= ExamFormat::totalQuestions($struct) ?></span></div>
        <div class="kv"><span>Rời màn hình</span><span class="<?= (int) $a['violations'] ? 'text-warning' : '' ?>"><?= (int) $a['violations'] ?> lần</span></div>
        <div class="kv"><span>Thiết bị</span><span><?= e($a['device_info'] ?: '–') ?></span></div>
        <div class="kv"><span>Địa chỉ IP</span><span class="mono"><?= e($a['ip'] ?: '–') ?></span></div>
        <div class="kv"><span>Lưu gần nhất</span><span><?= $a['last_saved_at'] ? e(fmt_dt($a['last_saved_at'], 'H:i:s')) : '–' ?> · <?= (int) $a['seq'] ?> lần</span></div>
      </div>
    </div>
    <?php if (count($others) > 1): ?>
      <div class="card">
        <div class="card-head"><h3><?= icon('repeat') ?> Các lần làm bài</h3></div>
        <div class="card-body">
          <?php foreach ($others as $o): ?>
            <a class="row between" style="padding:6px 0" href="<?= e(url('results/attempt', ['id' => $o['id']])) ?>"><span>Lần <?= (int) $o['attempt_no'] ?> <?= (int) $o['id'] === (int) $a['id'] ? '(đang xem)' : '' ?></span><span><?= Attempts::statusBadge($o) ?> <b class="num"><?= $o['score'] !== null ? e(fmt_score($o['score'])) : '' ?></b></span></a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
    <div class="card">
      <div class="card-head"><h3><?= icon('activity') ?> Nhật ký bài làm</h3><?php if ($answerEvents): ?><label class="check text-sm"><input type="checkbox" id="show-answers"> Hiện <?= $answerEvents ?> lần tô</label><?php endif; ?></div>
      <div class="card-body" style="max-height:640px;overflow:auto">
        <div class="timeline" id="att-events">
          <?php foreach ($events as $ev):
              [$label, $ico, $detail, $level] = $evLabel($ev); ?>
            <div class="tl-item <?= e($level) ?>"<?= $ev['type'] === 'answer' ? ' data-answer hidden' : '' ?>>
              <div class="tl-time"><?= e(fmt_dt($ev['created_at'], 'H:i:s d/m')) ?><?= $ev['ip'] ? ' · ' . e($ev['ip']) : '' ?></div>
              <div class="tl-title"><?= e($label) ?></div>
              <?php if ($detail !== ''): ?><div class="tl-data"><?= e($detail) ?></div><?php endif; ?>
            </div>
          <?php endforeach; ?>
          <?php if (!$events): ?><div class="text-muted text-sm">Chưa có sự kiện.</div><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php \App\Core\View::push('scripts', '<script>
TN.ready(function () {
  var cb = document.getElementById("show-answers");
  if (cb) cb.addEventListener("change", function () { document.querySelectorAll("#att-events [data-answer]").forEach(function (x) { x.hidden = !cb.checked; }); });
  document.querySelectorAll("[data-md]").forEach(function (el) { if (window.AnswerSheet) { el.innerHTML = AnswerSheet.md(el.textContent); AnswerSheet.math(el); } });
  var ro = document.getElementById("btn-reopen");
  if (ro) ro.addEventListener("click", function () {
    TN.confirm({ title: "Mở lại bài làm", message: "Học sinh sẽ vào lại và làm tiếp bài này. Cộng thêm bao nhiêu phút?", input: { type: "number", value: "5", label: "Số phút cộng thêm" }, ok: "Mở lại" }).then(function (v) {
      if (v === false) return;
      TN.api(' . js_json($act) . ', { data: { action: "reopen", aid: ' . (int) $a['id'] . ', minutes: parseInt(v, 10) || 0 } }).then(function (r) { TN.toast(r.message, "success"); setTimeout(function () { location.reload(); }, 700); }, function (e) { TN.toast(e.message, "error"); });
    });
  });
});
</script>'); ?>
