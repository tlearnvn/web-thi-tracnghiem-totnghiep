<?php

namespace App\Lib;

use App\Core\App;
use App\Core\Logger;

/**
 * Nghiệp vụ bài làm: bắt đầu, tính giờ phía máy chủ, lưu tiến độ, nộp bài, chấm điểm, can thiệp của giám thị.
 */
final class Attempts
{
    public const STATUS = [
        'in_progress' => ['Đang làm', 'primary'],
        'submitted' => ['Đã nộp', 'success'],
        'expired' => ['Hết giờ – tự thu', 'warning'],
        'forced' => ['Bị thu bài', 'warning'],
        'voided' => ['Hủy bài', 'danger'],
    ];

    public const REASONS = [
        'manual' => 'Học sinh nộp bài',
        'timeout' => 'Hết giờ, hệ thống tự thu',
        'violation' => 'Vi phạm quá số lần cho phép',
        'proctor' => 'Giám thị thu bài',
        'session_closed' => 'Kết thúc ca thi',
    ];

    public const EVENTS = [
        'start' => ['Bắt đầu làm bài', 'circle-play'],
        'resume' => ['Vào lại bài thi', 'refresh-cw'],
        'answer' => ['Chọn / đổi đáp án', 'pencil'],
        'submit' => ['Nộp bài', 'circle-check'],
        'leave' => ['Rời khỏi màn hình làm bài', 'triangle-alert'],
        'fullscreen_exit' => ['Thoát toàn màn hình', 'minimize'],
        'copy' => ['Thử sao chép / chụp', 'copy'],
        'print' => ['Thử in / lưu trang', 'printer'],
        'contextmenu' => ['Nhấn chuột phải', 'circle-dot'],
        'offline' => ['Mất kết nối mạng', 'wifi-off'],
        'online' => ['Có mạng trở lại', 'wifi'],
        'reclaim' => ['Tự nhận lại thiết bị (cùng máy)', 'laptop'],
        'device_blocked' => ['Bị chặn: đang làm trên thiết bị khác', 'shield-alert'],
        'unlock' => ['Giám thị mở khóa thiết bị', 'lock-open'],
        'add_time' => ['Được cộng thêm thời gian', 'timer'],
        'pause' => ['Tạm dừng tính giờ', 'circle-pause'],
        'resume_time' => ['Tiếp tục tính giờ', 'circle-play'],
        'force_submit' => ['Giám thị thu bài', 'hourglass'],
        'reopen' => ['Mở lại bài để làm tiếp', 'lock-open'],
        'void' => ['Giám thị hủy bài – cho thi lại từ đầu', 'rotate-ccw'],
        'violation_lock' => ['Bị tạm khóa do vi phạm', 'lock'],
        'violation_unlock' => ['Giám thị mở khóa vi phạm', 'lock-open'],
        'multi_tab' => ['Mở bài thi ở nhiều tab', 'layers'],
        'relogin' => ['Đăng nhập lại trong phòng thi', 'log-in'],
        'grade' => ['Chấm điểm tự luận', 'pen-line'],
        'rescore' => ['Chấm lại', 'rotate-ccw'],
        'timeout' => ['Hết giờ – tự thu bài', 'alarm-clock'],
        'message' => ['Tin nhắn riêng từ giám thị', 'message-square'],
    ];

    private static array $keyCache = [];
    private static array $examCache = [];

    public static function find(int $id): ?array
    {
        return $id > 0 ? App::db()->one('SELECT * FROM {attempts} WHERE id = ?', [$id]) : null;
    }

    public static function exam(int $examId): ?array
    {
        if (!array_key_exists($examId, self::$examCache)) {
            $e = App::db()->one('SELECT * FROM {exams} WHERE id = ?', [$examId]);
            if ($e) {
                $e['_structure'] = ExamFormat::normalizeStructure($e['structure']);
                $e['_scoring'] = Scoring::normalize($e['scoring']);
            }
            self::$examCache[$examId] = $e;
        }
        return self::$examCache[$examId];
    }

    public static function forgetExam(int $examId): void
    {
        unset(self::$examCache[$examId]);
    }

    /** Đáp án của một mã đề: ['p1.1' => [...], 'p2.1' => [...], 'e.1' => [...]] */
    public static function keys(int $variantId, bool $fresh = false): array
    {
        if ($fresh || !isset(self::$keyCache[$variantId])) {
            $map = [1 => 'p1', 2 => 'p2', 3 => 'p3', 4 => 'e'];
            $out = [];
            foreach (App::db()->all('SELECT * FROM {exam_keys} WHERE variant_id = ? ORDER BY part, num', [$variantId]) as $k) {
                $out[($map[(int) $k['part']] ?? 'p' . $k['part']) . '.' . $k['num']] = $k;
            }
            self::$keyCache[$variantId] = $out;
        }
        return self::$keyCache[$variantId];
    }

    public static function forgetKeys(int $variantId): void
    {
        unset(self::$keyCache[$variantId]);
    }

    // ------------------------------------------------------------ Thời gian

    /** Hạn nộp lưu trong CSDL (chưa tính các lần đang tạm dừng). 0 = không giới hạn. */
    public static function computeDeadline(array $a, array $s): int
    {
        if ((int) $a['duration_sec'] <= 0) {
            return 0;
        }
        $base = (int) $a['started_at'] + (int) $a['duration_sec'] + (int) $a['hold_sec'];
        $o = Sessions::options($s);
        if ($o['time_policy'] === 'cap' && !empty($s['end_at'])) {
            $base = min($base, (int) $s['end_at']);
        }
        return $base + (int) $a['extra_sec'] + (int) $a['paused_total'];
    }

    public static function isPaused(array $a, array $s): bool
    {
        return !empty($a['paused_at']) || !empty($s['paused_at']);
    }

    /** Hạn nộp thực tế tại thời điểm $now (đã cộng thời gian đang tạm dừng). */
    public static function effectiveDeadline(array $a, array $s, ?int $now = null): int
    {
        $d = (int) $a['deadline_at'];
        if ($d <= 0) {
            return 0;
        }
        $now = $now ?? time();
        $starts = [];
        if (!empty($a['paused_at'])) {
            $starts[] = (int) $a['paused_at'];
        }
        if (!empty($s['paused_at'])) {
            $starts[] = max((int) $s['paused_at'], (int) $a['started_at']);
        }
        if ($starts) {
            $d += max(0, $now - min($starts));
        }
        return $d;
    }

    /** Số giây còn lại; null = không giới hạn. */
    public static function remaining(array $a, array $s, ?int $now = null): ?int
    {
        $d = self::effectiveDeadline($a, $s, $now);
        if ($d === 0) {
            return null;
        }
        return max(0, $d - ($now ?? time()));
    }

    // ------------------------------------------------------------ Bắt đầu

    /**
     * Tạo bài làm mới (hoặc trả về bài đang làm dở).
     * @return array{0: ?array, 1: ?string}
     */
    public static function start(array $s, array $exam, array $user): array
    {
        $db = App::db();
        $now = time();
        $uid = (int) $user['id'];
        $sid = (int) $s['id'];

        $existing = $db->one("SELECT * FROM {attempts} WHERE session_id = ? AND user_id = ? AND status = 'in_progress' ORDER BY id DESC", [$sid, $uid]);
        if ($existing) {
            return [$existing, null];
        }
        $state = Sessions::state($s);
        if ($state === 'upcoming') {
            return [null, 'Ca thi chưa bắt đầu.'];
        }
        if (in_array($state, ['ended', 'closed'], true)) {
            return [null, 'Ca thi đã kết thúc.'];
        }
        if ($state === 'paused') {
            return [null, 'Ca thi đang tạm dừng, vui lòng chờ giám thị.'];
        }
        $count = (int) $db->value("SELECT COUNT(*) FROM {attempts} WHERE session_id = ? AND user_id = ? AND status <> 'voided'", [$sid, $uid]);
        $nextNo = (int) $db->value('SELECT COALESCE(MAX(attempt_no), 0) FROM {attempts} WHERE session_id = ? AND user_id = ?', [$sid, $uid]) + 1;
        $max = (int) $s['max_attempts'];
        if ($max > 0 && $count >= $max) {
            return [null, $max === 1 ? 'Em đã làm bài thi này rồi.' : 'Em đã dùng hết ' . $max . ' lượt làm bài.'];
        }
        if ((int) $s['late_join'] > 0 && !empty($s['start_at']) && $now > (int) $s['start_at'] + (int) $s['late_join'] * 60) {
            return [null, 'Đã hết thời gian cho phép vào phòng thi (' . (int) $s['late_join'] . ' phút sau giờ bắt đầu).'];
        }
        $variantId = Sessions::pickVariant($s);
        if (!$variantId) {
            return [null, 'Đề thi chưa có mã đề nào. Vui lòng báo giáo viên.'];
        }
        $o = Sessions::options($s);
        $a = [
            'session_id' => $sid,
            'exam_id' => (int) $exam['id'],
            'variant_id' => $variantId,
            'user_id' => $uid,
            'attempt_no' => $nextNo,
            'status' => 'in_progress',
            'started_at' => $now,
            'duration_sec' => Sessions::durationSec($s, $exam),
            'extra_sec' => 0,
            'paused_at' => null,
            'paused_total' => 0,
            'hold_sec' => 0,
            'deadline_at' => 0,
            'last_seen_at' => $now,
            'last_saved_at' => null,
            'answers' => '{}',
            'flags' => '[]',
            'seq' => 0,
            'answered' => 0,
            'device_token' => random_token(16),
            'device_info' => describe_ua(user_agent()),
            'ip' => client_ip(),
            'violations' => 0,
            'locked' => 0,
            'score' => null,
            'grading_status' => 'auto',
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $a['deadline_at'] = self::computeDeadline($a, $s);
        if ($a['deadline_at'] > 0 && $a['deadline_at'] <= $now + 30) {
            return [null, 'Ca thi sắp kết thúc, không đủ thời gian để bắt đầu làm bài.'];
        }
        try {
            $id = $db->insert('attempts', $a);
        } catch (\PDOException $e) {
            // Bấm "Bắt đầu" hai lần cùng lúc: lấy bài vừa tạo
            $row = $db->one("SELECT * FROM {attempts} WHERE session_id = ? AND user_id = ? AND status = 'in_progress' ORDER BY id DESC", [$sid, $uid]);
            if ($row) {
                return [$row, null];
            }
            throw $e;
        }
        self::event($id, 'start', ['variant' => $variantId, 'device' => $a['device_info']]);
        Logger::audit('attempt.start', 'attempt', $id, ['session' => $sid]);
        unset($o);
        return [self::find($id), null];
    }

    // ------------------------------------------------------------ Lưu bài

    /**
     * Lưu bài làm (toàn bộ trạng thái, có số thứ tự $seq tăng dần để bỏ qua yêu cầu cũ đến muộn).
     */
    public static function save(array $a, array $structure, $answers, $flags, int $seq): array
    {
        $db = App::db();
        $now = time();
        if ($seq <= (int) $a['seq']) {
            $db->update('attempts', ['last_seen_at' => $now], 'id = ?', [(int) $a['id']]);
            return ['seq' => (int) $a['seq'], 'ignored' => true, 'answered' => (int) $a['answered']];
        }
        $clean = KeyFormat::sanitizeAnswers($answers, $structure);
        $old = json_dec($a['answers'], []);
        $diff = self::diff($old, $clean);
        $flagList = [];
        foreach ((array) $flags as $f) {
            if (is_string($f) && preg_match('/^(p1|p2|p3|e)\.\d{1,3}$/', $f)) {
                $flagList[] = $f;
            }
        }
        $flagList = array_slice(array_values(array_unique($flagList)), 0, 300);
        $answered = KeyFormat::countAnswered($clean);
        $n = $db->run(
            "UPDATE {attempts} SET answers = ?, flags = ?, seq = ?, answered = ?, last_saved_at = ?, last_seen_at = ?, updated_at = ?
             WHERE id = ? AND status = 'in_progress' AND seq < ?",
            [json_enc($clean), json_enc($flagList), $seq, $answered, $now, $now, $now, (int) $a['id'], $seq]
        )->rowCount();
        if ($n > 0 && $diff) {
            self::event((int) $a['id'], 'answer', $diff);
        }
        return ['seq' => $n > 0 ? $seq : (int) $a['seq'], 'ignored' => $n === 0, 'answered' => $answered];
    }

    /** Các câu thay đổi giữa 2 lần lưu (để ghi nhật ký phục vụ giải quyết khiếu nại). */
    public static function diff(array $old, array $new): array
    {
        $d = [];
        foreach (['p1', 'p2', 'p3', 'e'] as $p) {
            $o = (array) ($old[$p] ?? []);
            $n = (array) ($new[$p] ?? []);
            foreach ($n + $o as $k => $_) {
                $ov = $o[$k] ?? '';
                $nv = $n[$k] ?? '';
                if ((string) $ov !== (string) $nv) {
                    $d[$p . '.' . $k] = $p === 'e' ? ('[' . mb_strlen((string) $nv) . ' ký tự]') : (string) $nv;
                }
            }
        }
        return $d;
    }

    // ------------------------------------------------------------ Chấm & nộp

    public static function score(array $a, ?array $exam = null): array
    {
        $exam = $exam ?? self::exam((int) $a['exam_id']);
        $structure = $exam['_structure'] ?? ExamFormat::normalizeStructure($exam['structure']);
        $scoring = $exam['_scoring'] ?? Scoring::normalize($exam['scoring']);
        return Scoring::compute(
            $structure,
            $scoring,
            self::keys((int) $a['variant_id']),
            json_dec($a['answers'], []),
            json_dec($a['essay_scores'] ?? null, [])
        );
    }

    /** Nộp bài (chỉ một lần – an toàn khi nhiều yêu cầu đến cùng lúc). */
    public static function finalize(array $a, string $reason, ?array $s = null): ?array
    {
        if ($a['status'] !== 'in_progress') {
            return null;
        }
        $db = App::db();
        $now = time();
        $s = $s ?? $db->one('SELECT * FROM {exam_sessions} WHERE id = ?', [(int) $a['session_id']]);
        $a = self::find((int) $a['id']) ?? $a;
        if ($a['status'] !== 'in_progress') {
            return null;
        }
        $r = self::score($a);
        $status = $reason === 'timeout' ? 'expired' : (in_array($reason, ['proctor', 'violation', 'session_closed'], true) ? 'forced' : 'submitted');
        $submittedAt = $now;
        if ($reason === 'timeout' && $s) {
            $eff = self::effectiveDeadline($a, $s, $now);
            if ($eff > 0 && $eff < $now) {
                $submittedAt = $eff;
            }
        }
        $n = $db->run(
            "UPDATE {attempts} SET status = ?, submit_reason = ?, submitted_at = ?, score = ?, score_detail = ?, grading_status = ?, paused_at = NULL, updated_at = ?
             WHERE id = ? AND status = 'in_progress'",
            [$status, $reason, $submittedAt, $r['score'], json_enc(Scoring::summary($r)), $r['pending'] ? 'pending' : 'auto', $now, (int) $a['id']]
        )->rowCount();
        if ($n === 0) {
            return null;
        }
        self::event((int) $a['id'], $reason === 'timeout' ? 'timeout' : ($reason === 'proctor' ? 'force_submit' : 'submit'), ['score' => $r['score'], 'reason' => $reason]);
        Logger::audit('attempt.submit', 'attempt', (int) $a['id'], ['reason' => $reason, 'score' => $r['score']]);
        return self::find((int) $a['id']);
    }

    /** Tự thu các bài đã hết giờ (chạy khi có truy cập – không cần cron). */
    public static function finalizeExpired(?int $sessionId = null): int
    {
        $db = App::db();
        $now = time();
        $sql = "SELECT * FROM {attempts} WHERE status = 'in_progress' AND deadline_at > 0 AND deadline_at < ?";
        $params = [$now - 15];
        if ($sessionId) {
            $sql .= ' AND session_id = ?';
            $params[] = $sessionId;
        }
        $rows = $db->all($sql . ' ORDER BY deadline_at LIMIT 300', $params);
        $sessions = [];
        $n = 0;
        foreach ($rows as $a) {
            $sid = (int) $a['session_id'];
            if (!isset($sessions[$sid])) {
                $sessions[$sid] = $db->one('SELECT * FROM {exam_sessions} WHERE id = ?', [$sid]);
            }
            $s = $sessions[$sid];
            if (!$s) {
                continue;
            }
            $grace = Sessions::options($s)['grace_seconds'];
            $eff = self::effectiveDeadline($a, $s, $now);
            if ($eff > 0 && $now > $eff + $grace) {
                if (self::finalize($a, 'timeout', $s)) {
                    $n++;
                }
            }
        }
        return $n;
    }

    /** Chấm lại các bài đã nộp (sau khi sửa đáp án / hủy câu). */
    public static function rescore(array $attemptIds): int
    {
        $db = App::db();
        $n = 0;
        foreach (array_chunk($attemptIds, 200) as $chunk) {
            foreach ($db->all('SELECT * FROM {attempts} WHERE id IN ' . $db->in($chunk), $chunk) as $a) {
                if ($a['status'] === 'in_progress') {
                    continue;
                }
                self::forgetKeys((int) $a['variant_id']);
                $r = self::score($a);
                $db->update('attempts', [
                    'score' => $r['score'],
                    'score_detail' => json_enc(Scoring::summary($r)),
                    'grading_status' => $r['pending'] ? 'pending' : ($a['grading_status'] === 'graded' ? 'graded' : 'auto'),
                    'updated_at' => time(),
                ], 'id = ?', [(int) $a['id']]);
                $n++;
            }
        }
        return $n;
    }

    // ------------------------------------------------------------ Can thiệp của giám thị

    public static function addTime(array $a, array $s, int $minutes, bool $log = true): void
    {
        $a['extra_sec'] = max(-86400, (int) $a['extra_sec'] + $minutes * 60);
        App::db()->update('attempts', [
            'extra_sec' => $a['extra_sec'],
            'deadline_at' => self::computeDeadline($a, $s),
            'updated_at' => time(),
        ], 'id = ?', [(int) $a['id']]);
        self::event((int) $a['id'], 'add_time', ['minutes' => $minutes]);
        if ($log) {
            Sessions::broadcast((int) $s['id'], 'Em được cộng thêm ' . $minutes . ' phút làm bài.', 'info', (int) $a['user_id']);
        }
    }

    public static function pause(array $a): void
    {
        if (!empty($a['paused_at']) || $a['status'] !== 'in_progress') {
            return;
        }
        App::db()->update('attempts', ['paused_at' => time(), 'updated_at' => time()], 'id = ?', [(int) $a['id']]);
        self::event((int) $a['id'], 'pause');
    }

    public static function resume(array $a, array $s): void
    {
        if (empty($a['paused_at'])) {
            return;
        }
        $now = time();
        $a['paused_total'] = (int) $a['paused_total'] + max(0, $now - (int) $a['paused_at']);
        App::db()->update('attempts', [
            'paused_at' => null,
            'paused_total' => $a['paused_total'],
            'locked' => 0,
            'deadline_at' => self::computeDeadline($a, $s),
            'updated_at' => $now,
        ], 'id = ?', [(int) $a['id']]);
        self::event((int) $a['id'], 'resume_time', ['paused' => $now - (int) $a['paused_at']]);
    }

    /** Mở khóa thiết bị: lần truy cập tiếp theo (máy mới) sẽ được nhận bài. */
    public static function unlockDevice(array $a): void
    {
        App::db()->update('attempts', ['device_token' => null, 'device_prev' => $a['device_token'] ?: ($a['device_prev'] ?? null), 'updated_at' => time()], 'id = ?', [(int) $a['id']]);
        self::event((int) $a['id'], 'unlock');
    }

    /** Mở lại bài đã nộp (nộp nhầm, sự cố…) – có thể cộng thêm thời gian. */
    public static function reopen(array $a, array $s, int $extraMinutes = 0): void
    {
        $now = time();
        $a['status'] = 'in_progress';
        $a['extra_sec'] = (int) $a['extra_sec'];
        $eff = self::effectiveDeadline($a, $s, $now);
        if ($eff > 0 && $eff < $now + 60 && $extraMinutes <= 0) {
            $extraMinutes = 5;
        }
        if ($eff > 0 && $eff < $now) {
            $a['extra_sec'] += $now - $eff;
        }
        $a['extra_sec'] += $extraMinutes * 60;
        App::db()->update('attempts', [
            'status' => 'in_progress',
            'submitted_at' => null,
            'submit_reason' => null,
            'score' => null,
            'score_detail' => null,
            'extra_sec' => $a['extra_sec'],
            'deadline_at' => self::computeDeadline($a, $s),
            'locked' => 0,
            'updated_at' => $now,
        ], 'id = ?', [(int) $a['id']]);
        self::event((int) $a['id'], 'reopen', ['extra' => $extraMinutes]);
    }

    /** Khóa làm rối tệp PDF gửi cho một bài làm (mỗi bài một khóa riêng). */
    public static function pdfKey(array $a): string
    {
        return base64_encode(hash_hmac('sha256', 'pdf|' . $a['id'] . '|' . $a['variant_id'], App::secret(), true));
    }

    public static function event(int $attemptId, string $type, $data = null): void
    {
        try {
            App::db()->insert('attempt_events', [
                'attempt_id' => $attemptId,
                'type' => $type,
                'data' => $data === null ? null : (is_string($data) ? $data : json_enc($data)),
                'ip' => client_ip(),
                'created_at' => time(),
            ]);
        } catch (\Throwable $e) {
            // bỏ qua
        }
    }

    public static function statusBadge(array $a): string
    {
        $st = self::STATUS[$a['status']] ?? [$a['status'], 'default'];
        if ($a['status'] === 'in_progress' && !empty($a['paused_at'])) {
            return badge('Tạm dừng', 'warning', 'circle-pause');
        }
        return badge($st[0], $st[1]);
    }

    /** Xóa hẳn một bài làm (cho thi lại từ đầu). */
    public static function delete(int $id): void
    {
        $db = App::db();
        $db->run('DELETE FROM {attempt_events} WHERE attempt_id = ?', [$id]);
        $db->run('DELETE FROM {attempts} WHERE id = ?', [$id]);
    }
}
