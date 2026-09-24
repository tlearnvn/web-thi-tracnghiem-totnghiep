<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;

final class HomeController extends Controller
{
    protected array $public = ['index'];

    public function index(): void
    {
        if (!Auth::check()) {
            $this->redirect('login');
            return;
        }
        $this->redirect(Auth::isStudent() ? 'student' : 'dashboard');
    }
}
