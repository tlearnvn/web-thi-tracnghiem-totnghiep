<?php

namespace App\Core;

final class App
{
    private static array $config = [];
    private static bool $loaded = false;
    private static ?Database $db = null;

    public static function loadConfig(): bool
    {
        if (!self::$loaded) {
            self::$loaded = true;
            if (is_file(CONFIG_FILE)) {
                $c = require CONFIG_FILE;
                if (is_array($c)) {
                    self::$config = $c;
                }
            }
        }
        return !empty(self::$config['installed']);
    }

    public static function setConfig(array $config): void
    {
        self::$config = $config;
        self::$loaded = true;
        self::$db = null;
    }

    public static function config(string $key, $default = null)
    {
        if (!self::$loaded) {
            self::loadConfig();
        }
        $cur = self::$config;
        foreach (explode('.', $key) as $k) {
            if (!is_array($cur) || !array_key_exists($k, $cur)) {
                return $default;
            }
            $cur = $cur[$k];
        }
        return $cur;
    }

    public static function db(): Database
    {
        if (self::$db === null) {
            $cfg = self::config('db', []);
            if (!is_array($cfg) || !$cfg) {
                throw new \RuntimeException('Chưa cấu hình cơ sở dữ liệu.');
            }
            self::$db = Database::connect($cfg);
        }
        return self::$db;
    }

    /** Khóa bí mật của hệ thống (ký token, làm rối file PDF). */
    public static function secret(): string
    {
        $k = (string) self::config('app_key', '');
        return $k !== '' ? $k : hash('sha256', BASE_PATH . '|tn-exam');
    }

    public static function sign(string $data): string
    {
        return hash_hmac('sha256', $data, self::secret());
    }

    public static function securityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: same-origin');
        header_remove('X-Powered-By');
    }

    public static function run(): void
    {
        ErrorHandler::register();
        self::securityHeaders();
        if (!self::loadConfig()) {
            Response::redirect(base_uri() . 'install.php');
            return;
        }
        $db = self::db();
        Schema::ensureUpToDate($db);
        Session::start();
        $route = (string) ($_GET['r'] ?? '');
        Router::dispatch($route);
    }
}
