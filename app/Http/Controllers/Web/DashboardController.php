<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Web\BaseController;

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
