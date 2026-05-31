<?php

namespace App\Middlewares;

use Core\Request;
use Core\Session;

/**
 * CsrfMiddleware — Valida token CSRF em requisições POST/PUT/DELETE
 *
 * Uso:
 *   $router->post('/users', [UserController::class, 'store'], ['CsrfMiddleware']);
 *
 * Para incluir o token em formulários:
 *   <?= csrf_field() ?>
 *
 * Para incluir via JS/AJAX:
 *   headers: { 'X-CSRF-Token': '<?= csrf_token() ?>' }
 */
class CsrfMiddleware
{
    protected array $safeMethods = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(Request $request): void
    {
        if (in_array($request->method(), $this->safeMethods)) return;

        // Busca token no POST ou no header (para AJAX)
        $token = $_POST['_csrf_token']
            ?? $request->header('X-CSRF-Token')
            ?? $request->header('X-XSRF-Token')
            ?? '';

        if (!Session::validateCsrf($token)) {
            http_response_code(419);
            $isAjax = $request->isAjax() || $request->isJson();

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Token CSRF inválido ou expirado.']);
                exit;
            }

            // Regenera token após falha
            Session::regenerateCsrf();
            Session::flash('error', 'Sua sessão expirou. Por favor, tente novamente.');
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? APP_URL));
            exit;
        }
    }
}
