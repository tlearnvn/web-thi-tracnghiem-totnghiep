<?php
$toBytes = static function (string $v): int {
    $v = trim($v);
    if ($v === '' || $v === '-1') {
        return -1;
    }
    $n = (int) $v;
    $u = strtolower(substr($v, -1));
    return $u === 'g' ? $n * 1073741824 : ($u === 'm' ? $n * 1048576 : ($u === 'k' ? $n * 1024 : $n));
};
$checks = [];
$checks[] = [version_compare($php['version'], '8.0.0', '>='), 'PHP ' . $php['version'], 'Yêu cầu PHP 8.0 trở lên'];
$mem = $toBytes($php['memory_limit']);
$checks[] = [$mem === -1 || $mem >= 128 * 1048576, 'memory_limit = ' . $php['memory_limit'], 'Khuyến nghị ≥ 128M (xuất Excel lớn, thống kê)'];
$met = (int) $php['max_execution_time'];
$checks[] = [$met === 0 || $met >= 120, 'max_execution_time = ' . $php['max_execution_time'] . ($met === 0 ? ' (không giới hạn)' : ' giây'), 'Hệ thống tự kéo dài khi cần; khuyến nghị ≥ 300 trong .user.ini'];
$checks[] = [$php['timezone'] === 'Asia/Ho_Chi_Minh', 'Múi giờ PHP: ' . $php['timezone'], 'Hệ thống tự đặt Asia/Ho_Chi_Minh (UTC+7)'];
$checks[] = [$storageWritable, 'Thư mục storage/ ' . ($storageWritable ? 'ghi được' : 'KHÔNG ghi được'), 'Cần quyền ghi cho tệp SQLite / cấu hình'];
$checks[] = [$storageFiles <= 12, 'storage/ có ' . $storageFiles . ' tệp', 'Mọi dữ liệu nằm trong CSDL nên số tệp (inode) gần như không tăng'];
if ($configPerm) {
    $checks[] = [($configPerm & 0x0004) === 0, 'storage/config.php quyền ' . substr(sprintf('%o', $configPerm), -4), 'Nên đặt 0640 hoặc 0600 (không cho người khác đọc)'];
}
?>
<div class="page-head">
  <div><h1>Thông tin hệ thống</h1><div class="sub">Kiểm tra môi trường hosting, dung lượng dữ liệu và các công cụ bảo trì.</div></div>
  <div class="actions"><span class="version-pill">v<?= e(TN_VERSION) ?></span></div>
</div>

<div class="grid grid-4 mb-3">
  <div class="stat"><div class="stat-icon success"><?= icon('users') ?></div><div><div class="stat-value"><?= (int) $online ?></div><div class="stat-label">Người đang trực tuyến (5 phút)</div></div></div>
  <div class="stat"><div class="stat-icon"><?= icon('pencil-line') ?></div><div><div class="stat-value"><?= (int) $doing ?></div><div class="stat-label">Học sinh đang làm bài</div></div></div>
  <div class="stat"><div class="stat-icon info"><?= icon('database') ?></div><div><div class="stat-value"><?= e(fmt_bytes($db['size'] ?? 0)) ?></div><div class="stat-label"><?= e($db['version'] ?? '') ?></div></div></div>
  <div class="stat"><div class="stat-icon purple"><?= icon('files') ?></div><div><div class="stat-value"><?= e(fmt_bytes($filesSize)) ?></div><div class="stat-label">Đề thi PDF, logo… (trong CSDL)</div></div></div>
</div>

<div class="grid grid-2">
  <div class="stack" style="gap:20px">
    <div class="card">
      <div class="card-head"><h3><?= icon('shield-check') ?> Kiểm tra môi trường</h3></div>
      <div class="card-body check-list">
        <?php foreach ($checks as $c): ?>
          <div class="check-item <?= $c[0] ? 'ok' : 'warn' ?>"><?= icon($c[0] ? 'circle-check' : 'triangle-alert') ?><span><?= e($c[1]) ?></span><small><?= e($c[2]) ?></small></div>
        <?php endforeach; ?>
        <?php foreach ($exts as $ext => $x): ?>
          <div class="check-item <?= $x[1] ? 'ok' : (in_array($ext, ['pdo_sqlite', 'pdo_mysql', 'intl', 'gd', 'fileinfo', 'opcache'], true) ? 'warn' : 'bad') ?>"><?= icon($x[1] ? 'circle-check' : 'circle-minus') ?><span><?= e($ext) ?></span><small><?= e($x[0]) ?><?= $x[1] ? '' : ' – chưa bật' ?></small></div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h3><?= icon('clock') ?> Thời gian</h3></div>
      <div class="card-body kv-list">
        <div class="kv"><span>Giờ máy chủ (UTC+7)</span><span><?= e(date('H:i:s d/m/Y', $now)) ?></span></div>
        <div class="kv"><span>Giờ máy của bạn</span><span id="client-time">…</span></div>
        <div class="kv"><span>Chênh lệch</span><span id="clock-diff">…</span></div>
        <div class="kv"><span>Cron gần nhất</span><span><?= $cronLast ? e(fmt_dt($cronLast)) . ' (' . e(fmt_ago($cronLast)) . ')' : 'Chưa cài (không bắt buộc)' ?></span></div>
      </div>
      <?php $cronUrl = $baseUrl . 'cron.php?key=' . substr(\App\Core\App::sign('cron'), 0, 24); ?>
      <div class="card-foot text-sm text-muted stack" style="gap:6px;align-items:stretch;justify-content:flex-start">
        <div>Giờ làm bài luôn tính theo máy chủ. <b>Cron là tùy chọn</b>: bài quá giờ vẫn được tự thu khi có người truy cập. Nếu hosting có cron, đặt chạy mỗi 5 phút:</div>
        <div class="copy-box"><span class="grow truncate">php <?= e(BASE_PATH) ?>/cron.php</span><button type="button" class="btn btn-xs btn-ghost" data-copy="php <?= e(BASE_PATH) ?>/cron.php"><?= icon('copy') ?></button></div>
        <div>Hoặc hosting chỉ hỗ trợ gọi URL:</div>
        <div class="copy-box"><span class="grow truncate"><?= e($cronUrl) ?></span><button type="button" class="btn btn-xs btn-ghost" data-copy="<?= e($cronUrl) ?>"><?= icon('copy') ?></button></div>
      </div>
    </div>
  </div>
  <div class="stack" style="gap:20px">
    <div class="card">
      <div class="card-head"><h3><?= icon('server') ?> Máy chủ & PHP</h3></div>
      <div class="card-body kv-list">
        <div class="kv"><span>Địa chỉ hệ thống</span><span class="mono text-sm"><?= e($baseUrl) ?></span></div>
        <div class="kv"><span>Cài đặt lúc</span><span><?= e((string) $installedAt) ?></span></div>
        <div class="kv"><span>Phiên bản ứng dụng</span><span>v<?= e(TN_VERSION) ?> · CSDL phiên bản <?= (int) \App\Core\Schema::VERSION ?></span></div>
        <div class="kv"><span>PHP</span><span><?= e($php['version']) ?> (<?= e($php['sapi']) ?>, <?= e($php['os']) ?>)</span></div>
        <div class="kv"><span>upload_max_filesize / post_max_size</span><span><?= e($php['upload_max_filesize']) ?> / <?= e($php['post_max_size']) ?></span></div>
        <div class="kv"><span>Lưu phiên đăng nhập</span><span><?= e($php['session_save']) ?> · <?= (int) $sessionsCount ?> phiên</span></div>
        <?php if (!empty($db['journal'])): ?><div class="kv"><span>SQLite journal</span><span><?= e($db['journal']) ?></span></div><?php endif; ?>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h3><?= icon('wrench') ?> Bảo trì</h3></div>
      <div class="card-body stack">
        <div class="row between"><div><b>Dọn dẹp</b><div class="help">Xóa lượt tải lên dở dang, tệp không còn dùng, phiên hết hạn, nhật ký quá cũ; thu các bài quá giờ.</div></div><button class="btn" data-post="<?= e(url('system/cleanup')) ?>"><?= icon('eraser') ?> Dọn dẹp</button></div>
        <div class="row between"><div><b>Tối ưu CSDL</b><div class="help"><?= $db['driver'] === 'sqlite' ? 'VACUUM: thu gọn tệp SQLite sau khi xóa nhiều dữ liệu.' : 'OPTIMIZE TABLE cho các bảng.' ?> Chỉ chạy khi không có ca thi.</div></div><button class="btn" data-post="<?= e(url('system/optimize')) ?>" data-confirm="Tối ưu CSDL có thể mất vài phút. Tiếp tục?"><?= icon('gauge') ?> Tối ưu</button></div>
        <?php if (is_array($demo)): ?>
          <div class="row between"><div><b>Xóa dữ liệu mẫu</b><div class="help">Xóa lớp, học sinh, giáo viên, đề và ca thi mẫu tạo lúc cài đặt.</div></div><button class="btn btn-danger-soft" data-post="<?= e(url('system/remove-demo')) ?>" data-confirm="Xóa toàn bộ dữ liệu mẫu (tài khoản hs12a1xx, gv.toan, giamthi, đề Toán mẫu, ca thi mẫu)? Không thể hoàn tác." data-danger><?= icon('trash-2') ?> Xóa dữ liệu mẫu</button></div>
        <?php endif; ?>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h3><?= icon('table') ?> Số bản ghi</h3></div>
      <div class="table-wrap" style="max-height:360px"><table class="table table-sm">
        <tbody><?php foreach ($tables as $t => $n): ?><tr><td class="mono text-sm"><?= e($t) ?></td><td class="text-right num"><?= $n === null ? '<span class="text-danger">lỗi</span>' : e(fmt_num($n, 0)) ?></td></tr><?php endforeach; ?></tbody>
      </table></div>
    </div>
  </div>
</div>
<?php \App\Core\View::push('scripts', '<script>TN.ready(function () {
  document.getElementById("client-time").textContent = new Date().toLocaleString("vi-VN");
  var off = -Math.round(TN.serverOffset / 1000);
  document.getElementById("clock-diff").textContent = Math.abs(off) < 2 ? "Khớp giờ máy chủ" : (off > 0 ? "Máy bạn nhanh hơn " : "Máy bạn chậm hơn ") + Math.abs(off) + " giây (không ảnh hưởng bài thi)";
});</script>'); ?>
