<?php

namespace Core;

use Core\Request;

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
 *
 * CSRF automático em forms:
 *   Toda página renderizada via view() passa pela injeção automática de
 *   csrf_field() em qualquer <form> com method="post" (ou put/patch/delete)
 *   que ainda não tenha um campo _csrf_token. Funciona tanto para views que
 *   usam sections (View::start/end) quanto para views que só imprimem HTML
 *   direto, porque a injeção roda sobre a página inteira já renderizada
 *   (view + layout), não sobre pedaços isolados.
 *   Forms sem atributo "method" ou com method="get" são ignorados,
 *   pois são tratados como GET (não alteram estado, não precisam de CSRF).
 */
abstract class Controller
{
    /**
     * Instância da requisição HTTP atual, disponível automaticamente
     * em todo controller que estenda esta classe base.
     * Não é necessário instanciar manualmente em controllers filhos,
     * basta chamar parent::__construct().
     */
    protected Request $request;

    public function __construct()
    {
        $this->request = new Request();
    }

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
        View::resetSections();

        extract($data, EXTR_SKIP);

        $viewPath = $this->resolveViewPath($view);

        if (!file_exists($viewPath)) {
            throw new \RuntimeException("View [{$view}] não encontrada em [{$viewPath}].");
        }

        $layoutName = $this->resolveLayoutName($layout);
        $layoutPath = $layoutName ? $this->resolveLayoutPath($layoutName) : null;

        // ── Captura a página inteira (view + layout) num único buffer ──────────
        // Isso garante que a injeção de CSRF enxergue o HTML final completo,
        // independente de a view usar sections (View::start/end) ou $content direto.
        ob_start();

        if ($layoutPath === null) {
            require $viewPath;
        } else {
            ob_start();
            require $viewPath;
            $content = ob_get_clean();

            require $layoutPath;
        }

        echo $this->injectCsrfTokens(ob_get_clean());
    }

    /** Renderiza view sem layout (componentes parciais, emails, etc.) */
    protected function viewOnly(string $view, array $data = []): void
    {
        $this->view($view, $data, false);
    }

    /**
     * Injeta automaticamente o csrf_field() em qualquer <form> presente no
     * HTML final renderizado, para que ninguém esqueça de proteger o formulário.
     *
     * Regras:
     *  - Forms SEM atributo "method" são tratados como GET implícito
     *    (padrão do HTML) e são ignorados — GET não deve alterar estado
     *    no servidor, então não precisa de CSRF.
     *  - Forms com method="get" (case-insensitive) também são ignorados.
     *  - Se o form já contém um campo "_csrf_token" (colocado manualmente
     *    com csrf_field()), nada é duplicado.
     *  - Caso contrário (method="post", "put", "patch", "delete" etc.),
     *    o token é inserido logo após a tag de abertura do <form>.
     *
     * Uso: chamado automaticamente por view() — não precisa chamar direto.
     */
    protected function injectCsrfTokens(string|false $html): string
    {
        $html = $html ?: '';

        return preg_replace_callback(
            '/<form\b([^>]*)>(.*?)<\/form>/is',
            function (array $m): string {
                [$full, $attrs, $body] = $m;

                // Sem atributo "method" -> GET implícito (padrão HTML), não precisa de CSRF
                if (!preg_match('/method\s*=\s*["\']([^"\']*)["\']/i', $attrs, $methodMatch)) {
                    return $full;
                }

                // method="get" explícito -> também não precisa de CSRF
                if (strtolower($methodMatch[1]) === 'get') {
                    return $full;
                }

                // Já tem token manual — não duplica
                if (str_contains($body, '_csrf_token')) {
                    return $full;
                }

                return "<form{$attrs}>" . \csrf_field() . $body . '</form>';
            },
            $html
        ) ?? $html;
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

    /**
     * Valida um FormRequest e redireciona de volta se falhar.
     * Evita repetir o bloco fails()/flash()/back() em todo controller.
     */
    protected function validateRequest(object $request, string $redirectBack = ''): array
    {
        if (!method_exists($request, 'fails')) {
            throw new \InvalidArgumentException('Objeto informado não é um FormRequest válido.');
        }

        if ($request->fails()) {
            Session::flash('error', $request->firstError());
            Session::flashInput($request->all());

            $back = $redirectBack ?: ($_SERVER['HTTP_REFERER'] ?? APP_URL);
            $this->redirect($back);
        }

        return $request->validated();
    }

    // ── Abort ─────────────────────────────────────────────────────────────────

    protected function abort(int $code, string $message = ''): never
    {
        throw new \RuntimeException($message ?: "HTTP {$code}", $code);
    }

    protected function abortIf(mixed $condition, int $code, string $message = ''): void
    {
        if ($condition) {
            $this->abort($code, $message);
        }
    }

    protected function abortUnless(mixed $condition, int $code, string $message = ''): void
    {
        if (!$condition) {
            $this->abort($code, $message);
        }
    }
}