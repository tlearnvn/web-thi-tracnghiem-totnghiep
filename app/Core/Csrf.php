<?php

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(24));
        }
        return $_SESSION['_csrf'];
    }

    public static function valid(?string $token): bool
    {
        $expected = $_SESSION['_csrf'] ?? '';
        return is_string($token) && $token !== '' && is_string($expected) && $expected !== '' && hash_equals($expected, $token);
    }

    public static function fromRequest(): ?string
    {
        $t = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if ($t === null) {
            $json = Request::json();
            $t = $json['_token'] ?? null;
        }
        return is_string($t) ? $t : null;
    }
}
