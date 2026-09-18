<?php

namespace App\Controllers;

use App\Controllers\BaseController;

/**
 * DashboardController
 * Substitua este conteúdo pela lógica do seu projeto.
 */
class DashboardController extends BaseController
{
    public function index(): void
    {
        $this->view('dashboard.index', [
            'title' => 'Dashboard',
            'user'  => $this->user(),
        ], 'main');
    }
}
