<?php

namespace Framework;

use Framework\Request;

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
    protected string $defaultLayout = '';

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
     *
     * ── Detalhes de implementação
     * Se a view (ou o layout) lançasse uma exceção no meio do require, os
     * ob_start() já abertos ficavam pendurados (um deles, ou os dois, quando
     * havia layout). O buffer nunca era fechado, então a PRÓXIMA saída da
     * aplicação (ex: a página de erro 500 do Router) saía misturada com o
     * HTML parcial que já tinha sido bufferizado — uma página bagunçada em
     * vez de um erro limpo. A implementação qualquer buffer aberto por este método é
     * fechado antes de repropagar a exceção, então o handler de erro (Router)
     * recebe uma saída limpa pra trabalhar.
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

        $bufferLevelBefore = ob_get_level();

        // ── Captura a página inteira (view + layout) num único buffer ──────────
        // Isso garante que a injeção de CSRF enxergue o HTML final completo,
        // independente de a view usar sections (View::start/end) ou $content direto.
        ob_start();

        try {
            if ($layoutPath === null) {
                require $viewPath;
            } else {
                ob_start();
                require $viewPath;
                $content = ob_get_clean();

                require $layoutPath;
            }
        } catch (\Throwable $e) {
            while (ob_get_level() > $bufferLevelBefore) {
                ob_end_clean();
            }
            throw $e;
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

    /**
     * ── Detalhes de implementação
     * Remove sequências ".." antes de montar o caminho — proteção básica
     * contra directory traversal caso $view algum dia venha de um valor
     * dinâmico (ex: name de view montado a partir de parâmetro de rota) em
     * vez de sempre hardcoded pelo desenvolvedor.
     */
    protected function resolveViewPath(string $view): string
    {
        $view = str_replace(['..', '\\'], '', $view);
        return VIEW_PATH . '/' . str_replace('.', '/', $view) . '.php';
    }

    protected function resolveLayoutPath(string $layout): string
    {
        $layout = str_replace(['..', '\\'], '', $layout);
        return VIEW_PATH . '/layouts/' . str_replace('.', '/', $layout) . '.php';
    }

    /**
     * Decide qual name de layout deve ser efetivamente usado, com fallback seguro:
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

    /**
     * ── Detalhes de implementação
     * Usa appUrl() em vez de APP_URL direto, evitando
     * Fatal Error se a constante não estiver definida; (2) cai pra um
     * redirect via HTML/JS se os headers já tiverem sido enviados, em vez de
     * só emitir um warning e não redirecionar de verdade.
     */
    protected function redirect(string $url): never
    {
        $url = safeRedirectTarget($url, $this->appUrl() ?: '/');
        if (!str_starts_with($url, 'http')) $url = rtrim($this->appUrl(), '/') . '/' . ltrim($url, '/');

        if (!headers_sent()) {
            header("Location: {$url}");
            exit;
        }

        echo '<script>window.location.href=' . json_encode($url) . ';</script>'
            . '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES) . '"></noscript>';
        exit;
    }

    /** Redireciona para a URL anterior (Referer) */
    protected function back(): never
    {
        $this->redirect(safeRedirectTarget($_SERVER['HTTP_REFERER'] ?? '', $this->appUrl() ?: '/'));
    }

    /** Redireciona com flash de sucesso */
    protected function redirectWith(string $url, string $type, string $message): never
    {
        Session::flash($type, $message);
        $this->redirect($url);
    }

    /** Valor seguro de APP_URL, mesmo se a constante ainda não tiver sido definida */
    protected function appUrl(): string
    {
        return defined('APP_URL') ? APP_URL : '';
    }

    // ── Respostas JSON (APIs) ─────────────────────────────────────────────────

    /**
     * ── Detalhes de implementação
     * json_encode() pode falhar (retorna false) — ex: dados com encoding
     * inválido, NAN/INF numa struct, referência circular. Esse caso fazia
     * echo imprimir a string vazia de `false`, respondendo 200 com corpo
     * vazio como se tivesse dado tudo certo. A implementação detecta a falha, responde
     * 500 e devolve uma mensagem de erro real em vez de um corpo vazio
     * enganoso.
     */
    protected function json(mixed $data, int $status = 200): never
    {
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            http_response_code(500);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao gerar resposta JSON: ' . json_last_error_msg(),
            ]);
            exit;
        }

        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo $encoded;
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
        return Auth::check();
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

    // ── Verificação de tipo de requisição ────────────────────────────────────

    /**
     * Garante que a requisição atual é de um dos tipos esperados — útil no
     * topo de métodos de API/AJAX pra rejeitar cedo requisições fora do
     * formato certo, sem repetir if/jsonError em cada action.
     *
     * Aceita um tipo só ou vários (OR — basta um bater):
     *   $this->checkMethod('post');               // só POST
     *   $this->checkMethod(['post', 'put']);       // POST ou PUT
     *   $this->checkMethod('ajax', 'Só via AJAX.'); // mensagem customizada
     *
     * Tipos válidos: ajax, json, get, post, put, patch, delete.
     *
     * Diferença importante em relação à primeira versão: um $tipo digitado
     * errado (ex: 'pust') a implementação É um erro de verdade — lança exceção em vez
     * de silenciosamente deixar passar sem validar nada. Faz sentido separar
     * os dois casos: "o tipo que você pediu pra checar não existe" é bug de
     * programação (quer barulho, não passe batido); "a requisição não bate
     * com o tipo esperado" é o cliente errando o request (aí sim é 400/jsonError).
     */
    protected function checkMethod(string|array $tipos, string $mensagem = 'Requisição inválida.', int $status = 400): void
    {
        foreach ((array) $tipos as $tipo) {
            if ($this->requestMatchesType($tipo)) {
                return; // pelo menos um tipo bateu — segue o fluxo normal
            }
        }

        $this->jsonError($mensagem, $status);
    }

    /**
     * Confere um único tipo contra o Request atual.
     * Lança exceção se $tipo não é reconhecido, ou se o Request não tiver o
     * método correspondente — nos dois casos é erro de código, não de
     * requisição do cliente, então não deve ser tratado como "requisição
     * inválida" (400): deve travar e avisar quem programou o check errado.
     */
    private function requestMatchesType(string $tipo): bool
    {
        $checks = [
            'ajax'   => 'isAjax',
            'json'   => 'isJson',
            'get'    => 'isGet',
            'post'   => 'isPost',
            'put'    => 'isPut',
            'patch'  => 'isPatch',
            'delete' => 'isDelete',
        ];

        if (!isset($checks[$tipo])) {
            throw new \InvalidArgumentException(
                "checkMethod(): tipo \"{$tipo}\" não existe. Tipos válidos: " . implode(', ', array_keys($checks)) . '.'
            );
        }

        $method = $checks[$tipo];

        if (!method_exists($this->request, $method)) {
            throw new \RuntimeException("checkMethod(): Request não possui o método {$method}().");
        }

        return (bool) $this->request->{$method}();
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

            $back = $redirectBack ?: safeRedirectTarget($_SERVER['HTTP_REFERER'] ?? '', $this->appUrl() ?: '/');
            $this->redirect($back);
        }

        return $data;
    }

    /**
     * Valida um FormRequest e redireciona de volta se falhar.
     * Evita repetir o bloco fails()/flash()/back() em todo controller.
     *
     * @param array|null $flashOnly Campos a reenviar via flashInput() em caso de
     * falha. null = reenvia tudo (padrão anterior).
     * [] = não reenvia nada (ex.: formulário com senha).
     * ['campo', ...] = reenvia só os campos listados.
     */
    protected function validateRequest(object $request, string $redirectBack = '', ?array $flashOnly = null): array
    {
        if (!method_exists($request, 'fails')) {
            throw new \InvalidArgumentException('Objeto informado não é um FormRequest válido.');
        }

        if ($request->fails()) {
            Session::flash('error', $request->firstError());

            if ($flashOnly === null) {
                Session::flashInput($request->all());
            } elseif ($flashOnly !== []) {
                $old = [];
                foreach ($flashOnly as $field) {
                    $old[$field] = $request->old($field);
                }
                Session::flashInput($old);
            }

            $back = $redirectBack ?: safeRedirectTarget($_SERVER['HTTP_REFERER'] ?? '', $this->appUrl() ?: '/');
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
