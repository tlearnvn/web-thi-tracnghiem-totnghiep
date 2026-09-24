<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;

final class AuthController extends Controller
{
    protected array $public = ['login', 'csrf'];

    /** Cấp mã CSRF mới (dùng khi phiên đăng nhập hết hạn giữa giờ thi để đăng nhập lại tại chỗ). */
    public function csrf(): void
    {
        \App\Core\Response::noCache();
        $this->ok(['csrf' => Csrf::token(), 'user' => Auth::id()]);
    }

    public function login(): void
    {
        if (Request::isPost()) {
            $username = Request::str('username');
            [$user, $error] = Auth::attempt($username, (string) Request::post('password', ''));
            if (Request::wantsJson()) {
                if (!$user) {
                    $this->fail($error ?? 'Đăng nhập thất bại.', 422);
                    return;
                }
                // Đăng nhập lại ngay trong phòng thi (phiên cũ hết hạn): chỉ cho đúng người
                $expect = (int) Request::int('expect_user');
                if ($expect > 0 && $expect !== (int) $user['id']) {
                    $this->fail('Tài khoản này không phải của bài thi đang làm.', 403);
                    return;
                }
                Auth::login($user);
                $this->ok(['csrf' => Csrf::token(), 'user' => ['id' => (int) $user['id'], 'name' => $user['full_name']]]);
                return;
            }
            if (!$user) {
                Session::setOld(['username' => $username]);
                Session::flash('danger', (string) $error);
                $this->redirect('login');
                return;
            }
            Session::clearOld();
            Auth::login($user);
            $intended = (string) ($_SESSION['_intended'] ?? '');
            unset($_SESSION['_intended']);
            if ($intended !== '' && strpos($intended, base_uri()) === 0 && strpos($intended, 'logout') === false && strpos($intended, 'r=exam/') === false) {
                \App\Core\Response::redirect($intended);
                return;
            }
            $this->redirect(Auth::isStudent() ? 'student' : 'dashboard');
            return;
        }
        if (Auth::check()) {
            $this->redirect(Auth::isStudent() ? 'student' : 'dashboard');
            return;
        }
        $this->render('auth/login', ['title' => 'Đăng nhập'], 'bare');
        Session::clearOld();
    }

    public function logout(): void
    {
        $this->requirePost();
        Auth::logout();
        Session::restart();
        Session::flash('success', 'Bạn đã đăng xuất an toàn.');
        $this->redirect('login');
    }
}
