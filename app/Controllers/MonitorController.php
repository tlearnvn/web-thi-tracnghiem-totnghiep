<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Session;
use App\Lib\Attempts;
use App\Lib\ExamFormat;
use App\Lib\Sessions;

/**
 * Giám sát ca thi theo thời gian thực: ai đang làm, mất kết nối, vi phạm, còn bao nhiêu giờ…
 * và các thao tác xử lý sự cố cho từng học sinh (cộng giờ, mở khóa máy, tạm dừng, thu bài, mở lại, cho thi lại).
 */
final class MonitorController extends Controller
{
    /** Học sinh coi là "đang kết nối" nếu có tín hiệu trong khoảng này (giây). */
    private const ONLINE_WINDOW = 50;

    public function index(): void
    {
        // Giám thị được phân công (không cần quyền xem kết quả) hoặc người xem được kết quả ca thi
        $s = SessionsController::sessionFor(Request::int('id'), ['proctor', 'results']);
        Attempts::finalizeExpired((int) $s['id']);
        $exam = Attempts::exam((int) $s['exam_id']);
        $classes = $this->db->all('SELECT c.id, c.name FROM {session_targets} t JOIN {classes} c ON c.id = t.class_id WHERE t.session_id = ? ORDER BY c.sort_key, c.name', [(int) $s['id']]);
        $this->render('monitor/index', [
            'title' => 'Giám sát: ' . $s['name'],
            'crumbs' => ['Ca thi' => url('sessions'), $s['name'] => url('sessions/view', ['id' => $s['id']]), 'Giám sát' => null],
            's' => $s,
            'o' => Sessions::options($s),
            'exam' => $exam,
            'classes' => $classes,
            'canAct' => $s['_access']['proctor'],
            'canManage' => $s['_access']['manage'],
            'canResults' => $s['_access']['results'],
            'total' => ExamFormat::totalQuestions($exam['_structure']),
        ]);
    }

    /** Dữ liệu trực tiếp (gọi định kỳ vài giây/lần). */
    public function data(): void
    {
        $s = SessionsController::sessionFor(Request::int('id'), ['proctor', 'results']);
        Session::release();
        $showScore = $s['_access']['results'];
        $sid = (int) $s['id'];
        Attempts::finalizeExpired($sid);
        $s = $this->db->one('SELECT * FROM {exam_sessions} WHERE id = ?', [$sid]);
        $now = time();
        $exam = Attempts::exam((int) $s['exam_id']);
        $total = ExamFormat::totalQuestions($exam['_structure']);

        $students = [];
        foreach (Sessions::students($sid) as $u) {
            $students[(int) $u['id']] = $u;
        }
        $attempts = $this->db->all(
            'SELECT id, user_id, attempt_no, status, variant_id, started_at, submitted_at, submit_reason, deadline_at, duration_sec, extra_sec, paused_at, paused_total, hold_sec,
                    last_seen_at, last_saved_at, answered, violations, locked, score, grading_status, device_info, ip, device_token
             FROM {attempts} WHERE session_id = ? ORDER BY attempt_no',
            [$sid]
        );
        // Học sinh có bài làm nhưng không còn thuộc danh sách dự thi (chuyển lớp…)
        $missing = array_diff(array_unique(array_map(static fn($a) => (int) $a['user_id'], $attempts)), array_keys($students));
        if ($missing) {
            foreach ($this->db->all('SELECT u.id, u.full_name, u.code, u.username, u.class_id, u.sort_key, c.name AS class_name FROM {users} u LEFT JOIN {classes} c ON c.id = u.class_id WHERE u.id IN ' . $this->db->in(array_values($missing)), array_values($missing)) as $u) {
                $students[(int) $u['id']] = $u + ['_extra' => true];
            }
        }
        $byUser = [];
        foreach ($attempts as $a) {
            $byUser[(int) $a['user_id']][] = $a;
        }
        $aids = array_map(static fn($a) => (int) $a['id'], $attempts);
        $blocked = [];
        if ($aids) {
            foreach (array_chunk($aids, 500) as $chunk) {
                foreach ($this->db->all("SELECT attempt_id, MAX(created_at) AS t FROM {attempt_events} WHERE type = 'device_blocked' AND created_at > ? AND attempt_id IN " . $this->db->in($chunk) . ' GROUP BY attempt_id', array_merge([$now - 180], $chunk)) as $r) {
                    $blocked[(int) $r['attempt_id']] = (int) $r['t'];
                }
            }
        }
        $variants = $this->db->keyed('SELECT id, code FROM {exam_variants} WHERE exam_id = ?', [(int) $s['exam_id']], 'id', 'code');

        $rows = [];
        $count = ['total' => 0, 'doing' => 0, 'online' => 0, 'offline' => 0, 'none' => 0, 'done' => 0, 'violations' => 0, 'locked' => 0, 'blocked' => 0];
        foreach ($students as $uid => $u) {
            $list = $byUser[$uid] ?? [];
            $cur = null;
            foreach ($list as $a) {
                if ($a['status'] === 'in_progress') {
                    $cur = $a;
                }
            }
            if (!$cur) {
                $valid = array_values(array_filter($list, static fn($a) => $a['status'] !== 'voided'));
                $cur = $valid ? end($valid) : ($list ? end($list) : null);
            }
            $row = [
                'uid' => $uid,
                'name' => $u['full_name'],
                'code' => $u['code'] ?: $u['username'],
                'cls' => $u['class_name'] ?? '',
                'sk' => (string) ($u['sort_key'] ?? ''),
                'extra' => !empty($u['_extra']),
                'n' => count(array_filter($list, static fn($a) => $a['status'] !== 'voided')),
            ];
            $count['total']++;
            if (!$cur) {
                $row['st'] = 'none';
                $count['none']++;
            } else {
                $a = $cur;
                $st = $a['status'];
                $online = (int) $a['last_seen_at'] >= $now - self::ONLINE_WINDOW;
                if ($st === 'in_progress') {
                    $count['doing']++;
                    if (!empty($a['paused_at']) || (int) $a['locked']) {
                        $row['st'] = (int) $a['locked'] ? 'locked' : 'paused';
                        if ((int) $a['locked']) {
                            $count['locked']++;
                        }
                    } else {
                        $row['st'] = $online ? 'online' : 'offline';
                    }
                    $count[$online ? 'online' : 'offline']++;
                } elseif ($st === 'voided') {
                    $row['st'] = 'voided';
                    $count['none']++;
                } else {
                    $row['st'] = 'done';
                    $count['done']++;
                }
                if ((int) $a['violations'] > 0) {
                    $count['violations']++;
                }
                $isBlocked = isset($blocked[(int) $a['id']]) && $st === 'in_progress';
                if ($isBlocked) {
                    $count['blocked']++;
                }
                $row += [
                    'aid' => (int) $a['id'],
                    'no' => (int) $a['attempt_no'],
                    'status' => $st,
                    'answered' => (int) $a['answered'],
                    'rem' => $st === 'in_progress' ? Attempts::remaining($a, $s, $now) : null,
                    'paused' => !empty($a['paused_at']) || !empty($s['paused_at']),
                    'locked' => (int) $a['locked'] === 1,
                    'viol' => (int) $a['violations'],
                    'seen' => $a['last_seen_at'] ? (int) $a['last_seen_at'] : null,
                    'saved' => $a['last_saved_at'] ? (int) $a['last_saved_at'] : null,
                    'started' => (int) $a['started_at'],
                    'sub' => $a['submitted_at'] ? (int) $a['submitted_at'] : null,
                    'reason' => $a['submit_reason'],
                    'score' => $showScore && $a['score'] !== null ? (float) $a['score'] : null,
                    'pending' => $showScore && $a['grading_status'] === 'pending',
                    'dev' => (string) $a['device_info'],
                    'ip' => (string) $a['ip'],
                    'free' => $a['device_token'] === null || $a['device_token'] === '',
                    'blocked' => $isBlocked,
                    'var' => $variants[$a['variant_id']] ?? '',
                    'extraMin' => (int) round((int) $a['extra_sec'] / 60),
                ];
            }
            $rows[] = $row;
        }
        usort($rows, static fn($x, $y) => [$x['cls'], $x['sk']] <=> [$y['cls'], $y['sk']]);

        $since = Request::int('since');
        $events = $this->db->all(
            "SELECT e.id, e.attempt_id, e.type, e.data, e.created_at, u.full_name, u.id AS uid
             FROM {attempt_events} e JOIN {attempts} a ON a.id = e.attempt_id JOIN {users} u ON u.id = a.user_id
             WHERE a.session_id = ? AND e.type <> 'answer' AND e.id > ? ORDER BY e.id DESC LIMIT 60",
            [$sid, $since]
        );
        foreach ($events as &$ev) {
            $info = Attempts::EVENTS[$ev['type']] ?? [$ev['type'], 'circle'];
            $ev['label'] = $info[0];
            $ev['icon'] = $info[1];
            $ev['level'] = in_array($ev['type'], ['leave', 'fullscreen_exit', 'device_blocked', 'violation_lock', 'multi_tab', 'print', 'copy', 'offline'], true) ? 'warning'
                : (in_array($ev['type'], ['submit', 'timeout', 'force_submit'], true) ? 'success' : 'info');
            $ev['detail'] = self::eventDetail($ev['type'], json_dec($ev['data'], []), $showScore);
            unset($ev['data']);
        }
        unset($ev);

        $this->ok([
            'now' => $now,
            'session' => [
                'state' => Sessions::state($s, $now),
                'paused' => !empty($s['paused_at']),
                'start_at' => $s['start_at'] ? (int) $s['start_at'] : null,
                'end_at' => $s['end_at'] ? (int) $s['end_at'] : null,
                'released' => (int) $s['released'] === 1,
            ],
            'total' => $total,
            'count' => $count,
            'rows' => $rows,
            'events' => $events,
            'messages' => $this->db->all('SELECT m.id, m.message, m.level, m.user_id, m.created_at, u.full_name FROM {session_messages} m LEFT JOIN {users} u ON u.id = m.user_id WHERE m.session_id = ? ORDER BY m.id DESC LIMIT 20', [$sid]),
        ]);
    }

    private static function eventDetail(string $type, array $d, bool $showScore = true): string
    {
        switch ($type) {
            case 'leave':
            case 'fullscreen_exit':
                return trim(($d['detail'] ?? '') . (!empty($d['counted']) ? ' · lần ' . ($d['n'] ?? '') : ' · không tính (trùng)'), ' ·');
            case 'add_time':
                return (($d['minutes'] ?? 0) > 0 ? '+' : '') . ($d['minutes'] ?? 0) . ' phút';
            case 'submit':
            case 'timeout':
            case 'force_submit':
                return $showScore && isset($d['score']) ? 'Điểm ' . fmt_score($d['score']) : '';
            case 'reopen':
                return !empty($d['extra']) ? '+' . $d['extra'] . ' phút' : '';
            case 'resume_time':
                return isset($d['paused']) ? 'tạm dừng ' . fmt_duration((int) $d['paused']) : (isset($d['session_pause']) ? 'cả phòng tạm dừng ' . fmt_duration((int) $d['session_pause']) : '');
            case 'start':
            case 'resume':
            case 'reclaim':
            case 'device_blocked':
                return (string) ($d['device'] ?? '');
            default:
                return (string) ($d['detail'] ?? '');
        }
    }

    /** Thao tác với bài làm của một hoặc nhiều học sinh. */
    public function act(): void
    {
        $this->requirePost();
        $s = SessionsController::sessionFor(Request::int('id'), 'proctor');
        if (!$s['_access']['proctor']) {
            throw new HttpException(403, 'Bạn không được phân công giám sát ca thi này.');
        }
        $action = Request::str('action');
        $aids = Request::ints('aids');
        if (!$aids && Request::int('aid')) {
            $aids = [Request::int('aid')];
        }
        if (!$aids) {
            $this->fail('Chưa chọn học sinh.');
            return;
        }
        $done = 0;
        $skipped = [];
        foreach ($aids as $aid) {
            $a = Attempts::find($aid);
            if (!$a || (int) $a['session_id'] !== (int) $s['id']) {
                continue;
            }
            $s = $this->db->one('SELECT * FROM {exam_sessions} WHERE id = ?', [(int) $s['id']]) + ['_access' => $s['_access']];
            $name = (string) $this->db->value('SELECT full_name FROM {users} WHERE id = ?', [(int) $a['user_id']]);
            $running = $a['status'] === 'in_progress';
            switch ($action) {
                case 'add_time':
                    $m = max(-60, min(180, Request::int('minutes', 5)));
                    if (!$running || $m === 0) {
                        $skipped[] = $name;
                        continue 2;
                    }
                    Attempts::addTime($a, $s, $m);
                    break;
                case 'pause':
                    if (!$running) {
                        $skipped[] = $name;
                        continue 2;
                    }
                    Attempts::pause($a);
                    Sessions::broadcast((int) $s['id'], 'Giám thị đã tạm dừng bài làm của em. Thời gian được giữ nguyên, em chờ hướng dẫn.', 'warning', (int) $a['user_id']);
                    break;
                case 'resume':
                case 'unlock_violation':
                    if (!$running) {
                        $skipped[] = $name;
                        continue 2;
                    }
                    if ((int) $a['locked'] === 1) {
                        Attempts::event((int) $a['id'], 'violation_unlock');
                    }
                    if (!empty($a['paused_at'])) {
                        Attempts::resume($a, $s);
                    } else {
                        $this->db->update('attempts', ['locked' => 0, 'updated_at' => time()], 'id = ?', [(int) $a['id']]);
                    }
                    Sessions::broadcast((int) $s['id'], 'Giám thị đã cho em tiếp tục làm bài.', 'success', (int) $a['user_id']);
                    break;
                case 'unlock_device':
                    if (!$running) {
                        $skipped[] = $name;
                        continue 2;
                    }
                    Attempts::unlockDevice($a);
                    break;
                case 'force_submit':
                    if (!$running) {
                        $skipped[] = $name;
                        continue 2;
                    }
                    Attempts::finalize($a, 'proctor', $s);
                    break;
                case 'reopen':
                    if ($running || $a['status'] === 'voided') {
                        $skipped[] = $name;
                        continue 2;
                    }
                    if ($this->db->value("SELECT 1 FROM {attempts} WHERE session_id = ? AND user_id = ? AND status = 'in_progress'", [(int) $s['id'], (int) $a['user_id']])) {
                        $skipped[] = $name;
                        continue 2;
                    }
                    Attempts::reopen($a, $s, max(0, min(180, Request::int('minutes', 0))));
                    Sessions::broadcast((int) $s['id'], 'Giám thị đã mở lại bài làm để em làm tiếp.', 'info', (int) $a['user_id']);
                    break;
                case 'void':
                    // Hủy bài để học sinh thi lại từ đầu (bài cũ vẫn lưu để đối chiếu)
                    $this->db->update('attempts', ['status' => 'voided', 'paused_at' => null, 'locked' => 0, 'submitted_at' => $a['submitted_at'] ?: time(), 'submit_reason' => $a['submit_reason'] ?: 'proctor', 'updated_at' => time()], 'id = ?', [(int) $a['id']]);
                    Attempts::event((int) $a['id'], 'void');
                    break;
                case 'delete':
                    if (!$s['_access']['manage']) {
                        throw new HttpException(403, 'Chỉ người quản lý ca thi mới được xóa bài làm.');
                    }
                    Attempts::delete((int) $a['id']);
                    break;
                case 'message':
                    $text = trim(Request::str('message'));
                    if ($text === '') {
                        $this->fail('Nội dung tin nhắn trống.');
                        return;
                    }
                    Sessions::broadcast((int) $s['id'], $text, Request::str('level', 'info'), (int) $a['user_id']);
                    Attempts::event((int) $a['id'], 'message', ['detail' => mb_substr($text, 0, 120)]);
                    break;
                default:
                    throw new HttpException(400, 'Thao tác không hợp lệ.');
            }
            Logger::audit('monitor.' . $action, 'attempt', (int) $a['id'], ['session' => (int) $s['id'], 'user' => (int) $a['user_id']] + ($action === 'add_time' ? ['minutes' => Request::int('minutes')] : []));
            $done++;
        }
        $labels = [
            'add_time' => 'Đã cộng giờ', 'pause' => 'Đã tạm dừng', 'resume' => 'Đã cho tiếp tục', 'unlock_violation' => 'Đã mở khóa',
            'unlock_device' => 'Đã mở khóa thiết bị – học sinh đăng nhập ở máy mới là làm tiếp được', 'force_submit' => 'Đã thu bài', 'reopen' => 'Đã mở lại bài',
            'void' => 'Đã hủy bài – học sinh có thể vào thi lại', 'delete' => 'Đã xóa bài làm', 'message' => 'Đã gửi tin nhắn',
        ];
        $msg = ($labels[$action] ?? 'Đã thực hiện') . ($done > 1 ? ' (' . $done . ' học sinh)' : '') . '.';
        if ($skipped) {
            $msg .= ' Bỏ qua ' . count($skipped) . ' bài không phù hợp: ' . implode(', ', array_slice($skipped, 0, 5)) . (count($skipped) > 5 ? '…' : '');
        }
        if (!Request::wantsJson()) {
            $this->flash($done ? 'success' : 'warning', $msg);
            $this->back('monitor', ['id' => $s['id']]);
            return;
        }
        $this->ok(['message' => $msg, 'done' => $done]);
    }

    /** Màn hình trình chiếu mã vào phòng + đồng hồ cho cả phòng thi (máy chiếu). */
    public function board(): void
    {
        $s = SessionsController::sessionFor(Request::int('id'), ['proctor', 'results']);
        $exam = Attempts::exam((int) $s['exam_id']);
        echo \App\Core\View::render('monitor/board', [
            'title' => $s['name'],
            's' => $s,
            'exam' => $exam,
            'duration' => Sessions::durationSec($s, $exam),
            'subject' => $exam['subject_id'] ? $this->db->value('SELECT name FROM {subjects} WHERE id = ?', [(int) $exam['subject_id']]) : '',
        ], 'bare');
    }
}
