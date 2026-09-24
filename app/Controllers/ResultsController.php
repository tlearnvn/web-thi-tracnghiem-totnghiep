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
use App\Lib\Scoring;
use App\Lib\Sessions;
use App\Lib\Stats;
use App\Lib\XlsxWriter;

/** Kết quả thi: danh sách, chi tiết bài làm, chấm tự luận, chấm lại, xuất Excel, in phiếu trả lời. */
final class ResultsController extends Controller
{
    public function index(): void
    {
        $this->authorize('results.view', 'results.view_all', 'sessions.manage', 'sessions.manage_all');
        Attempts::finalizeExpired();
        $params = [];
        $where = [Scope::sessionListSql($params)];
        $q = Request::str('q');
        if ($q !== '') {
            $where[] = '(LOWER(s.name) LIKE ? OR LOWER(e.title) LIKE ?)';
            $params[] = '%' . mb_strtolower($q) . '%';
            $params[] = '%' . mb_strtolower($q) . '%';
        }
        $subject = Request::int('subject');
        if ($subject) {
            $where[] = 'e.subject_id = ?';
            $params[] = $subject;
        }
        $mode = Request::str('mode');
        if (isset(Sessions::MODES[$mode])) {
            $where[] = 's.mode = ?';
            $params[] = $mode;
        }
        $sql = 'FROM {exam_sessions} s JOIN {exams} e ON e.id = s.exam_id LEFT JOIN {subjects} sub ON sub.id = e.subject_id WHERE ' . implode(' AND ', $where);
        $pager = new Paginator((int) $this->db->value('SELECT COUNT(*) ' . $sql, $params), 20);
        $rows = $this->db->all(
            "SELECT s.*, e.title AS exam_title, e.structure, e.scoring, sub.name AS subject_name, sub.color AS subject_color,
                    (SELECT COUNT(*) FROM {attempts} a WHERE a.session_id = s.id AND a.status NOT IN ('in_progress','voided')) AS done,
                    (SELECT COUNT(*) FROM {attempts} a WHERE a.session_id = s.id AND a.status = 'in_progress') AS doing,
                    (SELECT AVG(a.score) FROM {attempts} a WHERE a.session_id = s.id AND a.status NOT IN ('in_progress','voided')) AS avg_score,
                    (SELECT MAX(a.score) FROM {attempts} a WHERE a.session_id = s.id AND a.status NOT IN ('in_progress','voided')) AS max_score,
                    (SELECT COUNT(*) FROM {attempts} a WHERE a.session_id = s.id AND a.grading_status = 'pending' AND a.status NOT IN ('in_progress','voided')) AS pending
             " . $sql . ' ORDER BY COALESCE(s.start_at, s.created_at) DESC, s.id DESC ' . $pager->sqlLimit(),
            $params
        );
        foreach ($rows as &$r) {
            $r['_targets'] = count(Sessions::students((int) $r['id']));
            $r['_max'] = Scoring::maxScore(ExamFormat::normalizeStructure($r['structure']), Scoring::normalize($r['scoring']));
        }
        unset($r);
        $this->render('results/index', [
            'title' => 'Kết quả & chấm bài',
            'rows' => $rows,
            'pager' => $pager,
            'subjects' => $this->db->all('SELECT id, name FROM {subjects} ORDER BY sort_order, name'),
        ]);
    }

    /** Bảng kết quả của một ca thi (kể cả học sinh vắng). */
    private function sessionRows(array $s, ?int $classId = null): array
    {
        $official = [];
        foreach (Stats::officialAttempts($s, $classId) as $a) {
            $official[(int) $a['user_id']] = $a;
        }
        $doing = [];
        foreach ($this->db->all("SELECT user_id, id, answered FROM {attempts} WHERE session_id = ? AND status = 'in_progress'", [(int) $s['id']]) as $a) {
            $doing[(int) $a['user_id']] = $a;
        }
        $rows = [];
        $seen = [];
        foreach (Sessions::students((int) $s['id'], $classId) as $u) {
            $uid = (int) $u['id'];
            $seen[$uid] = true;
            $rows[] = ['u' => $u, 'a' => $official[$uid] ?? null, 'doing' => $doing[$uid] ?? null];
        }
        // Có bài làm nhưng không còn trong danh sách dự thi
        foreach ($official as $uid => $a) {
            if (!isset($seen[$uid])) {
                $rows[] = ['u' => ['id' => $uid, 'full_name' => $a['full_name'], 'code' => $a['user_code'], 'username' => $a['username'], 'class_name' => $a['class_name'], 'birthday' => $a['birthday'], 'class_id' => $a['class_id'], 'sort_key' => $a['sort_key']], 'a' => $a, 'doing' => null, 'extra' => true];
            }
        }
        return $rows;
    }

    public function session(): void
    {
        $s = SessionsController::sessionFor(Request::int('id'), 'results');
        Attempts::finalizeExpired((int) $s['id']);
        $exam = Attempts::exam((int) $s['exam_id']);
        $classId = Request::int('class') ?: null;
        $rows = $this->sessionRows($s, $classId);
        $filter = Request::str('f');
        $rows = array_values(array_filter($rows, static function ($r) use ($filter) {
            switch ($filter) {
                case 'done': return $r['a'] !== null;
                case 'absent': return $r['a'] === null && $r['doing'] === null;
                case 'doing': return $r['doing'] !== null;
                case 'pending': return $r['a'] !== null && $r['a']['grading_status'] === 'pending';
                default: return true;
            }
        }));
        if (Request::str('sort') === 'score') {
            usort($rows, static fn($x, $y) => ((float) ($y['a']['score'] ?? -1)) <=> ((float) ($x['a']['score'] ?? -1)));
        }
        $scores = array_values(array_filter(array_map(static fn($r) => $r['a'] && $r['a']['score'] !== null ? (float) $r['a']['score'] : null, $rows), static fn($v) => $v !== null));
        $this->render('results/session', [
            'title' => 'Kết quả: ' . $s['name'],
            'crumbs' => ['Kết quả' => url('results'), $s['name'] => null],
            's' => $s,
            'o' => Sessions::options($s),
            'exam' => $exam,
            'rows' => $rows,
            'desc' => Stats::describe($scores),
            'max' => Scoring::maxScore($exam['_structure'], $exam['_scoring']),
            'classes' => $this->db->all('SELECT c.id, c.name FROM {session_targets} t JOIN {classes} c ON c.id = t.class_id WHERE t.session_id = ? ORDER BY c.sort_key, c.name', [(int) $s['id']]),
            'classId' => $classId,
            'filter' => $filter,
            'canGrade' => $s['_access']['grade'] || $s['_access']['manage'],
        ]);
    }

    private function attemptFor(int $id): array
    {
        $a = Attempts::find($id);
        if (!$a) {
            throw new HttpException(404, 'Không tìm thấy bài làm.');
        }
        $s = SessionsController::sessionFor((int) $a['session_id'], 'results');
        return [$a, $s];
    }

    public function attempt(): void
    {
        [$a, $s] = $this->attemptFor(Request::int('id'));
        $exam = Attempts::exam((int) $a['exam_id']);
        $u = $this->db->one('SELECT u.*, c.name AS class_name FROM {users} u LEFT JOIN {classes} c ON c.id = u.class_id WHERE u.id = ?', [(int) $a['user_id']]);
        $variant = $this->db->one('SELECT * FROM {exam_variants} WHERE id = ?', [(int) $a['variant_id']]);
        $r = Attempts::score($a, $exam);
        $events = $this->db->all('SELECT * FROM {attempt_events} WHERE attempt_id = ? ORDER BY id DESC LIMIT 400', [(int) $a['id']]);
        $others = $this->db->all('SELECT id, attempt_no, status, score FROM {attempts} WHERE session_id = ? AND user_id = ? ORDER BY attempt_no', [(int) $s['id'], (int) $a['user_id']]);
        $this->render('results/attempt', [
            'title' => 'Bài làm: ' . ($u['full_name'] ?? ''),
            'crumbs' => ['Kết quả' => url('results'), $s['name'] => url('results/session', ['id' => $s['id']]), ($u['full_name'] ?? 'Bài làm') => null],
            'a' => $a, 's' => $s, 'exam' => $exam, 'u' => $u, 'variant' => $variant, 'r' => $r,
            'answers' => json_dec($a['answers'], []),
            'essayScores' => json_dec($a['essay_scores'] ?? null, []),
            'keys' => Attempts::keys((int) $a['variant_id']),
            'events' => $events,
            'others' => $others,
            'canGrade' => $s['_access']['grade'] || $s['_access']['manage'],
            'canProctor' => $s['_access']['proctor'],
            'grader' => $a['graded_by'] ? $this->db->value('SELECT full_name FROM {users} WHERE id = ?', [(int) $a['graded_by']]) : null,
            'nextPending' => (int) $this->db->value("SELECT id FROM {attempts} WHERE session_id = ? AND grading_status = 'pending' AND status NOT IN ('in_progress','voided') AND id <> ? ORDER BY id LIMIT 1", [(int) $s['id'], (int) $a['id']]),
        ]);
    }

    /** Chấm điểm tự luận + ghi chú của giáo viên. */
    public function grade(): void
    {
        $this->requirePost();
        [$a, $s] = $this->attemptFor(Request::int('id'));
        if (!$s['_access']['grade'] && !$s['_access']['manage']) {
            throw new HttpException(403, 'Bạn không có quyền chấm bài.');
        }
        if ($a['status'] === 'in_progress') {
            $this->flash('warning', 'Bài đang làm, chưa thể chấm.');
            $this->redirect('results/attempt', ['id' => $a['id']]);
            return;
        }
        $exam = Attempts::exam((int) $a['exam_id']);
        $keys = Attempts::keys((int) $a['variant_id']);
        $scores = [];
        foreach (($exam['_structure']['essay'] ?? []) as $i => $e) {
            $n = $i + 1;
            $raw = trim(str_replace(',', '.', (string) (Request::arr('essay')[$n] ?? '')));
            if ($raw === '') {
                continue;
            }
            if (!is_numeric($raw)) {
                $this->flash('danger', 'Điểm câu tự luận ' . $n . ' không hợp lệ.');
                $this->redirect('results/attempt', ['id' => $a['id']]);
                return;
            }
            $k = $keys['e.' . $n] ?? null;
            $max = ($k && $k['points'] !== null && $k['points'] !== '') ? (float) $k['points'] : (float) $e['points'];
            $scores[$n] = max(0.0, min($max, round((float) $raw, 2)));
        }
        $a['essay_scores'] = json_enc((object) $scores);
        $r = Attempts::score($a, $exam);
        $this->db->update('attempts', [
            'essay_scores' => $a['essay_scores'],
            'score' => $r['score'],
            'score_detail' => json_enc(Scoring::summary($r)),
            'grading_status' => $r['pending'] ? 'pending' : 'graded',
            'graded_by' => Auth::id(),
            'graded_at' => time(),
            'note' => mb_substr(Request::str('note'), 0, 255) ?: null,
            'updated_at' => time(),
        ], 'id = ?', [(int) $a['id']]);
        Attempts::event((int) $a['id'], 'grade', ['scores' => $scores, 'total' => $r['score']]);
        Logger::audit('attempt.grade', 'attempt', (int) $a['id'], ['score' => $r['score']]);
        $this->flash('success', 'Đã lưu điểm. Tổng điểm mới: ' . fmt_score($r['score']) . ($r['pending'] ? ' (còn câu chưa chấm)' : '') . '.');
        $next = Request::int('next');
        if ($next) {
            $this->redirect('results/attempt', ['id' => $next]);
            return;
        }
        $this->redirect('results/attempt', ['id' => $a['id']]);
    }

    /** Chấm lại (sau khi sửa đáp án, hủy câu, đổi cách tính điểm). */
    public function rescore(): void
    {
        $this->requirePost();
        $aid = Request::int('aid');
        if ($aid) {
            [$a, $s] = $this->attemptFor($aid);
            $ids = [$aid];
        } else {
            $s = SessionsController::sessionFor(Request::int('id'), 'results');
            $ids = Request::ints('aids') ?: array_map('intval', $this->db->column("SELECT id FROM {attempts} WHERE session_id = ? AND status NOT IN ('in_progress','voided')", [(int) $s['id']]));
        }
        if (!$s['_access']['grade'] && !$s['_access']['manage']) {
            throw new HttpException(403, 'Bạn không có quyền chấm lại.');
        }
        Attempts::forgetExam((int) $s['exam_id']);
        $n = Attempts::rescore($ids);
        foreach ($ids as $id) {
            Attempts::event($id, 'rescore');
        }
        Logger::audit('session.rescore', 'session', (int) $s['id'], ['count' => $n]);
        $this->flash('success', 'Đã chấm lại ' . $n . ' bài làm theo đáp án & cách tính điểm hiện tại.');
        $this->back('results/session', ['id' => $s['id']]);
    }

    /** Giáo viên xem lại bài làm giống màn hình của học sinh (có đáp án, lời giải). */
    public function review(): void
    {
        [$a, $s] = $this->attemptFor(Request::int('id'));
        $u = $this->db->one('SELECT * FROM {users} WHERE id = ?', [(int) $a['user_id']]);
        echo \App\Core\View::render('exam/review', StudentPortalController::reviewData($a, $s, $u, false), null);
    }

    /** In phiếu trả lời (bản lưu) của cả ca thi hoặc các bài được chọn. */
    public function print(): void
    {
        $s = SessionsController::sessionFor(Request::int('id'), 'results');
        $exam = Attempts::exam((int) $s['exam_id']);
        $ids = Request::ints('aids');
        $classId = Request::int('class') ?: null;
        $list = Stats::officialAttempts($s, $classId);
        if ($ids) {
            $list = array_values(array_filter($list, static fn($a) => in_array((int) $a['id'], $ids, true)));
        }
        $withKey = Request::bool('keys');
        $subject = $exam['subject_id'] ? (string) $this->db->value('SELECT name FROM {subjects} WHERE id = ?', [(int) $exam['subject_id']]) : '';
        $sheets = [];
        foreach ($list as $a) {
            $r = $withKey ? Attempts::score($a, $exam) : null;
            $sheets[] = [
                'aid' => (int) $a['id'],
                'answers' => json_dec($a['answers'], []),
                'results' => $r ? ['items' => $r['items'], 'parts' => $r['parts']] : null,
                'score' => $a['score'] !== null ? (float) $a['score'] : null,
                'max' => (float) (json_dec($a['score_detail'], [])['max'] ?? 10),
                'status' => Attempts::STATUS[$a['status']][0] ?? '',
                'submitted' => fmt_dt($a['submitted_at'], 'H:i:s d/m/Y'),
                'header' => [
                    'title' => 'PHIẾU TRẢ LỜI TRẮC NGHIỆM – BẢN LƯU', 'exam' => $s['name'], 'subject' => $subject, 'date' => fmt_dt($a['started_at'], 'd/m/Y'),
                    'name' => $a['full_name'], 'birthday' => fmt_date($a['birthday']), 'className' => (string) $a['class_name'],
                    'code' => $a['user_code'] ?: $a['username'], 'variant' => (string) $a['variant_code'], 'room' => (string) ($s['room'] ?: ''),
                ],
            ];
        }
        echo \App\Core\View::render('results/print', ['title' => 'In phiếu trả lời – ' . $s['name'], 's' => $s, 'exam' => $exam, 'sheets' => $sheets, 'withKey' => $withKey], 'bare');
    }

    /** Tra cứu toàn bộ kết quả của một học sinh. */
    public function student(): void
    {
        $this->authorize('results.view', 'results.view_all', 'students.view', 'students.view_all');
        $uid = Request::int('uid');
        $u = $uid ? $this->db->one('SELECT u.*, c.name AS class_name FROM {users} u LEFT JOIN {classes} c ON c.id = u.class_id WHERE u.id = ?', [$uid]) : null;
        if (!$u || !Scope::canAccessClass($u['class_id'] ? (int) $u['class_id'] : null)) {
            throw new HttpException(404, 'Không tìm thấy học sinh.');
        }
        $rows = $this->db->all(
            "SELECT a.*, s.name AS session_name, s.mode, s.created_by AS s_owner, e.title AS exam_title, sub.name AS subject_name, sub.color AS subject_color
             FROM {attempts} a JOIN {exam_sessions} s ON s.id = a.session_id JOIN {exams} e ON e.id = a.exam_id LEFT JOIN {subjects} sub ON sub.id = e.subject_id
             WHERE a.user_id = ? AND a.status <> 'voided' ORDER BY a.started_at DESC",
            [$uid]
        );
        $this->render('results/student', [
            'title' => 'Kết quả của ' . $u['full_name'],
            'crumbs' => ['Học sinh' => url('students'), $u['full_name'] => url('students/view', ['id' => $u['id']]), 'Kết quả' => null],
            'u' => $u,
            'rows' => $rows,
        ]);
    }

    // ------------------------------------------------------------------ Xuất Excel

    public function export(): void
    {
        $this->authorize('results.export');
        $s = SessionsController::sessionFor(Request::int('id'), 'results');
        $exam = Attempts::exam((int) $s['exam_id']);
        $classId = Request::int('class') ?: null;
        $rows = $this->sessionRows($s, $classId);
        $official = array_values(array_filter(array_map(static fn($r) => $r['a'], $rows)));
        $struct = $exam['_structure'];
        $subject = $exam['subject_id'] ? (string) $this->db->value('SELECT name FROM {subjects} WHERE id = ?', [(int) $exam['subject_id']]) : '';
        $maxScore = Scoring::maxScore($struct, $exam['_scoring']);

        $x = new XlsxWriter();
        $x->title = 'Kết quả ' . $s['name'];
        $title = $x->style(['bold' => true, 'size' => 14, 'color' => '1E3A8A']);
        $sub = $x->style(['italic' => true, 'color' => '64748B']);
        $h = $x->style(['bold' => true, 'fill' => '1D4ED8', 'color' => 'FFFFFF', 'border' => true, 'align' => 'center', 'wrap' => true]);
        $t = $x->style(['border' => true]);
        $c = $x->style(['border' => true, 'align' => 'center']);
        $num = $x->style(['border' => true, 'align' => 'center', 'numFmt' => '0.00']);
        $bold = $x->style(['border' => true, 'align' => 'center', 'numFmt' => '0.00', 'bold' => true, 'fill' => 'EFF6FF']);
        $muted = $x->style(['border' => true, 'color' => '94A3B8', 'italic' => true, 'align' => 'center']);
        $ok = $x->style(['border' => true, 'align' => 'center', 'fill' => 'DCFCE7', 'color' => '166534']);
        $bad = $x->style(['border' => true, 'align' => 'center', 'fill' => 'FEE2E2', 'color' => '991B1B']);
        $keyStyle = $x->style(['border' => true, 'align' => 'center', 'bold' => true, 'fill' => 'FEF3C7']);
        $pct = $x->style(['border' => true, 'align' => 'center', 'numFmt' => '0.0%']);
        $dec = $x->style(['border' => true, 'align' => 'center', 'numFmt' => '0.00']);

        // --- Sheet 1: Kết quả
        $sh = $x->sheet('Kết quả');
        $sh->landscape();
        $hasEssay = !empty($struct['essay']);
        $head = ['STT', 'SBD / Mã HS', 'Họ và tên', 'Ngày sinh', 'Lớp', 'Mã đề', 'Trạng thái', 'Bắt đầu', 'Nộp bài', 'Thời gian làm', 'Số câu đã làm'];
        if ($struct['p1']) {
            $head[] = 'Phần I';
        }
        if ($struct['p2']) {
            $head[] = 'Phần II';
        }
        if ($struct['p3']) {
            $head[] = 'Phần III';
        }
        if ($hasEssay) {
            $head[] = 'Tự luận';
        }
        array_push($head, 'Tổng điểm', 'Xếp loại', 'Rời màn hình', 'Ghi chú');
        $sh->widths(array_merge([6, 14, 26, 11, 8, 8, 16, 16, 16, 12, 10], array_fill(0, 4, 9), [10, 11, 10, 24]));
        $sh->row([mb_strtoupper('BẢNG KẾT QUẢ – ' . $s['name'])], $title);
        $sh->row([setting('org_name') . ' · Môn ' . $subject . ' · ' . $exam['title'] . ' · Thang điểm ' . fmt_num($maxScore) . ' · Xuất lúc ' . date('H:i d/m/Y') . ' (UTC+7)'], $sub);
        $sh->blank();
        $sh->row($head, $h, [], 32);
        $i = 0;
        foreach ($rows as $r) {
            $u = $r['u'];
            $a = $r['a'];
            $i++;
            if (!$a) {
                $status = $r['doing'] ? 'Đang làm (' . (int) $r['doing']['answered'] . ' câu)' : 'Vắng / chưa làm';
                $cells = [$i, (string) ($u['code'] ?: $u['username']), $u['full_name'], fmt_date($u['birthday'] ?? null), (string) ($u['class_name'] ?? ''), '', $status, '', '', '', ''];
                $cells = array_merge($cells, array_fill(0, ($struct['p1'] ? 1 : 0) + ($struct['p2'] ? 1 : 0) + ($struct['p3'] ? 1 : 0) + ($hasEssay ? 1 : 0), ''), ['', '', '', '']);
                $sh->row($cells, $t, [0 => $c, 6 => $muted]);
                continue;
            }
            $d = json_dec($a['score_detail'], []);
            $p = $d['parts'] ?? [];
            $used = $a['submitted_at'] ? max(0, (int) $a['submitted_at'] - (int) $a['started_at'] - (int) $a['paused_total'] - (int) $a['hold_sec']) : 0;
            $cells = [$i, (string) ($a['user_code'] ?: $a['username']), $a['full_name'], fmt_date($a['birthday']), (string) $a['class_name'], (string) $a['variant_code'],
                (Attempts::STATUS[$a['status']][0] ?? $a['status']) . ((int) $a['_count'] > 1 ? ' (lần ' . (int) $a['attempt_no'] . '/' . (int) $a['_count'] . ')' : ''),
                fmt_dt($a['started_at'], 'H:i:s d/m/Y'), fmt_dt($a['submitted_at'], 'H:i:s d/m/Y'), $used ? fmt_duration($used) : '', (int) $a['answered']];
            $styles = [0 => $c, 1 => $c, 3 => $c, 4 => $c, 5 => $c, 9 => $c, 10 => $c];
            foreach (['p1', 'p2', 'p3'] as $pp) {
                if ($struct[$pp]) {
                    $styles[count($cells)] = $num;
                    $cells[] = isset($p[$pp]['score']) ? round((float) $p[$pp]['score'], 2) : '';
                }
            }
            if ($hasEssay) {
                $styles[count($cells)] = $num;
                $cells[] = $a['grading_status'] === 'pending' ? 'Chờ chấm' : (isset($p['essay']['score']) ? round((float) $p['essay']['score'], 2) : '');
            }
            $styles[count($cells)] = $bold;
            $cells[] = $a['score'] !== null ? (float) $a['score'] : '';
            $styles[count($cells)] = $c;
            $cells[] = Scoring::classify($a['score'] !== null ? (float) $a['score'] : null, (float) ($d['max'] ?? 10) ?: 10);
            $styles[count($cells)] = $c;
            $cells[] = (int) $a['violations'];
            $cells[] = (string) ($a['note'] ?? '');
            $sh->row($cells, $t, $styles);
        }
        $sh->freeze('D5');
        $sh->autoFilter('A4:' . XlsxWriter::col(count($head) - 1) . (4 + count($rows)));
        $sh->printTitles(4, 4);

        // --- Sheet 2: Chi tiết từng câu
        $ids = ExamFormat::questionIds($struct);
        $ids = array_values(array_filter($ids, static fn($q) => strpos($q, 'e.') !== 0));
        $sd = $x->sheet('Chi tiết từng câu');
        $sd->widths(array_merge([6, 14, 24, 8, 8], array_fill(0, count($ids), 7), [9]));
        $sd->row(['CHI TIẾT BÀI LÀM TỪNG CÂU – ' . mb_strtoupper($s['name'])], $title);
        $sd->row(['Ô xanh: đúng · Ô đỏ: sai · "–": bỏ trống. Phần II ghi theo thứ tự ý a b c d (Đ = đúng, S = sai, _ = không chọn).'], $sub);
        $sd->blank();
        $labels = array_map(static fn($q) => str_replace(['p1.', 'p2.', 'p3.'], ['I.', 'II.', 'III.'], $q), $ids);
        $sd->row(array_merge(['STT', 'SBD', 'Họ và tên', 'Lớp', 'Mã đề'], $labels, ['Tổng']), $h, [], 24);
        $variants = $this->db->all('SELECT id, code FROM {exam_variants} WHERE exam_id = ? ORDER BY sort_order, code', [(int) $exam['id']]);
        foreach ($variants as $v) {
            $keys = Attempts::keys((int) $v['id']);
            $cells = ['', '', 'ĐÁP ÁN MÃ ĐỀ ' . $v['code'], '', (string) $v['code']];
            foreach ($ids as $q) {
                $kv = (string) ($keys[$q]['answer'] ?? '');
                $cells[] = strpos($q, 'p2.') === 0 ? str_replace('D', 'Đ', $kv) : $kv;
            }
            $cells[] = '';
            $sd->row($cells, $keyStyle);
        }
        $j = 0;
        foreach ($official as $a) {
            $j++;
            $r = Attempts::score($a, $exam);
            $cells = [$j, (string) ($a['user_code'] ?: $a['username']), $a['full_name'], (string) $a['class_name'], (string) $a['variant_code']];
            $styles = [0 => $c, 1 => $c, 3 => $c, 4 => $c];
            foreach ($ids as $q) {
                $it = $r['items'][$q] ?? null;
                $given = $it ? (string) $it['given'] : '';
                $given = strpos($q, 'p2.') === 0 ? str_replace('D', 'Đ', $given) : $given;
                $blank = trim($given, '_') === '';
                $styles[count($cells)] = $blank ? $muted : ($it && $it['ok'] ? $ok : $bad);
                $cells[] = $blank ? '–' : $given;
            }
            $styles[count($cells)] = $bold;
            $cells[] = $a['score'] !== null ? (float) $a['score'] : '';
            $sd->row($cells, $t, $styles);
        }
        $sd->freeze('F5');

        // --- Sheet 3: Thống kê
        $scores = array_map(static fn($a) => $a['score'] !== null ? (float) $a['score'] : null, $official);
        $desc = Stats::describe($scores);
        $st = $x->sheet('Thống kê');
        $st->widths([30, 14, 14, 14, 14, 14, 14, 14]);
        $st->row(['THỐNG KÊ KẾT QUẢ – ' . mb_strtoupper($s['name'])], $title);
        $st->blank();
        $st->row(['Chỉ số', 'Giá trị'], $h);
        $targets = count($rows);
        foreach ([
            ['Số học sinh dự thi', $targets], ['Số bài đã nộp', $desc['n']], ['Vắng / chưa làm', $targets - $desc['n']],
            ['Điểm trung bình', $desc['mean']], ['Trung vị', $desc['median']], ['Độ lệch chuẩn', $desc['sd']],
            ['Cao nhất', $desc['max']], ['Thấp nhất', $desc['min']], ['Mốt (điểm nhiều nhất)', $desc['mode']],
        ] as $kv) {
            $st->row([$kv[0], $kv[1] === null ? '' : (is_float($kv[1]) ? round($kv[1], 2) : $kv[1])], $t, [1 => is_float($kv[1]) ? $dec : $c]);
        }
        $pass = count(array_filter($scores, static fn($v) => $v !== null && $v * 10 / ($maxScore ?: 10) >= 5));
        $st->row(['Tỉ lệ đạt từ 5 điểm (thang 10)', $desc['n'] ? $pass / $desc['n'] : 0], $t, [1 => $pct]);
        $st->blank();
        $st->row(['Xếp loại', 'Số lượng', 'Tỉ lệ'], $h);
        foreach (Stats::classification($official) as $k => $n) {
            $st->row([$k, $n, $desc['n'] ? $n / $desc['n'] : 0], $t, [1 => $c, 2 => $pct]);
        }
        $st->blank();
        $st->row(['Phổ điểm (mức điểm)', 'Số bài', 'Tỉ lệ'], $h);
        foreach (Stats::histogram($scores, $maxScore ?: 10) as $b) {
            $st->row([fmt_num($b['x']), $b['n'], $desc['n'] ? $b['n'] / $desc['n'] : 0], $t, [0 => $c, 1 => $c, 2 => $pct]);
        }
        $st->blank();
        $st->row(['Lớp', 'Số bài', 'Điểm TB', 'Độ lệch chuẩn', 'Cao nhất', 'Thấp nhất', 'Tỉ lệ ≥ 5', 'Tỉ lệ ≥ 8'], $h);
        foreach (Stats::byClass($official) as $cl) {
            $st->row([$cl['name'], $cl['n'], round((float) $cl['mean'], 2), round((float) $cl['sd'], 2), $cl['max'], $cl['min'], $cl['pass'], $cl['good']], $t, [1 => $c, 2 => $dec, 3 => $dec, 4 => $dec, 5 => $dec, 6 => $pct, 7 => $pct]);
        }

        // --- Sheet 4: Phân tích câu hỏi
        $sa = $x->sheet('Phân tích câu hỏi');
        $sa->widths([18, 10, 12, 18, 9, 9, 9, 10, 26, 36]);
        $sa->row(['PHÂN TÍCH CÂU HỎI – ' . mb_strtoupper($s['name'])], $title);
        $sa->row(['p: tỉ lệ làm đúng (độ khó) · D: độ phân biệt (nhóm 27% cao − 27% thấp) · r: tương quan điểm câu – tổng điểm. Câu tốt: 0,3 ≤ p ≤ 0,8 và D ≥ 0,3.'], $sub);
        foreach (Stats::items($official, $exam) as $va) {
            $sa->blank();
            $sa->row(['MÃ ĐỀ ' . $va['variant']['code'] . ' – ' . $va['n'] . ' bài · Độ tin cậy Cronbach α = ' . ($va['alpha'] !== null ? number_format($va['alpha'], 2, ',', '') . ' (' . Stats::alphaLabel($va['alpha']) . ')' : 'chưa đủ dữ liệu')], $x->style(['bold' => true, 'color' => '1D4ED8']));
            $sa->row(['Câu', 'Đáp án', 'Mức độ', 'Chủ đề', 'p', 'D', 'r', 'Bỏ trống', 'Phân bố lựa chọn', 'Nhận xét'], $h);
            foreach ($va['items'] as $it) {
                $dist = [];
                if ($it['part'] === 'p2') {
                    foreach (($it['subs'] ?? []) as $k2 => $v2) {
                        $dist[] = ['a', 'b', 'c', 'd'][$k2] . ') ' . round($v2 * 100) . '%';
                    }
                    $dist[] = 'đúng cả 4 ý: ' . round(($it['full'] ?? 0) * 100) . '%';
                } else {
                    foreach ($it['options'] as $o2 => $n2) {
                        $dist[] = $o2 . ': ' . $n2;
                    }
                }
                $sa->row([
                    $it['label'], $it['part'] === 'p2' ? str_replace('D', 'Đ', (string) $it['key']) : (string) $it['key'], (string) $it['level'], (string) $it['topic'],
                    round($it['p'], 2), $it['d'] !== null ? round($it['d'], 2) : '', $it['rpb'] !== null ? round($it['rpb'], 2) : '', $it['blank'],
                    implode(' · ', $dist), implode('; ', array_map(static fn($f) => $f[0], $it['flags'])),
                ], $t, [1 => $c, 4 => $dec, 5 => $dec, 6 => $dec, 7 => $c]);
            }
        }
        Logger::audit('results.export', 'session', (int) $s['id']);
        $x->download('ket-qua-' . preg_replace('/[^a-z0-9]+/', '-', strtolower(\App\Lib\Text::unaccent($s['name']))) . '-' . date('Ymd-Hi') . '.xlsx');
    }
}
