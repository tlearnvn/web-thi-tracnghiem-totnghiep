<?php

namespace App\Core;

final class ErrorHandler
{
    public static function register(): void
    {
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleError(int $severity, string $message, string $file = '', int $line = 0): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        if (App::config('debug', false) && !in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        }
        if (!in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED, E_NOTICE, E_USER_NOTICE], true)) {
            Logger::warning($message, $file, $line);
        }
        return true;
    }

    public static function handleShutdown(): void
    {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            self::handleException(new \ErrorException($err['message'], 0, $err['type'], $err['file'], $err['line']));
        }
    }

    public static function handleException(\Throwable $e): void
    {
        $status = $e instanceof HttpException ? $e->status : 500;
        $ref = null;
        if ($status >= 500) {
            $ref = Logger::error($e);
        }
        $message = $e instanceof HttpException ? $e->getMessage() : 'Đã xảy ra lỗi hệ thống. Mã lỗi: ' . $ref;
        if ($status >= 500 && App::config('debug', false)) {
            $message = $e->getMessage() . ' @ ' . str_replace(BASE_PATH, '', $e->getFile()) . ':' . $e->getLine();
        }

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        if (Request::wantsJson() || strpos((string) ($_GET['r'] ?? ''), 'api/') === 0) {
            $payload = ['ok' => false, 'error' => $message, 'code' => $status];
            if ($e instanceof HttpException && $e->extra) {
                $payload = array_merge($payload, $e->extra);
            }
            if ($status === 419 || $status === 401) {
                $payload['relogin'] = true;
            }
            Response::json($payload, $status);
            return;
        }
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/html; charset=utf-8');
        }
        try {
            echo View::render('errors/error', [
                'status' => $status,
                'message' => $message,
                'ref' => $ref,
                'title' => $status === 404 ? 'Không tìm thấy' : ($status === 403 ? 'Không có quyền truy cập' : 'Đã xảy ra lỗi'),
            ], 'bare');
        } catch (\Throwable $e2) {
            echo '<!doctype html><meta charset="utf-8"><title>Lỗi</title><div style="font-family:sans-serif;padding:40px;text-align:center">'
                . '<h1>' . (int) $status . '</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>'
                . '<p><a href="' . htmlspecialchars(base_uri(), ENT_QUOTES, 'UTF-8') . '">Về trang chủ</a></p></div>';
        }
    }
}
