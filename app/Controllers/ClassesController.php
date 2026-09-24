<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Scope;
use App\Core\Settings;
use App\Lib\Sessions;
use App\Lib\Text;
use App\Lib\Users;

final class ClassesController extends Controller
{
    private function teachers(): array
    {
        $roles = Users::staffRoles();
        return $this->db->all('SELECT id, full_name, code, subject_id FROM {users} WHERE role IN ' . $this->db->in($roles) . " AND status = 'active' ORDER BY sort_key", $roles);
    }

    private function subjects(): array
    {
        return $this->db->all('SELECT id, name FROM {subjects} WHERE is_active = 1 ORDER BY sort_order, name');
    }

    private function classOr404(int $id): array
    {
        $c = $this->findOr404('classes', $id, 'Không tìm thấy lớp.');
        if (!Scope::canAccessClass((int) $c['id'])) {
            throw new HttpException(403, 'Bạn không phụ trách lớp này.');
        }
        return $c;
    }

    public function index(): void
    {
        $this->authorize('classes.view', 'classes.manage');
        $params = [];
        $where = [Scope::classFilterSql('c.id', $params)];
        $year = Request::str('year');
        if ($year !== '') {
            $where[] = 'c.school_year = ?';
            $params[] = $year;
        }
        $status = Request::str('status', 'active');
        if ($status !== 'all') {
            $where[] = 'c.status = ?';
            $params[] = $status;
        }
        $roles = Sessions::studentRoles();
        $rows = $this->db->all(
            'SELECT c.*, t.full_name AS homeroom_name,
                    (SELECT COUNT(*) FROM {users} u WHERE u.class_id = c.id AND u.role IN ' . $this->db->in($roles) . ') AS students,
                    (SELECT COUNT(*) FROM {class_teachers} ct WHERE ct.class_id = c.id) AS teachers
             FROM {classes} c LEFT JOIN {users} t ON t.id = c.homeroom_teacher_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY c.school_year DESC, c.grade DESC, c.sort_key, c.name',
            array_merge($roles, $params)
        );
        $years = $this->db->column('SELECT DISTINCT school_year FROM {classes} WHERE school_year IS NOT NULL ORDER BY school_year DESC');
        $this->render('classes/index', [
            'title' => 'Lớp học',
            'crumbs' => ['Lớp học' => null],
            'rows' => $rows,
            'years' => $years,
            'canManage' => Auth::can('classes.manage'),
        ]);
    }

    public function create(): void
    {
        $this->authorize('classes.manage');
        $this->render('classes/form', [
            'title' => 'Thêm lớp',
            'crumbs' => ['Lớp học' => url('classes'), 'Thêm lớp' => null],
            'c' => ['id' => 0, 'name' => '', 'grade' => 12, 'school_year' => Settings::get('default_school_year'), 'homeroom_teacher_id' => null, 'description' => '', 'status' => 'active'],
            'assign' => [],
            'teachers' => $this->teachers(),
            'subjects' => $this->subjects(),
        ]);
    }

    public function edit(): void
    {
        $this->authorize('classes.manage');
        $c = $this->classOr404(Request::int('id'));
        $this->render('classes/form', [
            'title' => 'Sửa lớp ' . $c['name'],
            'crumbs' => ['Lớp học' => url('classes'), $c['name'] => url('classes/view', ['id' => $c['id']]), 'Sửa' => null],
            'c' => $c,
            'assign' => $this->db->all('SELECT user_id, subject_id FROM {class_teachers} WHERE class_id = ? ORDER BY id', [(int) $c['id']]),
            'teachers' => $this->teachers(),
            'subjects' => $this->subjects(),
        ]);
    }

    public function save(): void
    {
        $this->authorize('classes.manage');
        $this->requirePost();
        $id = Request::int('id');
        $cur = $id ? $this->classOr404($id) : null;
        $name = trim((string) preg_replace('/\s+/', ' ', Request::str('name')));
        $year = Request::str('school_year');
        if ($name === '') {
            $this->flash('danger', 'Vui lòng nhập tên lớp.');
            $this->back('classes');
            return;
        }
        $dup = $this->db->value('SELECT id FROM {classes} WHERE LOWER(name) = LOWER(?) AND COALESCE(school_year, \'\') = ?' . ($cur ? ' AND id <> ' . (int) $cur['id'] : ''), [$name, $year]);
        if ($dup) {
            $this->flash('danger', 'Lớp "' . $name . '" năm học ' . $year . ' đã tồn tại.');
            $this->back('classes');
            return;
        }
        $data = [
            'name' => mb_substr($name, 0, 50),
            'grade' => Request::int('grade') ?: Text::gradeFromClassName($name),
            'school_year' => $year !== '' ? $year : null,
            'homeroom_teacher_id' => Request::int('homeroom_teacher_id') ?: null,
            'description' => mb_substr(Request::str('description'), 0, 255) ?: null,
            'status' => Request::str('status') === 'archived' ? 'archived' : 'active',
            'sort_key' => Text::collate($name),
            'updated_at' => time(),
        ];
        $this->db->transaction(function () use (&$id, $cur, $data) {
            if ($cur) {
                $this->db->update('classes', $data, 'id = ?', [(int) $cur['id']]);
                $id = (int) $cur['id'];
            } else {
                $data['created_at'] = time();
                $id = $this->db->insert('classes', $data);
            }
            $this->db->run('DELETE FROM {class_teachers} WHERE class_id = ?', [$id]);
            $users = Request::arr('assign_user');
            $subjects = Request::arr('assign_subject');
            $seen = [];
            foreach ($users as $i => $uid) {
                $uid = (int) $uid;
                $sid = (int) ($subjects[$i] ?? 0) ?: null;
                $k = $uid . '-' . $sid;
                if ($uid <= 0 || isset($seen[$k])) {
                    continue;
                }
                $seen[$k] = true;
                $this->db->insert('class_teachers', ['class_id' => $id, 'user_id' => $uid, 'subject_id' => $sid, 'created_at' => time()]);
            }
        });
        Logger::audit($cur ? 'class.update' : 'class.create', 'class', $id, ['name' => $name]);
        $this->flash('success', ($cur ? 'Đã cập nhật lớp ' : 'Đã tạo lớp ') . $name . '.');
        $this->redirect('classes/view', ['id' => $id]);
    }

    public function view(): void
    {
        $this->authorize('classes.view', 'classes.manage');
        $c = $this->classOr404(Request::int('id'));
        $roles = Sessions::studentRoles();
        $students = $this->db->all(
            'SELECT u.*, (SELECT AVG(a.score) FROM {attempts} a WHERE a.user_id = u.id AND a.score IS NOT NULL) AS avg_score,
                    (SELECT COUNT(*) FROM {attempts} a WHERE a.user_id = u.id AND a.status <> \'in_progress\') AS done
             FROM {users} u WHERE u.class_id = ? AND u.role IN ' . $this->db->in($roles) . ' ORDER BY u.sort_key',
            array_merge([(int) $c['id']], $roles)
        );
        $teachers = $this->db->all(
            'SELECT ct.*, u.full_name, u.code, s.name AS subject_name FROM {class_teachers} ct JOIN {users} u ON u.id = ct.user_id LEFT JOIN {subjects} s ON s.id = ct.subject_id WHERE ct.class_id = ? ORDER BY s.sort_order, u.sort_key',
            [(int) $c['id']]
        );
        $homeroom = $c['homeroom_teacher_id'] ? Users::find((int) $c['homeroom_teacher_id']) : null;
        $sessions = $this->db->all(
            "SELECT s.id, s.name, s.mode, s.start_at, sub.name AS subject_name,
                    COUNT(a.id) AS n, AVG(a.score) AS avg_score
             FROM {session_targets} t JOIN {exam_sessions} s ON s.id = t.session_id JOIN {exams} e ON e.id = s.exam_id
             LEFT JOIN {subjects} sub ON sub.id = e.subject_id
             LEFT JOIN {attempts} a ON a.session_id = s.id AND a.status <> 'in_progress' AND a.user_id IN (SELECT id FROM {users} WHERE class_id = ?)
             WHERE t.class_id = ? GROUP BY s.id, s.name, s.mode, s.start_at, sub.name ORDER BY s.start_at DESC LIMIT 20",
            [(int) $c['id'], (int) $c['id']]
        );
        $this->render('classes/view', [
            'title' => 'Lớp ' . $c['name'],
            'crumbs' => ['Lớp học' => url('classes'), $c['name'] => null],
            'c' => $c, 'students' => $students, 'teachers' => $teachers, 'homeroom' => $homeroom, 'sessions' => $sessions,
        ]);
    }

    public function delete(): void
    {
        $this->authorize('classes.manage');
        $this->requirePost();
        $c = $this->classOr404(Request::int('id'));
        $n = (int) $this->db->value('SELECT COUNT(*) FROM {users} WHERE class_id = ?', [(int) $c['id']]);
        $mode = Request::str('students', 'detach');
        $this->db->transaction(function () use ($c, $mode, $n) {
            if ($n > 0 && $mode === 'delete') {
                foreach ($this->db->column('SELECT id FROM {users} WHERE class_id = ? AND role IN ' . $this->db->in(Sessions::studentRoles()), array_merge([(int) $c['id']], Sessions::studentRoles())) as $uid) {
                    Users::delete((int) $uid);
                }
            }
            $this->db->run('UPDATE {users} SET class_id = NULL WHERE class_id = ?', [(int) $c['id']]);
            $this->db->run('DELETE FROM {class_teachers} WHERE class_id = ?', [(int) $c['id']]);
            $this->db->run('DELETE FROM {session_targets} WHERE class_id = ?', [(int) $c['id']]);
            $this->db->run('DELETE FROM {classes} WHERE id = ?', [(int) $c['id']]);
        });
        Logger::audit('class.delete', 'class', (int) $c['id'], ['name' => $c['name'], 'students' => $mode]);
        $this->flash('success', 'Đã xóa lớp ' . $c['name'] . '.');
        $this->redirect('classes');
    }

    /** Lên lớp / chuyển năm học: đổi tên lớp hàng loạt (10A1 -> 11A1) và năm học. */
    public function promote(): void
    {
        $this->authorize('classes.manage');
        $this->requirePost();
        $ids = Request::ints('ids');
        $newYear = Request::str('new_year');
        if (!$ids || $newYear === '') {
            $this->flash('warning', 'Chọn lớp và nhập năm học mới.');
            $this->back('classes');
            return;
        }
        $n = 0;
        foreach ($ids as $id) {
            $c = $this->classOr404($id);
            $grade = (int) ($c['grade'] ?: Text::gradeFromClassName($c['name']));
            $newName = $c['name'];
            if ($grade >= 6 && $grade < 12) {
                $newName = (string) preg_replace('/^' . $grade . '/', (string) ($grade + 1), $c['name']);
            }
            $this->db->update('classes', [
                'name' => $newName, 'grade' => $grade < 12 ? $grade + 1 : $grade, 'school_year' => $newYear,
                'status' => $grade >= 12 ? 'archived' : 'active', 'sort_key' => Text::collate($newName), 'updated_at' => time(),
            ], 'id = ?', [$id]);
            $n++;
        }
        Logger::audit('class.update', 'class', null, ['promote' => $n, 'year' => $newYear]);
        $this->flash('success', 'Đã chuyển ' . $n . ' lớp sang năm học ' . $newYear . ' (lớp 12 được lưu trữ).');
        $this->redirect('classes');
    }
}
