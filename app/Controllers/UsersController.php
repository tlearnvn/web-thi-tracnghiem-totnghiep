<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Logger;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Settings;
use App\Lib\Text;
use App\Lib\Users;

/** Quản lý mọi tài khoản (dành cho quản trị). */
final class UsersController extends Controller
{
    public function index(): void
    {
        $this->authorize('users.manage', 'roles.manage');
        if (!Auth::can('users.manage')) {
            $this->redirect('roles');
            return;
        }
        $where = ['1=1'];
        $params = [];
        $q = Request::str('q');
        if ($q !== '') {
            $where[] = 'u.search_text LIKE ?';
            $params[] = '%' . Text::normalizeSearch($q) . '%';
        }
        $role = Request::str('role');
        if ($role !== '') {
            $where[] = 'u.role = ?';
            $params[] = $role;
        }
        $status = Request::str('status');
        if (in_array($status, ['active', 'locked'], true)) {
            $where[] = 'u.status = ?';
            $params[] = $status;
        }
        $online = Request::bool('online');
        if ($online) {
            $where[] = 'u.id IN (SELECT user_id FROM {web_sessions} WHERE user_id IS NOT NULL AND last_activity >= ?)';
            $params[] = time() - 600;
        }
        $total = (int) $this->db->value('SELECT COUNT(*) FROM {users} u WHERE ' . implode(' AND ', $where), $params);
        $pager = new Paginator($total, Paginator::perPageFromRequest(50));
        $rows = $this->db->all(
            'SELECT u.*, r.name AS role_name, r.kind, c.name AS class_name,
                    (SELECT MAX(last_activity) FROM {web_sessions} w WHERE w.user_id = u.id) AS seen
             FROM {users} u LEFT JOIN {roles} r ON r.code = u.role LEFT JOIN {classes} c ON c.id = u.class_id
             WHERE ' . implode(' AND ', $where) . ' ORDER BY r.sort_order, u.sort_key' . $pager->sqlLimit(),
            $params
        );
        $counts = $this->db->keyed('SELECT role, COUNT(*) AS n FROM {users} GROUP BY role', [], 'role', 'n');
        $this->render('users/index', [
            'title' => 'Tài khoản & phân quyền',
            'crumbs' => ['Tài khoản' => null],
            'rows' => $rows, 'pager' => $pager, 'counts' => $counts,
            'roles' => Users::roleOptions(),
        ]);
    }

    public function edit(): void
    {
        $this->authorize('users.manage');
        $id = Request::int('id');
        $u = $id ? $this->findOr404('users', $id) : [
            'id' => 0, 'full_name' => '', 'username' => '', 'role' => Request::str('role', 'admin'), 'code' => '', 'class_id' => null,
            'email' => '', 'phone' => '', 'status' => 'active', 'must_change_password' => 0, 'note' => '',
        ];
        $this->render('users/form', [
            'title' => $id ? 'Sửa tài khoản' : 'Thêm tài khoản',
            'crumbs' => ['Tài khoản' => url('users'), $id ? $u['full_name'] : 'Thêm mới' => null],
            'u' => $u,
            'roles' => $this->db->all('SELECT code, name, kind FROM {roles} ORDER BY sort_order'),
            'classes' => $this->db->all("SELECT id, name FROM {classes} WHERE status = 'active' ORDER BY grade DESC, sort_key"),
        ]);
    }

    public function save(): void
    {
        $this->authorize('users.manage');
        $this->requirePost();
        $id = Request::int('id');
        $cur = $id ? $this->findOr404('users', $id) : null;
        $name = Text::normalizeName(Request::str('full_name'));
        $username = Text::cleanUsername(Request::str('username'));
        $role = Request::str('role');
        $password = (string) Request::post('password', '');
        $errors = [];
        if ($name === '') {
            $errors[] = 'Vui lòng nhập họ tên.';
        }
        if (strlen($username) < 3) {
            $errors[] = 'Tên đăng nhập cần ít nhất 3 ký tự.';
        } elseif (Users::usernameTaken($username, $cur ? (int) $cur['id'] : null)) {
            $errors[] = 'Tên đăng nhập đã được dùng.';
        }
        if (!$this->db->value('SELECT 1 FROM {roles} WHERE code = ?', [$role])) {
            $errors[] = 'Vai trò không hợp lệ.';
        }
        if (!$cur && $password === '') {
            $errors[] = 'Vui lòng đặt mật khẩu cho tài khoản mới.';
        }
        if ($password !== '' && mb_strlen($password) < max(4, (int) Settings::get('password_min_length', 6))) {
            $errors[] = 'Mật khẩu quá ngắn.';
        }
        if ($cur && (int) $cur['id'] === (int) Auth::id() && ($role !== $cur['role'] || Request::str('status') === 'locked')) {
            $errors[] = 'Không thể tự đổi vai trò hoặc khóa chính mình.';
        }
        if ($cur && $cur['role'] === 'admin' && $role !== 'admin') {
            $admins = (int) $this->db->value("SELECT COUNT(*) FROM {users} WHERE role = 'admin' AND status = 'active'");
            if ($admins <= 1) {
                $errors[] = 'Hệ thống cần ít nhất một quản trị viên.';
            }
        }
        if ($errors) {
            $this->flash('danger', implode(' ', $errors));
            $this->back('users');
            return;
        }
        $data = [
            'full_name' => $name, 'username' => $username, 'role' => $role, 'code' => Request::str('code'),
            'class_id' => Request::int('class_id') ?: null, 'email' => Request::str('email'), 'phone' => Request::str('phone'),
            'status' => Request::str('status') === 'locked' ? 'locked' : 'active',
            'must_change_password' => Request::bool('must_change_password') ? 1 : 0, 'note' => Request::str('note'),
        ];
        if ($cur) {
            Users::update((int) $cur['id'], $data, $password !== '' ? $password : null);
            $id = (int) $cur['id'];
        } else {
            $id = Users::create($data, $password);
        }
        Logger::audit($cur ? 'user.update' : 'user.create', 'user', $id, ['role' => $role]);
        $this->flash('success', 'Đã lưu tài khoản ' . $username . '.');
        $this->redirect('users');
    }

    public function delete(): void
    {
        $this->authorize('users.manage');
        $this->requirePost();
        $u = $this->findOr404('users', Request::int('id'));
        if ((int) $u['id'] === (int) Auth::id()) {
            $this->flash('danger', 'Không thể xóa tài khoản đang đăng nhập.');
            $this->back('users');
            return;
        }
        if ($u['role'] === 'admin' && (int) $this->db->value("SELECT COUNT(*) FROM {users} WHERE role = 'admin'") <= 1) {
            $this->flash('danger', 'Không thể xóa quản trị viên cuối cùng.');
            $this->back('users');
            return;
        }
        $this->db->run('UPDATE {exams} SET created_by = ? WHERE created_by = ?', [(int) Auth::id(), (int) $u['id']]);
        $this->db->run('UPDATE {exam_sessions} SET created_by = ? WHERE created_by = ?', [(int) Auth::id(), (int) $u['id']]);
        Users::delete((int) $u['id']);
        Logger::audit('user.delete', 'user', (int) $u['id'], ['username' => $u['username']]);
        $this->flash('success', 'Đã xóa tài khoản ' . $u['username'] . '.');
        $this->redirect('users');
    }

    /** Buộc đăng xuất khỏi mọi thiết bị. */
    public function logoutAll(): void
    {
        $this->authorize('users.manage');
        $this->requirePost();
        $u = $this->findOr404('users', Request::int('id'));
        Auth::killSessions((int) $u['id'], (int) $u['id'] === (int) Auth::id() ? session_id() : null);
        $this->flash('success', 'Đã đăng xuất ' . $u['full_name'] . ' khỏi mọi thiết bị.');
        $this->back('users');
    }
}
