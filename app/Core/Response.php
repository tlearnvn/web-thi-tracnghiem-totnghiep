<?php

namespace App\Core;

final class Response
{
    public static function json($data, int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

    public static function redirect(string $url, int $status = 302): void
    {
        if (!headers_sent()) {
            header('Location: ' . $url, true, $status);
        } else {
            echo '<script>location.href=' . json_encode($url) . ';</script>';
        }
    }

    public static function noCache(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    /** Header tải file với tên tiếng Việt (RFC 5987). */
    public static function downloadHeaders(string $filename, string $mime, ?int $length = null, bool $inline = false): void
    {
        $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', \App\Lib\Text::unaccent($filename));
        header('Content-Type: ' . $mime);
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
        if ($length !== null) {
            header('Content-Length: ' . $length);
        }
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
    }
}
