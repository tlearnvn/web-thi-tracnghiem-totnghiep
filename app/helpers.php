<?php
/**
 * Các hàm tiện ích dùng chung trong view và controller.
 */

use App\Core\App;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Settings;
use App\Lib\Icons;

function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function now(): int
{
    return time();
}

/** Đường dẫn gốc (thư mục chứa index.php), luôn kết thúc bằng "/". */
function base_uri(): string
{
    static $base = null;
    if ($base === null) {
        $cfg = App::config('base_url', '');
        if (is_string($cfg) && $cfg !== '') {
            $base = rtrim($cfg, '/') . '/';
        } else {
            $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
            $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
            $base = ($dir === '' || $dir === '.') ? '/' : $dir . '/';
        }
    }
    return $base;
}

function url(string $route = '', array $params = []): string
{
    $q = [];
    if ($route !== '') {
        $q['r'] = $route;
    }
    foreach ($params as $k => $v) {
        if ($v === null || $v === '') {
            continue;
        }
        $q[$k] = $v;
    }
    return base_uri() . 'index.php' . ($q ? '?' . http_build_query($q) : '');
}

/** URL tuyệt đối (dùng cho QR, phiếu tài khoản...). */
function absolute_url(string $route = '', array $params = []): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https://' : 'http://') . $host . url($route, $params);
}

function asset(string $path): string
{
    return base_uri() . 'assets/' . ltrim($path, '/') . '?v=' . rawurlencode(TN_VERSION);
}

function icon(string $name, string $class = ''): string
{
    return Icons::svg($name, $class);
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

function setting(string $key, $default = null)
{
    return Settings::get($key, $default);
}

function auth(): ?array
{
    return Auth::user();
}

function can(string ...$perms): bool
{
    foreach ($perms as $p) {
        if (Auth::can($p)) {
            return true;
        }
    }
    return false;
}

/** Định dạng thời gian theo UTC+7. */
function fmt_dt($ts, string $format = 'd/m/Y H:i'): string
{
    if ($ts === null || $ts === '' || (int) $ts <= 0) {
        return '';
    }
    return date($format, (int) $ts);
}

function fmt_date(?string $ymd): string
{
    if (!$ymd) {
        return '';
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $ymd, $m)) {
        return $m[3] . '/' . $m[2] . '/' . $m[1];
    }
    return $ymd;
}

/** Thời lượng dạng "1 giờ 5 phút" / "45 phút" / "30 giây". */
function fmt_duration(int $seconds): string
{
    if ($seconds < 60) {
        return max(0, $seconds) . ' giây';
    }
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    $out = [];
    if ($h > 0) {
        $out[] = $h . ' giờ';
    }
    if ($m > 0) {
        $out[] = $m . ' phút';
    }
    return implode(' ', $out);
}

/** "5 phút trước" */
function fmt_ago($ts): string
{
    if (!$ts) {
        return '';
    }
    $d = time() - (int) $ts;
    if ($d < 10) {
        return 'vừa xong';
    }
    if ($d < 60) {
        return $d . ' giây trước';
    }
    if ($d < 3600) {
        return intdiv($d, 60) . ' phút trước';
    }
    if ($d < 86400) {
        return intdiv($d, 3600) . ' giờ trước';
    }
    if ($d < 86400 * 30) {
        return intdiv($d, 86400) . ' ngày trước';
    }
    return fmt_dt($ts, 'd/m/Y');
}

/** Số kiểu Việt Nam: 8,75 – bỏ số 0 thừa ở phần thập phân khi $trim = true. */
function fmt_num($n, int $dec = 2, bool $trim = true): string
{
    if ($n === null || $n === '') {
        return '';
    }
    $s = number_format((float) $n, $dec, ',', '.');
    if ($trim && $dec > 0) {
        $s = rtrim(rtrim($s, '0'), ',');
    }
    return $s;
}

function fmt_score($n): string
{
    if ($n === null || $n === '') {
        return '–';
    }
    return number_format((float) $n, 2, ',', '.');
}

function fmt_bytes($bytes): string
{
    $b = (float) $bytes;
    $u = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($b >= 1024 && $i < 3) {
        $b /= 1024;
        $i++;
    }
    return fmt_num($b, $i === 0 ? 0 : 1) . ' ' . $u[$i];
}

function fmt_percent($ratio, int $dec = 1): string
{
    return fmt_num((float) $ratio * 100, $dec) . '%';
}

function json_enc($value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: 'null';
}

function json_dec($json, $default = [])
{
    if ($json === null || $json === '') {
        return $default;
    }
    if (is_array($json)) {
        return $json;
    }
    $v = json_decode((string) $json, true);
    return $v === null ? $default : $v;
}

/** JSON an toàn để nhúng vào <script>. */
function js_json($value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: 'null';
}

function old(string $key, $default = '')
{
    $old = $_SESSION['_old'] ?? [];
    return array_key_exists($key, $old) ? $old[$key] : $default;
}

function selected($a, $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function checked($cond): string
{
    return $cond ? ' checked' : '';
}

function str_limit(?string $s, int $len = 80): string
{
    $s = (string) $s;
    if (mb_strlen($s) <= $len) {
        return $s;
    }
    return rtrim(mb_substr($s, 0, $len - 1)) . '…';
}

/** Chữ cái đầu của tên (hiển thị avatar). */
function initials(?string $name): string
{
    $name = trim((string) $name);
    if ($name === '') {
        return '?';
    }
    $parts = preg_split('/\s+/u', $name);
    $last = end($parts);
    $first = count($parts) > 1 ? $parts[count($parts) - 2] : '';
    $out = mb_strtoupper(mb_substr($last, 0, 1));
    if ($first !== '') {
        $out = mb_strtoupper(mb_substr($first, 0, 1)) . $out;
    }
    return $out;
}

/** Màu ổn định theo chuỗi (avatar). */
function color_for(string $s): string
{
    $palette = ['#2563eb', '#7c3aed', '#db2777', '#ea580c', '#059669', '#0891b2', '#4f46e5', '#b45309', '#be123c', '#0d9488'];
    return $palette[abs(crc32($s)) % count($palette)];
}

function badge(string $text, string $type = 'default', string $icon = ''): string
{
    return '<span class="badge badge-' . e($type) . '">' . ($icon !== '' ? icon($icon) : '') . e($text) . '</span>';
}

/** Chuỗi truy vấn hiện tại với các tham số được thay thế. */
function query_with(array $replace): string
{
    $q = array_merge($_GET, $replace);
    foreach ($q as $k => $v) {
        if ($v === null || $v === '') {
            unset($q[$k]);
        }
    }
    return base_uri() . 'index.php?' . http_build_query($q);
}

/** Chuyển "Y-m-d\TH:i" (giờ Việt Nam) sang timestamp. */
function parse_local_datetime(?string $s): ?int
{
    $s = trim((string) $s);
    if ($s === '') {
        return null;
    }
    $s = str_replace('T', ' ', $s);
    foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'd/m/Y H:i', 'd/m/Y H:i:s', 'd/m/Y', 'Y-m-d'] as $f) {
        $d = DateTime::createFromFormat('!' . $f, $s, new DateTimeZone(TN_TZ));
        if ($d !== false) {
            return $d->getTimestamp();
        }
    }
    $t = strtotime($s);
    return $t === false ? null : $t;
}

function input_datetime($ts): string
{
    return $ts ? date('Y-m-d\TH:i', (int) $ts) : '';
}

function random_token(int $bytes = 16): string
{
    return bin2hex(random_bytes($bytes));
}

function client_ip(): string
{
    $hdr = App::config('trusted_proxy_header', '');
    if (is_string($hdr) && $hdr !== '' && !empty($_SERVER[$hdr])) {
        $ip = trim(explode(',', (string) $_SERVER[$hdr])[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function user_agent(): string
{
    return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

/** Tên ngắn gọn của trình duyệt / hệ điều hành từ User-Agent. */
function describe_ua(?string $ua): string
{
    $ua = (string) $ua;
    if ($ua === '') {
        return '';
    }
    $browser = 'Trình duyệt';
    if (preg_match('/\bSEB(?:\/(\d+(?:\.\d+)?)|\b)/', $ua, $m)) {
        $browser = 'Safe Exam Browser' . (isset($m[1]) ? ' ' . $m[1] : '');
    } elseif (preg_match('/Edg\/([\d]+)/', $ua, $m)) {
        $browser = 'Edge ' . $m[1];
    } elseif (preg_match('/OPR\/([\d]+)/', $ua, $m)) {
        $browser = 'Opera ' . $m[1];
    } elseif (preg_match('/coc_coc_browser\/([\d]+)/i', $ua, $m)) {
        $browser = 'Cốc Cốc ' . $m[1];
    } elseif (preg_match('/Chrome\/([\d]+)/', $ua, $m)) {
        $browser = 'Chrome ' . $m[1];
    } elseif (preg_match('/Firefox\/([\d]+)/', $ua, $m)) {
        $browser = 'Firefox ' . $m[1];
    } elseif (preg_match('/Version\/([\d]+).*Safari/', $ua, $m)) {
        $browser = 'Safari ' . $m[1];
    }
    $os = '';
    if (stripos($ua, 'Windows NT 10') !== false) {
        $os = 'Windows 10/11';
    } elseif (stripos($ua, 'Windows NT 6.1') !== false) {
        $os = 'Windows 7';
    } elseif (stripos($ua, 'Windows') !== false) {
        $os = 'Windows';
    } elseif (stripos($ua, 'Android') !== false) {
        $os = 'Android';
    } elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) {
        $os = 'iOS';
    } elseif (stripos($ua, 'Mac OS') !== false) {
        $os = 'macOS';
    } elseif (stripos($ua, 'Linux') !== false) {
        $os = 'Linux';
    }
    return trim($browser . ($os !== '' ? ' · ' . $os : ''));
}

/** Văn bản chân trang có hỗ trợ {year}, {version}, {org} và liên kết dạng [chữ](url). */
function render_footer_text(string $text): string
{
    $html = e($text);
    $html = str_replace(['{year}', '{version}', '{org}'], [date('Y'), e(TN_VERSION), e((string) setting('org_name', ''))], $html);
    $html = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/u', static function ($m) {
        return '<a href="' . $m[2] . '" target="_blank" rel="noopener">' . $m[1] . '</a>';
    }, $html);
    return nl2br($html);
}

function logo_url(): string
{
    $id = (int) setting('logo_file_id', 0);
    if ($id > 0) {
        return url('media/logo', ['v' => setting('logo_version', '1')]);
    }
    return base_uri() . 'assets/img/logo.svg?v=' . rawurlencode(TN_VERSION);
}

function favicon_url(): string
{
    $id = (int) setting('favicon_file_id', 0);
    if ($id > 0) {
        return url('media/favicon', ['v' => setting('favicon_version', '1')]);
    }
    if ((int) setting('logo_file_id', 0) > 0) {
        return logo_url();
    }
    return base_uri() . 'assets/img/logo.svg?v=' . rawurlencode(TN_VERSION);
}

/** Tạo các sắc độ từ màu chủ đạo (thay cho color-mix để hỗ trợ trình duyệt cũ). */
function color_shades(string $hex): array
{
    $hex = ltrim(trim($hex), '#');
    if (!preg_match('/^[0-9a-f]{6}$/i', $hex)) {
        $hex = '2563eb';
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $mix = static function (int $c, int $t, float $p): int {
        return (int) round($c + ($t - $c) * $p);
    };
    $toHex = static function (int $r, int $g, int $b): string {
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    };
    return [
        'base' => '#' . strtolower($hex),
        'hover' => $toHex($mix($r, 0, 0.14), $mix($g, 0, 0.14), $mix($b, 0, 0.14)),
        'dark' => $toHex($mix($r, 0, 0.35), $mix($g, 0, 0.35), $mix($b, 0, 0.35)),
        'soft' => $toHex($mix($r, 255, 0.9), $mix($g, 255, 0.9), $mix($b, 255, 0.9)),
        'soft2' => $toHex($mix($r, 255, 0.8), $mix($g, 255, 0.8), $mix($b, 255, 0.8)),
        'darksoft' => $toHex($mix($r, 17, 0.78), $mix($g, 24, 0.78), $mix($b, 39, 0.78)),
        'rgb' => $r . ',' . $g . ',' . $b,
    ];
}

/** Tương thích PHP 8.0 (array_is_list có từ PHP 8.1). */
function array_is_list_compat(array $a): bool
{
    if (function_exists('array_is_list')) {
        return array_is_list($a);
    }
    $i = 0;
    foreach ($a as $k => $_) {
        if ($k !== $i++) {
            return false;
        }
    }
    return true;
}

/** Nạp thư viện biểu đồ cho trang hiện tại (chỉ một lần). */
function use_charts(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    \App\Core\View::push('scripts', '<script src="' . asset('vendor/chartjs/chart.umd.min.js') . '"></script><script src="' . asset('js/charts.js') . '"></script>');
}

/** Nạp KaTeX để hiển thị công thức toán trong lời giải. */
function use_katex(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    \App\Core\View::push('scripts', '<link rel="stylesheet" href="' . asset('vendor/katex/katex.min.css') . '"><script src="' . asset('vendor/katex/katex.min.js') . '"></script><script src="' . asset('vendor/katex/auto-render.min.js') . '"></script>');
}

/** Lớp CSS màu điểm theo thang. */
function score_class($score, float $max = 10.0): string
{
    if ($score === null || $score === '') {
        return '';
    }
    $v = $max > 0 ? (float) $score * 10 / $max : (float) $score;
    return $v >= 8 ? 'score-hi' : ($v >= 6.5 ? 'score-mid' : ($v >= 5 ? 'score-lo' : 'score-fail'));
}

/** Lời chào theo giờ Việt Nam. */
function greeting(): string
{
    $h = (int) date('G');
    return $h < 11 ? 'Chào buổi sáng' : ($h < 13 ? 'Chào buổi trưa' : ($h < 18 ? 'Chào buổi chiều' : 'Chào buổi tối'));
}

function weekday_vi(?int $ts = null): string
{
    $d = ['Chủ nhật', 'Thứ hai', 'Thứ ba', 'Thứ tư', 'Thứ năm', 'Thứ sáu', 'Thứ bảy'];
    return $d[(int) date('w', $ts ?? time())];
}
