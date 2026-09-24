<?php

namespace App\Core;

abstract class Controller
{
    protected Database $db;

    /** Các action không cần đăng nhập. */
    protected array $public = [];

    public function __construct()
    {
        $this->db = App::db();
    }

    public function isPublic(string $action): bool
    {
        return in_array($action, $this->public, true);
    }

    protected function render(string $view, array $data = [], string $layout = 'app'): void
    {
        echo View::render($view, $data, $layout);
    }

    protected function json($data, int $status = 200): void
    {
        Response::json($data, $status);
    }

    protected function ok(array $data = []): void
    {
        Response::json(['ok' => true] + $data);
    }

    protected function fail(string $message, int $status = 422, array $extra = []): void
    {
        Response::json(['ok' => false, 'error' => $message] + $extra, $status);
    }

    protected function redirect(string $route = '', array $params = []): void
    {
        Response::redirect(url($route, $params));
    }

    /** Quay lại trang trước (cùng máy chủ) hoặc route dự phòng. */
    protected function back(string $fallbackRoute = '', array $params = []): void
    {
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($ref !== '' && $host !== '' && parse_url($ref, PHP_URL_HOST) === parse_url('http://' . $host, PHP_URL_HOST)) {
            Response::redirect($ref);
            return;
        }
        $this->redirect($fallbackRoute, $params);
    }

    protected function flash(string $type, string $message): void
    {
        Session::flash($type, $message);
    }

    protected function user(): array
    {
        $u = Auth::user();
        if (!$u) {
            throw new HttpException(401);
        }
        return $u;
    }

    /** Yêu cầu có ít nhất một trong các quyền. */
    protected function authorize(string ...$perms): void
    {
        foreach ($perms as $p) {
            if (Auth::can($p)) {
                return;
            }
        }
        throw new HttpException(403);
    }

    protected function requireStaff(): void
    {
        if (!Auth::isStaff()) {
            throw new HttpException(403);
        }
    }

    protected function requirePost(): void
    {
        if (!Request::isPost()) {
            throw new HttpException(405);
        }
    }

    protected function notFound(string $message = ''): void
    {
        throw new HttpException(404, $message);
    }

    /** Tìm một dòng theo id hoặc báo 404. */
    protected function findOr404(string $table, int $id, string $message = ''): array
    {
        $row = $id > 0 ? $this->db->one('SELECT * FROM {' . $table . '} WHERE id = ?', [$id]) : null;
        if (!$row) {
            throw new HttpException(404, $message);
        }
        return $row;
    }
}
