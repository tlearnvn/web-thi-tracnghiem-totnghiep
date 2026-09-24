<?php
use App\Lib\ExamFormat;
use App\Lib\Sessions;

$state = Sessions::state($s);
$acc = $s['_access'];
$tg = max(1, count($students));
$ctl = static fn(string $action, string $label, string $icon, string $cls = 'btn', string $confirm = '', array $extra = []) =>
    '<button class="' . $cls . '" data-post="' . e(url('sessions/control', ['id' => $s['id']])) . '" data-fields=\'' . e(json_encode(['action' => $action] + $extra)) . '\'' . ($confirm !== '' ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . icon($icon) . ' ' . e($label) . '</button>';
?>
<div class="page-head">
  <div>
    <div class="eyebrow"><?= e($subject['name'] ?? '') ?> · <?= e(Sessions::MODES[$s['mode']]) ?></div>
    <h1><?= e($s['name']) ?></h1>
    <div class="sub row gap-sm"><?= Sessions::stateBadge($s) ?><?= (int) $s['released'] ? badge('Đã công bố điểm', 'success', 'badge-check') : '' ?><span>Đề: <?= $examLink ? '<a href="' . e(url('exams/view', ['id' => $exam['id']])) . '">' . e($exam['title']) . '</a>' : e($exam['title']) ?></span></div>
  </div>
  <div class="actions">
    <?php if ($acc['proctor'] && in_array($state, ['running', 'paused', 'upcoming'], true)): ?><a class="btn btn-primary" href="<?= e(url('monitor', ['id' => $s['id']])) ?>"><?= icon('monitor') ?> Giám sát trực tiếp</a><?php endif; ?>
    <?php if ($acc['results']): ?><a class="btn" href="<?= e(url('results/session', ['id' => $s['id']])) ?>"><?= icon('clipboard-check') ?> Kết quả</a><?php endif; ?>
    <?php if ($acc['manage']): ?>
    <div class="dropdown">
      <button class="btn" data-dropdown><?= icon('ellipsis') ?></button>
      <div class="dropdown-menu">
        <a href="<?= e(url('sessions/edit', ['id' => $s['id']])) ?>"><?= icon('square-pen') ?> Sửa ca thi</a>
        <a href="<?= e(url('sessions/create', ['exam_id' => $exam['id']])) ?>"><?= icon('copy') ?> Tạo ca khác cùng đề</a>
        <hr>
        <button type="button" class="danger" data-post="<?= e(url('sessions/delete', ['id' => $s['id']])) ?>" data-fields='{"force":1}' data-confirm="Xóa ca thi này cùng TOÀN BỘ bài làm của học sinh? Không thể hoàn tác." data-danger><?= icon('trash-2') ?> Xóa ca thi</button>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="grid grid-4">
  <div class="stat"><div class="stat-icon"><?= icon('users') ?></div><div><div class="stat-value"><?= count($students) ?></div><div class="stat-label">Học sinh dự thi</div></div></div>
  <div class="stat"><div class="stat-icon info"><?= icon('activity') ?></div><div><div class="stat-value"><?= (int) $stats['doing'] ?></div><div class="stat-label">Đang làm bài</div></div></div>
  <div class="stat"><div class="stat-icon success"><?= icon('circle-check') ?></div><div><div class="stat-value"><?= (int) $stats['done'] ?></div><div class="stat-label">Bài đã nộp</div></div></div>
  <?php if ($acc['results']): ?>
  <div class="stat"><div class="stat-icon warning"><?= icon('award') ?></div><div><div class="stat-value"><?= $stats['avg_score'] !== null ? e(fmt_num($stats['avg_score'], 2)) : '–' ?></div><div class="stat-label">Điểm trung bình</div></div></div>
  <?php else: ?>
  <div class="stat"><div class="stat-icon warning"><?= icon('user-x') ?></div><div><div class="stat-value"><?= max(0, count($students) - (int) $stats['started']) ?></div><div class="stat-label">Chưa vào thi</div></div></div>
  <?php endif; ?>
</div>

<div class="grid grid-sidebar mt-3">
  <div class="stack" style="gap:20px">
    <?php if ($acc['proctor']): ?>
    <div class="card card-accent">
      <div class="card-head"><h3><?= icon('sliders-horizontal') ?> Điều khiển ca thi</h3><span class="hint">Áp dụng cho tất cả học sinh</span></div>
      <div class="card-body row" style="gap:10px">
        <?php if ($state === 'upcoming'): ?><?= $ctl('start_now', 'Bắt đầu ngay', 'circle-play', 'btn btn-success', 'Mở ca thi ngay bây giờ?') ?><?php endif; ?>
        <?php if ($state === 'running'): ?><?= $ctl('pause', 'Tạm dừng cả phòng', 'circle-pause', 'btn btn-warning', 'Tạm dừng ca thi? Thời gian làm bài của mọi học sinh sẽ được giữ nguyên (dùng khi mất điện, sự cố mạng…).') ?><?php endif; ?>
        <?php if ($state === 'paused'): ?><?= $ctl('resume', 'Tiếp tục ca thi', 'circle-play', 'btn btn-success') ?><?php endif; ?>
        <?php if (in_array($state, ['running', 'paused'], true)): ?>
          <button class="btn" type="button" id="add-time-all"><?= icon('timer') ?> Cộng giờ cả phòng</button>
          <button class="btn" type="button" id="broadcast"><?= icon('megaphone') ?> Gửi thông báo</button>
          <?= $ctl('close', 'Kết thúc & thu bài', 'circle-stop', 'btn btn-danger-soft', 'Kết thúc ca thi và THU TẤT CẢ bài đang làm ngay bây giờ?') ?>
        <?php endif; ?>
        <?php if (in_array($state, ['ended', 'closed'], true) && $acc['manage']): ?><?= $ctl('reopen', 'Mở lại ca thi', 'lock-open', 'btn', 'Mở lại ca thi (không giới hạn giờ kết thúc)?') ?><?php endif; ?>
        <?php if ($acc['manage'] || can('results.grade')): ?>
          <?= (int) $s['released'] ? $ctl('unrelease', 'Ẩn điểm', 'eye-off', 'btn') : $ctl('release', 'Công bố điểm', 'badge-check', 'btn btn-soft', 'Cho học sinh xem điểm (và xem lại bài nếu cài đặt cho phép)?') ?>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-head"><h3><?= icon('info') ?> Thông tin ca thi</h3></div>
      <div class="card-body">
        <dl class="dl">
          <dt>Thời gian mở</dt><dd><?= $s['start_at'] ? e(fmt_dt($s['start_at'], 'H:i – d/m/Y')) : 'Mở ngay' ?><?= $state === 'upcoming' ? ' · còn <span class="countdown" data-countdown="' . (int) $s['start_at'] . '" data-reload="1"></span>' : '' ?></dd>
          <dt>Kết thúc</dt><dd><?= $s['end_at'] ? e(fmt_dt($s['end_at'], 'H:i – d/m/Y')) : 'Không giới hạn' ?></dd>
          <dt>Thời gian làm bài</dt><dd><?php $d = Sessions::durationSec($s, $exam); echo $d ? e(fmt_duration($d)) : 'Không giới hạn'; ?> · <?= e(Sessions::TIME_POLICIES[$o['time_policy']]) ?></dd>
          <dt>Cấu trúc</dt><dd><?= e(ExamFormat::describe($exam['_structure'])) ?></dd>
          <dt>Chia mã đề</dt><dd><?= e(Sessions::VARIANT_MODES[$s['variant_mode']] ?? '') ?> · <?php foreach ($variants as $v) { echo '<span class="badge mono">' . e($v['code']) . ' (' . (int) $v['n'] . ')</span> '; } ?></dd>
          <dt>Lượt làm</dt><dd><?= (int) $s['max_attempts'] ? (int) $s['max_attempts'] . ' lượt' : 'Không giới hạn' ?><?= (int) $s['late_join'] ? ' · vào muộn tối đa ' . (int) $s['late_join'] . ' phút' : '' ?></dd>
          <dt>Mã vào phòng</dt><dd><?= $s['access_code'] ? '<span class="copy-box" style="display:inline-flex;font-size:18px;font-weight:800;letter-spacing:.2em">' . e($s['access_code']) . '</span>' : 'Không yêu cầu' ?></dd>
          <dt>Phòng thi</dt><dd><?= e($s['room'] ?: '—') ?></dd>
          <dt>Xem điểm</dt><dd><?= e(Sessions::SCORE_POLICIES[$o['show_score']]) ?></dd>
          <dt>Xem lại bài</dt><dd><?= e(Sessions::REVIEW_POLICIES[$o['allow_review']]) ?><?= $o['show_explanations'] ? ' · có lời giải' : '' ?></dd>
          <dt>Chống gian lận</dt><dd class="row gap-sm"><?= $o['device_lock'] ? badge('Khóa thiết bị', 'primary') : '' ?><?= $o['track_focus'] ? badge('Ghi nhận rời màn hình', 'primary') : '' ?><?= $o['require_fullscreen'] ? badge('Toàn màn hình', 'primary') : '' ?><?= $o['watermark'] ? badge('Hình mờ', 'primary') : '' ?><?= $o['max_violations'] ? badge('Tối đa ' . $o['max_violations'] . ' vi phạm → ' . Sessions::VIOLATION_ACTIONS[$o['violation_action']], 'warning') : '' ?></dd>
          <dt>Người tạo</dt><dd><?= e($owner ?? '—') ?></dd>
        </dl>
      </div>
    </div>
  </div>
  <div class="stack" style="gap:20px">
    <div class="card">
      <div class="card-head"><h3><?= icon('school') ?> Lớp dự thi</h3></div>
      <div class="card-body"><?php foreach ($classes as $c): ?><a class="badge badge-primary badge-lg" style="margin:0 6px 6px 0" href="<?= e(url('classes/view', ['id' => $c['id']])) ?>"><?= e($c['name']) ?></a><?php endforeach; ?><?= !$classes ? '<span class="text-muted">Chỉ học sinh được chọn riêng.</span>' : '' ?>
        <div class="text-sm text-muted mt-2"><?= count($students) ?> học sinh · tiến độ <?= round(((int) $stats['done']) * 100 / $tg) ?>%</div>
        <div class="progress mt-1"><span style="width:<?= min(100, round(((int) $stats['done']) * 100 / $tg)) ?>%"></span></div>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h3><?= icon('user-check') ?> Giám thị</h3></div>
      <div class="card-body"><?php if (!$proctors): ?><span class="text-muted">Chưa phân công (người tạo ca thi có quyền giám sát).</span><?php endif; ?><ul class="list-plain"><?php foreach ($proctors as $p): ?><li><?= e($p['full_name']) ?></li><?php endforeach; ?></ul></div>
    </div>
    <div class="card">
      <div class="card-head"><h3><?= icon('lightbulb') ?> Hướng dẫn học sinh</h3></div>
      <div class="card-body text-sm">
        <ol style="padding-left:18px;margin:0">
          <li>Truy cập <code><?= e(absolute_url('login')) ?></code></li>
          <li>Đăng nhập bằng tài khoản được cấp</li>
          <li>Chọn ca thi <b><?= e($s['name']) ?></b> → <b>Vào phòng thi</b><?= $s['access_code'] ? ' → nhập mã phòng' : '' ?></li>
        </ol>
      </div>
    </div>
  </div>
</div>
<?php \App\Core\View::push('scripts', '<script>window.SESSION_CTL=' . js_json(url('sessions/control', ['id' => $s['id']])) . ';</script>'); ?>
<?php \App\Core\View::push('scripts', <<<'JS'
<script>
TN.ready(function () {
  var a = document.getElementById('add-time-all');
  if (a) a.onclick = function () {
    TN.confirm({ title: 'Cộng giờ cả phòng', message: 'Số phút cộng thêm cho tất cả học sinh đang làm bài:', input: { type: 'number', value: '5' }, ok: 'Cộng giờ' }).then(function (v) {
      if (v) TN.post(window.SESSION_CTL, { action: 'add_time', minutes: v });
    });
  };
  var b = document.getElementById('broadcast');
  if (b) b.onclick = function () {
    TN.confirm({ title: 'Gửi thông báo tới cả phòng thi', message: 'Thông báo hiện ngay trên màn hình làm bài của tất cả học sinh.', input: { placeholder: 'VD: Câu 5 Phần I có lỗi in, các em bỏ qua.' }, ok: 'Gửi' }).then(function (v) {
      if (v) TN.post(window.SESSION_CTL, { action: 'message', message: v });
    });
  };
});
</script>
JS); ?>
