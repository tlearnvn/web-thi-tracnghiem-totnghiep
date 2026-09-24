<?php

namespace App\Core;

final class Router
{
    /** Tên module trong URL => lớp controller. */
    private const CONTROLLERS = [
        'home' => \App\Controllers\HomeController::class,
        'auth' => \App\Controllers\AuthController::class,
        'dashboard' => \App\Controllers\DashboardController::class,
        'profile' => \App\Controllers\ProfileController::class,
        'students' => \App\Controllers\StudentsController::class,
        'teachers' => \App\Controllers\TeachersController::class,
        'classes' => \App\Controllers\ClassesController::class,
        'users' => \App\Controllers\UsersController::class,
        'roles' => \App\Controllers\RolesController::class,
        'subjects' => \App\Controllers\SubjectsController::class,
        'exams' => \App\Controllers\ExamsController::class,
        'variants' => \App\Controllers\VariantsController::class,
        'sessions' => \App\Controllers\SessionsController::class,
        'monitor' => \App\Controllers\MonitorController::class,
        'results' => \App\Controllers\ResultsController::class,
        'stats' => \App\Controllers\StatsController::class,
        'student' => \App\Controllers\StudentPortalController::class,
        'exam' => \App\Controllers\ExamRoomController::class,
        'files' => \App\Controllers\FilesController::class,
        'media' => \App\Controllers\MediaController::class,
        'announcements' => \App\Controllers\AnnouncementsController::class,
        'settings' => \App\Controllers\SettingsController::class,
        'backup' => \App\Controllers\BackupController::class,
        'logs' => \App\Controllers\LogsController::class,
        'system' => \App\Controllers\SystemController::class,
    ];

    private const ALIASES = [
        '' => 'home/index',
        'login' => 'auth/login',
        'logout' => 'auth/logout',
    ];

    public static function dispatch(string $route): void
    {
        $route = strtolower(trim($route, '/'));
        $route = self::ALIASES[$route] ?? $route;
        $parts = explode('/', $route);
        $module = $parts[0] !== '' ? $parts[0] : 'home';
        $actionSlug = $parts[1] ?? 'index';
        if ($actionSlug === '') {
            $actionSlug = 'index';
        }
        if (!isset(self::CONTROLLERS[$module]) || !preg_match('/^[a-z][a-z0-9-]*$/', $actionSlug)) {
            throw new HttpException(404);
        }
        $class = self::CONTROLLERS[$module];
        $method = lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $actionSlug))));
        if (!method_exists($class, $method)) {
            throw new HttpException(404);
        }
        $ref = new \ReflectionMethod($class, $method);
        if (!$ref->isPublic() || $ref->isStatic() || $ref->getDeclaringClass()->getName() !== $class || strpos($method, '_') === 0) {
            throw new HttpException(404);
        }

        /** @var Controller $controller */
        $controller = new $class();
        $isPublic = $controller->isPublic($method);

        // CSRF cho mọi yêu cầu POST
        if (Request::isPost() && !Csrf::valid(Csrf::fromRequest())) {
            throw new HttpException(419);
        }

        $user = Auth::user();

        // Chế độ bảo trì: chỉ quản trị viên được vào
        if ((int) Settings::get('maintenance', 0) === 1 && !Auth::isAdmin() && !in_array($module, ['auth', 'media'], true)) {
            if (Request::wantsJson()) {
                throw new HttpException(503, (string) Settings::get('maintenance_message'));
            }
            http_response_code(503);
            echo View::render('errors/maintenance', ['title' => 'Bảo trì'], 'bare');
            return;
        }

        if (!$isPublic && !$user) {
            if (Request::wantsJson() || ($module === 'exam' && $method !== 'room')) {
                throw new HttpException(401);
            }
            if (!Request::isPost()) {
                $_SESSION['_intended'] = $_SERVER['REQUEST_URI'] ?? '';
            }
            Response::redirect(url('login'));
            return;
        }

        // Bắt buộc đổi mật khẩu lần đầu
        if ($user && (int) $user['must_change_password'] === 1 && !in_array($module . '/' . $method, ['profile/password', 'auth/logout', 'media/logo', 'media/favicon'], true)) {
            if (Request::wantsJson()) {
                throw new HttpException(403, 'Bạn cần đổi mật khẩu trước khi tiếp tục.');
            }
            Session::flash('warning', 'Vui lòng đổi mật khẩu trước khi sử dụng hệ thống.');
            Response::redirect(url('profile/password'));
            return;
        }

        $controller->$method();
    }
}
