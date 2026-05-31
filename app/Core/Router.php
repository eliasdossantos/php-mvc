<?php

namespace Core;

/**
 * Router Desacoplado
 * ─────────────────────────────────────────────────────────────────────────────
 * Responsável por mapear URIs para Controllers e aplicar Middlewares.
 *
 * Funcionalidades:
 *  - Métodos HTTP: GET, POST, PUT, PATCH, DELETE
 *  - Parâmetros dinâmicos: /users/{id}
 *  - Parâmetros opcionais: /posts/{slug?}
 *  - Grupos de rotas com prefixo e middlewares compartilhados
 *  - Sintaxe fluente (method chaining)
 *  - Routes nomeadas para geração de URL
 *  - Suporte a "Controller@method" e [Controller::class, 'method']
 *
 * Uso básico:
 *   $router->get('/users',       [UserController::class, 'index']);
 *   $router->post('/users',      [UserController::class, 'store'], ['CsrfMiddleware']);
 *   $router->get('/users/{id}',  [UserController::class, 'show']);
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
    protected array   $routes     = [];
    protected array   $namedRoutes = [];
    protected Request $request;

    // Estado interno de grupo (stack para suporte a grupos aninhados)
    protected array $groupStack = [];

    // Namespace padrão de controllers
    protected string $controllerNamespace = 'App\\Controllers\\';

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
            $this->routes[$key]['name'] = $name;
            $this->namedRoutes[$name]   = $last['path'];
        }
        return $this;
    }

    /** Gera URL a partir do nome de uma rota e parâmetros */
    public function route(string $name, array $params = []): string
    {
        $path = $this->namedRoutes[$name]
            ?? throw new \InvalidArgumentException("Rota [{$name}] não encontrada.");

        foreach ($params as $key => $value) {
            $path = preg_replace('/\{' . $key . '\??\}/', $value, $path);
        }

        // Remove parâmetros opcionais não fornecidos
        $path = preg_replace('/\/\{[^}]+\?\}/', '', $path);

        return APP_URL . '/' . ltrim($path, '/');
    }

    // ── Dispatch ──────────────────────────────────────────────────────────────

    public function dispatch(): void
    {
        $method = $this->request->method();
        $uri    = $this->request->uri();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;

            $params = $this->matchUri($route['path'], $uri);
            if ($params === false) continue;

            // ── Executa middlewares ──────────────────────────────────────────
            foreach ($route['middlewares'] as $middleware) {
                $this->runMiddleware($middleware, $this->request);
            }

            // ── Executa a action ─────────────────────────────────────────────
            $this->callAction($route['action'], $params);
            return;
        }

        $this->handleNotFound();
    }

    // ── Internos ──────────────────────────────────────────────────────────────

    protected function addRoute(string $method, string $path, array|string $action, array $middlewares): static
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
            [$cls, $method_name] = explode('@', $action, 2);
            $action = [$cls, $method_name];
        }

        $this->routes[] = [
            'method'      => strtoupper($method),
            'path'        => $fullPath,
            'action'      => $action,
            'middlewares' => array_merge($groupMidds, $middlewares),
            'name'        => null,
        ];

        return $this;
    }

    /** Tenta casar a URI com o padrão da rota; retorna parâmetros ou false */
    protected function matchUri(string $pattern, string $uri): array|false
    {
        // {param} → captura obrigatória, {param?} → opcional
        $regex = preg_replace('/\{([a-zA-Z_]+)\?\}/', '([^/]*)',  $pattern);
        $regex = preg_replace('/\{([a-zA-Z_]+)\}/',   '([^/]+)',  $regex);
        $regex = '#^' . $regex . '$#';

        if (!preg_match($regex, $uri, $matches)) return false;

        array_shift($matches);
        return $matches;
    }

    /** Resolve e instancia o controller, chamando o método */
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

        call_user_func_array([$instance, $methodName], $params);
    }

    /** Resolve e executa um middleware */
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

        if (!class_exists($fqcn)) return;

        $instance = new $fqcn();
        $param ? $instance->handle($request, $param) : $instance->handle($request);
    }

    protected function handleNotFound(): void
    {
        http_response_code(404);
        $view = VIEW_PATH . '/errors/404.php';
        file_exists($view) ? require $view : print('<h1>404 — Página não encontrada</h1>');
    }

    // ── Getters ───────────────────────────────────────────────────────────────

    public function getRoutes(): array { return $this->routes; }
    public function setControllerNamespace(string $ns): void { $this->controllerNamespace = $ns; }
}
