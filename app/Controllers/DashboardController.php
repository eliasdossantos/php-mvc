<?php

namespace App\Controllers;

use Core\Controller;

/**
 * DashboardController
 * Substitua este conteúdo pela lógica do seu projeto.
 */
class DashboardController extends Controller
{
    public function index(): void
    {
        $this->view('dashboard.index', [
            'title' => 'Dashboard',
            'user'  => $this->user(),
        ], 'main');
    }
}
