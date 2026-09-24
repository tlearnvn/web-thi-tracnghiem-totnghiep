<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\FileStore;
use App\Core\Scope;
use App\Lib\Attempts;
use App\Lib\Sessions;

final class DashboardController extends Controller
{
    public function index(): void
    {
        if (Auth::isStudent()) {
            $this->redirect('student');
            return;
        }
        $this->authorize('dashboard.view');
        Attempts::finalizeExpired();
        $db = $this->db;
        $now = time();
        $roles = Sessions::studentRoles();

        // --- Học sinh / lớp trong phạm vi ---
        $p = $roles;
        $classSql = Scope::classFilterSql('u.class_id', $p);
        $students = (int) $db->value('SELECT COUNT(*) FROM {users} u WHERE u.role IN ' . $db->in($roles) . ' AND ' . $classSql, $p);
        $p2 = [];
        $classes = (int) $db->value('SELECT COUNT(*) FROM {classes} c WHERE c.status = \'active\' AND ' . Scope::classFilterSql('c.id', $p2), $p2);

        // --- Đề thi ---
        $uid = (int) Auth::id();
        $exams = Auth::can('exams.manage_all')
            ? (int) $db->value('SELECT COUNT(*) FROM {exams}')
            : (int) $db->value('SELECT COUNT(*) FROM {exams} WHERE created_by = ? OR is_shared = 1', [$uid]);

        // --- Ca thi trong phạm vi ---
        $sp = [];
        $sessionWhere = Scope::sessionListSql($sp);
        $sessions = $db->all(
            'SELECT s.*, e.title AS exam_title, sub.name AS subject_name, sub.color AS subject_color
             FROM {exam_sessions} s JOIN {exams} e ON e.id = s.exam_id LEFT JOIN {subjects} sub ON sub.id = e.subject_id
             WHERE ' . $sessionWhere . ' AND s.status <> \'closed\' AND (s.end_at IS NULL OR s.end_at > ?)
             ORDER BY COALESCE(s.start_at, s.created_at) ASC LIMIT 12',
            array_merge($sp, [$now - 3600])
        );
        $live = [];
        $upcoming = [];
        foreach ($sessions as $s) {
            $st = Sessions::state($s, $now);
            $s['_state'] = $st;
            $s['_stats'] = $db->one(
                "SELECT COUNT(*) AS total, SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS doing,
                        SUM(CASE WHEN status <> 'in_progress' THEN 1 ELSE 0 END) AS done
                 FROM {attempts} WHERE session_id = ?",
                [(int) $s['id']]
            );
            $s['_targets'] = count(Sessions::students((int) $s['id']));
            if (in_array($st, ['running', 'paused'], true)) {
                $live[] = $s;
            } elseif ($st === 'upcoming') {
                $upcoming[] = $s;
            }
        }

        $today = strtotime('today');
        $sp2 = [];
        $sw = Scope::sessionListSql($sp2);
        $submittedToday = (int) $db->value(
            "SELECT COUNT(*) FROM {attempts} a JOIN {exam_sessions} s ON s.id = a.session_id WHERE a.status <> 'in_progress' AND a.submitted_at >= ? AND " . $sw,
            array_merge([$today], $sp2)
        );
        $sp3 = [];
        $sw3 = Scope::sessionListSql($sp3);
        $doingNow = (int) $db->value(
            "SELECT COUNT(*) FROM {attempts} a JOIN {exam_sessions} s ON s.id = a.session_id WHERE a.status = 'in_progress' AND a.last_seen_at >= ? AND " . $sw3,
            array_merge([$now - 90], $sp3)
        );

        // --- Điểm trung bình các ca thi gần đây ---
        $sp4 = [];
        $sw4 = Scope::sessionListSql($sp4);
        $recent = $db->all(
            "SELECT s.id, s.name, s.mode, COUNT(a.id) AS n, AVG(a.score) AS avg_score, MAX(a.score) AS max_score
             FROM {exam_sessions} s JOIN {attempts} a ON a.session_id = s.id AND a.status <> 'in_progress' AND a.score IS NOT NULL
             WHERE " . $sw4 . "
             GROUP BY s.id, s.name, s.mode ORDER BY MAX(a.submitted_at) DESC LIMIT 8",
            $sp4
        );
        $recent = array_reverse($recent);

        $announcements = $db->all(
            "SELECT * FROM {announcements} WHERE audience IN ('all', 'staff') AND (starts_at IS NULL OR starts_at <= ?) AND (ends_at IS NULL OR ends_at >= ?)
             ORDER BY is_pinned DESC, created_at DESC LIMIT 5",
            [$now, $now]
        );

        $activity = [];
        if (Auth::can('logs.view')) {
            $activity = $db->all(
                'SELECT l.*, u.full_name FROM {audit_logs} l LEFT JOIN {users} u ON u.id = l.user_id ORDER BY l.id DESC LIMIT 8'
            );
        }

        $system = null;
        if (Auth::isAdmin()) {
            $info = $db->info();
            $system = [
                'db' => $info,
                'files' => FileStore::totalSize(),
                'online' => (int) $db->value('SELECT COUNT(DISTINCT user_id) FROM {web_sessions} WHERE user_id IS NOT NULL AND last_activity >= ?', [$now - 600]),
                'errors' => (int) $db->value("SELECT COUNT(*) FROM {error_logs} WHERE level = 'error' AND created_at >= ?", [$now - 86400 * 7]),
            ];
        }

        $this->render('dashboard/index', [
            'title' => 'Tổng quan',
            'stats' => compact('students', 'classes', 'exams', 'submittedToday', 'doingNow'),
            'live' => $live,
            'upcoming' => $upcoming,
            'recent' => $recent,
            'announcements' => $announcements,
            'activity' => $activity,
            'system' => $system,
        ]);
    }
}
