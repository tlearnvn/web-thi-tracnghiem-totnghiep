<?php

namespace App\Lib;

use App\Core\App;

/**
 * Safe Exam Browser (SEB): tạo tệp cấu hình .seb cho ca thi, tính Config Key và kiểm tra học sinh
 * có đang làm bài bằng SEB đúng cấu hình hay không.
 *
 * Cách SEB (bản 3.x cho Windows, macOS, iOS) chứng minh với máy chủ:
 *  - Config Key = SHA-256 của các thiết lập trong tệp .seb viết thành JSON chuẩn hóa: khóa xếp theo bảng
 *    chữ cái không phân biệt hoa thường, không khoảng trắng, bỏ khóa originatorVersion và các từ điển rỗng.
 *  - Mỗi yêu cầu, SEB gửi header X-SafeExamBrowser-ConfigKeyHash = SHA-256(URL đầy đủ + Config Key)
 *    và X-SafeExamBrowser-RequestHash = SHA-256(URL + Browser Exam Key).
 *  - Nơi không gắn được header (WebView trên macOS / iOS), trang đọc SafeExamBrowser.security.configKey
 *    – cũng là SHA-256(URL trang + Config Key) – qua JavaScript rồi gửi lên máy chủ (xem verifyJs()).
 * Xác minh được một lần (header hoặc JavaScript) thì ghi nhớ cho phiên đăng nhập đó với ca thi đó – giống cách
 * Moodle làm – nên các trang được chuyển hướng tới hay lệnh lưu bài không phụ thuộc việc SEB có gắn header hay không.
 * URL dùng để xác minh qua JavaScript mang mã dùng một lần của phiên đăng nhập (không dùng lại được mã băm của người khác).
 * Lưu ý: ai có tệp .seb cũng tính được Config Key, nên đây là rào chắn kỹ thuật chứ không phải chữ ký không thể giả.
 */
final class Seb
{
    public const MODES = [
        'off' => 'Không yêu cầu',
        'config' => 'Bắt buộc – tệp .seb của hệ thống',
        'keys' => 'Bắt buộc – tệp .seb riêng của trường',
        'browser' => 'Bắt buộc – chỉ nhận diện SEB',
    ];

    /** Mô tả đầy đủ từng chế độ (trang chi tiết ca thi). */
    public const MODE_INFO = [
        'off' => 'Không yêu cầu Safe Exam Browser',
        'config' => 'Bắt buộc dùng tệp cấu hình do hệ thống tạo – kiểm tra Config Key ở mọi thao tác làm bài (khuyên dùng)',
        'keys' => 'Bắt buộc dùng tệp cấu hình riêng của trường – kiểm tra theo Config Key / Browser Exam Key đã nhập',
        'browser' => 'Bắt buộc dùng SEB – chỉ nhận diện qua thông tin trình duyệt (dễ giả mạo)',
    ];

    /** Trang tải SEB chính thức (Windows, macOS, iOS). */
    public const DOWNLOAD_URL = 'https://safeexambrowser.org/download_en.html';

    private const H_CONFIG = 'HTTP_X_SAFEEXAMBROWSER_CONFIGKEYHASH';
    private const H_REQUEST = 'HTTP_X_SAFEEXAMBROWSER_REQUESTHASH';

    public static function required(array $o): bool
    {
        return ($o['seb'] ?? 'off') !== 'off';
    }

    /** Phiên bản SEB ghi trong User-Agent (vd "3.7.1"); "" nếu là SEB nhưng không rõ bản; null nếu không phải SEB. */
    public static function version(?string $ua = null): ?string
    {
        $ua = $ua ?? (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        return preg_match('~\bSEB(?:/(\d+(?:\.\d+)*)|\b)~', $ua, $m) ? ($m[1] ?? '') : null;
    }

    /** Chuẩn hóa danh sách khóa nhập tay: mỗi khóa 64 ký tự hex, không trùng. */
    public static function parseKeys(string $text): array
    {
        preg_match_all('/\b[0-9a-fA-F]{64}\b/', $text, $m);
        return array_values(array_unique(array_map('strtolower', $m[0])));
    }

    // ------------------------------------------------------------ Tệp cấu hình .seb

    private static function origin(bool $https): string
    {
        return ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    private static function https(): bool
    {
        return \App\Core\Session::isHttps();
    }

    /**
     * Thiết lập SEB của ca thi – theo tên miền học sinh đang dùng để vào hệ thống.
     * Chỉ ghi các thiết lập cần thiết, còn lại để SEB dùng mặc định (Config Key chỉ tính trên các khóa có trong tệp).
     */
    public static function settings(array $s, array $o, ?bool $https = null): array
    {
        $origin = self::origin($https ?? self::https());
        $cfg = [
            'startURL' => $origin . url('student/lobby', ['sid' => (int) $s['id']]),
            'sendBrowserExamKey' => true,
            'allowQuit' => true,
            'quitURL' => $origin . url('seb/quit'),
            'quitURLConfirm' => true,
        ];
        if (($o['seb_quit_hash'] ?? '') !== '') {
            $cfg['hashedQuitPassword'] = (string) $o['seb_quit_hash'];
        }
        return $cfg;
    }

    /** Tệp .seb dạng XML (property list của Apple, không nén, không mã hóa – SEB đọc được trên mọi nền tảng). */
    public static function plist(array $settings): string
    {
        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<!DOCTYPE plist PUBLIC \"-//Apple//DTD PLIST 1.0//EN\" \"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">\n"
            . "<plist version=\"1.0\">\n" . self::plistValue($settings, '') . "\n</plist>\n";
    }

    private static function plistValue($v, string $ind): string
    {
        if (is_bool($v)) {
            return $v ? '<true/>' : '<false/>';
        }
        if (is_int($v)) {
            return '<integer>' . $v . '</integer>';
        }
        if (is_string($v)) {
            return '<string>' . htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</string>';
        }
        if (!is_array($v)) {
            throw new \InvalidArgumentException('Kiểu giá trị không hỗ trợ trong tệp SEB.');
        }
        if ($v !== [] && array_is_list_compat($v)) {
            $out = $ind . '<array>';
            foreach ($v as $item) {
                $out .= "\n" . $ind . "\t" . ltrim(self::plistValue($item, $ind . "\t"));
            }
            return $out . "\n" . $ind . '</array>';
        }
        $out = $ind . '<dict>';
        foreach (self::sortKeys($v) as $k => $item) {
            $out .= "\n" . $ind . "\t<key>" . htmlspecialchars((string) $k, ENT_XML1, 'UTF-8') . '</key>'
                . "\n" . $ind . "\t" . ltrim(self::plistValue($item, $ind . "\t"));
        }
        return $out . "\n" . $ind . '</dict>';
    }

    /** Khóa xếp theo bảng chữ cái, không phân biệt hoa thường (như SEB). */
    private static function sortKeys(array $a): array
    {
        uksort($a, static fn($x, $y) => strcmp(strtolower((string) $x), strtolower((string) $y)) ?: strcmp((string) $x, (string) $y));
        return $a;
    }

    /** Config Key của một bộ thiết lập (64 ký tự hex thường). */
    public static function configKey(array $settings): string
    {
        unset($settings['originatorVersion']);
        return hash('sha256', self::canonicalJson($settings));
    }

    /** JSON chuẩn hóa để tính Config Key: không khoảng trắng, khóa đã sắp xếp, "/" và Unicode giữ nguyên. */
    public static function canonicalJson($v): string
    {
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        if (is_int($v)) {
            return (string) $v;
        }
        if (is_string($v)) {
            return json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS);
        }
        if (!is_array($v)) {
            throw new \InvalidArgumentException('Kiểu giá trị không hỗ trợ trong tệp SEB.');
        }
        if ($v === [] || array_is_list_compat($v)) {
            return '[' . implode(',', array_map([self::class, 'canonicalJson'], $v)) . ']';
        }
        $pairs = [];
        foreach (self::sortKeys($v) as $k => $item) {
            if (is_array($item) && $item === []) {
                continue; // từ điển rỗng không tính (mảng rỗng không dùng trong tệp của hệ thống)
            }
            $pairs[] = json_encode((string) $k, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ':' . self::canonicalJson($item);
        }
        return '{' . implode(',', $pairs) . '}';
    }

    // ------------------------------------------------------------ Kiểm tra

    /** Các khóa hợp lệ của ca thi (Config Key tính từ tệp của hệ thống – cả http lẫn https – hoặc khóa nhập tay). */
    public static function validKeys(array $s, array $o): array
    {
        if (($o['seb'] ?? '') === 'keys') {
            return self::parseKeys((string) ($o['seb_keys'] ?? ''));
        }
        if (($o['seb'] ?? '') === 'config') {
            return array_values(array_unique([self::configKey(self::settings($s, $o, true)), self::configKey(self::settings($s, $o, false))]));
        }
        return [];
    }

    /** URL đầy đủ của yêu cầu hiện tại (http và https – phòng khi máy chủ đứng sau proxy). */
    private static function requestUrls(): array
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if ($uri === '') {
            $uri = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php') . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '');
        }
        $uri = explode('#', $uri, 2)[0];
        return [self::origin(true) . $uri, self::origin(false) . $uri];
    }

    private static function matches(array $keys, array $urls, string $hash): bool
    {
        $hash = strtolower(trim($hash));
        if (!preg_match('/^[0-9a-f]{64}$/', $hash)) {
            return false;
        }
        foreach ($keys as $k) {
            foreach ($urls as $u) {
                if (hash_equals(hash('sha256', $u . $k), $hash)) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function sentHeaders(): bool
    {
        return !empty($_SERVER[self::H_CONFIG]) || !empty($_SERVER[self::H_REQUEST]);
    }

    /** Header SEB của yêu cầu hiện tại khớp khóa của ca thi. */
    public static function headerValid(array $s, array $o): bool
    {
        if (!self::sentHeaders()) {
            return false;
        }
        $keys = self::validKeys($s, $o);
        if (!$keys) {
            return false;
        }
        $urls = self::requestUrls();
        return self::matches($keys, $urls, (string) ($_SERVER[self::H_CONFIG] ?? ''))
            || (($o['seb'] ?? '') === 'keys' && self::matches($keys, $urls, (string) ($_SERVER[self::H_REQUEST] ?? '')));
    }

    /**
     * Mã băm do SafeExamBrowser.security (JavaScript API) trả về cho URL trang.
     * URL phải thuộc hệ thống và mang mã dùng một lần của phiên đăng nhập hiện tại.
     */
    public static function verifyJs(array $s, array $o, string $url, string $ck, string $bek): bool
    {
        $url = explode('#', $url, 2)[0];
        $ok = false;
        foreach ([true, false] as $https) {
            $ok = $ok || str_starts_with($url, self::origin($https) . base_uri());
        }
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        if (!$ok || !isset($q['sebn']) || !hash_equals(self::nonce(), (string) $q['sebn'])) {
            return false;
        }
        $keys = self::validKeys($s, $o);
        return self::matches($keys, [$url], $ck) || (($o['seb'] ?? '') === 'keys' && self::matches($keys, [$url], $bek));
    }

    /** Mã dùng một lần gắn vào URL trang khi xác minh bằng JavaScript (mỗi phiên đăng nhập một mã). */
    public static function nonce(): string
    {
        if (empty($_SESSION['_seb_nonce'])) {
            $_SESSION['_seb_nonce'] = random_token(12);
        }
        return (string) $_SESSION['_seb_nonce'];
    }

    public static function remember(int $sid): void
    {
        $ua = md5(user_agent());
        if (($_SESSION['_seb'][$sid] ?? null) !== $ua) {
            $_SESSION['_seb'][$sid] = $ua;
        }
    }

    private static function remembered(int $sid): bool
    {
        return isset($_SESSION['_seb'][$sid]) && hash_equals((string) $_SESSION['_seb'][$sid], md5(user_agent()));
    }

    /**
     * Trạng thái SEB của yêu cầu hiện tại với một ca thi. Header hợp lệ -> ghi nhớ vào phiên đăng nhập, để các yêu cầu
     * sau (trang được chuyển hướng tới, lệnh lưu bài, SEB trên macOS / iOS không gửi header) không cần xác minh lại.
     * reason: not_seb (không phải SEB) | bad_key (SEB gửi khóa không khớp) | need_js (cần xác minh bằng JavaScript)
     *
     * @return array{required: bool, mode: string, ok: bool, via: string, version: ?string, reason: string}
     */
    public static function status(array $s, array $o): array
    {
        $mode = (string) ($o['seb'] ?? 'off');
        $ver = self::version();
        $r = ['required' => $mode !== 'off', 'mode' => $mode, 'ok' => true, 'via' => '', 'version' => $ver, 'reason' => ''];
        if ($mode === 'off') {
            return $r;
        }
        if ($mode === 'browser') {
            $r['ok'] = $ver !== null;
            $r['via'] = 'ua';
            $r['reason'] = $ver !== null ? '' : 'not_seb';
            return $r;
        }
        if (self::headerValid($s, $o)) {
            self::remember((int) $s['id']);
            $r['via'] = 'header';
            return $r;
        }
        if ($ver !== null && self::remembered((int) $s['id'])) {
            $r['via'] = 'session';
            return $r;
        }
        $r['ok'] = false;
        $r['reason'] = $ver === null ? 'not_seb' : (self::sentHeaders() ? 'bad_key' : 'need_js');
        return $r;
    }

    /** Lời giải thích cho học sinh khi chưa đạt. */
    public static function reasonText(string $reason): string
    {
        return [
            'not_seb' => 'Em đang dùng trình duyệt thường. Bài thi này chỉ làm được bằng Safe Exam Browser.',
            'bad_key' => 'Safe Exam Browser đang chạy với tệp cấu hình khác. Hãy thoát SEB rồi mở lại bằng tệp cấu hình của ca thi này.',
            'need_js' => 'Chưa xác minh được Safe Exam Browser. Hãy tải lại trang; nếu vẫn báo, thoát SEB rồi mở lại bằng tệp cấu hình của ca thi này.',
        ][$reason] ?? 'Bài thi này chỉ làm được bằng Safe Exam Browser.';
    }

    // ------------------------------------------------------------ Liên kết

    /** Mã bảo vệ liên kết tải cấu hình (SEB tải tệp không kèm đăng nhập). */
    public static function token(int $sid): string
    {
        return substr(App::sign('seb-config|' . $sid), 0, 24);
    }

    /** Liên kết tải tệp .seb; $open = true -> dạng sebs:// (seb:// với http) để mở thẳng SEB. */
    public static function configUrl(array $s, bool $open = false): string
    {
        $https = self::https();
        $u = self::origin($https) . url('seb/config', ['sid' => (int) $s['id'], 'k' => self::token((int) $s['id'])]);
        return $open ? preg_replace('~^http(s?)://~', 'seb$1://', $u) : $u;
    }

    public static function quitUrl(): string
    {
        return self::origin(self::https()) . url('seb/quit');
    }

    public static function fileName(array $s): string
    {
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(Text::unaccent((string) $s['name']))), '-');
        return ($slug !== '' ? substr($slug, 0, 60) : 'ca-thi-' . (int) $s['id']) . '.seb';
    }
}
