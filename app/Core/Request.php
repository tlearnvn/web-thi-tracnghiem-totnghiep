<?php

namespace App\Core;

final class Request
{
    private static ?array $json = null;

    public static function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    /** Yêu cầu AJAX / mong đợi JSON. */
    public static function wantsJson(): bool
    {
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        $xhr = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        return $xhr === 'xmlhttprequest' || $xhr === 'tnexam' || stripos($accept, 'application/json') !== false
            || stripos((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false;
    }

    public static function json(): array
    {
        if (self::$json === null) {
            self::$json = [];
            $ct = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
            if (stripos($ct, 'application/json') !== false) {
                $raw = file_get_contents('php://input');
                $d = json_decode((string) $raw, true);
                self::$json = is_array($d) ? $d : [];
            }
        }
        return self::$json;
    }

    public static function get(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    public static function post(string $key, $default = null)
    {
        if (array_key_exists($key, $_POST)) {
            return $_POST[$key];
        }
        $j = self::json();
        return array_key_exists($key, $j) ? $j[$key] : $default;
    }

    /** Ưu tiên POST/JSON rồi đến GET. */
    public static function input(string $key, $default = null)
    {
        $v = self::post($key, null);
        if ($v !== null) {
            return $v;
        }
        return $_GET[$key] ?? $default;
    }

    public static function str(string $key, string $default = ''): string
    {
        $v = self::input($key, $default);
        return is_scalar($v) ? trim((string) $v) : $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::input($key, null);
        return is_numeric($v) ? (int) $v : $default;
    }

    public static function float(string $key, ?float $default = null): ?float
    {
        $v = self::input($key, null);
        if ($v === null || $v === '') {
            return $default;
        }
        $v = str_replace(',', '.', (string) $v);
        return is_numeric($v) ? (float) $v : $default;
    }

    public static function bool(string $key): bool
    {
        $v = self::input($key, null);
        return in_array($v, [1, '1', 'on', 'true', true, 'yes'], true);
    }

    public static function arr(string $key): array
    {
        $v = self::input($key, []);
        return is_array($v) ? $v : [];
    }

    public static function ints(string $key): array
    {
        $out = [];
        foreach (self::arr($key) as $v) {
            if (is_numeric($v) && (int) $v > 0) {
                $out[] = (int) $v;
            }
        }
        return array_values(array_unique($out));
    }

    public static function file(string $key): ?array
    {
        $f = $_FILES[$key] ?? null;
        if (!$f || !is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $f;
    }

    public static function uploadError(array $f): ?string
    {
        switch ($f['error'] ?? UPLOAD_ERR_OK) {
            case UPLOAD_ERR_OK:
                return is_uploaded_file($f['tmp_name']) ? null : 'Tệp tải lên không hợp lệ.';
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'Tệp quá lớn so với giới hạn của máy chủ (' . ini_get('upload_max_filesize') . ').';
            case UPLOAD_ERR_PARTIAL:
                return 'Tệp chỉ được tải lên một phần, vui lòng thử lại.';
            case UPLOAD_ERR_NO_TMP_DIR:
            case UPLOAD_ERR_CANT_WRITE:
                return 'Máy chủ không ghi được tệp tạm.';
            default:
                return 'Không tải được tệp lên.';
        }
    }
}
