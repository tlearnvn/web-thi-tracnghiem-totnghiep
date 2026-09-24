<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Scope;
use App\Core\Settings;
use App\Lib\PeopleImporter;
use App\Lib\Sessions;
use App\Lib\Text;
use App\Lib\Users;
use App\Lib\XlsxWriter;

final class StudentsController extends Controller
{
    private function classOptions(): array
    {
        $p = [];
        return $this->db->all(
            "SELECT id, name, school_year, grade FROM {classes} c WHERE c.status = 'active' AND " . Scope::classFilterSql('c.id', $p) . ' ORDER BY c.grade DESC, c.sort_key, c.name',
            $p
        );
    }

    private function studentOr404(int $id): array
    {
        $u = Users::find($id);
        if (!$u || !in_array($u['role'], Sessions::studentRoles(), true)) {
            throw new HttpException(404, 'Không tìm thấy học sinh.');
        }
        if (!Scope::canAccessClass($u['class_id'] ? (int) $u['class_id'] : null) && Scope::classIds() !== null) {
            throw new HttpException(403, 'Học sinh này không thuộc lớp bạn phụ trách.');
        }
        return $u;
    }

    /** Điều kiện lọc dùng chung cho danh sách và xuất Excel. */
    private function filters(array &$params): string
    {
        $roles = Sessions::studentRoles();
        $where = ['u.role IN ' . $this->db->in($roles)];
        $params = array_merge($params, $roles);
        $where[] = Scope::classFilterSql('u.class_id', $params);
        $q = Request::str('q');
        if ($q !== '') {
            $where[] = 'u.search_text LIKE ?';
            $params[] = '%' . Text::normalizeSearch($q) . '%';
        }
        $cid = Request::int('class_id');
        if ($cid > 0) {
            $where[] = 'u.class_id = ?';
            $params[] = $cid;
        } elseif (Request::str('class_id') === 'none') {
            $where[] = 'u.class_id IS NULL';
        }
        $st = Request::str('status');
        if (in_array($st, ['active', 'locked'], true)) {
            $where[] = 'u.status = ?';
            $params[] = $st;
        }
        $g = Request::str('gender');
        if (in_array($g, ['Nam', 'Nữ'], true)) {
            $where[] = 'u.gender = ?';
            $params[] = $g;
        }
        return implode(' AND ', $where);
    }

    public function index(): void
    {
        $this->authorize('students.view', 'students.view_all', 'students.manage');
        $params = [];
        $where = $this->filters($params);
        $total = (int) $this->db->value('SELECT COUNT(*) FROM {users} u WHERE ' . $where, $params);
        $pager = new Paginator($total, Paginator::perPageFromRequest(50));
        $sort = Request::str('sort', 'class');
        $order = $sort === 'code' ? 'u.code, u.sort_key' : ($sort === 'name' ? 'u.sort_key' : ($sort === 'login' ? 'u.last_login_at DESC' : 'c.grade DESC, c.sort_key, c.name, u.sort_key'));
        $rows = $this->db->all(
            'SELECT u.*, c.name AS class_name,
                    (SELECT COUNT(*) FROM {attempts} a WHERE a.user_id = u.id AND a.status <> \'in_progress\') AS attempts_done,
                    (SELECT AVG(a.score) FROM {attempts} a WHERE a.user_id = u.id AND a.score IS NOT NULL) AS avg_score
             FROM {users} u LEFT JOIN {classes} c ON c.id = u.class_id WHERE ' . $where . ' ORDER BY ' . $order . $pager->sqlLimit(),
            $params
        );
        $this->render('students/index', [
            'title' => 'Học sinh',
            'crumbs' => ['Học sinh' => null],
            'rows' => $rows,
            'pager' => $pager,
            'classes' => $this->classOptions(),
            'canManage' => Auth::can('students.manage'),
            'canPassword' => Auth::can('students.password') || Auth::can('students.manage'),
        ]);
    }

    public function create(): void
    {
        $this->authorize('students.manage');
        $this->render('students/form', [
            'title' => 'Thêm học sinh',
            'crumbs' => ['Học sinh' => url('students'), 'Thêm mới' => null],
            'u' => ['id' => 0, 'full_name' => old('full_name'), 'username' => old('username'), 'code' => old('code'), 'class_id' => old('class_id', Request::int('class_id') ?: null), 'gender' => old('gender'), 'birthday' => old('birthday'), 'email' => old('email'), 'phone' => old('phone'), 'status' => 'active', 'note' => old('note'), 'must_change_password' => 0],
            'classes' => $this->classOptions(),
        ]);
    }

    public function edit(): void
    {
        $this->authorize('students.manage');
        $u = $this->studentOr404(Request::int('id'));
        $this->render('students/form', [
            'title' => 'Sửa học sinh',
            'crumbs' => ['Học sinh' => url('students'), $u['full_name'] => url('students/view', ['id' => $u['id']]), 'Sửa' => null],
            'u' => $u,
            'classes' => $this->classOptions(),
        ]);
    }

    public function save(): void
    {
        $this->authorize('students.manage');
        $this->requirePost();
        $id = Request::int('id');
        $cur = $id ? $this->studentOr404($id) : null;
        $name = Text::normalizeName(Request::str('full_name'));
        $code = mb_substr(Request::str('code'), 0, 50);
        $username = Text::cleanUsername(Request::str('username'));
        $classId = Request::int('class_id') ?: null;
        $errors = [];
        if ($name === '') {
            $errors[] = 'Vui lòng nhập họ tên.';
        }
        if ($classId && !Scope::canAccessClass($classId)) {
            $errors[] = 'Bạn không phụ trách lớp đã chọn.';
        }
        if (!$classId && Scope::classIds() !== null) {
            $errors[] = 'Vui lòng chọn lớp.';
        }
        if ($username === '') {
            $username = $code !== '' ? Text::cleanUsername($code) : '';
        }
        if ($username === '' && !$cur) {
            $username = Users::uniqueUsername(Text::usernameFromName($name));
        }
        if ($username !== '' && Users::usernameTaken($username, $cur ? (int) $cur['id'] : null)) {
            $errors[] = 'Tên đăng nhập "' . $username . '" đã được dùng.';
        }
        if ($code !== '') {
            $dup = $this->db->value('SELECT full_name FROM {users} WHERE code = ? AND role IN ' . $this->db->in(Sessions::studentRoles()) . ($cur ? ' AND id <> ' . (int) $cur['id'] : ''), array_merge([$code], Sessions::studentRoles()));
            if ($dup) {
                $errors[] = 'Mã học sinh "' . $code . '" đã thuộc về ' . $dup . '.';
            }
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
        if ($errors) {
            \App\Core\Session::setOld($_POST);
            $this->flash('danger', implode(' ', $errors));
            $this->back('students');
            return;
        }
        $data = [
            'full_name' => $name,
            'username' => $username,
            'code' => $code,
            'class_id' => $classId,
            'gender' => in_array(Request::str('gender'), ['Nam', 'Nữ'], true) ? Request::str('gender') : null,
            'birthday' => Text::parseDate(Request::str('birthday')),
            'email' => $email,
            'phone' => Request::str('phone'),
            'note' => mb_substr(Request::str('note'), 0, 255),
            'status' => Request::str('status') === 'locked' ? 'locked' : 'active',
            'must_change_password' => Request::bool('must_change_password') ? 1 : 0,
            'role' => 'student',
        ];
        if ($cur) {
            unset($data['role']);
            Users::update((int) $cur['id'], $data, $password !== '' ? $password : null);
            Logger::audit('user.update', 'user', (int) $cur['id'], ['name' => $name]);
            $this->flash('success', 'Đã cập nhật học sinh ' . $name . '.');
            $this->redirect('students/view', ['id' => $cur['id']]);
            return;
        }
        $plain = $password !== '' ? $password : Users::makePassword('random6');
        $newId = Users::create($data, $plain);
        Logger::audit('user.create', 'user', $newId, ['name' => $name, 'role' => 'student']);
        Users::stashCredentials('Tài khoản học sinh mới', [[
            'class' => $classId ? (string) $this->db->value('SELECT name FROM {classes} WHERE id = ?', [$classId]) : '',
            'code' => $code, 'name' => $name, 'username' => $username, 'password' => $plain,
        ]]);
        $this->flash('success', 'Đã thêm học sinh ' . $name . '. Tên đăng nhập: ' . $username . ' – Mật khẩu: ' . $plain);
        if (Request::bool('add_another')) {
            $this->redirect('students/create', ['class_id' => $classId]);
            return;
        }
        $this->redirect('students', ['class_id' => $classId]);
    }

    public function view(): void
    {
        $this->authorize('students.view', 'students.view_all', 'students.manage');
        $u = $this->studentOr404(Request::int('id'));
        $class = $u['class_id'] ? $this->db->one('SELECT * FROM {classes} WHERE id = ?', [(int) $u['class_id']]) : null;
        $attempts = $this->db->all(
            'SELECT a.*, s.name AS session_name, s.mode, e.title AS exam_title, sub.name AS subject_name, sub.color AS subject_color, v.code AS variant_code
             FROM {attempts} a JOIN {exam_sessions} s ON s.id = a.session_id JOIN {exams} e ON e.id = a.exam_id
             LEFT JOIN {subjects} sub ON sub.id = e.subject_id LEFT JOIN {exam_variants} v ON v.id = a.variant_id
             WHERE a.user_id = ? ORDER BY a.started_at DESC',
            [(int) $u['id']]
        );
        $this->render('students/view', [
            'title' => $u['full_name'],
            'crumbs' => ['Học sinh' => url('students'), $u['full_name'] => null],
            'u' => $u,
            'class' => $class,
            'attempts' => $attempts,
        ]);
    }

    /** Thao tác hàng loạt: chuyển lớp, khóa, mở khóa, xóa, cấp lại mật khẩu. */
    public function bulk(): void
    {
        $this->requirePost();
        $action = Request::str('action');
        $ids = Request::ints('ids');
        if (!$ids) {
            $this->flash('warning', 'Chưa chọn học sinh nào.');
            $this->back('students');
            return;
        }
        $this->authorize($action === 'password' || in_array($action, ['lock', 'unlock'], true) ? 'students.password' : 'students.manage', 'students.manage');
        $students = [];
        foreach ($ids as $id) {
            $students[] = $this->studentOr404($id);
        }
        $n = count($students);
        switch ($action) {
            case 'move':
                $cid = Request::int('class_id') ?: null;
                if ($cid && !Scope::canAccessClass($cid)) {
                    throw new HttpException(403);
                }
                foreach ($students as $s) {
                    Users::update((int) $s['id'], ['class_id' => $cid]);
                }
                Logger::audit('user.update', 'user', null, ['bulk' => 'move', 'count' => $n, 'class' => $cid]);
                $this->flash('success', 'Đã chuyển ' . $n . ' học sinh sang lớp mới.');
                break;
            case 'lock':
            case 'unlock':
                foreach ($students as $s) {
                    Users::update((int) $s['id'], ['status' => $action === 'lock' ? 'locked' : 'active']);
                }
                Logger::audit('user.lock', 'user', null, ['bulk' => $action, 'count' => $n]);
                $this->flash('success', ($action === 'lock' ? 'Đã khóa ' : 'Đã mở khóa ') . $n . ' tài khoản.');
                break;
            case 'delete':
                $this->authorize('students.manage');
                foreach ($students as $s) {
                    Users::delete((int) $s['id']);
                }
                Logger::audit('user.delete', 'user', null, ['bulk' => true, 'count' => $n]);
                $this->flash('success', 'Đã xóa ' . $n . ' học sinh cùng toàn bộ bài làm.');
                break;
            case 'password':
                $mode = Request::str('mode', 'random6');
                $fixed = (string) Request::post('fixed', '');
                $must = Request::bool('must_change');
                $creds = [];
                $classNames = $this->db->keyed('SELECT id, name FROM {classes}', [], 'id', 'name');
                foreach ($students as $s) {
                    $pw = Users::makePassword($mode, $s, $fixed);
                    Users::setPassword((int) $s['id'], $pw, $must);
                    $creds[] = ['class' => $classNames[$s['class_id']] ?? '', 'code' => $s['code'], 'name' => $s['full_name'], 'username' => $s['username'], 'password' => $pw];
                }
                Users::stashCredentials('Cấp lại mật khẩu học sinh', $creds);
                Logger::audit('user.reset_password', 'user', null, ['count' => $n]);
                $this->flash('success', 'Đã cấp lại mật khẩu cho ' . $n . ' học sinh. Hãy tải danh sách hoặc in phiếu tài khoản.');
                $this->redirect('students/credentials');
                return;
            default:
                $this->flash('danger', 'Thao tác không hợp lệ.');
        }
        $this->back('students');
    }

    public function delete(): void
    {
        $this->authorize('students.manage');
        $this->requirePost();
        $u = $this->studentOr404(Request::int('id'));
        Users::delete((int) $u['id']);
        Logger::audit('user.delete', 'user', (int) $u['id'], ['name' => $u['full_name']]);
        $this->flash('success', 'Đã xóa học sinh ' . $u['full_name'] . '.');
        $this->redirect('students');
    }

    public function password(): void
    {
        $this->authorize('students.password', 'students.manage');
        $this->requirePost();
        $u = $this->studentOr404(Request::int('id'));
        $pw = Users::makePassword(Request::str('mode', 'random6'), $u, (string) Request::post('fixed', ''));
        Users::setPassword((int) $u['id'], $pw, Request::bool('must_change'));
        $cls = $u['class_id'] ? (string) $this->db->value('SELECT name FROM {classes} WHERE id = ?', [(int) $u['class_id']]) : '';
        Users::stashCredentials('Cấp lại mật khẩu', [['class' => $cls, 'code' => $u['code'], 'name' => $u['full_name'], 'username' => $u['username'], 'password' => $pw]]);
        Logger::audit('user.reset_password', 'user', (int) $u['id']);
        if (Request::wantsJson()) {
            $this->ok(['password' => $pw, 'username' => $u['username']]);
            return;
        }
        $this->flash('success', 'Mật khẩu mới của ' . $u['full_name'] . ': ' . $pw);
        $this->back('students');
    }

    // ------------------------------------------------------------ Nhập từ Excel

    public function import(): void
    {
        $this->authorize('students.manage');
        $this->render('students/import', [
            'title' => 'Nhập học sinh từ Excel',
            'crumbs' => ['Học sinh' => url('students'), 'Nhập từ Excel' => null],
            'classes' => $this->classOptions(),
            'year' => (string) Settings::get('default_school_year'),
            'restricted' => Scope::classIds() !== null,
        ]);
    }

    public function template(): void
    {
        $this->authorize('students.manage');
        $data = PeopleImporter::templateXlsx('student');
        Response::downloadHeaders('mau-nhap-hoc-sinh.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', strlen($data));
        echo $data;
    }

    /** Xem trước (commit=0) hoặc ghi vào CSDL (commit=1) – gửi lại tệp ở cả 2 bước, không lưu tệp tạm. */
    public function importRun(): void
    {
        $this->authorize('students.manage');
        $this->requirePost();
        @set_time_limit(300);
        $f = Request::file('file');
        if (!$f) {
            $this->fail('Vui lòng chọn tệp Excel.');
            return;
        }
        if ($err = Request::uploadError($f)) {
            $this->fail($err);
            return;
        }
        $parsed = PeopleImporter::parse((string) file_get_contents($f['tmp_name']), 'student', Request::int('sheet'));
        if ($parsed['errors']) {
            $this->fail(implode(' ', $parsed['errors']), 422, ['sheets' => $parsed['sheets']]);
            return;
        }
        $commit = Request::bool('commit');
        $defaultClass = Request::int('default_class') ?: null;
        $createClasses = Request::bool('create_classes') && Scope::classIds() === null;
        $year = Request::str('school_year') ?: (string) Settings::get('default_school_year');
        $onDuplicate = Request::str('on_duplicate', 'update');
        $pwMode = Request::str('password_mode', 'random6');
        $pwFixed = (string) Request::post('password_fixed', '');
        $mustChange = Request::bool('must_change');
        $usernameMode = Request::str('username_mode', 'code');
        $roles = Sessions::studentRoles();
        $allowed = Scope::classIds();

        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        $preview = [];
        $creds = [];
        $reserved = [];
        $classCache = [];
        $codeSeen = [];
        $ops = [];
        foreach ($parsed['rows'] as $r) {
            $d = $r['data'];
            $item = ['row' => $r['row'], 'name' => $d['full_name'] ?? '', 'code' => $d['code'] ?? '', 'class' => $d['class'] ?? '', 'birthday' => $d['birthday'] ?? null, 'gender' => $d['gender'] ?? null, 'status' => '', 'message' => implode('; ', array_merge($r['errors'], $r['warnings'] ?? [])), 'username' => ''];
            if (!empty($r['skip'])) {
                $item['status'] = 'error';
                $result['errors']++;
                $preview[] = $item;
                continue;
            }
            // Lớp
            $classId = $defaultClass;
            $newClassKey = null;
            if (($d['class'] ?? '') !== '') {
                $key = mb_strtolower($d['class']);
                if (!array_key_exists($key, $classCache)) {
                    $found = Users::classByName($d['class'], $year, false);
                    $classCache[$key] = $found ?? ($createClasses ? 'new' : null);
                }
                $cc = $classCache[$key];
                if ($cc === null) {
                    $item['status'] = 'error';
                    $item['message'] = 'Lớp "' . $d['class'] . '" chưa có trong hệ thống' . ($allowed === null ? ' (bật "Tự tạo lớp mới")' : '');
                    $result['errors']++;
                    $preview[] = $item;
                    continue;
                }
                if ($cc === 'new') {
                    $newClassKey = $key;
                    $classId = null;
                    $item['class'] = $d['class'] . ' (lớp mới)';
                } else {
                    $classId = (int) $cc;
                }
            }
            if ($allowed !== null && (!$classId || !in_array((int) $classId, $allowed, true))) {
                $item['status'] = 'error';
                $item['message'] = $classId ? 'Lớp không thuộc phạm vi bạn phụ trách' : 'Chưa xác định được lớp';
                $result['errors']++;
                $preview[] = $item;
                continue;
            }
            if ($commit && $newClassKey !== null) {
                $classId = Users::classByName($d['class'], $year, true);
                $classCache[$newClassKey] = $classId;
            }
            if (($d['code'] ?? '') !== '') {
                if (isset($codeSeen[$d['code']])) {
                    $item['status'] = 'error';
                    $item['message'] = 'Trùng mã học sinh với dòng ' . $codeSeen[$d['code']];
                    $result['errors']++;
                    $preview[] = $item;
                    continue;
                }
                $codeSeen[$d['code']] = $r['row'];
            }
            // Tìm học sinh đã có
            $existing = null;
            if (($d['code'] ?? '') !== '') {
                $existing = $this->db->one('SELECT * FROM {users} WHERE code = ? AND role IN ' . $this->db->in($roles), array_merge([$d['code']], $roles));
            }
            if (!$existing && ($d['username'] ?? '') !== '') {
                $existing = $this->db->one('SELECT * FROM {users} WHERE username = ?', [$d['username']]);
                if ($existing && !in_array($existing['role'], $roles, true)) {
                    $item['status'] = 'error';
                    $item['message'] = 'Tên đăng nhập "' . $d['username'] . '" đang là tài khoản giáo viên/quản trị';
                    $result['errors']++;
                    $preview[] = $item;
                    continue;
                }
            }
            if ($existing) {
                $item['username'] = $existing['username'];
                if ($onDuplicate === 'skip') {
                    $item['status'] = 'skip';
                    $item['message'] = 'Đã có trong hệ thống – bỏ qua';
                    $result['skipped']++;
                    $preview[] = $item;
                    continue;
                }
                $item['status'] = 'update';
                $item['message'] = trim('Cập nhật thông tin. ' . $item['message']);
                if ($commit) {
                    $upd = ['full_name' => $d['full_name'], 'class_id' => $classId ?: $existing['class_id']];
                    foreach (['birthday', 'gender', 'email', 'phone'] as $k) {
                        if (!empty($d[$k])) {
                            $upd[$k] = $d[$k];
                        }
                    }
                    $pw = ($d['password'] ?? '') !== '' ? $d['password'] : null;
                    $ops[] = ['update', (int) $existing['id'], $upd, $pw, $pw ? Auth::hash($pw) : null];
                    if ($pw) {
                        $creds[] = ['class' => $d['class'] ?? '', 'code' => $d['code'], 'name' => $d['full_name'], 'username' => $existing['username'], 'password' => $pw];
                    }
                }
                $result['updated']++;
                $preview[] = $item;
                continue;
            }
            // Tạo mới
            $base = $d['username'] ?? '';
            if ($base === '') {
                $base = $usernameMode === 'name' || ($d['code'] ?? '') === '' ? Text::usernameFromName($d['full_name']) : $d['code'];
            }
            $username = ($d['username'] ?? '') !== '' && !Users::usernameTaken($d['username']) && !in_array($d['username'], $reserved, true)
                ? Text::cleanUsername($d['username'])
                : Users::uniqueUsername($base, $reserved);
            if (!in_array($username, $reserved, true)) {
                $reserved[] = $username;
            }
            $item['username'] = $username;
            $item['status'] = 'new';
            $pw = ($d['password'] ?? '') !== '' ? $d['password'] : Users::makePassword($pwMode, $d + ['username' => $username], $pwFixed);
            if ($commit) {
                // Băm mật khẩu TRƯỚC khi mở giao dịch để không khóa CSDL lâu (học sinh khác đang thi vẫn lưu bài được)
                $ops[] = ['create', [
                    'username' => $username, 'role' => 'student', 'full_name' => $d['full_name'], 'code' => $d['code'],
                    'class_id' => $classId, 'gender' => $d['gender'], 'birthday' => $d['birthday'], 'email' => $d['email'],
                    'phone' => $d['phone'], 'note' => $d['note'], 'must_change_password' => $mustChange ? 1 : 0,
                ], $pw, Auth::hash($pw)];
                $creds[] = ['class' => $d['class'] ?? '', 'code' => $d['code'], 'name' => $d['full_name'], 'username' => $username, 'password' => $pw];
            }
            $result['created']++;
            $preview[] = $item;
        }
        if ($commit && $ops) {
            foreach (array_chunk($ops, 200) as $chunk) {
                $this->db->transaction(function () use ($chunk) {
                    foreach ($chunk as $op) {
                        if ($op[0] === 'create') {
                            Users::create($op[1], $op[2], $op[3]);
                        } else {
                            Users::update($op[1], $op[2], $op[3], $op[4]);
                        }
                    }
                });
            }
        }
        if ($commit) {
            Logger::audit('user.import', 'user', null, $result + ['type' => 'student']);
            if ($creds) {
                Users::stashCredentials('Tài khoản học sinh nhập từ Excel', $creds);
            }
        }
        $this->ok([
            'committed' => $commit,
            'result' => $result,
            'rows' => $preview,
            'columns' => $parsed['columns'],
            'sheets' => $parsed['sheets'],
            'credentials' => $commit ? count($creds) : 0,
            'credentials_url' => url('students/credentials'),
        ]);
    }

    // ------------------------------------------------------------ Tài khoản vừa cấp

    public function credentials(): void
    {
        $this->authorize('students.manage', 'students.password', 'teachers.manage', 'users.manage');
        $c = Users::stashedCredentials();
        $this->render('students/credentials', [
            'title' => 'Danh sách tài khoản vừa cấp',
            'crumbs' => ['Học sinh' => url('students'), 'Tài khoản vừa cấp' => null],
            'creds' => $c,
        ]);
    }

    public function credentialsExcel(): void
    {
        $this->authorize('students.manage', 'students.password', 'teachers.manage', 'users.manage');
        $c = Users::stashedCredentials();
        if (!$c) {
            $this->flash('warning', 'Không còn danh sách tài khoản tạm (chỉ lưu 2 giờ).');
            $this->redirect('students');
            return;
        }
        $x = new XlsxWriter();
        $h = $x->style(['bold' => true, 'fill' => '1D4ED8', 'color' => 'FFFFFF', 'border' => true, 'align' => 'center']);
        $t = $x->style(['border' => true]);
        $m = $x->style(['border' => true, 'bold' => true]);
        $s = $x->sheet('Tài khoản');
        $s->widths([6, 10, 14, 28, 18, 14]);
        $s->row([mb_strtoupper($c['title'])], $x->style(['bold' => true, 'size' => 14]));
        $s->row(['Tạo lúc ' . fmt_dt($c['at'], 'H:i d/m/Y') . ' · Địa chỉ đăng nhập: ' . absolute_url('login')], $x->style(['italic' => true, 'color' => '64748B']));
        $s->row(['STT', 'Lớp', 'Mã', 'Họ và tên', 'Tên đăng nhập', 'Mật khẩu'], $h);
        foreach ($c['rows'] as $i => $r) {
            $s->row([$i + 1, (string) $r['class'], (string) $r['code'], $r['name'], $r['username'], (string) $r['password']], $t, [4 => $m, 5 => $m]);
        }
        $s->freeze('A4');
        $x->download('tai-khoan-' . date('Ymd-His') . '.xlsx');
    }

    /** In phiếu tài khoản (cắt phát cho học sinh). */
    public function slips(): void
    {
        $this->authorize('students.manage', 'students.password', 'teachers.manage', 'users.manage');
        $c = Users::stashedCredentials();
        echo \App\Core\View::render('students/slips', ['title' => 'In phiếu tài khoản', 'creds' => $c], 'bare');
    }

    public function clearCredentials(): void
    {
        $this->requirePost();
        unset($_SESSION['_creds']);
        $this->flash('success', 'Đã xóa danh sách mật khẩu tạm.');
        $this->redirect('students');
    }

    // ------------------------------------------------------------ Xuất Excel

    public function export(): void
    {
        $this->authorize('students.view', 'students.view_all', 'students.manage');
        $params = [];
        $where = $this->filters($params);
        $rows = $this->db->all(
            'SELECT u.*, c.name AS class_name FROM {users} u LEFT JOIN {classes} c ON c.id = u.class_id WHERE ' . $where . ' ORDER BY c.grade DESC, c.sort_key, c.name, u.sort_key',
            $params
        );
        $x = new XlsxWriter();
        $x->title = 'Danh sách học sinh';
        $h = $x->style(['bold' => true, 'fill' => '1D4ED8', 'color' => 'FFFFFF', 'border' => true, 'align' => 'center', 'wrap' => true]);
        $t = $x->style(['border' => true]);
        $c = $x->style(['border' => true, 'align' => 'center']);
        $s = $x->sheet('Học sinh');
        $s->widths([6, 10, 14, 28, 12, 9, 18, 24, 14, 11, 18]);
        $s->row(['DANH SÁCH HỌC SINH – ' . mb_strtoupper((string) setting('org_name'))], $x->style(['bold' => true, 'size' => 14]));
        $s->row(['Xuất lúc ' . date('H:i d/m/Y') . ' (UTC+7) · ' . count($rows) . ' học sinh'], $x->style(['italic' => true, 'color' => '64748B']));
        $s->row(['STT', 'Lớp', 'Mã HS', 'Họ và tên', 'Ngày sinh', 'Giới tính', 'Tên đăng nhập', 'Email', 'Điện thoại', 'Trạng thái', 'Đăng nhập gần nhất'], $h, [], 30);
        foreach ($rows as $i => $r) {
            $s->row([
                $i + 1, (string) $r['class_name'], (string) $r['code'], $r['full_name'], fmt_date($r['birthday']), (string) $r['gender'],
                $r['username'], (string) $r['email'], (string) $r['phone'], $r['status'] === 'active' ? 'Hoạt động' : 'Đã khóa', fmt_dt($r['last_login_at']),
            ], $t, [0 => $c, 1 => $c, 4 => $c, 5 => $c, 9 => $c]);
        }
        $s->freeze('A4');
        $s->autoFilter('A3:K' . (count($rows) + 3));
        $x->download('danh-sach-hoc-sinh-' . date('Ymd') . '.xlsx');
    }
}
