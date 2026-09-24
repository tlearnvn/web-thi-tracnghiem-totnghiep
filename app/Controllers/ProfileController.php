<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Settings;
use App\Lib\Text;

final class ProfileController extends Controller
{
    private function layout(): string
    {
        return Auth::isStudent() ? 'student' : 'app';
    }

    public function index(): void
    {
        $u = $this->user();
        $class = $u['class_id'] ? $this->db->one('SELECT * FROM {classes} WHERE id = ?', [(int) $u['class_id']]) : null;
        $role = Auth::roleInfo();
        $sessions = $this->db->all('SELECT id, ip, user_agent, created_at, last_activity FROM {web_sessions} WHERE user_id = ? ORDER BY last_activity DESC LIMIT 10', [(int) $u['id']]);
        $this->render('profile/index', [
            'title' => 'Hồ sơ cá nhân',
            'crumbs' => ['Hồ sơ cá nhân' => null],
            'u' => $u, 'class' => $class, 'role' => $role, 'sessions' => $sessions,
        ], $this->layout());
    }

    public function save(): void
    {
        $this->requirePost();
        $u = $this->user();
        $data = [
            'email' => Request::str('email') ?: null,
            'phone' => Request::str('phone') ?: null,
            'updated_at' => time(),
        ];
        if (!Auth::isStudent()) {
            $name = Text::normalizeName(Request::str('full_name'));
            if ($name !== '') {
                $data['full_name'] = $name;
                $data['search_text'] = Text::searchText($name, $u['username'], $u['code'] ?? '');
                $data['sort_key'] = Text::sortKey($name);
            }
        }
        if ($data['email'] && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->flash('danger', 'Email không hợp lệ.');
            $this->redirect('profile');
            return;
        }
        $this->db->update('users', $data, 'id = ?', [(int) $u['id']]);
        Logger::audit('user.update', 'user', (int) $u['id'], ['self' => true]);
        $this->flash('success', 'Đã cập nhật hồ sơ.');
        $this->redirect('profile');
    }

    public function password(): void
    {
        $u = $this->user();
        $forced = (int) $u['must_change_password'] === 1;
        if (Auth::isStudent() && !$forced && !(int) Settings::get('student_can_change_password', 1)) {
            $this->flash('warning', 'Học sinh không được tự đổi mật khẩu. Vui lòng liên hệ giáo viên.');
            $this->redirect('profile');
            return;
        }
        if (Request::isPost()) {
            $cur = (string) Request::post('current_password', '');
            $new = (string) Request::post('new_password', '');
            $confirm = (string) Request::post('new_password_confirm', '');
            $min = max(4, (int) Settings::get('password_min_length', 6));
            $err = null;
            if (!password_verify($cur, $u['password_hash'])) {
                $err = 'Mật khẩu hiện tại không đúng.';
            } elseif (mb_strlen($new) < $min) {
                $err = 'Mật khẩu mới cần ít nhất ' . $min . ' ký tự.';
            } elseif ($new !== $confirm) {
                $err = 'Mật khẩu nhập lại không khớp.';
            } elseif ($new === $cur) {
                $err = 'Mật khẩu mới phải khác mật khẩu hiện tại.';
            }
            if ($err) {
                $this->flash('danger', $err);
                $this->redirect('profile/password');
                return;
            }
            $hash = Auth::hash($new);
            $this->db->update('users', ['password_hash' => $hash, 'must_change_password' => 0, 'updated_at' => time()], 'id = ?', [(int) $u['id']]);
            Auth::refresh();
            Auth::killSessions((int) $u['id'], session_id());
            Logger::audit('auth.password', 'user', (int) $u['id']);
            $this->flash('success', 'Đã đổi mật khẩu. Các phiên đăng nhập khác đã bị đăng xuất.');
            $this->redirect(Auth::isStudent() ? 'student' : 'dashboard');
            return;
        }
        $this->render('profile/password', [
            'title' => 'Đổi mật khẩu',
            'crumbs' => ['Hồ sơ cá nhân' => url('profile'), 'Đổi mật khẩu' => null],
            'forced' => $forced,
            'min' => max(4, (int) Settings::get('password_min_length', 6)),
        ], $this->layout());
    }
}
