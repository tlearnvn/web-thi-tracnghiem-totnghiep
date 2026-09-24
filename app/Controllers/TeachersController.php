<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Lib\PeopleImporter;
use App\Lib\Text;
use App\Lib\Users;
use App\Lib\XlsxWriter;

final class TeachersController extends Controller
{
    /** Vai trò được phép gán ở trang giáo viên (không gồm quản trị nếu không có quyền users.manage). */
    private function assignableRoles(): array
    {
        $roles = $this->db->keyed("SELECT code, name FROM {roles} WHERE kind <> 'student' ORDER BY sort_order", [], 'code', 'name');
        if (!Auth::can('users.manage')) {
            unset($roles['admin']);
        }
        return $roles;
    }

    private function teacherOr404(int $id): array
    {
        $u = Users::find($id);
        if (!$u || !array_key_exists($u['role'], $this->db->keyed("SELECT code, name FROM {roles} WHERE kind <> 'student'", [], 'code', 'name'))) {
            throw new HttpException(404, 'Không tìm thấy giáo viên.');
        }
        if ($u['role'] === 'admin' && !Auth::can('users.manage')) {
            throw new HttpException(403);
        }
        return $u;
    }

    public function index(): void
    {
        $this->authorize('teachers.view', 'teachers.manage');
        $roles = array_keys($this->assignableRoles());
        if (!$roles) {
            $roles = ['teacher'];
        }
        $where = ['u.role IN ' . $this->db->in($roles)];
        $params = $roles;
        $q = Request::str('q');
        if ($q !== '') {
            $where[] = 'u.search_text LIKE ?';
            $params[] = '%' . Text::normalizeSearch($q) . '%';
        }
        $role = Request::str('role');
        if ($role !== '' && in_array($role, $roles, true)) {
            $where[] = 'u.role = ?';
            $params[] = $role;
        }
        $sid = Request::int('subject_id');
        if ($sid) {
            $where[] = 'u.subject_id = ?';
            $params[] = $sid;
        }
        $total = (int) $this->db->value('SELECT COUNT(*) FROM {users} u WHERE ' . implode(' AND ', $where), $params);
        $pager = new Paginator($total, Paginator::perPageFromRequest(50));
        $rows = $this->db->all(
            'SELECT u.*, s.name AS subject_name, r.name AS role_name FROM {users} u
             LEFT JOIN {subjects} s ON s.id = u.subject_id LEFT JOIN {roles} r ON r.code = u.role
             WHERE ' . implode(' AND ', $where) . ' ORDER BY u.sort_key' . $pager->sqlLimit(),
            $params
        );
        $ids = array_map(static fn($r) => (int) $r['id'], $rows);
        $homeroom = [];
        $teach = [];
        if ($ids) {
            foreach ($this->db->all('SELECT id, name, homeroom_teacher_id FROM {classes} WHERE homeroom_teacher_id IN ' . $this->db->in($ids), $ids) as $c) {
                $homeroom[(int) $c['homeroom_teacher_id']][] = $c['name'];
            }
            foreach ($this->db->all('SELECT ct.user_id, c.name FROM {class_teachers} ct JOIN {classes} c ON c.id = ct.class_id WHERE ct.user_id IN ' . $this->db->in($ids) . ' ORDER BY c.sort_key', $ids) as $c) {
                $teach[(int) $c['user_id']][] = $c['name'];
            }
        }
        $this->render('teachers/index', [
            'title' => 'Giáo viên',
            'crumbs' => ['Giáo viên' => null],
            'rows' => $rows, 'pager' => $pager, 'homeroom' => $homeroom, 'teach' => $teach,
            'roles' => $this->assignableRoles(),
            'subjects' => $this->db->all('SELECT id, name FROM {subjects} ORDER BY sort_order'),
            'canManage' => Auth::can('teachers.manage'),
        ]);
    }

    private function formData(array $u): array
    {
        return [
            'u' => $u,
            'roles' => $this->assignableRoles(),
            'subjects' => $this->db->all('SELECT id, name FROM {subjects} WHERE is_active = 1 ORDER BY sort_order'),
            'classes' => $this->db->all("SELECT id, name, school_year FROM {classes} WHERE status = 'active' ORDER BY grade DESC, sort_key"),
            'homeroom' => $u['id'] ? array_map('intval', $this->db->column('SELECT id FROM {classes} WHERE homeroom_teacher_id = ?', [(int) $u['id']])) : [],
            'assign' => $u['id'] ? $this->db->all('SELECT class_id, subject_id FROM {class_teachers} WHERE user_id = ?', [(int) $u['id']]) : [],
        ];
    }

    public function create(): void
    {
        $this->authorize('teachers.manage');
        $this->render('teachers/form', ['title' => 'Thêm giáo viên', 'crumbs' => ['Giáo viên' => url('teachers'), 'Thêm mới' => null]] + $this->formData([
            'id' => 0, 'full_name' => '', 'username' => '', 'code' => '', 'email' => '', 'phone' => '', 'gender' => '', 'role' => 'teacher',
            'subject_id' => null, 'status' => 'active', 'must_change_password' => 1, 'note' => '',
        ]));
    }

    public function edit(): void
    {
        $this->authorize('teachers.manage');
        $u = $this->teacherOr404(Request::int('id'));
        $this->render('teachers/form', ['title' => 'Sửa giáo viên', 'crumbs' => ['Giáo viên' => url('teachers'), $u['full_name'] => null]] + $this->formData($u));
    }

    public function save(): void
    {
        $this->authorize('teachers.manage');
        $this->requirePost();
        $id = Request::int('id');
        $cur = $id ? $this->teacherOr404($id) : null;
        $name = Text::normalizeName(Request::str('full_name'));
        $username = Text::cleanUsername(Request::str('username'));
        $role = Request::str('role', 'teacher');
        $roles = $this->assignableRoles();
        $errors = [];
        if ($name === '') {
            $errors[] = 'Vui lòng nhập họ tên.';
        }
        if (!isset($roles[$role])) {
            $errors[] = 'Vai trò không hợp lệ.';
        }
        if ($username === '') {
            $username = $cur ? $cur['username'] : Users::uniqueUsername(Text::usernameFromName($name));
        }
        if (Users::usernameTaken($username, $cur ? (int) $cur['id'] : null)) {
            $errors[] = 'Tên đăng nhập "' . $username . '" đã được dùng.';
        }
        $email = Request::str('email');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email không hợp lệ.';
        }
        $password = (string) Request::post('password', '');
        $min = max(4, (int) Settings::get('password_min_length', 6));
        if ($password !== '' && mb_strlen($password) < $min) {
            $errors[] = 'Mật khẩu cần ít nhất ' . $min . ' ký tự.';
        }
        if ($cur && (int) $cur['id'] === (int) Auth::id() && $role !== $cur['role']) {
            $errors[] = 'Không thể tự đổi vai trò của chính mình.';
        }
        if ($errors) {
            \App\Core\Session::setOld($_POST);
            $this->flash('danger', implode(' ', $errors));
            $this->back('teachers');
            return;
        }
        $data = [
            'full_name' => $name, 'username' => $username, 'role' => $role, 'code' => mb_substr(Request::str('code'), 0, 50),
            'email' => $email, 'phone' => Request::str('phone'), 'gender' => in_array(Request::str('gender'), ['Nam', 'Nữ'], true) ? Request::str('gender') : null,
            'subject_id' => Request::int('subject_id') ?: null, 'status' => Request::str('status') === 'locked' ? 'locked' : 'active',
            'must_change_password' => Request::bool('must_change_password') ? 1 : 0, 'note' => mb_substr(Request::str('note'), 0, 255),
        ];
        $plain = null;
        if ($cur) {
            Users::update((int) $cur['id'], $data, $password !== '' ? $password : null);
            $id = (int) $cur['id'];
        } else {
            $plain = $password !== '' ? $password : Users::makePassword('random8');
            $id = Users::create($data, $plain);
        }
        // Chủ nhiệm & phân công
        $this->db->transaction(function () use ($id) {
            $this->db->run('UPDATE {classes} SET homeroom_teacher_id = NULL WHERE homeroom_teacher_id = ?', [$id]);
            foreach (Request::ints('homeroom') as $cid) {
                $this->db->update('classes', ['homeroom_teacher_id' => $id, 'updated_at' => time()], 'id = ?', [$cid]);
            }
            $this->db->run('DELETE FROM {class_teachers} WHERE user_id = ?', [$id]);
            $classes = Request::arr('assign_class');
            $subjects = Request::arr('assign_subject');
            $seen = [];
            foreach ($classes as $i => $cid) {
                $cid = (int) $cid;
                $sid = (int) ($subjects[$i] ?? 0) ?: null;
                if ($cid <= 0 || isset($seen[$cid . '-' . $sid])) {
                    continue;
                }
                $seen[$cid . '-' . $sid] = 1;
                $this->db->insert('class_teachers', ['class_id' => $cid, 'user_id' => $id, 'subject_id' => $sid, 'created_at' => time()]);
            }
        });
        Logger::audit($cur ? 'user.update' : 'user.create', 'user', $id, ['name' => $name, 'role' => $role]);
        if ($plain) {
            Users::stashCredentials('Tài khoản giáo viên mới', [['class' => '', 'code' => $data['code'], 'name' => $name, 'username' => $username, 'password' => $plain]]);
            $this->flash('success', 'Đã tạo tài khoản ' . $username . ' – mật khẩu: ' . $plain);
        } else {
            $this->flash('success', 'Đã lưu thông tin giáo viên ' . $name . '.');
        }
        $this->redirect('teachers');
    }

    public function delete(): void
    {
        $this->authorize('teachers.manage');
        $this->requirePost();
        $u = $this->teacherOr404(Request::int('id'));
        if ((int) $u['id'] === (int) Auth::id()) {
            $this->flash('danger', 'Không thể xóa tài khoản đang đăng nhập.');
            $this->back('teachers');
            return;
        }
        $this->db->run('UPDATE {exams} SET created_by = ? WHERE created_by = ?', [(int) Auth::id(), (int) $u['id']]);
        $this->db->run('UPDATE {exam_sessions} SET created_by = ? WHERE created_by = ?', [(int) Auth::id(), (int) $u['id']]);
        Users::delete((int) $u['id']);
        Logger::audit('user.delete', 'user', (int) $u['id'], ['name' => $u['full_name']]);
        $this->flash('success', 'Đã xóa tài khoản ' . $u['full_name'] . '. Đề thi và ca thi của giáo viên được chuyển cho bạn.');
        $this->redirect('teachers');
    }

    public function password(): void
    {
        $this->authorize('teachers.manage');
        $this->requirePost();
        $u = $this->teacherOr404(Request::int('id'));
        $pw = Users::makePassword('random8');
        Users::setPassword((int) $u['id'], $pw, true);
        Users::stashCredentials('Cấp lại mật khẩu giáo viên', [['class' => '', 'code' => $u['code'], 'name' => $u['full_name'], 'username' => $u['username'], 'password' => $pw]]);
        Logger::audit('user.reset_password', 'user', (int) $u['id']);
        $this->flash('success', 'Mật khẩu mới của ' . $u['full_name'] . ': ' . $pw . ' (bắt đổi khi đăng nhập).');
        $this->back('teachers');
    }

    // ------------------------------------------------------------ Nhập Excel

    public function import(): void
    {
        $this->authorize('teachers.manage');
        $this->render('teachers/import', ['title' => 'Nhập giáo viên từ Excel', 'crumbs' => ['Giáo viên' => url('teachers'), 'Nhập từ Excel' => null], 'roles' => $this->assignableRoles()]);
    }

    public function template(): void
    {
        $this->authorize('teachers.manage');
        $data = PeopleImporter::templateXlsx('teacher');
        Response::downloadHeaders('mau-nhap-giao-vien.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', strlen($data));
        echo $data;
    }

    public function importRun(): void
    {
        $this->authorize('teachers.manage');
        $this->requirePost();
        @set_time_limit(300);
        $f = Request::file('file');
        if (!$f || ($err = Request::uploadError($f))) {
            $this->fail($f ? (string) $err : 'Vui lòng chọn tệp Excel.');
            return;
        }
        $parsed = PeopleImporter::parse((string) file_get_contents($f['tmp_name']), 'teacher', Request::int('sheet'));
        if ($parsed['errors']) {
            $this->fail(implode(' ', $parsed['errors']), 422, ['sheets' => $parsed['sheets']]);
            return;
        }
        $commit = Request::bool('commit');
        $defaultRole = Request::str('default_role', 'teacher');
        $roles = $this->assignableRoles();
        $roleByName = [];
        foreach ($roles as $code => $n) {
            $roleByName[Text::normalizeHeader($n)] = $code;
            $roleByName[Text::normalizeHeader($code)] = $code;
        }
        $roleByName['gv'] = 'teacher';
        $subjects = [];
        foreach ($this->db->all('SELECT id, code, name, short_name FROM {subjects}') as $s) {
            $subjects[Text::normalizeHeader($s['name'])] = (int) $s['id'];
            $subjects[Text::normalizeHeader($s['code'])] = (int) $s['id'];
            if ($s['short_name']) {
                $subjects[Text::normalizeHeader($s['short_name'])] = (int) $s['id'];
            }
        }
        $year = (string) Settings::get('default_school_year');
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        $preview = [];
        $creds = [];
        $reserved = [];
        $ops = [];
        foreach ($parsed['rows'] as $r) {
            $d = $r['data'];
            $item = ['row' => $r['row'], 'name' => $d['full_name'] ?? '', 'code' => $d['code'] ?? '', 'subject' => $d['subject'] ?? '', 'homeroom' => $d['homeroom'] ?? '', 'classes' => $d['classes'] ?? '', 'username' => '', 'status' => '', 'message' => implode('; ', array_merge($r['errors'], $r['warnings'] ?? []))];
            if (!empty($r['skip'])) {
                $item['status'] = 'error';
                $result['errors']++;
                $preview[] = $item;
                continue;
            }
            $role = $d['role'] !== '' ? ($roleByName[Text::normalizeHeader($d['role'])] ?? null) : $defaultRole;
            if (!$role || !isset($roles[$role])) {
                $item['status'] = 'error';
                $item['message'] = 'Vai trò "' . $d['role'] . '" không hợp lệ';
                $result['errors']++;
                $preview[] = $item;
                continue;
            }
            $subjectId = $d['subject'] !== '' ? ($subjects[Text::normalizeHeader($d['subject'])] ?? null) : null;
            if ($d['subject'] !== '' && !$subjectId) {
                $item['message'] = trim($item['message'] . ' Không nhận ra môn "' . $d['subject'] . '".');
            }
            $existing = null;
            if ($d['username'] !== '') {
                $existing = $this->db->one('SELECT * FROM {users} WHERE username = ?', [$d['username']]);
            }
            if (!$existing && $d['code'] !== '') {
                $existing = $this->db->one("SELECT * FROM {users} WHERE code = ? AND role IN (SELECT code FROM {roles} WHERE kind <> 'student')", [$d['code']]);
            }
            if ($existing && !isset($roles[$existing['role']])) {
                $item['status'] = 'error';
                $item['message'] = 'Trùng với tài khoản không được phép sửa (' . $existing['username'] . ')';
                $result['errors']++;
                $preview[] = $item;
                continue;
            }
            $assignments = [];
            foreach (preg_split('/[,;\n]+/', (string) $d['classes']) as $cn) {
                $cn = trim($cn);
                if ($cn !== '') {
                    $assignments[] = $cn;
                }
            }
            if ($existing) {
                $item['username'] = $existing['username'];
                $item['status'] = 'update';
                $pw = $d['password'] !== '' ? $d['password'] : null;
                if ($commit) {
                    $ops[] = ['update', (int) $existing['id'], ['full_name' => $d['full_name'], 'role' => $role, 'subject_id' => $subjectId ?: $existing['subject_id'], 'email' => $d['email'] ?: $existing['email'], 'phone' => $d['phone'] ?: $existing['phone'], 'code' => $d['code'] ?: $existing['code']], $pw, $pw ? Auth::hash($pw) : null, $d['homeroom'], $assignments, $subjectId];
                    if ($pw) {
                        $creds[] = ['class' => '', 'code' => $d['code'], 'name' => $d['full_name'], 'username' => $existing['username'], 'password' => $pw];
                    }
                }
                $result['updated']++;
                $preview[] = $item;
                continue;
            }
            $username = $d['username'] !== '' && !Users::usernameTaken($d['username']) && !in_array($d['username'], $reserved, true)
                ? $d['username'] : Users::uniqueUsername($d['username'] !== '' ? $d['username'] : Text::usernameFromName($d['full_name']), $reserved);
            $reserved[] = $username;
            $item['username'] = $username;
            $item['status'] = 'new';
            $pw = $d['password'] !== '' ? $d['password'] : Users::makePassword('random8');
            if ($commit) {
                $ops[] = ['create', ['username' => $username, 'role' => $role, 'full_name' => $d['full_name'], 'code' => $d['code'], 'gender' => $d['gender'], 'birthday' => $d['birthday'], 'email' => $d['email'], 'phone' => $d['phone'], 'subject_id' => $subjectId, 'note' => $d['note'], 'must_change_password' => 1], $pw, Auth::hash($pw), $d['homeroom'], $assignments, $subjectId];
                $creds[] = ['class' => '', 'code' => $d['code'], 'name' => $d['full_name'], 'username' => $username, 'password' => $pw];
            }
            $result['created']++;
            $preview[] = $item;
        }
        if ($commit && $ops) {
            $this->db->transaction(function () use ($ops, $year) {
                foreach ($ops as $op) {
                    if ($op[0] === 'create') {
                        $uid = Users::create($op[1], $op[2], $op[3]);
                        [, , , , $homeroom, $assign, $sid] = $op;
                    } else {
                        $uid = $op[1];
                        Users::update($uid, $op[2], $op[3], $op[4]);
                        [, , , , , $homeroom, $assign, $sid] = $op;
                    }
                    if ($homeroom !== '') {
                        $cid = Users::classByName($homeroom, $year, true);
                        $this->db->update('classes', ['homeroom_teacher_id' => $uid], 'id = ?', [$cid]);
                    }
                    foreach ($assign as $cn) {
                        $cid = Users::classByName($cn, $year, true);
                        if (!$this->db->value('SELECT 1 FROM {class_teachers} WHERE class_id = ? AND user_id = ?', [$cid, $uid])) {
                            $this->db->insert('class_teachers', ['class_id' => $cid, 'user_id' => $uid, 'subject_id' => $sid, 'created_at' => time()]);
                        }
                    }
                }
            });
            Logger::audit('user.import', 'user', null, $result + ['type' => 'teacher']);
            if ($creds) {
                Users::stashCredentials('Tài khoản giáo viên nhập từ Excel', $creds);
            }
        }
        $this->ok(['committed' => $commit, 'result' => $result, 'rows' => $preview, 'columns' => $parsed['columns'], 'sheets' => $parsed['sheets'], 'credentials' => $commit ? count($creds) : 0, 'credentials_url' => url('students/credentials')]);
    }

    public function export(): void
    {
        $this->authorize('teachers.view', 'teachers.manage');
        $roles = array_keys($this->assignableRoles());
        $rows = $this->db->all(
            'SELECT u.*, s.name AS subject_name, r.name AS role_name FROM {users} u LEFT JOIN {subjects} s ON s.id = u.subject_id LEFT JOIN {roles} r ON r.code = u.role WHERE u.role IN ' . $this->db->in($roles) . ' ORDER BY u.sort_key',
            $roles
        );
        $x = new XlsxWriter();
        $h = $x->style(['bold' => true, 'fill' => '1D4ED8', 'color' => 'FFFFFF', 'border' => true, 'align' => 'center']);
        $t = $x->style(['border' => true]);
        $s = $x->sheet('Giáo viên');
        $s->widths([6, 12, 28, 16, 14, 26, 14, 16, 12]);
        $s->row(['STT', 'Mã', 'Họ và tên', 'Vai trò', 'Môn', 'Email', 'Điện thoại', 'Tên đăng nhập', 'Trạng thái'], $h);
        foreach ($rows as $i => $r) {
            $s->row([$i + 1, (string) $r['code'], $r['full_name'], (string) $r['role_name'], (string) $r['subject_name'], (string) $r['email'], (string) $r['phone'], $r['username'], $r['status'] === 'active' ? 'Hoạt động' : 'Đã khóa'], $t);
        }
        $s->freeze('A2');
        $x->download('danh-sach-giao-vien-' . date('Ymd') . '.xlsx');
    }
}
