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
    /**
     * Layout padrão usado quando nenhum é informado explicitamente.
     * Pode ser sobrescrito por controller filho: protected string $defaultLayout = 'admin';
     * Ou globalmente via constante DEFAULT_LAYOUT definida no bootstrap da app.
     */
    protected string $defaultLayout = 'app';

    // ── Views ─────────────────────────────────────────────────────────────────

    /**
     * Renderiza uma view, opcionalmente dentro de um layout.
     *
     * @param string            $view   Caminho dot-notation: 'auth.login', 'admin.home.index'
     * @param array             $data   Variáveis passadas para a view
     * @param string|false|null $layout Nome do layout (sem .php).
     *                                  - string  -> usa o layout informado (com fallback se não existir)
     *                                  - null    -> usa o layout padrão ($this->defaultLayout)
     *                                  - false | '' -> força renderização SEM layout
     */
    protected function view(string $view, array $data = [], string|false|null $layout = null): void
    {
        extract($data, EXTR_SKIP);

        $viewPath = $this->resolveViewPath($view);

        if (!file_exists($viewPath)) {
            throw new \RuntimeException("View [{$view}] não encontrada em [{$viewPath}].");
        }

        $layoutName = $this->resolveLayoutName($layout);
        $layoutPath = $layoutName ? $this->resolveLayoutPath($layoutName) : null;

        // Sem layout resolvido (explicitamente desativado ou nenhum disponível) -> renderiza só a view
        if ($layoutPath === null) {
            require $viewPath;
            return;
        }

        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        require $layoutPath;
    }

    /** Renderiza view sem layout (componentes parciais, emails, etc.) */
    protected function viewOnly(string $view, array $data = []): void
    {
        $this->view($view, $data, false);
    }

    // ── Resolução de caminhos (helpers internos) ─────────────────────────────

    protected function resolveViewPath(string $view): string
    {
        return VIEW_PATH . '/' . str_replace('.', '/', $view) . '.php';
    }

    protected function resolveLayoutPath(string $layout): string
    {
        return VIEW_PATH . '/layouts/' . str_replace('.', '/', $layout) . '.php';
    }

    /**
     * Decide qual nome de layout deve ser efetivamente usado, com fallback seguro:
     *  1. $layout === false | ''  -> nenhum layout (retorna null)
     *  2. $layout === null        -> tenta o layout padrão
     *  3. $layout informado       -> tenta esse; se não existir, cai pro padrão; se o padrão também não existir, sem layout
     */
    protected function resolveLayoutName(string|false|null $layout): ?string
    {
        // Desativado explicitamente
        if ($layout === false || $layout === '') {
            return null;
        }

        $default = defined('DEFAULT_LAYOUT') ? DEFAULT_LAYOUT : $this->defaultLayout;

        // Nenhum informado -> usa o padrão, se existir
        if ($layout === null) {
            return $this->layoutExists($default) ? $default : null;
        }

        // Layout específico informado
        if ($this->layoutExists($layout)) {
            return $layout;
        }

        // Fallback: layout informado não existe -> tenta o padrão
        if ($this->layoutExists($default)) {
            return $default;
        }

        // Nada disponível -> renderiza sem layout, sem quebrar a aplicação
        return null;
    }

    protected function layoutExists(string $layout): bool
    {
        return $layout !== '' && file_exists($this->resolveLayoutPath($layout));
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
