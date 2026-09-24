<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Logger;
use App\Core\Permissions;
use App\Core\Request;
use App\Lib\Text;

/** Vai trò & ma trận phân quyền. */
final class RolesController extends Controller
{
    public function index(): void
    {
        $this->authorize('roles.manage');
        $roles = $this->db->all('SELECT r.*, (SELECT COUNT(*) FROM {users} u WHERE u.role = r.code) AS users FROM {roles} r ORDER BY r.sort_order, r.id');
        foreach ($roles as &$r) {
            $r['perms'] = json_dec($r['permissions'], []);
        }
        $this->render('users/roles', [
            'title' => 'Phân quyền',
            'crumbs' => ['Tài khoản' => url('users'), 'Phân quyền' => null],
            'roles' => $roles,
            'groups' => Permissions::GROUPS,
        ]);
    }

    public function save(): void
    {
        $this->authorize('roles.manage');
        $this->requirePost();
        $all = array_keys(Permissions::all());
        $matrix = Request::arr('perm');
        foreach ($this->db->all('SELECT * FROM {roles}') as $r) {
            if ($r['code'] === 'admin') {
                continue;
            }
            $perms = array_values(array_intersect($all, array_keys((array) ($matrix[$r['code']] ?? []))));
            $this->db->update('roles', ['permissions' => json_enc($perms), 'updated_at' => time()], 'id = ?', [(int) $r['id']]);
        }
        Logger::audit('role.update', 'role', null);
        $this->flash('success', 'Đã lưu phân quyền. Thay đổi có hiệu lực ngay với mọi người dùng.');
        $this->redirect('roles');
    }

    public function create(): void
    {
        $this->authorize('roles.manage');
        $this->requirePost();
        $name = trim(Request::str('name'));
        $code = Text::slug(Request::str('code') ?: $name, '_');
        $kind = Request::str('kind') === 'student' ? 'student' : 'staff';
        if ($name === '' || $code === '') {
            $this->flash('danger', 'Vui lòng nhập tên vai trò.');
            $this->redirect('roles');
            return;
        }
        if ($this->db->value('SELECT 1 FROM {roles} WHERE code = ?', [$code])) {
            $code .= '_' . substr(bin2hex(random_bytes(2)), 0, 3);
        }
        $copy = Request::str('copy_from');
        $perms = $copy ? json_dec($this->db->value('SELECT permissions FROM {roles} WHERE code = ?', [$copy]), []) : [];
        $perms = array_values(array_filter($perms, static fn($p) => $p !== '*'));
        $this->db->insert('roles', [
            'code' => mb_substr($code, 0, 30), 'name' => mb_substr($name, 0, 100), 'kind' => $kind,
            'description' => mb_substr(Request::str('description'), 0, 255) ?: null, 'permissions' => json_enc($perms),
            'is_system' => 0, 'sort_order' => 50, 'created_at' => time(), 'updated_at' => time(),
        ]);
        Logger::audit('role.update', 'role', null, ['create' => $code]);
        $this->flash('success', 'Đã tạo vai trò "' . $name . '".');
        $this->redirect('roles');
    }

    public function delete(): void
    {
        $this->authorize('roles.manage');
        $this->requirePost();
        $r = $this->findOr404('roles', Request::int('id'));
        if ((int) $r['is_system'] === 1) {
            $this->flash('danger', 'Không thể xóa vai trò mặc định của hệ thống.');
        } elseif ((int) $this->db->value('SELECT COUNT(*) FROM {users} WHERE role = ?', [$r['code']]) > 0) {
            $this->flash('danger', 'Vai trò đang được gán cho người dùng, hãy chuyển họ sang vai trò khác trước.');
        } else {
            $this->db->delete('roles', 'id = ?', [(int) $r['id']]);
            Logger::audit('role.update', 'role', (int) $r['id'], ['delete' => $r['code']]);
            $this->flash('success', 'Đã xóa vai trò.');
        }
        $this->redirect('roles');
    }
}
