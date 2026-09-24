<?php

namespace App\Lib;

use App\Core\App;
use App\Core\Auth;
use App\Core\Settings;

/**
 * Nghiệp vụ tài khoản dùng chung cho học sinh, giáo viên, quản trị.
 */
final class Users
{
    public const PASSWORD_MODES = [
        'random6' => '6 chữ số ngẫu nhiên (dễ nhập)',
        'random8' => '8 ký tự chữ + số ngẫu nhiên (an toàn hơn)',
        'birthday' => 'Theo ngày sinh (ddmmyyyy)',
        'username' => 'Giống tên đăng nhập (không khuyến khích)',
        'fixed' => 'Một mật khẩu chung do tôi nhập',
    ];

    public static function find(int $id): ?array
    {
        return $id > 0 ? App::db()->one('SELECT * FROM {users} WHERE id = ?', [$id]) : null;
    }

    public static function usernameTaken(string $username, ?int $exceptId = null): bool
    {
        $u = Auth::normalizeUsername($username);
        if ($exceptId) {
            return (bool) App::db()->value('SELECT 1 FROM {users} WHERE username = ? AND id <> ?', [$u, $exceptId]);
        }
        return (bool) App::db()->value('SELECT 1 FROM {users} WHERE username = ?', [$u]);
    }

    /** Tên đăng nhập chưa ai dùng, dựa trên $base (thêm số nếu trùng). */
    public static function uniqueUsername(string $base, array &$reserved = []): string
    {
        $base = Text::cleanUsername($base);
        if ($base === '' || strlen($base) < 2) {
            $base = 'user' . $base;
        }
        $base = substr($base, 0, 56);
        $candidate = $base;
        $i = 1;
        while (in_array($candidate, $reserved, true) || self::usernameTaken($candidate)) {
            $i++;
            $candidate = $base . $i;
        }
        $reserved[] = $candidate;
        return $candidate;
    }

    public static function makePassword(string $mode, array $user = [], string $fixed = ''): string
    {
        switch ($mode) {
            case 'random8':
                return Text::randomPassword(8);
            case 'birthday':
                if (!empty($user['birthday']) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $user['birthday'], $m)) {
                    return $m[3] . $m[2] . $m[1];
                }
                return Text::randomPassword(6, true);
            case 'username':
                return (string) ($user['username'] ?? Text::randomPassword(6, true));
            case 'fixed':
                return $fixed !== '' ? $fixed : Text::randomPassword(6, true);
            default:
                return Text::randomPassword(6, true);
        }
    }

    /** Tìm lớp theo tên (và năm học), tạo mới nếu được phép. */
    public static function classByName(string $name, ?string $year, bool $create): ?int
    {
        $name = trim((string) preg_replace('/\s+/', ' ', $name));
        if ($name === '') {
            return null;
        }
        $db = App::db();
        $year = $year ?: (string) Settings::get('default_school_year', Settings::guessSchoolYear());
        $id = $db->value('SELECT id FROM {classes} WHERE LOWER(name) = LOWER(?) AND (school_year = ? OR school_year IS NULL) ORDER BY id DESC', [$name, $year]);
        if (!$id) {
            $id = $db->value('SELECT id FROM {classes} WHERE LOWER(name) = LOWER(?) ORDER BY id DESC', [$name]);
        }
        if ($id) {
            return (int) $id;
        }
        if (!$create) {
            return null;
        }
        $now = time();
        return $db->insert('classes', [
            'name' => mb_substr($name, 0, 50),
            'grade' => Text::gradeFromClassName($name),
            'school_year' => $year,
            'homeroom_teacher_id' => null,
            'description' => null,
            'status' => 'active',
            'sort_key' => Text::collate($name),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public static function create(array $d, string $password, ?string $hash = null): int
    {
        $name = Text::normalizeName((string) $d['full_name']);
        $now = time();
        return App::db()->insert('users', [
            'username' => Auth::normalizeUsername($d['username']),
            'password_hash' => $hash ?? Auth::hash($password),
            'role' => $d['role'],
            'full_name' => $name,
            'code' => ($d['code'] ?? '') !== '' ? $d['code'] : null,
            'class_id' => $d['class_id'] ?? null,
            'gender' => $d['gender'] ?? null,
            'birthday' => $d['birthday'] ?? null,
            'email' => ($d['email'] ?? '') !== '' ? $d['email'] : null,
            'phone' => ($d['phone'] ?? '') !== '' ? $d['phone'] : null,
            'subject_id' => $d['subject_id'] ?? null,
            'status' => $d['status'] ?? 'active',
            'must_change_password' => (int) ($d['must_change_password'] ?? 0),
            'note' => ($d['note'] ?? '') !== '' ? $d['note'] : null,
            'search_text' => Text::searchText($name, $d['username'], $d['code'] ?? ''),
            'sort_key' => Text::sortKey($name),
            'created_by' => Auth::id(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public static function update(int $id, array $d, ?string $password = null, ?string $hash = null): void
    {
        $cur = self::find($id);
        if (!$cur) {
            return;
        }
        $data = [];
        foreach (['username', 'role', 'full_name', 'code', 'class_id', 'gender', 'birthday', 'email', 'phone', 'subject_id', 'status', 'must_change_password', 'note'] as $k) {
            if (array_key_exists($k, $d)) {
                $v = $d[$k];
                if (is_string($v) && $v === '' && !in_array($k, ['username', 'full_name', 'role'], true)) {
                    $v = null;
                }
                $data[$k] = $v;
            }
        }
        if (isset($data['full_name'])) {
            $data['full_name'] = Text::normalizeName((string) $data['full_name']);
        }
        if (isset($data['username'])) {
            $data['username'] = Auth::normalizeUsername((string) $data['username']);
        }
        $merged = array_merge($cur, $data);
        $data['search_text'] = Text::searchText((string) $merged['full_name'], (string) $merged['username'], (string) ($merged['code'] ?? ''));
        $data['sort_key'] = Text::sortKey((string) $merged['full_name']);
        if ($password !== null && $password !== '') {
            $data['password_hash'] = $hash ?? Auth::hash($password);
        }
        $data['updated_at'] = time();
        App::db()->update('users', $data, 'id = ?', [$id]);
        if (($password !== null && $password !== '') || (isset($data['status']) && $data['status'] !== 'active')) {
            if ($id !== (int) Auth::id()) {
                Auth::killSessions($id);
            }
        }
    }

    public static function setPassword(int $id, string $password, bool $mustChange = false): void
    {
        App::db()->update('users', [
            'password_hash' => Auth::hash($password),
            'must_change_password' => $mustChange ? 1 : 0,
            'updated_at' => time(),
        ], 'id = ?', [$id]);
        if ($id !== (int) Auth::id()) {
            Auth::killSessions($id);
        }
    }

    /** Xóa tài khoản kèm bài làm, phân công lớp. */
    public static function delete(int $id): void
    {
        $db = App::db();
        $db->transaction(function () use ($db, $id) {
            foreach ($db->column('SELECT id FROM {attempts} WHERE user_id = ?', [$id]) as $aid) {
                $db->run('DELETE FROM {attempt_events} WHERE attempt_id = ?', [(int) $aid]);
            }
            $db->run('DELETE FROM {attempts} WHERE user_id = ?', [$id]);
            $db->run('DELETE FROM {class_teachers} WHERE user_id = ?', [$id]);
            $db->run('DELETE FROM {session_targets} WHERE user_id = ?', [$id]);
            $db->run('DELETE FROM {session_staff} WHERE user_id = ?', [$id]);
            $db->run('UPDATE {classes} SET homeroom_teacher_id = NULL WHERE homeroom_teacher_id = ?', [$id]);
            $db->run('DELETE FROM {web_sessions} WHERE user_id = ?', [$id]);
            $db->run('DELETE FROM {users} WHERE id = ?', [$id]);
        });
    }

    public static function roleOptions(string $kind = 'all'): array
    {
        $sql = 'SELECT code, name, kind FROM {roles}';
        $params = [];
        if ($kind !== 'all') {
            $sql .= ' WHERE kind = ?';
            $params[] = $kind;
        }
        return App::db()->keyed($sql . ' ORDER BY sort_order, id', $params, 'code', 'name');
    }

    public static function staffRoles(): array
    {
        return App::db()->column("SELECT code FROM {roles} WHERE kind <> 'student'");
    }

    /** Lưu phiếu tài khoản vừa cấp để tải Excel / in (chỉ giữ trong phiên hiện tại). */
    public static function stashCredentials(string $title, array $rows): void
    {
        $_SESSION['_creds'] = ['title' => $title, 'rows' => array_values($rows), 'at' => time()];
    }

    public static function stashedCredentials(): ?array
    {
        $c = $_SESSION['_creds'] ?? null;
        if (!$c || ($c['at'] ?? 0) < time() - 7200) {
            return null;
        }
        return $c;
    }
}
