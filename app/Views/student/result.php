<?php
use App\Lib\Attempts;
use App\Lib\ExamFormat;
use App\Lib\Scoring;

$struct = $exam['_structure'];
$max = (float) ($detail['max'] ?? Scoring::maxScore($struct, $exam['_scoring']));
$score = $a['score'] !== null ? (float) $a['score'] : null;
$pct = $score !== null && $max > 0 ? max(0, min(100, $score * 100 / $max)) : 0;
$parts = $detail['parts'] ?? [];
$pending = $a['grading_status'] === 'pending';
$used = $a['submitted_at'] ? max(0, (int) $a['submitted_at'] - (int) $a['started_at'] - (int) $a['paused_total'] - (int) $a['hold_sec']) : 0;
$total = ExamFormat::totalQuestions($struct);
$cls = Scoring::classify($score, $max ?: 10);
$praise = $score === null ? '' : ($pct >= 80 ? 'Xuất sắc! Em làm bài rất tốt 🎉' : ($pct >= 65 ? 'Làm tốt lắm! Cố gắng thêm chút nữa nhé 💪' : ($pct >= 50 ? 'Em đã hoàn thành bài thi. Cùng ôn lại phần chưa đúng nhé!' : 'Đừng nản lòng! Xem lại lời giải để tiến bộ hơn nhé.')));
$policy = ['after_end' => 'sau khi ca thi kết thúc', 'manual' => 'khi giáo viên công bố', 'never' => 'Giáo viên sẽ thông báo điểm sau'][$o['show_score']] ?? '';
$reviewPolicy = ['after_end' => 'sau khi ca thi kết thúc', 'manual' => 'khi giáo viên công bố kết quả', 'never' => ''][$o['allow_review']] ?? '';
?>
<div class="crumbs mb-2"><a href="<?= e(url($s['mode'] === 'practice' ? 'student/practice' : 'student')) ?>"><?= icon('arrow-left', 'sm') ?> <?= $s['mode'] === 'practice' ? 'Luyện tập' : 'Bài thi của em' ?></a></div>

<?php if ($showScore && $score !== null): ?>
  <section class="score-hero result-hero rise mb-3">
    <div class="score-ring" style="--p:<?= round($pct, 1) ?>"><span><?= e(fmt_score($score)) ?><small>/ <?= e(fmt_num($max)) ?> điểm</small></span></div>
    <div class="rh-body">
      <div class="eyebrow" style="color:rgba(255,255,255,.8)"><?= icon('award', 'sm') ?> Kết quả bài làm<?= $cls ? ' · Xếp loại ' . e($cls) : '' ?></div>
      <h1><?= e($praise) ?></h1>
      <div style="opacity:.9"><?= e($s['name']) ?> · Lần <?= (int) $a['attempt_no'] ?></div>
      <?php if ($pending): ?><div class="alert alert-warning mt-2 mb-0" style="color:var(--text)"><?= icon('pen-line') ?><div>Phần <b>tự luận</b> đang chờ giáo viên chấm. Điểm trên là điểm phần trắc nghiệm, sẽ được cập nhật sau.</div></div><?php endif; ?>
      <div class="rh-meta">
        <span><?= icon('hash') ?>Mã đề <?= e((string) $variant) ?></span>
        <span><?= icon('list-checks') ?><?= (int) $a['answered'] ?>/<?= $total ?> câu đã làm</span>
        <?php if ($used): ?><span><?= icon('timer') ?><?= e(fmt_duration($used)) ?></span><?php endif; ?>
        <span><?= icon('circle-check') ?><?= e(fmt_dt($a['submitted_at'], 'H:i d/m/Y')) ?></span>
      </div>
    </div>
  </section>
<?php else: ?>
  <div class="card rise mb-3">
    <div class="done-hero">
      <div class="big-ic"><?= icon('circle-check') ?></div>
      <h1 class="mb-1">Em đã nộp bài thành công!</h1>
      <p class="text-muted mb-0">Bài làm đã được lưu an toàn lúc <b><?= e(fmt_dt($a['submitted_at'], 'H:i:s d/m/Y')) ?></b> (<?= e(Attempts::REASONS[$a['submit_reason']] ?? 'Đã nộp') ?>).</p>
      <?php if (!$showScore && $policy): ?><p class="text-muted mt-1 mb-0"><?= icon('info', 'sm') ?> Điểm sẽ được hiển thị <?= e($policy) ?>.</p><?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<div class="grid grid-sidebar">
  <div class="stack" style="gap:20px">
    <?php if ($showScore && $parts): ?>
      <div class="card rise rise-1">
        <div class="card-head"><h3><?= icon('chart-bar') ?> Điểm từng phần</h3></div>
        <div class="card-body part-bars">
          <?php foreach (['p1' => 'Phần I – Nhiều phương án lựa chọn', 'p2' => 'Phần II – Đúng / sai', 'p3' => 'Phần III – Trả lời ngắn', 'essay' => 'Tự luận'] as $k => $label):
              $p = $parts[$k] ?? null;
              if (!$p || (int) ($p['total'] ?? 0) === 0) {
                  continue;
              }
              $w = $p['max'] > 0 ? max(0, min(100, $p['score'] * 100 / $p['max'])) : 0;
              if ($k === 'p1') {
                  $sub = 'Đúng ' . $p['correct'] . '/' . $p['total'] . ' câu' . ($p['blank'] ? ' · bỏ trống ' . $p['blank'] : '');
              } elseif ($k === 'p2') {
                  $sub = 'Đúng ' . $p['correct_items'] . '/' . $p['total_items'] . ' ý · ' . $p['full'] . ' câu đúng cả 4 ý';
              } elseif ($k === 'p3') {
                  $sub = 'Đúng ' . $p['correct'] . '/' . $p['total'] . ' câu' . (!empty($p['invalid']) ? ' · ' . $p['invalid'] . ' câu tô sai quy cách' : '');
              } else {
                  $sub = 'Đã chấm ' . $p['graded'] . '/' . $p['total'] . ' câu';
              } ?>
            <div class="part-bar">
              <div class="pb-top"><span><?= e($label) ?></span><b><?= e(fmt_num($p['score'])) ?> / <?= e(fmt_num($p['max'])) ?></b></div>
              <div class="progress"><span style="width:<?= round($w, 1) ?>%"></span></div>
              <div class="pb-sub"><?= e($sub) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="card rise rise-2">
      <div class="card-head"><h3><?= icon('lightbulb') ?> Xem lại bài làm</h3></div>
      <div class="card-body">
        <?php if ($canReview): ?>
          <p class="text-muted">Xem lại đề thi bên cạnh phiếu trả lời của em: câu đúng tô <b class="text-success">xanh</b>, câu chưa đúng tô <b class="text-danger">đỏ</b><?= $o['show_explanations'] ? ', kèm lời giải chi tiết từng câu (nếu có)' : '' ?>.</p>
          <a class="btn btn-primary" href="<?= e(url('student/review', ['aid' => $a['id']])) ?>"><?= icon('eye') ?> Xem lại bài & lời giải</a>
        <?php elseif ($reviewPolicy): ?>
          <p class="text-muted mb-0"><?= icon('clock', 'sm') ?> Em sẽ được xem lại bài làm <?= e($reviewPolicy) ?>.</p>
        <?php else: ?>
          <p class="text-muted mb-0">Ca thi này không cho phép xem lại bài làm.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="stack" style="gap:20px">
    <div class="card rise rise-1">
      <div class="card-head"><h3><?= icon('id-card') ?> Thông tin bài làm</h3></div>
      <div class="card-body kv-list">
        <div class="kv"><span>Ca thi</span><span><?= e($s['name']) ?></span></div>
        <div class="kv"><span>Mã đề</span><span class="mono"><?= e((string) $variant) ?></span></div>
        <div class="kv"><span>Bắt đầu</span><span><?= e(fmt_dt($a['started_at'], 'H:i:s d/m/Y')) ?></span></div>
        <div class="kv"><span>Nộp bài</span><span><?= e(fmt_dt($a['submitted_at'], 'H:i:s d/m/Y')) ?></span></div>
        <?php if ($used): ?><div class="kv"><span>Thời gian làm</span><span><?= e(fmt_duration($used)) ?></span></div><?php endif; ?>
        <div class="kv"><span>Hình thức</span><span><?= e(Attempts::REASONS[$a['submit_reason']] ?? '–') ?></span></div>
        <div class="kv"><span>Số câu đã làm</span><span><?= (int) $a['answered'] ?> / <?= $total ?></span></div>
        <?php if ((int) $a['violations'] > 0): ?><div class="kv"><span>Rời màn hình</span><span class="text-warning"><?= (int) $a['violations'] ?> lần</span></div><?php endif; ?>
      </div>
    </div>
    <div class="row">
      <?php if ($canRetry): ?><a class="btn btn-primary" href="<?= e(url('student/lobby', ['sid' => $s['id']])) ?>"><?= icon('rotate-ccw') ?> Làm lại</a><?php endif; ?>
      <a class="btn" href="<?= e(url('student/history')) ?>"><?= icon('history') ?> Lịch sử làm bài</a>
      <a class="btn btn-ghost" href="<?= e(url('student')) ?>"><?= icon('house') ?> Trang chủ</a>
      <?php if (\App\Lib\Seb::version() !== null && $o['seb'] === 'config'): ?><a class="btn btn-danger" href="<?= e(\App\Lib\Seb::quitUrl()) ?>"><?= icon('door-open') ?> Thoát Safe Exam Browser</a><?php endif; ?>
    </div>
  </div>
</div>
