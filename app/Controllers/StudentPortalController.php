<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Lib\Attempts;
use App\Lib\ExamFormat;
use App\Lib\Scoring;
use App\Lib\Sessions;

/** Cổng học sinh: danh sách bài thi, phòng chờ, kết quả, xem lại, lịch sử. */
final class StudentPortalController extends Controller
{
    private function guard(): array
    {
        $u = $this->user();
        if (!Auth::isStudent()) {
            // Cán bộ xem thử cổng học sinh
            if (!Auth::can('exam.take')) {
                $this->redirect('dashboard');
                exit;
            }
        }
        return $u;
    }

    /** Gắn trạng thái hiển thị cho từng ca thi của học sinh. */
    private function decorate(array $rows): array
    {
        $now = time();
        foreach ($rows as &$s) {
            $s['_state'] = Sessions::state($s, $now);
            $inProgress = null;
            $done = [];
            foreach ($s['attempts'] as $a) {
                if ($a['status'] === 'in_progress') {
                    $inProgress = $a;
                } elseif ($a['status'] !== 'voided') {
                    $done[] = $a;
                }
            }
            $s['_doing'] = $inProgress;
            $s['_done'] = $done;
            $s['_official'] = Sessions::officialAttempt($s, $done);
            $max = (int) $s['max_attempts'];
            $s['_can_start'] = !$inProgress && in_array($s['_state'], ['running'], true) && ($max === 0 || count($done) < $max);
            $s['_structure'] = ExamFormat::normalizeStructure($s['structure']);
        }
        return $rows;
    }

    public function index(): void
    {
        $u = $this->guard();
        Attempts::finalizeExpired();
        $rows = $this->decorate(Sessions::forStudent($u, 'exam'));
        $groups = ['now' => [], 'upcoming' => [], 'done' => []];
        foreach ($rows as $s) {
            if ($s['_doing'] || ($s['_can_start'] && in_array($s['_state'], ['running', 'paused'], true)) || ($s['_state'] === 'paused' && !$s['_done'])) {
                $groups['now'][] = $s;
            } elseif ($s['_state'] === 'upcoming') {
                $groups['upcoming'][] = $s;
            } else {
                $groups['done'][] = $s;
            }
        }
        $groups['upcoming'] = array_reverse($groups['upcoming']);
        $now = time();
        $ann = $this->db->all(
            "SELECT * FROM {announcements} WHERE (audience IN ('all', 'students') OR (audience = 'class' AND class_id = ?))
             AND (starts_at IS NULL OR starts_at <= ?) AND (ends_at IS NULL OR ends_at >= ?) ORDER BY is_pinned DESC, created_at DESC LIMIT 5",
            [(int) ($u['class_id'] ?? 0), $now, $now]
        );
        $practice = count(Sessions::forStudent($u, 'practice'));
        $this->render('student/index', ['title' => 'Bài thi của em', 'groups' => $groups, 'announcements' => $ann, 'practiceCount' => $practice, 'u' => $u], 'student');
    }

    public function practice(): void
    {
        $u = $this->guard();
        $rows = $this->decorate(Sessions::forStudent($u, 'practice'));
        $this->render('student/practice', ['title' => 'Luyện tập', 'rows' => $rows], 'student');
    }

    private function sessionForStudent(int $sid, array $u): array
    {
        $s = $sid > 0 ? $this->db->one('SELECT * FROM {exam_sessions} WHERE id = ?', [$sid]) : null;
        if (!$s || !Sessions::isTarget($s, $u)) {
            throw new HttpException(404, 'Không tìm thấy ca thi dành cho em.');
        }
        return $s;
    }

    public function lobby(): void
    {
        $u = $this->guard();
        $s = $this->sessionForStudent(Request::int('sid'), $u);
        Attempts::finalizeExpired((int) $s['id']);
        $exam = Attempts::exam((int) $s['exam_id']);
        $subject = $exam['subject_id'] ? $this->db->one('SELECT * FROM {subjects} WHERE id = ?', [(int) $exam['subject_id']]) : null;
        $rows = $this->decorate([array_merge($s, [
            'structure' => $exam['structure'], 'exam_title' => $exam['title'], 'exam_duration' => $exam['duration'],
            'subject_name' => $subject['name'] ?? null, 'subject_color' => $subject['color'] ?? null,
            'attempts' => $this->db->all('SELECT * FROM {attempts} WHERE session_id = ? AND user_id = ? ORDER BY attempt_no', [(int) $s['id'], (int) $u['id']]),
        ])]);
        $s2 = $rows[0];
        $this->render('student/lobby', [
            'title' => $s['name'],
            's' => $s2,
            'o' => Sessions::options($s),
            'exam' => $exam,
            'subject' => $subject,
            'duration' => Sessions::durationSec($s, $exam),
            'maxScore' => Scoring::maxScore($exam['_structure'], $exam['_scoring']),
        ], 'student');
    }

    public function start(): void
    {
        $u = $this->guard();
        $this->authorize('exam.take');
        $this->requirePost();
        $s = $this->sessionForStudent(Request::int('sid'), $u);
        $existing = $this->db->one("SELECT id FROM {attempts} WHERE session_id = ? AND user_id = ? AND status = 'in_progress'", [(int) $s['id'], (int) $u['id']]);
        if (!$existing && $s['access_code'] && !hash_equals(mb_strtolower(trim((string) $s['access_code'])), mb_strtolower(Request::str('access_code')))) {
            $this->flash('danger', 'Mã vào phòng không đúng. Hỏi giám thị để lấy mã.');
            $this->redirect('student/lobby', ['sid' => $s['id']]);
            return;
        }
        $exam = Attempts::exam((int) $s['exam_id']);
        [$a, $err] = Attempts::start($s, $exam, $u);
        if (!$a) {
            $this->flash('danger', (string) $err);
            $this->redirect('student/lobby', ['sid' => $s['id']]);
            return;
        }
        if (!$existing && $a['device_token']) {
            setcookie('tnd_' . $a['id'], (string) $a['device_token'], ['expires' => time() + 86400 * 3, 'path' => base_uri(), 'secure' => \App\Core\Session::isHttps(), 'httponly' => true, 'samesite' => 'Lax']);
            $_SESSION['_device_' . $a['id']] = $a['device_token'];
        }
        $this->redirect('exam/room', ['aid' => $a['id']]);
    }

    private function attemptForStudent(int $aid, array $u): array
    {
        $a = Attempts::find($aid);
        if (!$a || (int) $a['user_id'] !== (int) $u['id']) {
            throw new HttpException(404, 'Không tìm thấy bài làm.');
        }
        return $a;
    }

    public function result(): void
    {
        $u = $this->guard();
        $a = $this->attemptForStudent(Request::int('aid'), $u);
        if ($a['status'] === 'in_progress') {
            Attempts::finalizeExpired((int) $a['session_id']);
            $a = Attempts::find((int) $a['id']);
            if ($a['status'] === 'in_progress') {
                $this->redirect('exam/room', ['aid' => $a['id']]);
                return;
            }
        }
        if ($a['status'] === 'voided') {
            $this->flash('info', 'Bài làm này đã được giám thị hủy. Nếu còn lượt, em có thể vào thi lại.');
            $this->redirect('student/lobby', ['sid' => $a['session_id']]);
            return;
        }
        $s = $this->db->one('SELECT * FROM {exam_sessions} WHERE id = ?', [(int) $a['session_id']]);
        $exam = Attempts::exam((int) $a['exam_id']);
        $showScore = Sessions::canSeeScore($s, $a);
        $canReview = Sessions::canReview($s, $a);
        $detail = json_dec($a['score_detail'], []);
        $o = Sessions::options($s);
        $done = (int) $this->db->value("SELECT COUNT(*) FROM {attempts} WHERE session_id = ? AND user_id = ? AND status NOT IN ('in_progress','voided')", [(int) $s['id'], (int) $u['id']]);
        $canRetry = in_array(Sessions::state($s), ['running'], true) && ((int) $s['max_attempts'] === 0 || $done < (int) $s['max_attempts']);
        $this->render('student/result', [
            'title' => 'Kết quả – ' . $s['name'],
            'a' => $a, 's' => $s, 'exam' => $exam, 'o' => $o,
            'showScore' => $showScore, 'canReview' => $canReview, 'detail' => $detail, 'canRetry' => $canRetry,
            'variant' => $this->db->value('SELECT code FROM {exam_variants} WHERE id = ?', [(int) $a['variant_id']]),
        ], 'student');
    }

    /** Xem lại bài: đề PDF bên trái + phiếu trả lời có tô đúng/sai + lời giải. */
    public function review(): void
    {
        $u = $this->guard();
        $a = $this->attemptForStudent(Request::int('aid'), $u);
        $s = $this->db->one('SELECT * FROM {exam_sessions} WHERE id = ?', [(int) $a['session_id']]);
        if ($a['status'] === 'in_progress' || !Sessions::canReview($s, $a)) {
            $this->flash('warning', 'Chưa đến thời gian được xem lại bài.');
            $this->redirect('student/result', ['aid' => $a['id']]);
            return;
        }
        echo \App\Core\View::render('exam/review', self::reviewData($a, $s, $u, true), null);
    }

    /** Dữ liệu trang xem lại (dùng chung cho học sinh & giáo viên). */
    public static function reviewData(array $a, array $s, array $u, bool $asStudent): array
    {
        $db = \App\Core\App::db();
        $o = Sessions::options($s);
        $exam = Attempts::exam((int) $a['exam_id']);
        $variant = $db->one('SELECT * FROM {exam_variants} WHERE id = ?', [(int) $a['variant_id']]);
        $r = Attempts::score($a, $exam);
        $showKey = !$asStudent || $o['show_key'];
        $keys = [];
        $exps = [];
        foreach (Attempts::keys((int) $a['variant_id']) as $qid => $k) {
            if ($showKey) {
                $keys[$qid] = ['answer' => $k['answer']];
            }
            if ((!$asStudent || $o['show_explanations']) && $k['explanation']) {
                $exps[$qid] = $k['explanation'];
            }
        }
        if (!$showKey) {
            foreach ($r['items'] as &$it) {
                $it['key'] = null;
            }
            unset($it);
        }
        $subject = $exam['subject_id'] ? $db->value('SELECT name FROM {subjects} WHERE id = ?', [(int) $exam['subject_id']]) : '';
        $class = $u['class_id'] ? $db->value('SELECT name FROM {classes} WHERE id = ?', [(int) $u['class_id']]) : '';
        return [
            'title' => 'Xem lại bài – ' . $s['name'],
            'a' => $a, 's' => $s, 'exam' => $exam, 'variant' => $variant,
            'asStudent' => $asStudent,
            'cfg' => [
                'aid' => (int) $a['id'],
                'structure' => $exam['_structure'],
                'answers' => json_dec($a['answers'], []),
                'results' => ['items' => $r['items'], 'parts' => $r['parts']],
                'keys' => (object) $keys,
                'explanations' => (object) $exps,
                'score' => $a['score'] !== null ? (float) $a['score'] : $r['score'],
                'max' => $r['max'],
                'pdf' => $variant['pdf_file_id'] ? ($asStudent ? url('exam/pdf', ['aid' => $a['id']]) : url('files/pdf', ['variant_id' => $variant['id']])) : null,
                'solution' => $variant['solution_file_id'] && (!$asStudent || $o['show_solution_pdf']) ? ($asStudent ? url('exam/pdf', ['aid' => $a['id'], 'kind' => 'solution']) : url('files/pdf', ['variant_id' => $variant['id'], 'kind' => 'solution'])) : null,
                'protected' => $asStudent,
                'pdfKey' => $asStudent && $o['protect_pdf'] ? Attempts::pdfKey($a) : null,
                'watermark' => $asStudent && $o['watermark'] ? ($u['full_name'] . ' • ' . ($u['code'] ?: $u['username'])) : '',
                'header' => [
                    'exam' => $s['name'], 'subject' => $subject, 'date' => fmt_dt($a['started_at'], 'd/m/Y'), 'name' => $u['full_name'],
                    'birthday' => fmt_date($u['birthday']), 'className' => $class, 'code' => $u['code'] ?: $u['username'], 'variant' => $variant['code'], 'room' => $s['room'] ?: '',
                ],
                'back' => $asStudent ? url('student/result', ['aid' => $a['id']]) : url('results/attempt', ['id' => $a['id']]),
            ],
        ];
    }

    public function history(): void
    {
        $u = $this->guard();
        $rows = $this->db->all(
            "SELECT a.*, s.name AS session_name, s.mode, s.opts, s.released, s.status AS s_status, s.start_at, s.end_at, s.paused_at AS s_paused, s.max_attempts,
                    e.title AS exam_title, sub.name AS subject_name, sub.color AS subject_color
             FROM {attempts} a JOIN {exam_sessions} s ON s.id = a.session_id JOIN {exams} e ON e.id = a.exam_id LEFT JOIN {subjects} sub ON sub.id = e.subject_id
             WHERE a.user_id = ? AND a.status <> 'voided' ORDER BY a.started_at DESC",
            [(int) $u['id']]
        );
        foreach ($rows as &$r) {
            $sess = ['id' => $r['session_id'], 'mode' => $r['mode'], 'opts' => $r['opts'], 'released' => $r['released'], 'status' => $r['s_status'], 'start_at' => $r['start_at'], 'end_at' => $r['end_at'], 'paused_at' => $r['s_paused']];
            $r['_show'] = Sessions::canSeeScore($sess, $r);
            $r['_review'] = Sessions::canReview($sess, $r);
        }
        unset($r);
        $this->render('student/history', ['title' => 'Kết quả & lịch sử làm bài', 'rows' => $rows], 'student');
    }
}
