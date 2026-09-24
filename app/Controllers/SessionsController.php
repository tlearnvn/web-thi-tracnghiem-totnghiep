<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Scope;
use App\Lib\Attempts;
use App\Lib\ExamFormat;
use App\Lib\Sessions;
use App\Lib\Users;

final class SessionsController extends Controller
{
    public static function sessionFor(int $id, string $need = 'results'): array
    {
        $db = \App\Core\App::db();
        $s = $id > 0 ? $db->one('SELECT * FROM {exam_sessions} WHERE id = ?', [$id]) : null;
        if (!$s) {
            throw new HttpException(404, 'Không tìm thấy ca thi.');
        }
        $acc = Scope::sessionAccess($s);
        if (!$acc[$need] && !$acc['manage']) {
            throw new HttpException(403, 'Bạn không có quyền với ca thi này.');
        }
        $s['_access'] = $acc;
        return $s;
    }

    public function index(): void
    {
        $this->authorize('sessions.manage', 'sessions.manage_all', 'sessions.proctor', 'results.view', 'results.view_all');
        Attempts::finalizeExpired();
        $params = [];
        $where = [Scope::sessionListSql($params)];
        $now = time();
        $state = Request::str('state');
        if ($state === 'running') {
            $where[] = "s.status <> 'closed' AND (s.start_at IS NULL OR s.start_at <= ?) AND (s.end_at IS NULL OR s.end_at > ?)";
            $params[] = $now;
            $params[] = $now;
        } elseif ($state === 'upcoming') {
            $where[] = "s.status <> 'closed' AND s.start_at > ?";
            $params[] = $now;
        } elseif ($state === 'ended') {
            $where[] = "(s.status = 'closed' OR (s.end_at IS NOT NULL AND s.end_at <= ?))";
            $params[] = $now;
        }
        $mode = Request::str('mode');
        if (isset(Sessions::MODES[$mode])) {
            $where[] = 's.mode = ?';
            $params[] = $mode;
        }
        $q = Request::str('q');
        if ($q !== '') {
            $where[] = '(LOWER(s.name) LIKE ? OR LOWER(e.title) LIKE ?)';
            $params[] = '%' . mb_strtolower($q) . '%';
            $params[] = '%' . mb_strtolower($q) . '%';
        }
        $sqlWhere = implode(' AND ', $where);
        $total = (int) $this->db->value('SELECT COUNT(*) FROM {exam_sessions} s JOIN {exams} e ON e.id = s.exam_id WHERE ' . $sqlWhere, $params);
        $pager = new Paginator($total, 30);
        $rows = $this->db->all(
            "SELECT s.*, e.title AS exam_title, sub.name AS subject_name, sub.color AS subject_color, u.full_name AS owner_name,
                    (SELECT COUNT(*) FROM {attempts} a WHERE a.session_id = s.id AND a.status = 'in_progress') AS doing,
                    (SELECT COUNT(DISTINCT a.user_id) FROM {attempts} a WHERE a.session_id = s.id AND a.status <> 'in_progress') AS done
             FROM {exam_sessions} s JOIN {exams} e ON e.id = s.exam_id LEFT JOIN {subjects} sub ON sub.id = e.subject_id LEFT JOIN {users} u ON u.id = s.created_by
             WHERE " . $sqlWhere . ' ORDER BY COALESCE(s.start_at, s.created_at) DESC, s.id DESC' . $pager->sqlLimit(),
            $params
        );
        foreach ($rows as &$r) {
            $r['_targets'] = count(Sessions::students((int) $r['id']));
            $r['_classes'] = $this->db->column('SELECT c.name FROM {session_targets} t JOIN {classes} c ON c.id = t.class_id WHERE t.session_id = ? ORDER BY c.sort_key', [(int) $r['id']]);
        }
        unset($r);
        $this->render('sessions/index', [
            'title' => 'Ca thi',
            'crumbs' => ['Ca thi' => null],
            'rows' => $rows,
            'pager' => $pager,
            'canCreate' => can('sessions.manage', 'sessions.manage_all'),
        ]);
    }

    private function examOptions(): array
    {
        $params = [];
        $w = "e.status <> 'archived'";
        if (!Auth::can('exams.manage_all')) {
            $w .= ' AND (e.created_by = ? OR e.is_shared = 1)';
            $params[] = (int) Auth::id();
        }
        return $this->db->all(
            'SELECT e.id, e.title, e.duration, e.structure, s.name AS subject_name, (SELECT COUNT(*) FROM {exam_variants} v WHERE v.exam_id = e.id) AS variants
             FROM {exams} e LEFT JOIN {subjects} s ON s.id = e.subject_id WHERE ' . $w . ' ORDER BY e.updated_at DESC',
            $params
        );
    }

    private function formData(array $s): array
    {
        $p = [];
        $classes = $this->db->all("SELECT c.id, c.name, c.grade, c.school_year FROM {classes} c WHERE c.status = 'active' AND " . Scope::classFilterSql('c.id', $p) . ' ORDER BY c.grade DESC, c.sort_key', $p);
        $roles = Users::staffRoles();
        $staff = $this->db->all('SELECT id, full_name, role FROM {users} WHERE role IN ' . $this->db->in($roles) . " AND status = 'active' ORDER BY sort_key", $roles);
        $targets = $s['id'] ? Sessions::targets((int) $s['id']) : ['classes' => [], 'users' => []];
        $extraUsers = $targets['users'] ? $this->db->column('SELECT COALESCE(code, username) FROM {users} WHERE id IN ' . $this->db->in($targets['users']), $targets['users']) : [];
        return [
            's' => $s,
            'opts' => Sessions::options($s),
            'exams' => $this->examOptions(),
            'variants' => $s['exam_id'] ? $this->db->all('SELECT id, code FROM {exam_variants} WHERE exam_id = ? ORDER BY sort_order, code', [(int) $s['exam_id']]) : [],
            'classes' => $classes,
            'staff' => $staff,
            'targetClasses' => $targets['classes'],
            'extraUsers' => implode("\n", $extraUsers),
            'proctors' => $s['id'] ? array_map('intval', $this->db->column('SELECT user_id FROM {session_staff} WHERE session_id = ?', [(int) $s['id']])) : [],
        ];
    }

    public function create(): void
    {
        $this->authorize('sessions.manage', 'sessions.manage_all');
        $examId = Request::int('exam_id');
        $exam = $examId ? ExamsController::examFor($examId) : null;
        $mode = Request::str('mode') === 'practice' ? 'practice' : 'exam';
        $start = strtotime(date('Y-m-d H:00', time() + 3600));
        $s = [
            'id' => 0, 'exam_id' => $exam['id'] ?? 0, 'name' => $exam ? ($mode === 'practice' ? 'Luyện tập – ' : 'Ca thi – ') . $exam['title'] : '', 'mode' => $mode,
            'start_at' => $mode === 'practice' ? time() : $start, 'end_at' => $mode === 'practice' ? null : $start + (int) ($exam['duration'] ?? 50) * 60 + 900,
            'duration' => $exam['duration'] ?? null, 'access_code' => '', 'variant_mode' => 'random', 'fixed_variant_id' => null,
            'max_attempts' => $mode === 'practice' ? 0 : 1, 'late_join' => $mode === 'practice' ? 0 : 15, 'opts' => null, 'status' => 'active', 'room' => '', 'released' => 0,
        ];
        $this->render('sessions/form', ['title' => 'Tạo ca thi', 'crumbs' => ['Ca thi' => url('sessions'), 'Tạo mới' => null]] + $this->formData($s));
    }

    public function edit(): void
    {
        $this->authorize('sessions.manage', 'sessions.manage_all');
        $s = self::sessionFor(Request::int('id'), 'manage');
        $this->render('sessions/form', ['title' => 'Sửa ca thi', 'crumbs' => ['Ca thi' => url('sessions'), $s['name'] => url('sessions/view', ['id' => $s['id']]), 'Sửa' => null]] + $this->formData($s));
    }

    public function save(): void
    {
        $this->authorize('sessions.manage', 'sessions.manage_all');
        $this->requirePost();
        $id = Request::int('id');
        $cur = $id ? self::sessionFor($id, 'manage') : null;
        $exam = ExamsController::examFor(Request::int('exam_id'));
        $name = trim(Request::str('name'));
        $mode = Request::str('mode') === 'practice' ? 'practice' : 'exam';
        $start = parse_local_datetime(Request::str('start_at'));
        $end = parse_local_datetime(Request::str('end_at'));
        $errors = [];
        if ($name === '') {
            $errors[] = 'Vui lòng nhập tên ca thi.';
        }
        if ($start && $end && $end <= $start) {
            $errors[] = 'Giờ kết thúc phải sau giờ bắt đầu.';
        }
        $variantMode = isset(Sessions::VARIANT_MODES[Request::str('variant_mode')]) ? Request::str('variant_mode') : 'random';
        $fixed = Request::int('fixed_variant_id') ?: null;
        if ($variantMode === 'fixed' && (!$fixed || !$this->db->value('SELECT 1 FROM {exam_variants} WHERE id = ? AND exam_id = ?', [$fixed, (int) $exam['id']]))) {
            $errors[] = 'Chọn mã đề cố định thuộc đề thi đã chọn.';
        }
        if (!$this->db->value('SELECT 1 FROM {exam_variants} WHERE exam_id = ?', [(int) $exam['id']])) {
            $errors[] = 'Đề thi chưa có mã đề nào.';
        }
        // Đối tượng dự thi
        $classIds = array_values(array_filter(Request::ints('classes'), static fn($c) => Scope::canAccessClass($c)));
        $extra = [];
        $notFound = [];
        foreach (preg_split('/[\s,;]+/', Request::str('extra_users')) as $tok) {
            $tok = trim($tok);
            if ($tok === '') {
                continue;
            }
            $uid = $this->db->value('SELECT id FROM {users} WHERE (code = ? OR username = ?) AND role IN ' . $this->db->in(Sessions::studentRoles()), array_merge([$tok, mb_strtolower($tok)], Sessions::studentRoles()));
            if ($uid) {
                $extra[] = (int) $uid;
            } else {
                $notFound[] = $tok;
            }
        }
        if (!$classIds && !$extra) {
            $errors[] = 'Chọn ít nhất một lớp hoặc học sinh dự thi.';
        }
        if ($errors) {
            \App\Core\Session::setOld($_POST);
            $this->flash('danger', implode(' ', $errors));
            $this->back('sessions');
            return;
        }
        $d = Sessions::defaultOptions($mode);
        $opts = [];
        foreach ($d as $k => $def) {
            if (is_int($def) && in_array($k, ['show_key', 'show_explanations', 'show_solution_pdf', 'device_lock', 'auto_reclaim', 'require_fullscreen', 'watermark', 'protect_pdf', 'track_focus', 'confirm_submit'], true)) {
                $opts[$k] = Request::bool('opt_' . $k) ? 1 : 0;
            } else {
                $opts[$k] = Request::input('opt_' . $k, $def);
            }
        }
        $opts = Sessions::options(['mode' => $mode, 'opts' => json_enc($opts)]);
        $dur = Request::str('duration');
        $data = [
            'exam_id' => (int) $exam['id'], 'name' => mb_substr($name, 0, 255), 'mode' => $mode,
            'start_at' => $start, 'end_at' => $end, 'duration' => $dur === '' ? null : max(0, min(600, (int) $dur)),
            'access_code' => Request::str('access_code') !== '' ? mb_substr(Request::str('access_code'), 0, 50) : null,
            'variant_mode' => $variantMode, 'fixed_variant_id' => $variantMode === 'fixed' ? $fixed : null,
            'max_attempts' => max(0, min(100, Request::int('max_attempts', $mode === 'practice' ? 0 : 1))),
            'late_join' => max(0, min(600, Request::int('late_join'))),
            'opts' => json_enc($opts), 'room' => mb_substr(Request::str('room'), 0, 100) ?: null, 'updated_at' => time(),
        ];
        $this->db->transaction(function () use (&$id, $cur, $data, $classIds, $extra) {
            if ($cur) {
                $this->db->update('exam_sessions', $data, 'id = ?', [(int) $cur['id']]);
                $id = (int) $cur['id'];
            } else {
                $data += ['status' => 'active', 'released' => 0, 'created_by' => Auth::id(), 'created_at' => time()];
                $id = $this->db->insert('exam_sessions', $data);
            }
            $this->db->run('DELETE FROM {session_targets} WHERE session_id = ?', [$id]);
            foreach (array_unique($classIds) as $cid) {
                $this->db->insert('session_targets', ['session_id' => $id, 'class_id' => $cid, 'user_id' => null]);
            }
            foreach (array_unique($extra) as $uid) {
                $this->db->insert('session_targets', ['session_id' => $id, 'class_id' => null, 'user_id' => $uid]);
            }
            $this->db->run('DELETE FROM {session_staff} WHERE session_id = ?', [$id]);
            foreach (Request::ints('proctors') as $uid) {
                $this->db->insert('session_staff', ['session_id' => $id, 'user_id' => $uid, 'role' => 'proctor']);
            }
        });
        if ($cur) {
            // Cập nhật hạn nộp của các bài đang làm theo giờ kết thúc mới
            $s = $this->db->one('SELECT * FROM {exam_sessions} WHERE id = ?', [$id]);
            foreach ($this->db->all("SELECT * FROM {attempts} WHERE session_id = ? AND status = 'in_progress'", [$id]) as $a) {
                $this->db->update('attempts', ['deadline_at' => Attempts::computeDeadline($a, $s)], 'id = ?', [(int) $a['id']]);
            }
        }
        Logger::audit($cur ? 'session.update' : 'session.create', 'session', $id, ['name' => $name]);
        $this->flash('success', ($cur ? 'Đã lưu ca thi.' : 'Đã tạo ca thi.') . ($notFound ? ' Không tìm thấy học sinh: ' . implode(', ', array_slice($notFound, 0, 10)) : ''));
        $this->redirect('sessions/view', ['id' => $id]);
    }

    public function view(): void
    {
        $s = self::sessionFor(Request::int('id'), 'results');
        Attempts::finalizeExpired((int) $s['id']);
        $exam = ExamsController::examFor((int) $s['exam_id']);
        $students = Sessions::students((int) $s['id']);
        $stats = $this->db->one(
            "SELECT COUNT(*) AS total, SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS doing, SUM(CASE WHEN status <> 'in_progress' THEN 1 ELSE 0 END) AS done,
                    AVG(CASE WHEN status <> 'in_progress' THEN score END) AS avg_score, MAX(score) AS max_score
             FROM {attempts} WHERE session_id = ?",
            [(int) $s['id']]
        );
        $this->render('sessions/view', [
            'title' => $s['name'],
            'crumbs' => ['Ca thi' => url('sessions'), $s['name'] => null],
            's' => $s,
            'o' => Sessions::options($s),
            'exam' => $exam,
            'subject' => $exam['subject_id'] ? $this->db->one('SELECT * FROM {subjects} WHERE id = ?', [(int) $exam['subject_id']]) : null,
            'students' => $students,
            'stats' => $stats,
            'classes' => $this->db->all('SELECT c.id, c.name FROM {session_targets} t JOIN {classes} c ON c.id = t.class_id WHERE t.session_id = ? ORDER BY c.sort_key', [(int) $s['id']]),
            'proctors' => $this->db->all('SELECT u.full_name FROM {session_staff} ss JOIN {users} u ON u.id = ss.user_id WHERE ss.session_id = ?', [(int) $s['id']]),
            'variants' => $this->db->all('SELECT v.code, (SELECT COUNT(*) FROM {attempts} a WHERE a.variant_id = v.id AND a.session_id = ?) AS n FROM {exam_variants} v WHERE v.exam_id = ? ORDER BY v.sort_order', [(int) $s['id'], (int) $exam['id']]),
            'owner' => $s['created_by'] ? $this->db->value('SELECT full_name FROM {users} WHERE id = ?', [(int) $s['created_by']]) : null,
        ]);
    }

    /** Điều khiển ca thi: bắt đầu ngay, tạm dừng, tiếp tục, cộng giờ, kết thúc, công bố điểm, gửi thông báo. */
    public function control(): void
    {
        $this->requirePost();
        $s = self::sessionFor(Request::int('id'), 'proctor');
        $action = Request::str('action');
        $acc = $s['_access'];
        $now = time();
        $msg = '';
        switch ($action) {
            case 'start_now':
                $upd = ['start_at' => $now, 'status' => 'active', 'updated_at' => $now];
                if ($s['end_at'] && (int) $s['end_at'] <= $now) {
                    $upd['end_at'] = null;
                }
                $this->db->update('exam_sessions', $upd, 'id = ?', [(int) $s['id']]);
                $msg = 'Ca thi đã bắt đầu. Học sinh có thể vào làm bài.';
                break;
            case 'pause':
                Sessions::pause($s);
                $msg = 'Đã tạm dừng ca thi – thời gian của tất cả học sinh được giữ nguyên.';
                break;
            case 'resume':
                Sessions::resume($s);
                $msg = 'Đã tiếp tục ca thi. Giờ kết thúc được lùi tương ứng thời gian tạm dừng.';
                break;
            case 'add_time':
                $m = max(1, min(180, Request::int('minutes', 5)));
                $n = Sessions::addTimeAll($s, $m);
                if ($s['end_at']) {
                    $this->db->update('exam_sessions', ['end_at' => max((int) $s['end_at'], $now) + $m * 60, 'updated_at' => $now], 'id = ?', [(int) $s['id']]);
                }
                $msg = 'Đã cộng ' . $m . ' phút cho ' . $n . ' bài đang làm.';
                break;
            case 'close':
                $n = Sessions::close($s);
                $msg = 'Đã kết thúc ca thi và thu ' . $n . ' bài đang làm.';
                break;
            case 'reopen':
                if (!$acc['manage']) {
                    throw new HttpException(403);
                }
                $end = parse_local_datetime(Request::str('end_at'));
                $this->db->update('exam_sessions', ['status' => 'active', 'end_at' => $end && $end > $now ? $end : null, 'updated_at' => $now], 'id = ?', [(int) $s['id']]);
                $msg = 'Đã mở lại ca thi.';
                break;
            case 'release':
            case 'unrelease':
                if (!$acc['manage'] && !Auth::can('results.grade')) {
                    throw new HttpException(403);
                }
                $this->db->update('exam_sessions', ['released' => $action === 'release' ? 1 : 0, 'updated_at' => $now], 'id = ?', [(int) $s['id']]);
                $msg = $action === 'release' ? 'Đã công bố điểm cho học sinh.' : 'Đã ẩn điểm với học sinh.';
                break;
            case 'message':
                $text = trim(Request::str('message'));
                if ($text === '') {
                    $this->fail('Nội dung thông báo trống.');
                    return;
                }
                Sessions::broadcast((int) $s['id'], $text, Request::str('level', 'info'), Request::int('user_id') ?: null);
                $msg = 'Đã gửi thông báo tới ' . (Request::int('user_id') ? 'học sinh' : 'cả phòng thi') . '.';
                break;
            default:
                throw new HttpException(400, 'Thao tác không hợp lệ.');
        }
        Logger::audit('session.control', 'session', (int) $s['id'], ['action' => $action]);
        if (\App\Core\Request::wantsJson()) {
            $this->ok(['message' => $msg]);
            return;
        }
        $this->flash('success', $msg);
        $this->back('sessions/view', ['id' => $s['id']]);
    }

    public function delete(): void
    {
        $this->requirePost();
        $s = self::sessionFor(Request::int('id'), 'manage');
        $n = (int) $this->db->value('SELECT COUNT(*) FROM {attempts} WHERE session_id = ?', [(int) $s['id']]);
        if ($n > 0 && !Request::bool('force')) {
            $this->flash('danger', 'Ca thi đã có ' . $n . ' bài làm. Muốn xóa cả bài làm, hãy xác nhận xóa vĩnh viễn.');
            $this->back('sessions');
            return;
        }
        $this->db->transaction(function () use ($s) {
            foreach ($this->db->column('SELECT id FROM {attempts} WHERE session_id = ?', [(int) $s['id']]) as $aid) {
                $this->db->run('DELETE FROM {attempt_events} WHERE attempt_id = ?', [(int) $aid]);
            }
            foreach (['attempts', 'session_targets', 'session_staff', 'session_messages'] as $t) {
                $this->db->run('DELETE FROM {' . $t . '} WHERE session_id = ?', [(int) $s['id']]);
            }
            $this->db->run('DELETE FROM {exam_sessions} WHERE id = ?', [(int) $s['id']]);
        });
        Logger::audit('session.delete', 'session', (int) $s['id'], ['name' => $s['name'], 'attempts' => $n]);
        $this->flash('success', 'Đã xóa ca thi "' . $s['name'] . '".');
        $this->redirect('sessions');
    }

    /** Danh sách mã đề của một đề (cho biểu mẫu – chọn mã đề cố định). */
    public function variants(): void
    {
        $e = ExamsController::examFor(Request::int('exam_id'));
        $this->ok([
            'variants' => $this->db->all('SELECT id, code FROM {exam_variants} WHERE exam_id = ? ORDER BY sort_order, code', [(int) $e['id']]),
            'duration' => (int) $e['duration'],
            'structure' => ExamFormat::describe($e['_structure']),
        ]);
    }
}
