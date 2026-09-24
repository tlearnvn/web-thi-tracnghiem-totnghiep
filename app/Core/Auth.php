<?php

namespace App\Core;

final class Auth
{
    private static ?array $user = null;
    private static bool $resolved = false;
    private static array $roles = [];

    public static function user(): ?array
    {
        if (!self::$resolved) {
            self::$resolved = true;
            $uid = (int) ($_SESSION['_uid'] ?? 0);
            if ($uid > 0) {
                $u = App::db()->one('SELECT * FROM {users} WHERE id = ?', [$uid]);
                if ($u && $u['status'] === 'active' && ($_SESSION['_pwv'] ?? '') === self::passwordVersion($u['password_hash'])) {
                    self::$user = $u;
                } else {
                    unset($_SESSION['_uid'], $_SESSION['_pwv']);
                }
            }
        }
        return self::$user;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int) $u['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function roleInfo(?string $code = null): ?array
    {
        $code = $code ?? (self::user()['role'] ?? null);
        if ($code === null) {
            return null;
        }
        if (!array_key_exists($code, self::$roles)) {
            $row = App::db()->one('SELECT * FROM {roles} WHERE code = ?', [$code]);
            if ($row) {
                $row['perms'] = json_dec($row['permissions'], []);
            }
            self::$roles[$code] = $row;
        }
        return self::$roles[$code];
    }

    public static function permissions(): array
    {
        $r = self::roleInfo();
        return $r ? $r['perms'] : [];
    }

    public static function can(string $perm): bool
    {
        if (!self::user()) {
            return false;
        }
        $perms = self::permissions();
        if (in_array('*', $perms, true)) {
            return true;
        }
        return in_array($perm, $perms, true);
    }

    public static function isAdmin(): bool
    {
        return self::user() !== null && in_array('*', self::permissions(), true);
    }

    public static function isStudent(): bool
    {
        $r = self::roleInfo();
        return $r !== null && $r['kind'] === 'student';
    }

    public static function isStaff(): bool
    {
        $r = self::roleInfo();
        return $r !== null && $r['kind'] !== 'student';
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function passwordVersion(string $hash): string
    {
        return substr(hash('sha256', $hash), 0, 16);
    }

    public static function normalizeUsername(string $u): string
    {
        return mb_strtolower(trim($u));
    }

    /**
     * Kiểm tra đăng nhập có chống dò mật khẩu.
     * @return array{0: ?array, 1: ?string} [user, lỗi]
     */
    public static function attempt(string $username, string $password): array
    {
        $db = App::db();
        $username = self::normalizeUsername($username);
        $ip = client_ip();
        $now = time();
        $lockMin = max(1, (int) Settings::get('login_lock_minutes', 5));
        $maxUser = max(3, (int) Settings::get('login_max_attempts', 5));
        $maxIp = max(10, (int) Settings::get('login_ip_max_attempts', 60));
        $since = $now - $lockMin * 60;

        if ($username === '' || $password === '') {
            return [null, 'Vui lòng nhập tên đăng nhập và mật khẩu.'];
        }

        $lastOk = (int) $db->value('SELECT MAX(created_at) FROM {login_attempts} WHERE username = ? AND success = 1', [$username]);
        $failsUser = (int) $db->value(
            'SELECT COUNT(*) FROM {login_attempts} WHERE username = ? AND success = 0 AND created_at >= ?',
            [$username, max($since, $lastOk + 1)]
        );
        if ($failsUser >= $maxUser) {
            return [null, 'Tài khoản tạm khóa ' . $lockMin . ' phút do nhập sai mật khẩu nhiều lần. Vui lòng thử lại sau hoặc liên hệ giáo viên.'];
        }
        $failsIp = (int) $db->value('SELECT COUNT(*) FROM {login_attempts} WHERE ip = ? AND success = 0 AND created_at >= ?', [$ip, $since]);
        if ($failsIp >= $maxIp) {
            return [null, 'Có quá nhiều lần đăng nhập sai từ địa chỉ mạng này. Vui lòng thử lại sau ' . $lockMin . ' phút.'];
        }

        $user = $db->one('SELECT * FROM {users} WHERE username = ?', [$username]);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $db->insert('login_attempts', ['username' => $username, 'ip' => $ip, 'success' => 0, 'created_at' => $now]);
            $left = $maxUser - $failsUser - 1;
            $msg = 'Tên đăng nhập hoặc mật khẩu không đúng.';
            if ($user && $left <= 2 && $left > 0) {
                $msg .= ' Còn ' . $left . ' lần thử trước khi tài khoản bị tạm khóa.';
            }
            return [null, $msg];
        }
        if ($user['status'] !== 'active') {
            return [null, 'Tài khoản đã bị khóa. Vui lòng liên hệ quản trị viên hoặc giáo viên.'];
        }
        $db->insert('login_attempts', ['username' => $username, 'ip' => $ip, 'success' => 1, 'created_at' => $now]);

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $user['password_hash'] = self::hash($password);
            $db->update('users', ['password_hash' => $user['password_hash']], 'id = ?', [$user['id']]);
        }
        if (random_int(1, 50) === 1) {
            $db->run('DELETE FROM {login_attempts} WHERE created_at < ?', [$now - 7 * 86400]);
        }
        return [$user, null];
    }

    public static function login(array $user): void
    {
        Session::regenerate();
        $_SESSION['_uid'] = (int) $user['id'];
        $_SESSION['_pwv'] = self::passwordVersion($user['password_hash']);
        $_SESSION['_login_at'] = time();
        unset($_SESSION['_csrf']);
        App::db()->update('users', ['last_login_at' => time(), 'last_login_ip' => client_ip()], 'id = ?', [$user['id']]);
        self::$user = $user;
        self::$resolved = true;
        Logger::audit('auth.login', 'user', (int) $user['id']);
    }

    public static function logout(): void
    {
        if (self::user()) {
            Logger::audit('auth.logout', 'user', (int) self::user()['id']);
        }
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $p['path'],
            'secure' => $p['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        self::$user = null;
    }

    /** Buộc đăng xuất mọi phiên của một người dùng (khi khóa tài khoản / đổi mật khẩu). */
    public static function killSessions(int $userId, ?string $exceptSessionId = null): void
    {
        if ($exceptSessionId) {
            App::db()->run('DELETE FROM {web_sessions} WHERE user_id = ? AND id <> ?', [$userId, $exceptSessionId]);
        } else {
            App::db()->run('DELETE FROM {web_sessions} WHERE user_id = ?', [$userId]);
        }
    }

    /** Làm mới dữ liệu người dùng hiện tại sau khi cập nhật hồ sơ. */
    public static function refresh(): void
    {
        $uid = (int) ($_SESSION['_uid'] ?? 0);
        self::$user = $uid > 0 ? App::db()->one('SELECT * FROM {users} WHERE id = ?', [$uid]) : null;
        if (self::$user) {
            // Chính người dùng vừa đổi mật khẩu: giữ phiên hiện tại hợp lệ
            $_SESSION['_pwv'] = self::passwordVersion(self::$user['password_hash']);
        }
        self::$resolved = true;
    }
}
