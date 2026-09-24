<?php

namespace App\Lib;

use App\Core\App;
use App\Core\Settings;

/**
 * Nghiệp vụ ca thi: trạng thái, đối tượng dự thi, chính sách hiển thị điểm, điều khiển (tạm dừng, cộng giờ, kết thúc).
 */
final class Sessions
{
    public const STATES = [
        'upcoming' => ['Sắp diễn ra', 'info', 'calendar-clock'],
        'running' => ['Đang diễn ra', 'success', 'circle-play'],
        'paused' => ['Tạm dừng', 'warning', 'circle-pause'],
        'ended' => ['Đã kết thúc', 'default', 'circle-stop'],
        'closed' => ['Đã đóng', 'default', 'lock'],
    ];

    public const MODES = ['exam' => 'Thi / kiểm tra', 'practice' => 'Luyện tập'];

    public const SCORE_POLICIES = [
        'after_submit' => 'Ngay sau khi nộp bài',
        'after_end' => 'Sau khi ca thi kết thúc',
        'manual' => 'Khi giáo viên bấm "Công bố điểm"',
        'never' => 'Không cho học sinh xem điểm',
    ];

    public const REVIEW_POLICIES = [
        'never' => 'Không cho xem lại bài',
        'after_submit' => 'Ngay sau khi nộp bài',
        'after_end' => 'Sau khi ca thi kết thúc',
        'manual' => 'Khi giáo viên bấm "Công bố điểm"',
    ];

    public const VARIANT_MODES = [
        'random' => 'Ngẫu nhiên, cân bằng số lượng mỗi mã đề',
        'sequential' => 'Lần lượt theo thứ tự vào thi',
        'fixed' => 'Cùng một mã đề cho tất cả',
    ];

    public const VIOLATION_ACTIONS = [
        'log' => 'Chỉ ghi nhận và cảnh báo học sinh',
        'lock' => 'Tạm khóa bài làm, chờ giám thị mở',
        'submit' => 'Tự động thu bài',
    ];

    public const TIME_POLICIES = [
        'cap' => 'Không vượt quá giờ kết thúc ca thi (vào muộn thì ít thời gian hơn)',
        'full' => 'Luôn đủ thời gian làm bài tính từ lúc bắt đầu',
    ];

    public static function defaultOptions(string $mode): array
    {
        $exam = $mode !== 'practice';
        return [
            'show_score' => $exam ? (string) Settings::get('exam_show_score', 'after_submit') : 'after_submit',
            'allow_review' => $exam ? (string) Settings::get('exam_allow_review', 'after_end') : 'after_submit',
            'show_key' => 1,
            'show_explanations' => 1,
            'show_solution_pdf' => 1,
            'device_lock' => $exam ? (int) Settings::get('exam_device_lock', 1) : 0,
            'auto_reclaim' => (int) Settings::get('exam_auto_reclaim', 1),
            'require_fullscreen' => $exam ? (int) Settings::get('exam_require_fullscreen', 0) : 0,
            'max_violations' => $exam ? (int) Settings::get('exam_max_violations', 0) : 0,
            'violation_action' => $exam ? (string) Settings::get('exam_violation_action', 'log') : 'log',
            'track_focus' => $exam ? 1 : 0,
            'watermark' => (int) Settings::get('exam_watermark', 1),
            'protect_pdf' => (int) Settings::get('exam_protect_pdf', 1),
            'time_policy' => 'cap',
            'grace_seconds' => (int) Settings::get('exam_grace_seconds', 90),
            'result_policy' => 'best',
            'confirm_submit' => 1,
            'min_submit_minutes' => 0,
        ];
    }

    public static function options(array $s): array
    {
        $d = self::defaultOptions((string) $s['mode']);
        $o = array_merge($d, array_intersect_key(json_dec($s['opts'] ?? null, []), $d));
        $o['grace_seconds'] = max(15, min(1800, (int) $o['grace_seconds']));
        $o['max_violations'] = max(0, min(100, (int) $o['max_violations']));
        foreach (['show_key', 'show_explanations', 'show_solution_pdf', 'device_lock', 'auto_reclaim', 'require_fullscreen', 'watermark', 'protect_pdf', 'track_focus', 'confirm_submit'] as $k) {
            $o[$k] = (int) !empty($o[$k]);
        }
        $o['min_submit_minutes'] = max(0, min(600, (int) $o['min_submit_minutes']));
        return $o;
    }

    public static function state(array $s, ?int $now = null): string
    {
        $now = $now ?? time();
        if (($s['status'] ?? '') === 'closed') {
            return 'closed';
        }
        if (!empty($s['start_at']) && $now < (int) $s['start_at']) {
            return 'upcoming';
        }
        if (!empty($s['end_at']) && $now >= (int) $s['end_at']) {
            return 'ended';
        }
        if (!empty($s['paused_at'])) {
            return 'paused';
        }
        return 'running';
    }

    public static function stateBadge(array $s): string
    {
        $st = self::STATES[self::state($s)];
        return badge($st[0], $st[1], $st[2]);
    }

    /** Thời gian làm bài (giây); 0 = không giới hạn. */
    public static function durationSec(array $s, array $exam): int
    {
        $m = $s['duration'] !== null && $s['duration'] !== '' ? (int) $s['duration'] : (int) $exam['duration'];
        return max(0, $m) * 60;
    }

    public static function studentRoles(): array
    {
        static $codes = null;
        if ($codes === null) {
            $codes = App::db()->column("SELECT code FROM {roles} WHERE kind = 'student'");
            if (!$codes) {
                $codes = ['student'];
            }
        }
        return $codes;
    }

    public static function isTarget(array $s, array $user): bool
    {
        $db = App::db();
        if ($db->value('SELECT 1 FROM {session_targets} WHERE session_id = ? AND user_id = ?', [(int) $s['id'], (int) $user['id']])) {
            return true;
        }
        if (!empty($user['class_id']) && $db->value('SELECT 1 FROM {session_targets} WHERE session_id = ? AND class_id = ?', [(int) $s['id'], (int) $user['class_id']])) {
            return true;
        }
        return false;
    }

    public static function targets(int $sid): array
    {
        $db = App::db();
        return [
            'classes' => array_map('intval', $db->column('SELECT class_id FROM {session_targets} WHERE session_id = ? AND class_id IS NOT NULL', [$sid])),
            'users' => array_map('intval', $db->column('SELECT user_id FROM {session_targets} WHERE session_id = ? AND user_id IS NOT NULL', [$sid])),
        ];
    }

    /** Danh sách học sinh thuộc ca thi (lớp được chọn + học sinh chọn riêng). */
    public static function students(int $sid, ?int $classId = null): array
    {
        $db = App::db();
        $roles = self::studentRoles();
        $params = $roles;
        $params[] = $sid;
        $params[] = $sid;
        $sql = 'SELECT u.id, u.full_name, u.code, u.username, u.class_id, u.birthday, u.gender, u.status, u.sort_key, c.name AS class_name
                FROM {users} u LEFT JOIN {classes} c ON c.id = u.class_id
                WHERE u.role IN ' . $db->in($roles) . '
                  AND (u.class_id IN (SELECT class_id FROM {session_targets} WHERE session_id = ? AND class_id IS NOT NULL)
                       OR u.id IN (SELECT user_id FROM {session_targets} WHERE session_id = ? AND user_id IS NOT NULL))';
        if ($classId) {
            $sql .= ' AND u.class_id = ?';
            $params[] = $classId;
        }
        $sql .= ' ORDER BY c.sort_key, c.name, u.sort_key';
        return $db->all($sql, $params);
    }

    /** Các ca thi dành cho một học sinh. */
    public static function forStudent(array $user, ?string $mode = null): array
    {
        $db = App::db();
        $params = [(int) ($user['class_id'] ?? 0), (int) $user['id']];
        $sql = 'SELECT s.*, e.title AS exam_title, e.duration AS exam_duration, e.structure, e.subject_id,
                       sub.name AS subject_name, sub.color AS subject_color
                FROM {exam_sessions} s
                JOIN {exams} e ON e.id = s.exam_id
                LEFT JOIN {subjects} sub ON sub.id = e.subject_id
                WHERE (s.id IN (SELECT session_id FROM {session_targets} WHERE class_id = ?)
                    OR s.id IN (SELECT session_id FROM {session_targets} WHERE user_id = ?))';
        if ($mode !== null) {
            $sql .= ' AND s.mode = ?';
            $params[] = $mode;
        }
        $sql .= ' ORDER BY COALESCE(s.start_at, s.created_at) DESC, s.id DESC';
        $rows = $db->all($sql, $params);
        if (!$rows) {
            return [];
        }
        $ids = array_map(static fn($r) => (int) $r['id'], $rows);
        $attempts = [];
        foreach ($db->all('SELECT * FROM {attempts} WHERE user_id = ? AND session_id IN ' . $db->in($ids) . ' ORDER BY attempt_no', array_merge([(int) $user['id']], $ids)) as $a) {
            $attempts[(int) $a['session_id']][] = $a;
        }
        foreach ($rows as &$r) {
            $r['attempts'] = $attempts[(int) $r['id']] ?? [];
        }
        return $rows;
    }

    /** Bài làm được tính kết quả chính thức khi có nhiều lượt (cao nhất / mới nhất / đầu tiên). */
    public static function officialAttempt(array $s, array $attempts): ?array
    {
        $done = array_values(array_filter($attempts, static fn($a) => $a['status'] !== 'in_progress'));
        if (!$done) {
            return null;
        }
        $policy = self::options($s)['result_policy'];
        if ($policy === 'latest') {
            return end($done);
        }
        if ($policy === 'first') {
            return $done[0];
        }
        usort($done, static fn($a, $b) => ((float) $b['score'] <=> (float) $a['score']) ?: ((int) $a['attempt_no'] <=> (int) $b['attempt_no']));
        return $done[0];
    }

    public static function canSeeScore(array $s, array $attempt): bool
    {
        if ($attempt['status'] === 'in_progress') {
            return false;
        }
        if ($s['mode'] === 'practice') {
            return true;
        }
        return self::policyOpen(self::options($s)['show_score'], $s);
    }

    public static function canReview(array $s, array $attempt): bool
    {
        if ($attempt['status'] === 'in_progress') {
            return false;
        }
        return self::policyOpen(self::options($s)['allow_review'], $s);
    }

    private static function policyOpen(string $policy, array $s): bool
    {
        switch ($policy) {
            case 'after_submit':
                return true;
            case 'after_end':
                return in_array(self::state($s), ['ended', 'closed'], true) || (int) $s['released'] === 1;
            case 'manual':
                return (int) $s['released'] === 1;
            default:
                return false;
        }
    }

    /** Chọn mã đề cho học sinh mới vào thi. */
    public static function pickVariant(array $s): ?int
    {
        $db = App::db();
        $variants = $db->all('SELECT id, pdf_file_id FROM {exam_variants} WHERE exam_id = ? ORDER BY sort_order, code', [(int) $s['exam_id']]);
        if (!$variants) {
            return null;
        }
        if ($s['variant_mode'] === 'fixed' && $s['fixed_variant_id']) {
            foreach ($variants as $v) {
                if ((int) $v['id'] === (int) $s['fixed_variant_id']) {
                    return (int) $v['id'];
                }
            }
        }
        $withPdf = array_values(array_filter($variants, static fn($v) => !empty($v['pdf_file_id'])));
        $pool = $withPdf ?: $variants;
        if (count($pool) === 1) {
            return (int) $pool[0]['id'];
        }
        $counts = $db->keyed('SELECT variant_id, COUNT(*) AS n FROM {attempts} WHERE session_id = ? GROUP BY variant_id', [(int) $s['id']], 'variant_id', 'n');
        if ($s['variant_mode'] === 'sequential') {
            $total = array_sum(array_map('intval', $counts));
            return (int) $pool[$total % count($pool)]['id'];
        }
        $min = PHP_INT_MAX;
        $cands = [];
        foreach ($pool as $v) {
            $n = (int) ($counts[$v['id']] ?? 0);
            if ($n < $min) {
                $min = $n;
                $cands = [(int) $v['id']];
            } elseif ($n === $min) {
                $cands[] = (int) $v['id'];
            }
        }
        return $cands[random_int(0, count($cands) - 1)];
    }

    // ------------------------------------------------------------ Điều khiển ca thi

    public static function pause(array $s): void
    {
        if (!empty($s['paused_at'])) {
            return;
        }
        App::db()->update('exam_sessions', ['paused_at' => time(), 'updated_at' => time()], 'id = ?', [(int) $s['id']]);
        self::broadcast((int) $s['id'], 'Giám thị đã TẠM DỪNG ca thi. Thời gian làm bài được giữ nguyên, các em chờ hướng dẫn.', 'warning');
    }

    public static function resume(array $s): void
    {
        if (empty($s['paused_at'])) {
            return;
        }
        $db = App::db();
        $now = time();
        $pausedAt = (int) $s['paused_at'];
        $db->transaction(function () use ($db, $s, $now, $pausedAt) {
            $upd = ['paused_at' => null, 'updated_at' => $now];
            if (!empty($s['end_at']) && (int) $s['end_at'] > $pausedAt) {
                $upd['end_at'] = (int) $s['end_at'] + ($now - $pausedAt);
            }
            $db->update('exam_sessions', $upd, 'id = ?', [(int) $s['id']]);
            $s2 = array_merge($s, $upd);
            foreach ($db->all("SELECT * FROM {attempts} WHERE session_id = ? AND status = 'in_progress'", [(int) $s['id']]) as $a) {
                $overlap = $now - max($pausedAt, (int) $a['started_at']);
                if ($overlap <= 0) {
                    continue;
                }
                $a['hold_sec'] = (int) $a['hold_sec'] + $overlap;
                $db->update('attempts', [
                    'hold_sec' => $a['hold_sec'],
                    'deadline_at' => Attempts::computeDeadline($a, $s2),
                    'updated_at' => $now,
                ], 'id = ?', [(int) $a['id']]);
                Attempts::event((int) $a['id'], 'resume_time', ['session_pause' => $overlap]);
            }
        });
        self::broadcast((int) $s['id'], 'Ca thi đã được TIẾP TỤC. Các em tiếp tục làm bài.', 'info');
    }

    /** Cộng giờ cho tất cả bài đang làm. */
    public static function addTimeAll(array $s, int $minutes): int
    {
        $db = App::db();
        $n = 0;
        foreach ($db->all("SELECT * FROM {attempts} WHERE session_id = ? AND status = 'in_progress'", [(int) $s['id']]) as $a) {
            Attempts::addTime($a, $s, $minutes, false);
            $n++;
        }
        self::broadcast((int) $s['id'], 'Giám thị đã cộng thêm ' . $minutes . ' phút làm bài cho cả phòng thi.', 'info');
        return $n;
    }

    /** Kết thúc ca thi và thu tất cả bài đang làm. */
    public static function close(array $s): int
    {
        $db = App::db();
        $now = time();
        $db->update('exam_sessions', ['status' => 'closed', 'paused_at' => null, 'end_at' => $s['end_at'] && (int) $s['end_at'] < $now ? $s['end_at'] : $now, 'updated_at' => $now], 'id = ?', [(int) $s['id']]);
        $s = $db->one('SELECT * FROM {exam_sessions} WHERE id = ?', [(int) $s['id']]);
        $n = 0;
        foreach ($db->all("SELECT * FROM {attempts} WHERE session_id = ? AND status = 'in_progress'", [(int) $s['id']]) as $a) {
            if (Attempts::finalize($a, 'session_closed', $s)) {
                $n++;
            }
        }
        return $n;
    }

    public static function broadcast(int $sid, string $message, string $level = 'info', ?int $userId = null): void
    {
        App::db()->insert('session_messages', [
            'session_id' => $sid,
            'user_id' => $userId,
            'message' => mb_substr($message, 0, 1000),
            'level' => in_array($level, ['info', 'warning', 'danger', 'success'], true) ? $level : 'info',
            'created_by' => isset($_SESSION['_uid']) ? (int) $_SESSION['_uid'] : null,
            'created_at' => time(),
        ]);
    }

    public static function messagesFor(int $sid, int $userId, int $afterId = 0): array
    {
        return App::db()->all(
            'SELECT id, message, level, created_at FROM {session_messages} WHERE session_id = ? AND (user_id IS NULL OR user_id = ?) AND id > ? ORDER BY id LIMIT 50',
            [$sid, $userId, $afterId]
        );
    }
}
