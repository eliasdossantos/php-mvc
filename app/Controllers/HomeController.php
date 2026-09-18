<?php

namespace App\Controllers;

use App\Controllers\BaseController;

class HomeController extends BaseController
{
    public function index(): void
    {
        $this->auth()
            ? $this->redirect('dashboard')
            : $this->view('home.index', ['title' => 'Início'], 'home');
        //                                                       ^^^^
        //  Alterado de 'main' → 'home' para usar o layout sem
        //  sidebar e topbar (app/Views/layouts/home.php).
        //  Sidebar e topbar só aparecem após o login, no layout 'main'.
    }
}
