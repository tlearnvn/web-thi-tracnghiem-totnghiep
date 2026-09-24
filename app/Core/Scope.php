<?php

namespace App\Core;

/**
 * Phạm vi dữ liệu theo người dùng: giáo viên chỉ thấy lớp được phân công,
 * đề của mình / đề được chia sẻ, ca thi mình tạo hoặc được phân công giám sát.
 */
final class Scope
{
    private static array $cache = [];

    /** null = toàn trường; mảng = các lớp được phép. */
    public static function classIds(): ?array
    {
        if (Auth::isAdmin() || Auth::can('students.view_all')) {
            return null;
        }
        if (!array_key_exists('classes', self::$cache)) {
            $uid = (int) Auth::id();
            $db = App::db();
            $ids = array_merge(
                $db->column('SELECT id FROM {classes} WHERE homeroom_teacher_id = ?', [$uid]),
                $db->column('SELECT class_id FROM {class_teachers} WHERE user_id = ?', [$uid])
            );
            self::$cache['classes'] = array_values(array_unique(array_map('intval', $ids)));
        }
        return self::$cache['classes'];
    }

    public static function canAccessClass(?int $classId): bool
    {
        $ids = self::classIds();
        return $ids === null || ($classId !== null && in_array((int) $classId, $ids, true));
    }

    /** Điều kiện SQL giới hạn theo lớp cho cột $col. */
    public static function classFilterSql(string $col, array &$params): string
    {
        $ids = self::classIds();
        if ($ids === null) {
            return '1=1';
        }
        if (!$ids) {
            return '1=0';
        }
        foreach ($ids as $id) {
            $params[] = $id;
        }
        return $col . ' IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
    }

    /** 'manage' | 'view' | 'none' */
    public static function examAccess(array $exam): string
    {
        $uid = (int) Auth::id();
        if (Auth::can('exams.manage_all')) {
            return 'manage';
        }
        $own = (int) ($exam['created_by'] ?? 0) === $uid;
        if ($own && Auth::can('exams.manage')) {
            return 'manage';
        }
        if (($own || (int) ($exam['is_shared'] ?? 0) === 1) && can('exams.view', 'exams.manage', 'sessions.manage')) {
            return 'view';
        }
        return 'none';
    }

    public static function isSessionStaff(int $sessionId): bool
    {
        $key = 'staff_' . $sessionId;
        if (!array_key_exists($key, self::$cache)) {
            self::$cache[$key] = (bool) App::db()->value(
                'SELECT 1 FROM {session_staff} WHERE session_id = ? AND user_id = ?',
                [$sessionId, (int) Auth::id()]
            );
        }
        return self::$cache[$key];
    }

    /** Ca thi có dành cho lớp nào mình phụ trách không. */
    public static function sessionTouchesMyClasses(int $sessionId): bool
    {
        $ids = self::classIds();
        if ($ids === null) {
            return true;
        }
        if (!$ids) {
            return false;
        }
        $params = [$sessionId];
        $in = implode(',', array_fill(0, count($ids), '?'));
        foreach ($ids as $i) {
            $params[] = $i;
        }
        return (bool) App::db()->value(
            'SELECT 1 FROM {session_targets} WHERE session_id = ? AND class_id IN (' . $in . ')',
            $params
        );
    }

    public static function sessionAccess(array $session): array
    {
        $uid = (int) Auth::id();
        $sid = (int) $session['id'];
        $own = (int) ($session['created_by'] ?? 0) === $uid;
        $manage = Auth::can('sessions.manage_all') || ($own && Auth::can('sessions.manage'));
        $staff = self::isSessionStaff($sid);
        $proctor = $manage || (Auth::can('sessions.proctor') && ($staff || $own));
        $results = $manage || Auth::can('results.view_all')
            || (Auth::can('results.view') && ($staff || $own || self::sessionTouchesMyClasses($sid)));
        return ['manage' => $manage, 'proctor' => $proctor, 'results' => $results, 'grade' => $results && Auth::can('results.grade')];
    }

    /**
     * Điều kiện SQL cho danh sách ca thi mà người dùng được thấy (bảng alias s).
     */
    public static function sessionListSql(array &$params): string
    {
        if (Auth::can('sessions.manage_all') || Auth::can('results.view_all')) {
            return '1=1';
        }
        $uid = (int) Auth::id();
        $conds = ['s.created_by = ?', 's.id IN (SELECT session_id FROM {session_staff} WHERE user_id = ?)'];
        $params[] = $uid;
        $params[] = $uid;
        if (Auth::can('results.view')) {
            $ids = self::classIds();
            if ($ids === null) {
                return '1=1';
            }
            if ($ids) {
                $conds[] = 's.id IN (SELECT session_id FROM {session_targets} WHERE class_id IN (' . implode(',', array_fill(0, count($ids), '?')) . '))';
                foreach ($ids as $i) {
                    $params[] = $i;
                }
            }
        }
        return '(' . implode(' OR ', $conds) . ')';
    }

    public static function reset(): void
    {
        self::$cache = [];
    }
}
