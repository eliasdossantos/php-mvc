<?php

namespace Core;

/**
 * Controller Base
 * ─────────────────────────────────────────────────────────────────────────────
 * Todos os controllers da aplicação herdam desta classe.
 * Fornece métodos utilitários para renderização, redirecionamento,
 * respostas JSON, flash messages e acesso ao usuário autenticado.
 *
 * Regras:
 *  - Nenhuma lógica de negócio aqui
 *  - Apenas infraestrutura de resposta HTTP
 *  - Métodos protected para acesso exclusivo dos controllers filhos
 */
abstract class Controller
{
    // ── Views ─────────────────────────────────────────────────────────────────

    /**
     * Renderiza uma view dentro de um layout.
     *
     * @param string $view   Caminho dot-notation: 'auth.login', 'dashboard.index'
     * @param array  $data   Variáveis passadas para a view
     * @param string $layout Nome do layout (sem .php). '' = sem layout
     */
    protected function view(string $view, array $data = [], string $layout = 'app'): void
    {
        extract($data, EXTR_SKIP);

        $viewPath = VIEW_PATH . '/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($viewPath)) {
            throw new \RuntimeException("View [{$view}] não encontrada em [{$viewPath}].");
        }

        if ($layout) {
            ob_start();
            require $viewPath;
            $content = ob_get_clean();

            $layoutPath = VIEW_PATH . '/layouts/' . $layout . '.php';
            if (!file_exists($layoutPath)) {
                throw new \RuntimeException("Layout [{$layout}] não encontrado em [{$layoutPath}].");
            }
            require $layoutPath;
        } else {
            require $viewPath;
        }
    }

    /** Renderiza view sem layout (componentes parciais, emails, etc.) */
    protected function viewOnly(string $view, array $data = []): void
    {
        $this->view($view, $data, '');
    }

    // ── Redirecionamento ──────────────────────────────────────────────────────

    protected function redirect(string $url): never
    {
        $url = str_starts_with($url, 'http') ? $url : APP_URL . '/' . ltrim($url, '/');
        header("Location: {$url}");
        exit;
    }

    /** Redireciona para a URL anterior (Referer) */
    protected function back(): never
    {
        $this->redirect($_SERVER['HTTP_REFERER'] ?? APP_URL);
    }

    /** Redireciona com flash de sucesso */
    protected function redirectWith(string $url, string $type, string $message): never
    {
        Session::flash($type, $message);
        $this->redirect($url);
    }

    // ── Respostas JSON (APIs) ─────────────────────────────────────────────────

    protected function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    protected function jsonSuccess(string $message = 'OK', array $data = [], int $status = 200): never
    {
        $this->json(array_merge(['success' => true, 'message' => $message], $data), $status);
    }

    protected function jsonError(string $message, int $status = 400, array $data = []): never
    {
        $this->json(array_merge(['success' => false, 'message' => $message], $data), $status);
    }

    // ── Flash Messages ────────────────────────────────────────────────────────

    protected function flash(string $type, string $message): void
    {
        Session::flash($type, $message);
    }

    // ── Usuário Autenticado ───────────────────────────────────────────────────

    protected function auth(): bool
    {
        return Session::has('user_id');
    }

    protected function user(): ?object
    {
        return Session::get('user');
    }

    protected function userId(): int
    {
        return (int) Session::get('user_id', 0);
    }

    protected function userRole(): string
    {
        return Session::get('user')?->role ?? 'guest';
    }

    // ── Validação inline ──────────────────────────────────────────────────────

    /**
     * Valida dados de uma request e redireciona de volta se falhar.
     * Salva erros e inputs antigos na sessão.
     */
    protected function validate(array $data, array $rules, string $redirectBack = ''): array
    {
        $validator = new Validator($data);
        $validator->validate($rules);

        if ($validator->fails()) {
            Session::set('_errors',    $validator->errors());
            Session::set('_old_input', $data);

            $back = $redirectBack ?: ($_SERVER['HTTP_REFERER'] ?? APP_URL);
            $this->redirect($back);
        }

        return $data;
    }

    // ── Abort ─────────────────────────────────────────────────────────────────

    protected function abort(int $code, string $message = ''): never
    {
        throw new \RuntimeException($message ?: "HTTP {$code}", $code);
    }

    protected function abortIf(bool $condition, int $code, string $message = ''): void
    {
        if ($condition) $this->abort($code, $message);
    }

    protected function abortUnless(bool $condition, int $code, string $message = ''): void
    {
        if (!$condition) $this->abort($code, $message);
    }
}
