<?php

namespace App\Controllers;

use Framework\Controller;

/**
 * BaseController
 * ─────────────────────────────────────────────────────────────────────────────
 * Controller base da aplicação. Fica entre Framework\Controller (infraestrutura
 * genérica do framework — reutilizável em qualquer projeto que use este
 * boilerplate) e os controllers concretos da aplicação.
 *
 * Todo controller do projeto deve estender esta classe, e NÃO
 * Framework\Controller diretamente:
 *
 *   class AuthController extends BaseController{ ... }
 *
 * Propositalmente vazio no momento — é o lugar certo para qualquer lógica
 * comum a vários controllers DESTE projeto que não seja genérica o
 * suficiente pra viver em Framework\Controller (ex: helper de upload de imagem
 * de entidade, resolução de tenant, etc). Adicione aqui conforme a
 * necessidade aparecer — não em Framework\Controller.
 */
abstract class BaseController extends Controller
{
    //
}
