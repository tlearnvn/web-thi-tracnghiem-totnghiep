<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Scope;
use App\Lib\Attempts;
use App\Lib\Scoring;
use App\Lib\Sessions;
use App\Lib\Stats;

/** Thống kê & phân tích: phổ điểm, xếp loại, theo lớp, theo phần, phân tích từng câu hỏi. */
final class StatsController extends Controller
{
    public function index(): void
    {
        $this->authorize('stats.view');
        $params = [];
        $where = Scope::sessionListSql($params);
        $rows = $this->db->all(
            "SELECT s.id, s.name, s.mode, s.start_at, s.end_at, s.status, s.paused_at, s.created_at, e.title AS exam_title, e.structure, e.scoring, sub.name AS subject_name, sub.color AS subject_color,
                    COUNT(a.id) AS n, AVG(a.score) AS avg_score, MAX(a.score) AS max_score, MIN(a.score) AS min_score
             FROM {exam_sessions} s JOIN {exams} e ON e.id = s.exam_id LEFT JOIN {subjects} sub ON sub.id = e.subject_id
             LEFT JOIN {attempts} a ON a.session_id = s.id AND a.status NOT IN ('in_progress','voided')
             WHERE " . $where . '
             GROUP BY s.id, s.name, s.mode, s.start_at, s.end_at, s.status, s.paused_at, s.created_at, e.title, e.structure, e.scoring, sub.name, sub.color
             ORDER BY COALESCE(s.start_at, s.created_at) DESC LIMIT 60',
            $params
        );
        $bySubject = [];
        foreach ($rows as &$r) {
            $r['_max'] = Scoring::maxScore(\App\Lib\ExamFormat::normalizeStructure($r['structure']), Scoring::normalize($r['scoring'])) ?: 10;
            if ((int) $r['n'] > 0 && $r['mode'] === 'exam') {
                $k = $r['subject_name'] ?: 'Khác';
                $bySubject[$k]['sum'] = ($bySubject[$k]['sum'] ?? 0) + (float) $r['avg_score'] * 10 / $r['_max'] * (int) $r['n'];
                $bySubject[$k]['n'] = ($bySubject[$k]['n'] ?? 0) + (int) $r['n'];
                $bySubject[$k]['color'] = $r['subject_color'] ?: '#2563eb';
            }
        }
        unset($r);
        $this->render('stats/index', ['title' => 'Thống kê & phân tích', 'rows' => $rows, 'bySubject' => $bySubject]);
    }

    public function session(): void
    {
        $this->authorize('stats.view', 'results.view', 'results.view_all');
        $s = SessionsController::sessionFor(Request::int('id'), 'results');
        Attempts::finalizeExpired((int) $s['id']);
        $exam = Attempts::exam((int) $s['exam_id']);
        $classId = Request::int('class') ?: null;
        $att = Stats::officialAttempts($s, $classId);
        $max = Scoring::maxScore($exam['_structure'], $exam['_scoring']) ?: 10;
        $scores = array_map(static fn($a) => $a['score'] !== null ? (float) $a['score'] : null, $att);
        $items = Stats::items($att, $exam);
        $allItems = [];
        foreach ($items as $v) {
            foreach ($v['items'] as $it) {
                $allItems[] = $it;
            }
        }
        // Tỉ lệ đạt tính trên điểm thật (phổ điểm làm tròn theo mốc 0,5 nên không dùng để đếm)
        $graded = array_values(array_filter($scores, static fn($x) => $x !== null));
        $rate = [
            'pass' => count(array_filter($graded, static fn($x) => $x * 10 / $max >= 5 - 1e-9)),
            'good' => count(array_filter($graded, static fn($x) => $x * 10 / $max >= 8 - 1e-9)),
        ];
        $ranked = array_values(array_filter($att, static fn($a) => $a['score'] !== null));
        usort($ranked, static fn($x, $y) => (float) $y['score'] <=> (float) $x['score']);
        $this->render('stats/session', [
            'title' => 'Thống kê: ' . $s['name'],
            'crumbs' => ['Thống kê' => url('stats'), $s['name'] => null],
            's' => $s,
            'exam' => $exam,
            'max' => $max,
            'targets' => count(Sessions::students((int) $s['id'], $classId)),
            'desc' => Stats::describe($scores),
            'hist' => Stats::histogram($scores, $max),
            'rate' => $rate,
            'cls' => Stats::classification($att),
            'byClass' => Stats::byClass($att),
            'parts' => Stats::parts($att),
            'items' => $items,
            'levels' => Stats::byTag($allItems, 'level'),
            'topics' => Stats::byTag($allItems, 'topic'),
            'top' => array_slice($ranked, 0, 10),
            'weak' => array_slice(array_reverse(array_values(array_filter($ranked, static fn($a) => (float) $a['score'] * 10 / $max < 5))), 0, 10),
            'classes' => $this->db->all('SELECT c.id, c.name FROM {session_targets} t JOIN {classes} c ON c.id = t.class_id WHERE t.session_id = ? ORDER BY c.sort_key, c.name', [(int) $s['id']]),
            'classId' => $classId,
        ]);
    }
}
