<?php
/**
 * Khởi động ứng dụng: hằng số, cấu hình PHP cho shared hosting, múi giờ UTC+7, autoload.
 */

if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    exit('Hệ thống cần PHP 8.0 trở lên (máy chủ đang chạy PHP ' . PHP_VERSION . ').');
}

define('TN_START', microtime(true));
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('CONFIG_FILE', STORAGE_PATH . '/config.php');
define('TN_VERSION', (static function (): string {
    $v = @file_get_contents(BASE_PATH . '/VERSION');
    return $v !== false && trim($v) !== '' ? trim($v) : '1.0.0';
})());
define('TN_TZ', 'Asia/Ho_Chi_Minh');

// ---- Cấu hình PHP phù hợp shared hosting ----
// Thời gian chạy dài để các thao tác nhập/xuất dữ liệu, nộp bài không bị ngắt giữa chừng.
@ini_set('max_execution_time', '300');
if (function_exists('set_time_limit')) {
    @set_time_limit(300);
}
@ini_set('default_socket_timeout', '120');
@ini_set('display_errors', '0');
@ini_set('log_errors', '0');          // lỗi được ghi vào CSDL, không sinh file error_log
@ini_set('html_errors', '0');
@ini_set('session.use_strict_mode', '1');
@ini_set('session.use_only_cookies', '1');
@ini_set('session.use_trans_sid', '0');
@ini_set('session.cookie_httponly', '1');
@ini_set('session.gc_maxlifetime', '43200');
@ini_set('session.gc_probability', '0'); // tự dọn session trong CSDL (xem DbSessionHandler)
@ini_set('zlib.output_compression', '0');

(static function (): void {
    $cur = ini_get('memory_limit');
    if ($cur === false || $cur === '-1') {
        return;
    }
    $n = (int) $cur;
    $u = strtolower(substr(trim($cur), -1));
    $bytes = $u === 'g' ? $n * 1073741824 : ($u === 'm' ? $n * 1048576 : ($u === 'k' ? $n * 1024 : $n));
    if ($bytes > 0 && $bytes < 268435456) {
        @ini_set('memory_limit', '256M');
    }
})();

date_default_timezone_set(TN_TZ);
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}
error_reporting(E_ALL);

spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'App\\', 4) !== 0) {
        return;
    }
    $file = APP_PATH . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require APP_PATH . '/helpers.php';
