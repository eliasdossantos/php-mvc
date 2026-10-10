<?php

namespace App\Http\Controllers\Web;

use Framework\Http\Controller;

abstract class BaseController extends Controller
{

    /**
     * Prepara a infraestrutura do controller (Request etc.).
     * Chamado pelo Router logo após instanciar o controller, então os filhos
     * não precisam chamar parent::__construct().
     * Idempotente: se o construtor do pai já rodou, não faz nada.
     */
    public function bootstrap(): void
    {
        if (!isset($this->request)) {
            parent::__construct();
        }
    }

    // Regras específicas do projeto entram aqui.
}

