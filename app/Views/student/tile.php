<?php
/** Thẻ một ca thi ở cổng học sinh. Biến: $s (đã gắn _state, _doing, _done, _official, _structure), $group: now|upcoming|done|practice */
use App\Lib\ExamFormat;
use App\Lib\Sessions;
use App\Lib\Text;

$state = $s['_state'];
$dur = Sessions::durationSec($s, ['duration' => $s['exam_duration']]);
$color = $s['subject_color'] ?: '#2563eb';
$doing = $s['_doing'];
$cls = $doing ? ' is-doing' : (in_array($group, ['now', 'practice'], true) && $state === 'running' ? ' is-live' : '');
?>
  <div class="tile<?= $cls ?>" style="--tile-color:<?= e($color) ?>">
    <div class="tile-head">
      <div class="tile-icon"><?= e(mb_strtoupper(mb_substr(Text::unaccent((string) ($s['subject_name'] ?: 'Đề')), 0, 2))) ?></div>
      <div style="min-width:0;flex:1">
        <div class="tile-title"><?= e($s['name']) ?></div>
        <div class="tile-sub"><?= e($s['subject_name'] ?: 'Bài thi') ?> · <?= e(str_limit($s['exam_title'], 60)) ?></div>
      </div>
      <?php if ($s['mode'] === 'practice'): ?><?= badge('Luyện tập', 'purple') ?><?php else: ?><?= Sessions::stateBadge($s) ?><?php endif; ?>
    </div>
    <div class="tile-meta">
      <span><?= icon('timer') ?><?= $dur > 0 ? e(fmt_duration($dur)) : 'Không giới hạn' ?></span>
      <span><?= icon('list-checks') ?><?= e(ExamFormat::describe($s['_structure'])) ?></span>
      <?php if ($s['start_at'] || $s['end_at']): ?>
        <span><?= icon('calendar-clock') ?><?= $s['start_at'] ? e(fmt_dt($s['start_at'], 'H:i d/m')) : 'Mở ngay' ?><?= $s['end_at'] ? ' → ' . e(fmt_dt($s['end_at'], 'H:i d/m')) : '' ?></span>
      <?php endif; ?>
      <?php if ($s['room']): ?><span><?= icon('door-open') ?><?= e($s['room']) ?></span><?php endif; ?>
      <?php if ((int) $s['max_attempts'] !== 1): ?><span><?= icon('repeat') ?><?= (int) $s['max_attempts'] === 0 ? 'Không giới hạn lượt' : count($s['_done']) . '/' . (int) $s['max_attempts'] . ' lượt' ?></span><?php endif; ?>
    </div>
    <?php if ($doing): ?>
      <div class="tile-note text-primary"><?= icon('circle-play') ?> Em đang làm dở bài này (đã làm <?= (int) $doing['answered'] ?> câu). Thời gian vẫn đang được tính.</div>
    <?php elseif ($s['access_code'] && $group === 'now'): ?>
      <div class="tile-note"><?= icon('key-round') ?> Cần mã vào phòng do giám thị cung cấp.</div>
    <?php endif; ?>
    <div class="tile-foot">
      <?php if ($group === 'upcoming'): ?>
        <div class="tile-state"><?= icon('hourglass') ?> Bắt đầu sau <span class="countdown" data-countdown="<?= (int) $s['start_at'] ?>" data-reload="1">…</span></div>
        <a class="btn btn-sm" href="<?= e(url('student/lobby', ['sid' => $s['id']])) ?>"><?= icon('info') ?> Chi tiết</a>
      <?php elseif ($group === 'now'): ?>
        <div class="tile-state">
          <?php if ($state === 'paused'): ?><?= icon('circle-pause') ?> Đang tạm dừng
          <?php elseif ($s['end_at'] && (int) $s['end_at'] - time() < 86400): ?><span class="dot-live"></span> Đóng sau <span class="countdown" data-countdown="<?= (int) $s['end_at'] ?>" data-reload="1">…</span>
          <?php elseif ($s['end_at']): ?><span class="dot-live"></span> Mở đến <?= e(fmt_dt($s['end_at'], 'H:i d/m')) ?>
          <?php else: ?><span class="dot-live"></span> Đang mở<?php endif; ?>
        </div>
        <?php if ($doing): ?>
          <a class="btn btn-primary" href="<?= e(url('exam/room', ['aid' => $doing['id']])) ?>"><?= icon('pencil-line') ?> Tiếp tục làm bài</a>
        <?php else: ?>
          <a class="btn btn-primary" href="<?= e(url('student/lobby', ['sid' => $s['id']])) ?>"><?= icon('log-in') ?> Vào phòng thi</a>
        <?php endif; ?>
      <?php elseif ($group === 'practice'):
          $off = $s['_official'];
          $max = $off ? (float) (json_dec($off['score_detail'], [])['max'] ?? 10) : 10; ?>
        <div class="tile-state">
          <?php if ($off && $off['score'] !== null): ?><?= icon('trophy') ?> Cao nhất: <span class="score-pill <?= score_class($off['score'], $max ?: 10) ?>"><?= e(fmt_score($off['score'])) ?></span>
          <?php elseif ($state === 'upcoming'): ?><?= icon('hourglass') ?> Mở sau <span class="countdown" data-countdown="<?= (int) $s['start_at'] ?>" data-reload="1">…</span>
          <?php elseif (in_array($state, ['ended', 'closed'], true)): ?><?= icon('circle-stop') ?> Đã đóng
          <?php else: ?><?= icon('sparkles') ?> Chưa làm lần nào<?php endif; ?>
        </div>
        <div class="row" style="gap:6px">
          <?php if ($off): ?><a class="btn btn-sm btn-ghost" href="<?= e(url('student/result', ['aid' => $off['id']])) ?>" title="Kết quả"><?= icon('clipboard-check') ?></a><?php endif; ?>
          <?php if ($doing): ?>
            <a class="btn btn-sm btn-primary" href="<?= e(url('exam/room', ['aid' => $doing['id']])) ?>"><?= icon('pencil-line') ?> Tiếp tục</a>
          <?php elseif ($s['_can_start'] && !$s['access_code']): ?>
            <form method="post" action="<?= e(url('student/start')) ?>"><?= csrf_field() ?><input type="hidden" name="sid" value="<?= (int) $s['id'] ?>"><button class="btn btn-sm btn-primary" type="submit"><?= icon($off ? 'rotate-ccw' : 'circle-play') ?> <?= $off ? 'Làm lại' : 'Bắt đầu' ?></button></form>
          <?php elseif ($s['_can_start']): ?>
            <a class="btn btn-sm btn-primary" href="<?= e(url('student/lobby', ['sid' => $s['id']])) ?>"><?= icon('log-in') ?> Vào làm</a>
          <?php endif; ?>
        </div>
      <?php else:
          $off = $s['_official'];
          $show = $off && Sessions::canSeeScore($s, $off);
          $max = $off ? (float) (json_dec($off['score_detail'], [])['max'] ?? 10) : 10; ?>
        <div class="tile-state">
          <?php if (!$off): ?><?= icon('circle-slash') ?> Em không làm bài này
          <?php elseif ($show && $off['score'] !== null): ?>Điểm: <span class="score-pill <?= score_class($off['score'], $max ?: 10) ?>"><?= e(fmt_score($off['score'])) ?></span>
          <?php elseif ($off['grading_status'] === 'pending' && $show): ?><?= icon('pen-line') ?> Chờ chấm tự luận
          <?php else: ?><?= icon('circle-check') ?> Đã nộp bài · <?= e(fmt_dt($off['submitted_at'], 'H:i d/m')) ?><?php endif; ?>
        </div>
        <?php if ($off): ?><a class="btn btn-sm" href="<?= e(url('student/result', ['aid' => $off['id']])) ?>"><?= icon('clipboard-check') ?> Kết quả</a><?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
