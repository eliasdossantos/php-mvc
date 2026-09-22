<?php

namespace App\Middlewares;

use Core\Request;
use Core\Session;

/**
 * CsrfMiddleware — Valida token CSRF em requisições POST/PUT/DELETE
 *
 * Uso:
 *   $router->post('/Usuarios', [UsuarioController::class, 'store'], ['CsrfMiddleware']);
 *
 * Para incluir o token em formulários:
 *   <?= csrf_field() ?>
 *
 * Para incluir via JS/AJAX:
 * headers: { 'X-CSRF-Token': '<?= csrf_token() ?>' }
 */
class CsrfMiddleware
{
    protected array $safeMethods = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(Request $request): void
    {
        if (in_array($request->method(), $this->safeMethods, true)) return;

        // ── MELHORIA #1 (corrige bug real) ────────────────────────────────────
        // A versão anterior fazia:
        // $_POST['_csrf_token'] ?? $request->header('X-CSRF-Token') ?? $request->header('X-XSRF-Token') ?? '';
        // Request::header() nunca retorna null — o default é '' — então assim
        // que $_POST['_csrf_token'] estivesse ausente, a cadeia caía direto no
        // header('X-CSRF-Token'), e mesmo que ELE também estivesse ausente
        // (retornando ''), o `??` já tinha "resolvido" naquele ponto: o
        // fallback pro X-XSRF-Token nunca era alcançado. Código morto.
        //
        // Também corrige um risco de TypeError: se "_csrf_token" viesse como
        // array (ex: corpo "_csrf_token[]=x"), Session::validateCsrf()
        // receberia um array em vez de string.
        $token = $_POST['_csrf_token'] ?? '';
        if (!is_string($token)) {
            $token = '';
        }
        if ($token === '') {
            $token = $request->header('X-CSRF-Token') ?: $request->header('X-XSRF-Token');
        }

        if (!Session::validateCsrf($token)) {
            if (!headers_sent()) {
                http_response_code(419);
            }

            $isAjax = $request->isAjax() || $request->isJson();

            if ($isAjax) {
                if (!headers_sent()) {
                    header('Content-Type: application/json');
                }
                echo json_encode(['success' => false, 'message' => 'Token CSRF inválido ou expirado.']);
                exit;
            }

            // Regenera token após falha
            Session::regenerateCsrf();
            Session::flash('error', 'Sua sessão expirou. Por favor, tente novamente.');

            // ── MELHORIA #2 ────────────────────────────────────────────────────
            // APP_URL cru vira Fatal Error se a constante não estiver definida
            // (ex: middleware disparado muito cedo no bootstrap); url() já tem
            // esse fallback embutido. headers_sent() também é respeitado antes
            // de tentar o header Location, com fallback via JS se necessário.
            $fallback = $_SERVER['HTTP_REFERER'] ?? url('/');
            if (!headers_sent()) {
                header('Location: ' . $fallback);
            } else {
                echo '<script>
window.location.href = ' . json_encode($fallback) . ';
</script>';
            }
            exit;
        }
    }
}
