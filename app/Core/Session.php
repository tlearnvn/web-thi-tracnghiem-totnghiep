<?php

namespace App\Core;

final class Session
{
    private static bool $started = false;

    public static function lifetime(): int
    {
        $h = (int) Settings::get('session_lifetime_hours', 12);
        return max(1, min(72, $h)) * 3600;
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443')
            || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    }

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }
        $lifetime = self::lifetime();
        @ini_set('session.gc_maxlifetime', (string) $lifetime);
        session_set_save_handler(new DbSessionHandler(App::db(), $lifetime), true);
        session_name((string) App::config('session_name', 'TNSESS'));
        session_set_cookie_params([
            'lifetime' => (int) Settings::get('remember_login', 0) ? $lifetime : 0,
            'path' => base_uri(),
            'secure' => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        self::$started = true;

        // Dọn phiên hết hạn (thay cho cơ chế GC theo file của PHP)
        if (random_int(1, 150) === 1) {
            try {
                DbSessionHandler::cleanup(App::db(), $lifetime);
            } catch (\Throwable $e) {
                // bỏ qua
            }
        }
    }

    /** Mở phiên mới sau khi đã hủy phiên cũ (đăng xuất). */
    public static function restart(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        self::$started = true;
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function takeFlash(): array
    {
        $f = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $f;
    }

    public static function setOld(array $input): void
    {
        unset($input['_token'], $input['password'], $input['password_confirm']);
        $_SESSION['_old'] = $input;
    }

    public static function clearOld(): void
    {
        unset($_SESSION['_old']);
    }

    /** Đóng ghi session sớm (các API chỉ đọc) để không giữ kết nối lâu. */
    public static function release(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
}
