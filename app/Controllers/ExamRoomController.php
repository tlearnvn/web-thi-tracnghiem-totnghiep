<?php

namespace App\Controllers;

use App\Core\App;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\FileStore;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Lib\Attempts;
use App\Lib\ExamFormat;
use App\Lib\Sessions;

/**
 * Phòng thi: trang làm bài + API lưu bài, nhịp tim, sự kiện, nộp bài, tải đề.
 * Thời gian luôn tính theo đồng hồ MÁY CHỦ – đồng hồ máy học sinh sai cũng không ảnh hưởng.
 */
final class ExamRoomController extends Controller
{
    /** @return array{0: array, 1: array, 2: array} [attempt, session, options] */
    private function context(bool $requireDevice = true): array
    {
        $u = $this->user();
        $aid = Request::int('aid');
        $a = Attempts::find($aid);
        if (!$a || (int) $a['user_id'] !== (int) $u['id']) {
            throw new HttpException(404, 'Không tìm thấy bài làm.');
        }
        $s = $this->db->one('SELECT * FROM {exam_sessions} WHERE id = ?', [(int) $a['session_id']]);
        if (!$s) {
            throw new HttpException(404, 'Ca thi không còn tồn tại.');
        }
        $o = Sessions::options($s);
        return [$a, $s, $o];
    }

    private static function cookieName(int $aid): string
    {
        return 'tnd_' . $aid;
    }

    private function setDeviceCookie(int $aid, string $token): void
    {
        setcookie(self::cookieName($aid), $token, [
            'expires' => time() + 86400 * 3, 'path' => base_uri(), 'secure' => Session::isHttps(), 'httponly' => true, 'samesite' => 'Lax',
        ]);
    }

    /**
     * Kiểm tra thiết bị. Trả về token mới nếu vừa cấp (nhận lại thiết bị), null nếu hợp lệ.
     * Ném 423 khi bài đang làm ở máy khác.
     */
    private function checkDevice(array &$a, array $o): ?string
    {
        $sent = (string) ($_SERVER['HTTP_X_DEVICE_TOKEN'] ?? '');
        $cookie = (string) ($_COOKIE[self::cookieName((int) $a['id'])] ?? '');
        $stored = (string) ($a['device_token'] ?? '');
        $issue = function (string $reason) use (&$a): string {
            $t = random_token(16);
            $this->db->update('attempts', ['device_token' => $t, 'device_info' => describe_ua(user_agent()), 'ip' => client_ip(), 'updated_at' => time()], 'id = ?', [(int) $a['id']]);
            $a['device_token'] = $t;
            $this->setDeviceCookie((int) $a['id'], $t);
            if ($reason !== '') {
                Attempts::event((int) $a['id'], $reason, ['device' => describe_ua(user_agent())]);
            }
            return $t;
        };
        if ($stored === '') {
            return $issue((int) $a['seq'] > 0 || (int) $a['answered'] > 0 ? 'reclaim' : '');
        }
        if (($sent !== '' && hash_equals($stored, $sent)) || ($cookie !== '' && hash_equals($stored, $cookie))) {
            if ($cookie === '' || !hash_equals($stored, $cookie)) {
                $this->setDeviceCookie((int) $a['id'], $stored);
            }
            return null;
        }
        if (!$o['device_lock']) {
            return $issue('');
        }
        if ($o['auto_reclaim'] && (string) $a['ip'] === client_ip() && (string) $a['device_info'] === describe_ua(user_agent())) {
            return $issue('reclaim');
        }
        $last = (int) $this->db->value("SELECT MAX(created_at) FROM {attempt_events} WHERE attempt_id = ? AND type = 'device_blocked'", [(int) $a['id']]);
        if ($last < time() - 60) {
            Attempts::event((int) $a['id'], 'device_blocked', ['device' => describe_ua(user_agent())]);
        }
        throw new HttpException(423, 'Bài thi của em đang được làm trên một máy khác. Nếu em vừa chuyển máy (máy hỏng, mất điện…), hãy báo giám thị bấm "Mở khóa thiết bị" rồi bấm Thử lại.', ['code' => 'device_locked']);
    }

    private function pdfKey(array $a): string
    {
        return base64_encode(hash_hmac('sha256', 'pdf|' . $a['id'] . '|' . $a['variant_id'], App::secret(), true));
    }

    /** Phần trạng thái dùng chung cho mọi phản hồi của API phòng thi. */
    private function live(array $a, array $s, array $o, int $lastMsg = 0): array
    {
        $now = time();
        $eff = Attempts::effectiveDeadline($a, $s, $now);
        $paused = $a['status'] === 'in_progress' && (!empty($a['paused_at']) || !empty($s['paused_at']));
        return [
            'now' => $now,
            'status' => $a['status'],
            'deadline' => $eff,
            'remaining' => $eff > 0 ? max(0, $eff - $now) : null,
            'paused' => $paused,
            'locked' => (int) $a['locked'] === 1,
            'violations' => (int) $a['violations'],
            'seq' => (int) $a['seq'],
            'messages' => Sessions::messagesFor((int) $s['id'], (int) $a['user_id'], $lastMsg),
            'redirect' => $a['status'] !== 'in_progress' ? url('student/result', ['aid' => $a['id']]) : null,
        ];
    }

    /** Hết giờ quá thời gian ân hạn -> thu bài. */
    private function enforceTime(array &$a, array $s, array $o): void
    {
        if ($a['status'] !== 'in_progress') {
            return;
        }
        $eff = Attempts::effectiveDeadline($a, $s);
        if ($eff > 0 && time() > $eff + $o['grace_seconds']) {
            Attempts::finalize($a, 'timeout', $s);
            $a = Attempts::find((int) $a['id']) ?? $a;
        }
    }

    // ------------------------------------------------------------------ Trang làm bài

    public function room(): void
    {
        $this->authorize('exam.take');
        [$a, $s, $o] = $this->context();
        $this->enforceTime($a, $s, $o);
        if ($a['status'] !== 'in_progress') {
            $this->redirect('student/result', ['aid' => $a['id']]);
            return;
        }
        $u = Auth::user();
        $exam = Attempts::exam((int) $a['exam_id']);
        $variant = $this->db->one('SELECT * FROM {exam_variants} WHERE id = ?', [(int) $a['variant_id']]);
        $subject = $exam['subject_id'] ? $this->db->one('SELECT name FROM {subjects} WHERE id = ?', [(int) $exam['subject_id']]) : null;
        $class = $u['class_id'] ? $this->db->value('SELECT name FROM {classes} WHERE id = ?', [(int) $u['class_id']]) : '';
        $newToken = $_SESSION['_device_' . $a['id']] ?? null;
        unset($_SESSION['_device_' . $a['id']]);
        Response::noCache();
        echo \App\Core\View::render('exam/room', [
            'title' => $exam['title'],
            'cfg' => [
                'aid' => (int) $a['id'],
                'userId' => (int) $u['id'],
                'username' => $u['username'],
                'structure' => $exam['_structure'],
                'deviceToken' => $newToken,
                'hasPdf' => (bool) $variant['pdf_file_id'],
                'mode' => $s['mode'],
                'urls' => [
                    'state' => url('exam/state', ['aid' => $a['id']]),
                    'save' => url('exam/save', ['aid' => $a['id']]),
                    'ping' => url('exam/ping', ['aid' => $a['id']]),
                    'event' => url('exam/event', ['aid' => $a['id']]),
                    'submit' => url('exam/submit', ['aid' => $a['id']]),
                    'pdf' => url('exam/pdf', ['aid' => $a['id']]),
                    'claim' => url('exam/claim', ['aid' => $a['id']]),
                    'login' => url('login'),
                    'result' => url('student/result', ['aid' => $a['id']]),
                    'home' => url('student'),
                ],
                'header' => [
                    'exam' => $s['name'],
                    'subject' => $subject['name'] ?? '',
                    'date' => date('d/m/Y', (int) $a['started_at']),
                    'name' => $u['full_name'],
                    'birthday' => fmt_date($u['birthday']),
                    'className' => $class,
                    'code' => $u['code'] ?: $u['username'],
                    'variant' => $variant['code'],
                    'room' => $s['room'] ?: '',
                ],
                'watermark' => $o['watermark'] ? ($u['full_name'] . ' • ' . ($u['code'] ?: $u['username']) . ' • ' . date('d/m/Y')) : '',
            ],
            'exam' => $exam,
            'session' => $s,
            'variant' => $variant,
            'subject' => $subject['name'] ?? '',
        ], null);
    }

    // ------------------------------------------------------------------ API

    public function state(): void
    {
        $this->authorize('exam.take');
        [$a, $s, $o] = $this->context();
        $this->enforceTime($a, $s, $o);
        if ($a['status'] !== 'in_progress') {
            $this->json(['ok' => false, 'code' => 'closed', 'error' => 'Bài thi đã được nộp.', 'redirect' => url('student/result', ['aid' => $a['id']])], 409);
            return;
        }
        $token = $this->checkDevice($a, $o);
        $exam = Attempts::exam((int) $a['exam_id']);
        $variant = $this->db->one('SELECT pdf_file_id FROM {exam_variants} WHERE id = ?', [(int) $a['variant_id']]);
        $first = (int) $this->db->value("SELECT COUNT(*) FROM {attempt_events} WHERE attempt_id = ? AND type = 'resume'", [(int) $a['id']]) === 0;
        if ((int) $a['seq'] > 0 || !$first) {
            Attempts::event((int) $a['id'], 'resume', ['device' => describe_ua(user_agent())]);
        }
        $this->db->update('attempts', ['last_seen_at' => time()], 'id = ?', [(int) $a['id']]);
        $minSubmitAt = $o['min_submit_minutes'] > 0 ? (int) $a['started_at'] + $o['min_submit_minutes'] * 60 : 0;
        $this->ok($this->live($a, $s, $o, Request::int('last_msg')) + [
            'answers' => json_dec($a['answers'], []),
            'flags' => json_dec($a['flags'], []),
            'started_at' => (int) $a['started_at'],
            'device_token' => $token,
            'pdf' => $variant && $variant['pdf_file_id'] ? ['url' => url('exam/pdf', ['aid' => $a['id']]), 'key' => $o['protect_pdf'] ? $this->pdfKey($a) : null] : null,
            'config' => [
                'autosave_ms' => max(500, (int) \App\Core\Settings::get('exam_autosave_ms', 1200)),
                'heartbeat' => max(8, (int) \App\Core\Settings::get('exam_heartbeat_seconds', 20)),
                'track_focus' => (int) $o['track_focus'],
                'require_fullscreen' => (int) $o['require_fullscreen'],
                'max_violations' => (int) $o['max_violations'],
                'violation_action' => $o['violation_action'],
                'confirm_submit' => (int) $o['confirm_submit'],
                'min_submit_at' => $minSubmitAt,
                'protect' => (int) $o['protect_pdf'],
                'grace' => (int) $o['grace_seconds'],
                'duration' => (int) $a['duration_sec'],
            ],
        ]);
    }

    public function save(): void
    {
        $this->authorize('exam.take');
        $this->requirePost();
        [$a, $s, $o] = $this->context();
        $this->enforceTime($a, $s, $o);
        if ($a['status'] !== 'in_progress') {
            $this->json(['ok' => false, 'code' => 'closed', 'error' => 'Bài thi đã được thu.', 'redirect' => url('student/result', ['aid' => $a['id']])], 409);
            return;
        }
        $token = $this->checkDevice($a, $o);
        $exam = Attempts::exam((int) $a['exam_id']);
        $r = Attempts::save($a, $exam['_structure'], Request::post('answers', []), Request::post('flags', []), Request::int('seq'));
        $a = Attempts::find((int) $a['id']) ?? $a;
        Session::release();
        $this->ok($this->live($a, $s, $o, Request::int('last_msg')) + ['saved_seq' => $r['seq'], 'answered' => $r['answered'], 'device_token' => $token]);
    }

    public function ping(): void
    {
        $this->authorize('exam.take');
        $this->requirePost();
        [$a, $s, $o] = $this->context();
        $this->enforceTime($a, $s, $o);
        if ($a['status'] !== 'in_progress') {
            $this->json(['ok' => false, 'code' => 'closed', 'error' => 'Bài thi đã được thu.', 'redirect' => url('student/result', ['aid' => $a['id']])], 409);
            return;
        }
        $token = $this->checkDevice($a, $o);
        if ((int) ($a['last_seen_at'] ?? 0) < time() - 8) {
            $this->db->update('attempts', ['last_seen_at' => time()], 'id = ?', [(int) $a['id']]);
        }
        $this->ok($this->live($a, $s, $o, Request::int('last_msg')) + ['device_token' => $token]);
    }

    public function event(): void
    {
        $this->authorize('exam.take');
        $this->requirePost();
        [$a, $s, $o] = $this->context();
        if ($a['status'] !== 'in_progress') {
            $this->json(['ok' => false, 'code' => 'closed', 'redirect' => url('student/result', ['aid' => $a['id']])], 409);
            return;
        }
        $this->checkDevice($a, $o);
        $type = Request::str('type');
        $allowed = ['leave', 'fullscreen_exit', 'copy', 'print', 'contextmenu', 'offline', 'online', 'multi_tab', 'relogin'];
        if (!in_array($type, $allowed, true)) {
            $this->fail('Sự kiện không hợp lệ.', 400);
            return;
        }
        $detail = mb_substr(Request::str('detail'), 0, 200);
        $counted = false;
        $action = 'none';
        if (($type === 'leave' && $o['track_focus']) || ($type === 'fullscreen_exit' && $o['require_fullscreen'])) {
            $last = (int) $this->db->value("SELECT MAX(created_at) FROM {attempt_events} WHERE attempt_id = ? AND type IN ('leave','fullscreen_exit')", [(int) $a['id']]);
            if ($last < time() - 3) {
                $counted = true;
                $this->db->run('UPDATE {attempts} SET violations = violations + 1, updated_at = ? WHERE id = ?', [time(), (int) $a['id']]);
                $a['violations'] = (int) $a['violations'] + 1;
            }
        }
        Attempts::event((int) $a['id'], $type, ['detail' => $detail, 'counted' => $counted, 'n' => (int) $a['violations']]);
        if ($counted && $o['max_violations'] > 0 && (int) $a['violations'] >= $o['max_violations']) {
            if ($o['violation_action'] === 'submit') {
                Attempts::finalize($a, 'violation', $s);
                $action = 'submitted';
            } elseif ($o['violation_action'] === 'lock' && !(int) $a['locked']) {
                $this->db->update('attempts', ['locked' => 1, 'paused_at' => time(), 'updated_at' => time()], 'id = ?', [(int) $a['id']]);
                Attempts::event((int) $a['id'], 'violation_lock', ['n' => (int) $a['violations']]);
                $action = 'locked';
            } else {
                $action = 'warn';
            }
        } elseif ($counted) {
            $action = 'warn';
        }
        $a = Attempts::find((int) $a['id']) ?? $a;
        $this->ok($this->live($a, $s, $o) + ['counted' => $counted, 'action' => $action, 'max' => (int) $o['max_violations']]);
    }

    public function submit(): void
    {
        $this->authorize('exam.take');
        $this->requirePost();
        [$a, $s, $o] = $this->context();
        if ($a['status'] !== 'in_progress') {
            $this->ok(['redirect' => url('student/result', ['aid' => $a['id']]), 'already' => true]);
            return;
        }
        $this->checkDevice($a, $o);
        $exam = Attempts::exam((int) $a['exam_id']);
        $eff = Attempts::effectiveDeadline($a, $s);
        $late = $eff > 0 && time() > $eff + $o['grace_seconds'];
        if (!$late) {
            Attempts::save($a, $exam['_structure'], Request::post('answers', []), Request::post('flags', []), Request::int('seq'));
            $a = Attempts::find((int) $a['id']) ?? $a;
        }
        $auto = Request::bool('auto');
        if (!$auto && $o['min_submit_minutes'] > 0 && time() < (int) $a['started_at'] + $o['min_submit_minutes'] * 60) {
            $this->fail('Chưa đến thời gian được phép nộp bài (sau ' . $o['min_submit_minutes'] . ' phút làm bài).', 422);
            return;
        }
        if (!$auto && (!empty($a['paused_at']) || !empty($s['paused_at']))) {
            $this->fail('Bài thi đang tạm dừng, chưa thể nộp.', 422);
            return;
        }
        Attempts::finalize($a, ($auto && $eff > 0 && time() >= $eff - 5) || $late ? 'timeout' : 'manual', $s);
        Attempts::finalizeExpired((int) $s['id']);
        $this->ok(['redirect' => url('student/result', ['aid' => $a['id']])]);
    }

    /** Nhận lại bài trên máy mới sau khi giám thị mở khóa. */
    public function claim(): void
    {
        $this->authorize('exam.take');
        $this->requirePost();
        [$a, $s, $o] = $this->context();
        $token = $this->checkDevice($a, $o);
        $this->ok(['device_token' => $token ?? (string) $a['device_token']]);
    }

    /** Gửi đề PDF: dữ liệu được làm rối (XOR) + chỉ trả khi gọi từ phòng thi (không mở trực tiếp được). */
    public function pdf(): void
    {
        $this->authorize('exam.take');
        [$a, $s, $o] = $this->context();
        $kind = Request::str('kind') === 'solution' ? 'solution' : 'exam';
        if (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) !== 'tnexam') {
            throw new HttpException(400, 'Đề thi chỉ hiển thị trong phòng thi, không mở trực tiếp được.');
        }
        if ($a['status'] === 'in_progress') {
            if ($kind === 'solution') {
                throw new HttpException(403);
            }
            $this->checkDevice($a, $o);
        } else {
            if (!Sessions::canReview($s, $a) || ($kind === 'solution' && !$o['show_solution_pdf'])) {
                throw new HttpException(403, 'Chưa được phép xem lại đề.');
            }
        }
        $v = $this->db->one('SELECT pdf_file_id, solution_file_id FROM {exam_variants} WHERE id = ?', [(int) $a['variant_id']]);
        $fid = (int) ($kind === 'solution' ? $v['solution_file_id'] : $v['pdf_file_id']);
        $f = FileStore::info($fid);
        if (!$f) {
            throw new HttpException(404, 'Chưa có tệp.');
        }
        Session::release();
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        $etag = '"' . substr((string) $f['sha256'], 0, 16) . $a['id'] . '"';
        header('Content-Type: application/octet-stream');
        header('X-File-Size: ' . (int) $f['size']);
        header('Content-Length: ' . (int) $f['size']);
        header('Cache-Control: private, max-age=7200');
        header('ETag: ' . $etag);
        header('X-Content-Type-Options: nosniff');
        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304);
            return;
        }
        FileStore::stream($fid, $o['protect_pdf'] ? base64_decode($this->pdfKey($a)) : null);
    }
}
