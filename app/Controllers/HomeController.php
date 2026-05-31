<?php

namespace App\Controllers;

use Core\Controller;

class HomeController extends Controller
{
    public function index(): void
    {
        $this->auth()
            ? $this->redirect('dashboard')
            : $this->view('home.index', ['title' => 'Início'], 'main');
    }
}
