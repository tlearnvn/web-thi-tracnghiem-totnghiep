<?php
/**
 * Tác vụ định kỳ (KHÔNG bắt buộc – hệ thống vẫn tự thu bài quá giờ khi có người truy cập).
 *
 *   Dòng lệnh (khuyến nghị, mỗi 5 phút):   php /đường/dẫn/cron.php
 *   Hoặc gọi qua URL (hosting chỉ có "URL cron"): https://ten-mien/cron.php?key=MÃ_CRON
 *   (MÃ_CRON xem ở trang Thông tin hệ thống)
 *
 * Việc thực hiện: thu các bài đã hết giờ, dọn phiên đăng nhập hết hạn, dọn lượt tải lên dở dang, nhật ký cũ.
 */

require __DIR__ . '/app/bootstrap.php';

use App\Core\App;
use App\Core\DbSessionHandler;
use App\Core\FileStore;
use App\Core\Logger;
use App\Core\Schema;
use App\Core\Session;
use App\Core\Settings;
use App\Lib\Attempts;

$cli = PHP_SAPI === 'cli';
if (!App::loadConfig()) {
    if (!$cli) {
        http_response_code(503);
    }
    echo "Hệ thống chưa được cài đặt.\n";
    exit(1);
}
if (!$cli) {
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    $key = (string) ($_GET['key'] ?? '');
    if (!hash_equals(substr(App::sign('cron'), 0, 24), $key)) {
        http_response_code(403);
        echo "Sai mã cron.\n";
        exit(1);
    }
}
@set_time_limit(600);
$t0 = microtime(true);
$db = App::db();
Schema::ensureUpToDate($db);
$out = [];
try {
    $out[] = 'Thu bài quá giờ: ' . Attempts::finalizeExpired();
    $out[] = 'Phiên hết hạn đã xóa: ' . DbSessionHandler::cleanup($db, Session::lifetime());
    FileStore::purgeStale();
    $db->run('DELETE FROM {restore_chunks} WHERE created_at < ?', [time() - 86400]);
    // Dọn nhật ký cũ khoảng mỗi ngày một lần
    if ((int) Settings::get('cron_last_prune', 0) < time() - 86400) {
        Logger::prune();
        Settings::set('cron_last_prune', time());
        $out[] = 'Đã dọn nhật ký cũ';
    }
    Settings::set('cron_last_run', time());
} catch (\Throwable $e) {
    Logger::error($e);
    echo 'Lỗi: ' . $e->getMessage() . "\n";
    exit(1);
}
echo '[' . date('d/m/Y H:i:s') . ' UTC+7] ' . implode(' · ', $out) . ' · ' . round((microtime(true) - $t0) * 1000) . " ms\n";
