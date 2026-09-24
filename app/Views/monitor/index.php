<?php
use App\Lib\Sessions;

$cfg = [
    'sid' => (int) $s['id'],
    'dataUrl' => url('monitor/data', ['id' => $s['id']]),
    'actUrl' => url('monitor/act', ['id' => $s['id']]),
    'ctlUrl' => url('sessions/control', ['id' => $s['id']]),
    'attemptUrl' => url('results/attempt'),
    'canAct' => (bool) $canAct,
    'canManage' => (bool) $canManage,
    'canResults' => (bool) $canResults,
    'total' => (int) $total,
    'maxViol' => (int) $o['max_violations'],
    'deviceLock' => (int) $o['device_lock'],
];
\App\Core\View::push('scripts', '<script src="' . asset('js/monitor.js') . '"></script>');
?>
<div class="page-head">
  <div>
    <div class="eyebrow"><span class="dot-live" id="live-dot"></span> Giám sát trực tiếp · <span id="upd-at">đang tải…</span></div>
    <h1><?= e($s['name']) ?></h1>
    <div class="sub row gap-sm"><span id="state-badge"><?= Sessions::stateBadge($s) ?></span><span id="end-info"></span></div>
  </div>
  <div class="actions">
    <a class="btn" href="<?= e(url('monitor/board', ['id' => $s['id']])) ?>" target="_blank" rel="noopener"><?= icon('monitor') ?> Màn hình trình chiếu</a>
    <?php if ($canResults): ?><a class="btn" href="<?= e(url('results/session', ['id' => $s['id']])) ?>"><?= icon('clipboard-check') ?> Kết quả</a><?php endif; ?>
  </div>
</div>

<?php if ($canAct): ?>
<div class="card card-accent mb-3">
  <div class="card-body row" style="gap:10px" id="session-ctl">
    <button class="btn btn-success" data-ctl="start_now" data-confirm="Mở ca thi ngay bây giờ?" hidden><?= icon('circle-play') ?> Bắt đầu ngay</button>
    <button class="btn btn-warning" data-ctl="pause" data-confirm="Tạm dừng CẢ PHÒNG? Thời gian làm bài của mọi học sinh được giữ nguyên (dùng khi mất điện, sự cố mạng…)." hidden><?= icon('circle-pause') ?> Tạm dừng cả phòng</button>
    <button class="btn btn-success" data-ctl="resume" hidden><?= icon('circle-play') ?> Tiếp tục ca thi</button>
    <button class="btn" data-ctl="add_time"><?= icon('timer') ?> Cộng giờ cả phòng</button>
    <button class="btn" data-ctl="message"><?= icon('megaphone') ?> Thông báo cả phòng</button>
    <span class="grow"></span>
    <?php if ($s['access_code']): ?><span class="copy-box" title="Mã vào phòng thi"><?= icon('key-round', 'sm') ?> <b style="letter-spacing:.18em;font-size:16px"><?= e($s['access_code']) ?></b></span><?php endif; ?>
    <button class="btn btn-danger-soft" data-ctl="close" data-confirm="KẾT THÚC ca thi và thu tất cả bài đang làm ngay bây giờ?"><?= icon('circle-stop') ?> Kết thúc & thu bài</button>
  </div>
</div>
<?php endif; ?>

<div class="mini-stats mb-3" id="counters">
  <div class="mini-stat"><div class="v" data-c="total">–</div><div class="l">Học sinh dự thi</div></div>
  <div class="mini-stat"><div class="v text-primary" data-c="online">–</div><div class="l"><span class="dot-live"></span> Đang làm, có kết nối</div></div>
  <div class="mini-stat"><div class="v text-warning" data-c="offline">–</div><div class="l"><span class="dot-warn"></span> Mất kết nối</div></div>
  <div class="mini-stat"><div class="v" data-c="none">–</div><div class="l">Chưa vào thi</div></div>
  <div class="mini-stat"><div class="v text-success" data-c="done">–</div><div class="l">Đã nộp bài</div></div>
  <div class="mini-stat"><div class="v text-danger" data-c="violations">–</div><div class="l">Có rời màn hình</div></div>
</div>

<div id="alerts"></div>

<div class="monitor-grid">
  <div class="card">
    <div class="table-toolbar">
      <div class="seg" id="f-status">
        <button type="button" class="active" data-f="">Tất cả</button>
        <button type="button" data-f="doing">Đang làm</button>
        <button type="button" data-f="offline">Mất kết nối</button>
        <button type="button" data-f="none">Chưa vào</button>
        <button type="button" data-f="done">Đã nộp</button>
        <button type="button" data-f="issue">Cần xử lý</button>
      </div>
      <?php if (count($classes) > 1): ?>
        <select class="select" id="f-class" style="width:auto"><option value="">Tất cả lớp</option><?php foreach ($classes as $c): ?><option value="<?= e($c['name']) ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select>
      <?php endif; ?>
      <div class="input-icon search"><?= icon('search') ?><input class="input" type="search" id="f-q" placeholder="Tìm tên, SBD…"></div>
    </div>
    <?php if ($canAct): ?>
    <div class="bulk-bar" id="bulk">
      <span><b data-count>0</b> học sinh được chọn</span>
      <button class="btn btn-sm" data-bulk="add_time"><?= icon('timer') ?> Cộng giờ</button>
      <button class="btn btn-sm" data-bulk="message"><?= icon('message-square') ?> Nhắn tin</button>
      <button class="btn btn-sm" data-bulk="pause"><?= icon('circle-pause') ?> Tạm dừng</button>
      <button class="btn btn-sm" data-bulk="resume"><?= icon('circle-play') ?> Tiếp tục</button>
      <button class="btn btn-sm btn-danger-soft" data-bulk="force_submit"><?= icon('hourglass') ?> Thu bài</button>
    </div>
    <?php endif; ?>
    <div class="table-wrap" style="max-height:calc(100vh - 250px)"><table class="table table-monitor">
      <thead><tr>
        <?php if ($canAct): ?><th style="width:34px"><input type="checkbox" id="chk-all" aria-label="Chọn tất cả"></th><?php endif; ?>
        <th>Học sinh</th><th>Trạng thái</th><th style="min-width:130px">Tiến độ</th><th class="text-right">Còn lại</th><th class="text-center" title="Số lần rời màn hình">Rời MH</th><th class="hide-sm">Thiết bị</th><?php if ($canResults): ?><th class="text-right">Điểm</th><?php endif; ?><th class="col-actions"></th>
      </tr></thead>
      <tbody id="rows"><tr><td colspan="<?= 7 + ($canAct ? 1 : 0) + ($canResults ? 1 : 0) ?>"><div class="empty" style="padding:30px"><span class="spinner"></span></div></td></tr></tbody>
    </table></div>
  </div>
  <div class="stack" style="gap:20px">
    <div class="card">
      <div class="card-head"><h3><?= icon('activity') ?> Diễn biến</h3><span class="hint" id="ev-count"></span></div>
      <div class="card-body" style="max-height:52vh;overflow:auto"><div class="timeline" id="events"><div class="text-muted text-sm">Chưa có sự kiện.</div></div></div>
    </div>
    <div class="card">
      <div class="card-head"><h3><?= icon('megaphone') ?> Thông báo đã gửi</h3></div>
      <div class="card-body" style="max-height:30vh;overflow:auto" id="msgs"><div class="text-muted text-sm">Chưa gửi thông báo nào.</div></div>
    </div>
    <div class="callout text-sm">
      <b><?= icon('lightbulb', 'sm') ?> Xử lý sự cố nhanh</b>
      <ul style="margin:6px 0 0;padding-left:18px;line-height:1.7">
        <li><b>Máy hỏng / mất điện:</b> cho học sinh sang máy khác đăng nhập, bấm <i>Mở khóa thiết bị</i>.</li>
        <li><b>Mất mạng cả phòng:</b> bấm <i>Tạm dừng cả phòng</i>, khi có mạng bấm <i>Tiếp tục</i>.</li>
        <li><b>Nộp nhầm:</b> dùng <i>Mở lại bài</i> (có thể cộng thêm phút).</li>
        <li><b>Bị khóa do vi phạm:</b> xác minh rồi bấm <i>Mở khóa vi phạm</i>.</li>
      </ul>
    </div>
  </div>
</div>
<script>window.MON_CFG = <?= js_json($cfg) ?>;</script>
