<?php

namespace App\Core;

final class View
{
    public static array $shared = [];
    private static array $stacks = [];

    public static function render(string $template, array $data = [], ?string $layout = 'app'): string
    {
        $content = self::capture($template, $data);
        if ($layout === null) {
            return $content;
        }
        return self::capture('layouts/' . $layout, array_merge($data, ['content' => $content]));
    }

    public static function partial(string $template, array $data = []): string
    {
        return self::capture($template, $data);
    }

    /** Đẩy đoạn HTML vào một "ngăn" (ví dụ: scripts riêng của trang) để layout in ra. */
    public static function push(string $stack, string $html): void
    {
        self::$stacks[$stack][] = $html;
    }

    public static function stack(string $stack): string
    {
        return implode("\n", self::$stacks[$stack] ?? []);
    }

    private static function capture(string $template, array $data): string
    {
        $file = APP_PATH . '/Views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Không tìm thấy giao diện: ' . $template);
        }
        extract(array_merge(self::$shared, $data), EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
