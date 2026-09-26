<?php

namespace Core;

/**
 * Router Desacoplado
 * ─────────────────────────────────────────────────────────────────────────────
 * Responsável por mapear URIs para Controllers e aplicar Middlewares.
 *
 * Funcionalidades:
 *  - Métodos HTTP: GET, POST, PUT, PATCH, DELETE
 *  - Parâmetros dinâmicos: /Users/{id}
 *  - Parâmetros opcionais: /posts/{slug?}
 *  - Grupos de rotas com prefixo e middlewares compartilhados
 *  - Sintaxe fluente (method chaining)
 *  - Routes nomeadas para geração de URL
 *  - Suporte a "Controller@method" e [Controller::class, 'method']
 *
 * Uso básico:
 *   $router->get('/Users',       [UserController::class, 'index']);
 *   $router->post('/Users',      [UserController::class, 'store'], ['CsrfMiddleware']);
 *   $router->get('/Users/{id}',  [UserController::class, 'show']);
 *
 * Grupos:
 *   $router->group(['prefix' => '/admin', 'middleware' => ['AuthMiddleware']], function ($r) {
 *       $r->get('/dashboard', [AdminController::class, 'index']);
 *   });
 *
 * Routes nomeadas:
 *   $router->get('/login', [AuthController::class, 'loginForm'])->name('auth.login');
 *   echo route('auth.login'); // → /login
 */
class Router
{
    protected array   $routes      = [];
    protected array   $namedRoutes = [];
    protected Request $request;

    // Estado interno de grupo (stack para suporte a grupos aninhados)
    protected array $groupStack = [];

    // Namespace padrão de controllers
    protected string $controllerNamespace = 'App\\Controllers\\';

    // ── Registry estático ─────────────────────────────────────────────────────
    // Permite que a função global route() acesse a instância do Router sem
    // depender de `global $router`, que falha quando chamada dentro de views
    // pois $router é variável local de Application::run(), não global.
    // Application::run() chama setInstance() antes do require de rotas.
    protected static ?self $instance = null;

    public static function getInstance(): ?static
    {
        return static::$instance;
    }

    // Usa `self` no tipo do parâmetro em vez de `static` para evitar ambiguidade
    // em contexto estático — `static` como tipo de parâmetro não é permitido
    // em métodos estáticos (Intelephense P1081). O comportamento é idêntico
    // pois Router não é estendido no projeto.
    public static function setInstance(self $router): void
    {
        static::$instance = $router;
    }

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    // ── Registro de rotas ─────────────────────────────────────────────────────

    public function get(string $path, array|string $action, array $middlewares = []): static
    {
        return $this->addRoute('GET', $path, $action, $middlewares);
    }

    public function post(string $path, array|string $action, array $middlewares = []): static
    {
        return $this->addRoute('POST', $path, $action, $middlewares);
    }

    public function put(string $path, array|string $action, array $middlewares = []): static
    {
        return $this->addRoute('PUT', $path, $action, $middlewares);
    }

    public function patch(string $path, array|string $action, array $middlewares = []): static
    {
        return $this->addRoute('PATCH', $path, $action, $middlewares);
    }

    public function delete(string $path, array|string $action, array $middlewares = []): static
    {
        return $this->addRoute('DELETE', $path, $action, $middlewares);
    }

    /**
     * Registra uma rota OPTIONS — usada para responder preflight de CORS.
     * O action normalmente nunca chega a rodar: CorsMiddleware já responde
     * 204 e encerra a requisição antes de chegar no Controller.
     */
    public function options(string $path, array|string $action, array $middlewares = []): static
    {
        return $this->addRoute('OPTIONS', $path, $action, $middlewares);
    }

    /** Registra múltiplos métodos para a mesma rota */
    public function match(array $methods, string $path, array|string $action, array $middlewares = []): static
    {
        $last = null;
        foreach ($methods as $method) {
            $last = $this->addRoute(strtoupper($method), $path, $action, $middlewares);
        }
        return $last ?? $this;
    }

    /**
     * CRUD completo — gera 7 rotas padrão REST:
     *   GET    /resource           → index
     *   GET    /resource/create    → create
     *   POST   /resource           → store
     *   GET    /resource/{id}      → show
     *   GET    /resource/{id}/edit → edit
     *   PUT    /resource/{id}      → update
     *   DELETE /resource/{id}      → destroy
     */
    public function resource(string $path, string $controller, array $only = []): void
    {
        $name = ltrim($path, '/');
        $all  = [
            'index'   => ['GET',    $path],
            'create'  => ['GET',    $path . '/create'],
            'store'   => ['POST',   $path],
            'show'    => ['GET',    $path . '/{id}'],
            'edit'    => ['GET',    $path . '/{id}/edit'],
            'update'  => ['PUT',    $path . '/{id}'],
            'destroy' => ['DELETE', $path . '/{id}'],
        ];

        foreach ($all as $action => [$method, $route]) {
            if ($only && !in_array($action, $only)) continue;
            $this->addRoute($method, $route, [$controller, $action])
                ->name("{$name}.{$action}");
        }
    }

    // ── Agrupamento ───────────────────────────────────────────────────────────

    public function group(array $options, callable $callback): void
    {
        $this->groupStack[] = $options;
        $callback($this);
        array_pop($this->groupStack);
    }

    // ── Nomes de rotas ────────────────────────────────────────────────────────

    public function name(string $name): static
    {
        $last = end($this->routes);
        if ($last) {
            $key = count($this->routes) - 1;

            // Acumula o 'as' de todos os grupos abertos no momento da chamada,
            // igual já é feito com 'prefix' em addRoute().
            $asPrefix = '';
            foreach ($this->groupStack as $g) {
                $asPrefix .= $g['as'] ?? '';
            }

            $fullName = $asPrefix . $name;

            $this->routes[$key]['name']   = $fullName;
            $this->namedRoutes[$fullName] = $last['path'];
        }
        return $this;
    }

    /** Gera URL a partir do nome de uma rota e parâmetros */
    public function route(string $name, array $params = []): string
    {
        $path = $this->namedRoutes[$name]
            ?? throw new \InvalidArgumentException("Rota [{$name}] não encontrada.");

        foreach ($params as $key => $value) {
            $encoded = rawurlencode((string) $value);
            $path = preg_replace('/\{' . preg_quote($key, '/') . '\??\}/', $encoded, $path) ?? $path;
        }

        // Remove segmentos opcionais não fornecidos.
        $path = preg_replace('/\/\{[a-zA-Z_][a-zA-Z0-9_]*\?\}/', '', $path) ?? $path;
        if (preg_match('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $path, $missing)) {
            throw new \InvalidArgumentException("Parâmetro obrigatório [{$missing[1]}] não informado para a rota [{$name}].");
        }

        return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
    }

    // ── Dispatch ──────────────────────────────────────────────────────────────

    public function dispatch(): void
    {
        $method = $this->request->method();
        $uri    = $this->request->uri();

        $allowedMethods = [];
        foreach ($this->routes as $route) {
            $params = $this->matchUri($route['path'], $uri, $route['paramNames']);
            if ($params === false) continue;
            if ($route['method'] !== $method) {
                $allowedMethods[] = $route['method'];
                continue;
            }

            // ── Detalhes de implementação
            // Exceções lançadas aqui dentro (middleware ausente,
            // controller/método não encontrado, parâmetro obrigatório faltando,
            // erro dentro da própria action) subia sem tratamento nenhum —
            // dependendo da configuração do PHP, isso vira uma tela branca ou
            // um stack trace cru exposto pro usuário final. A implementação a rota já
            // identificada (essa é a rota certa pra essa URI) tem sua execução
            // isolada: se falhar, vira uma resposta 500 controlada, e o resto
            // da aplicação continua de pé pra próxima requisição.
            try {
                foreach ($route['middlewares'] as $middleware) {
                    $this->runMiddleware($middleware, $this->request);
                }
                $this->callAction($route['action'], $params);
            } catch (\Throwable $e) {
                $this->handleServerError($e);
            }
            return;
        }

        if ($allowedMethods) {
            $this->handleMethodNotAllowed(array_values(array_unique($allowedMethods)));
            return;
        }
        $this->handleNotFound();
    }

    // ── Internos ──────────────────────────────────────────────────────────────

    protected function addRoute(string $method, string $path, array|string $action, array $middlewares = []): static
    {
        // Resolve stack de grupos
        $prefix     = '';
        $groupMidds = [];
        foreach ($this->groupStack as $g) {
            $prefix     .= $g['prefix']     ?? '';
            $groupMidds  = array_merge($groupMidds, $g['middleware'] ?? []);
        }

        $fullPath = $prefix . $path;

        // Resolve "ControllerClass@method"
        if (is_string($action) && str_contains($action, '@')) {
            [$cls, $methodName] = explode('@', $action, 2);
            $action = [$cls, $methodName];
        }

        // ── Mapeamento de parâmetros da rota ───────────────────────────────────────
        // Extrai os nomes dos parâmetros da rota para mapeamento posicional→nomeado.
        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\??\}/', $fullPath, $matches);
        $paramNames = $matches[1] ?? [];

        $this->routes[] = [
            'method'      => strtoupper($method),
            'path'        => $fullPath,
            'action'      => $action,
            'middlewares' => array_merge($groupMidds, $middlewares),
            'name'        => null,
            'paramNames'  => $paramNames,
        ];

        return $this;
    }

    /**
     * Tenta casar a URI com o padrão da rota.
     *
     * ── Correspondência de parâmetros da URI ────────────────────────────────────────────
     * Retorna array associativo ['nomeDoParam' => 'valor'] em vez de posicional.
     *
     * ── Detalhes de implementação
     * Explicita a checagem de preg_match (=== 1) e trata preg_replace()
     * retornando null (regex malformada) como "essa rota não bate", em vez de
     * deixar passar null adiante e quebrar o preg_match seguinte.
     *
     * @return array<string,string|null>|false
     */
    protected function matchUri(string $pattern, string $uri, array $paramNames): array|false
    {
        // {param} → captura obrigatória; /{param?} → segmento opcional completo.
        $regex = preg_replace('/\/\{([a-zA-Z_][a-zA-Z0-9_]*)\?\}/', '(?:/([^/]+))?', $pattern);
        $regex = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\?\}/', '([^/]*)', $regex ?? $pattern);
        $regex = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '([^/]+)', $regex ?? $pattern);

        if ($regex === null) {
            // preg_replace falhou (padrão de rota malformado) — trata como "não bate"
            return false;
        }

        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $uri, $matches) !== 1) {
            return false;
        }

        array_shift($matches); // remove o match completo

        // Monta array associativo: ['id' => '42', 'slug' => null, ...]
        $result = [];
        foreach ($paramNames as $i => $name) {
            $val = $matches[$i] ?? '';
            // Parâmetro opcional não informado → null (evita string vazia enganosa)
            $result[$name] = ($val === '') ? null : $val;
        }

        return $result;
    }

    /**
     * Resolve e instancia o controller, chamando o método.
     *
     * ── Injeção de parâmetros da action ────────────────────────────────────────────
     * Usa ReflectionMethod pra injetar parâmetros por nome, com fallback pro
     * valor padrão do método quando não vierem na URI.
     */
    protected function callAction(array $action, array $params): void
    {
        [$ctrl, $methodName] = $action;

        // FQCN já fornecido (ex: App\Controllers\UserController)
        // ou apenas o nome simples (ex: UserController)
        $fqcn = str_contains($ctrl, '\\')
            ? $ctrl
            : $this->controllerNamespace . $ctrl;

        if (!class_exists($fqcn)) {
            throw new \RuntimeException("Controller [{$fqcn}] não encontrado.", 500);
        }

        $instance = new $fqcn();

        if (!method_exists($instance, $methodName)) {
            throw new \RuntimeException("Método [{$methodName}] não existe em [{$fqcn}].", 500);
        }

        // Injeta parâmetros por nome via Reflection
        $reflection = new \ReflectionMethod($instance, $methodName);
        $args       = [];

        foreach ($reflection->getParameters() as $rParam) {
            $name = $rParam->getName();
            $type = $rParam->getType();

            // ── Injeção de FormRequest estilo Laravel ───────────────────────
            // Se o parâmetro é tipado com uma classe (não escalar) que estende
            // FormRequest, instancia automaticamente. O construtor do FormRequest
            // já captura o input, valida e (se authorize() falhar ou a validação
            // falhar) interrompe a requisição sozinho — então quando o controller
            // recebe o objeto aqui, ele já está resolvido.
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $className = $type->getName();
                if (is_subclass_of($className, \App\Requests\FormRequest::class)) {
                    $args[] = new $className($params);
                    continue;
                }
            }

            if (array_key_exists($name, $params) && $params[$name] !== null) {
                // Converte para o tipo declarado no método (int, string…)
                $args[] = $this->castParam($params[$name], $rParam);
            } elseif ($rParam->isOptional()) {
                // ── Detalhes de implementação
                // getDefaultValue() pode lançar ReflectionException em casos raros
                // (ex: parâmetro variádico marcado como opcional sem um default
                // "de verdade"). Isso não deveria travar a requisição inteira só
                // por causa disso — cai pra null.
                try {
                    $args[] = $rParam->getDefaultValue();
                } catch (\ReflectionException) {
                    $args[] = null;
                }
            } else {
                // Parâmetro obrigatório não presente na URI → erro claro
                throw new \RuntimeException(
                    "Parâmetro obrigatório [{$name}] não encontrado na URI para {$fqcn}::{$methodName}().",
                    500
                );
            }
        }

        $reflection->invokeArgs($instance, $args);
    }

    /**
     * Converte o valor capturado da URI para o tipo declarado no parâmetro do método.
     * Suporta int, float, bool, string. Outros tipos recebem o valor bruto (string).
     */
    protected function castParam(string $value, \ReflectionParameter $param): mixed
    {
        $type = $param->getType();

        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin() === false) {
            return $value;
        }

        return match ($type->getName()) {
            'int'    => (int)   $value,
            'float'  => (float) $value,
            'bool'   => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default  => $value,
        };
    }

    /**
     * Resolve e executa um middleware.
     *
     * ── Proteção de segurança
     * Se a classe do middleware não existir, o método
     * dava `return` — a rota seguia em frente SEM o middleware aplicado.
     * Isso é perigoso quando o middleware ausente é de autenticação/autorização:
     * um erro de digitação no nome (ex: "AuthMiddlware") liberava a rota
     * silenciosamente para qualquer um. A implementação lança uma exceção clara, que o
     * dispatch() acima captura e transforma numa resposta 500 — a rota não é
     * mais liberada por engano, e a aplicação ainda não trava por completo.
     */
    protected function runMiddleware(string $middleware, Request $request): void
    {
        // Suporte a parâmetro: "RoleMiddleware:admin"
        $param = null;
        if (str_contains($middleware, ':')) {
            [$middleware, $param] = explode(':', $middleware, 2);
        }

        $fqcn = str_contains($middleware, '\\')
            ? $middleware
            : "App\\Middlewares\\{$middleware}";

        if (!class_exists($fqcn)) {
            throw new \RuntimeException("Middleware [{$fqcn}] não encontrado.");
        }

        $instance = new $fqcn();

        if (!method_exists($instance, 'handle')) {
            throw new \RuntimeException("Middleware [{$fqcn}] não possui o método handle().");
        }

        $param ? $instance->handle($request, $param) : $instance->handle($request);
    }

    protected function handleNotFound(): void
    {
        http_response_code(404);
        $view = VIEW_PATH . '/errors/404.php';
        file_exists($view) ? require $view : print('<h1>404 — Página não encontrada</h1>');
    }

    protected function handleMethodNotAllowed(array $allowedMethods): void
    {
        http_response_code(405);
        if (!headers_sent()) header('Allow: ' . implode(', ', $allowedMethods));
        $isJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || str_starts_with($this->request->uri(), '/api/');
        if ($isJson) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => false, 'message' => 'Método HTTP não permitido.']);
            return;
        }
        echo '<h1>405 — Método não permitido</h1>';
    }

    /**
     * ── Suporte adicional
     * Handler central de erro pra qualquer exceção lançada durante a execução
     * de uma rota já identificada (middleware ou action). Segue o mesmo padrão
     * de handleNotFound(): tenta usar uma view de erro do projeto, com
     * fallback simples se ela não existir. Loga via Core\Logger quando
     * disponível, sem criar dependência rígida (funciona mesmo se a classe
     * não existir no projeto).
     */
    protected function handleServerError(\Throwable $e): void
    {
        http_response_code(500);

        if (class_exists(\Core\Logger::class)) {
            \Core\Logger::error('Erro não tratado no dispatch da rota', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);
        }

        $debug = isset($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true';

        $view = defined('VIEW_PATH') ? VIEW_PATH . '/errors/500.php' : null;
        if ($view !== null && file_exists($view)) {
            require $view; // a view pode usar $e e $debug se quiser mostrar detalhes
            return;
        }

        if ($debug) {
            echo '<h1>500 — Erro interno</h1><pre>'
                . htmlspecialchars($e->getMessage()) . "\n"
                . htmlspecialchars($e->getTraceAsString())
                . '</pre>';
        } else {
            echo '<h1>500 — Erro interno</h1>';
        }
    }

    // ── Getters ───────────────────────────────────────────────────────────────

    public function getRoutes(): array
    {
        return $this->routes;
    }
    public function setControllerNamespace(string $ns): void
    {
        $this->controllerNamespace = $ns;
    }
}
