<?php

namespace Core;

/**
 * Kernel da Aplicação
 * ─────────────────────────────────────────────────────────────────────────────
 * Orquestra o ciclo de vida completo de uma requisição HTTP:
 *
 *   1. Configura o modo de debug e handlers de erro
 *   2. Carrega o arquivo de rotas
 *   3. Faz o dispatch para o Controller correto
 *   4. Trata exceções de forma centralizada
 *
 * Esta classe é GENÉRICA — não contém nenhuma regra de negócio da aplicação.
 * Pode ser reutilizada como base em qualquer projeto PHP MVC.
 */
class Application
{
    protected Router  $router;
    protected Request $request;
    protected float   $startTime;

    /** Mapa de códigos HTTP para mensagens */
    protected array $httpMessages = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        419 => 'CSRF Token Mismatch',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        503 => 'Service Unavailable',
    ];

    public function __construct()
    {
        $this->startTime = microtime(true);
        $this->request   = new Request();
        $this->router    = new Router($this->request);

        $this->configureErrorHandling();
        $this->setSecurityHeaders();
    }

    // ── Ciclo de vida principal ───────────────────────────────────────────────

    /**
     * Executa a aplicação:
     * carrega rotas → despacha requisição → trata erros
     */
    public function run(): void
    {
        try {
            $router = $this->router; // disponível no escopo do require (routes/web.php)

            // ── BUG CORRIGIDO #14 (parte da Application) ─────────────────────
            // Registra a instância do router no Registry estático ANTES de
            // carregar as rotas, para que route() funcione dentro de views
            // sem depender de `global $router`.
            Router::setInstance($this->router);

            require ROUTES_PATH . '/web.php'; // registra as rotas
            $this->router->dispatch();
            $this->logDebugInfo();
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    // ── Configuração ─────────────────────────────────────────────────────────

    protected function configureErrorHandling(): void
    {
        ini_set('log_errors', 1);
        ini_set('error_log', STORAGE_PATH . '/logs/php_errors.log');

        if (!APP_DEBUG) {
            error_reporting(0);
            ini_set('display_errors', 0);
            return;
        }

        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        set_error_handler([$this, 'handlePhpError']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    protected function setSecurityHeaders(): void
    {
        if (headers_sent()) return;
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }

    // ── Tratamento de Exceções ────────────────────────────────────────────────

    protected function handleException(\Throwable $e): void
    {
        $httpCode = $this->resolveHttpCode($e);

        $context = [
            'type'       => get_class($e),
            'file'       => $e->getFile(),
            'line'       => $e->getLine(),
            'uri'        => $_SERVER['REQUEST_URI']     ?? 'unknown',
            'method'     => $_SERVER['REQUEST_METHOD']  ?? 'unknown',
            'ip'         => $_SERVER['REMOTE_ADDR']     ?? 'unknown',
            'memory'     => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
            'time_ms'    => round((microtime(true) - $this->startTime) * 1000, 2),
        ];

        Logger::error($e->getMessage(), $context);

        APP_DEBUG
            ? $this->renderDebugPage($e, $context, $httpCode)
            : $this->renderProductionError($httpCode);
    }

    /** Converte o tipo/código da exceção para um HTTP status code */
    protected function resolveHttpCode(\Throwable $e): int
    {
        $code = $e->getCode();
        if ($code >= 400 && $code < 600) return $code;

        $map = [
            'NotFoundException'     => 404,
            'UnauthorizedException' => 401,
            'ForbiddenException'    => 403,
            'ValidationException'   => 422,
            'CsrfException'         => 419,
        ];

        foreach ($map as $class => $status) {
            if (str_contains(get_class($e), $class)) return $status;
        }

        $msg = strtolower($e->getMessage());
        if (str_contains($msg, 'not found') || str_contains($msg, 'não encontrado')) return 404;
        if (str_contains($msg, 'unauthorized')  || str_contains($msg, 'não autorizado'))  return 401;
        if (str_contains($msg, 'forbidden')     || str_contains($msg, 'proibido'))        return 403;

        return 500;
    }

    // ── Renderização de Erros ─────────────────────────────────────────────────

    protected function renderProductionError(int $httpCode): void
    {
        while (ob_get_level()) ob_end_clean();
        http_response_code($httpCode);

        $viewFile = VIEW_PATH . "/errors/{$httpCode}.php";
        if (file_exists($viewFile)) {
            require $viewFile;
        } else {
            require VIEW_PATH . '/errors/generic.php';
        }

        exit(1);
    }

    protected function renderDebugPage(\Throwable $e, array $ctx, int $httpCode): void
    {
        while (ob_get_level()) ob_end_clean();
        http_response_code($httpCode);
        header('Content-Type: text/html; charset=utf-8');

        $data = [
            'exception'   => $e,
            'context'     => $ctx,
            'httpCode'    => $httpCode,
            'httpMessage' => $this->httpMessages[$httpCode] ?? 'Error',
            'source'      => $this->extractSourceLines($e->getFile(), $e->getLine()),
        ];

        extract($data);
        require VIEW_PATH . '/errors/debug.php';
        exit(1);
    }

    // ── Handlers de PHP errors ────────────────────────────────────────────────

    public function handlePhpError(int $errno, string $msg, string $file, int $line): bool
    {
        if (!(error_reporting() & $errno)) return false;
        throw new \ErrorException($msg, 500, $errno, $file, $line);
    }

    public function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->handleException(
                new \ErrorException($error['message'], 500, $error['type'], $error['file'], $error['line'])
            );
        }
    }

    // ── Utilidades ────────────────────────────────────────────────────────────

    protected function extractSourceLines(string $file, int $line, int $padding = 8): array
    {
        if (!is_readable($file)) return [];
        $lines  = file($file);
        $start  = max(0, $line - $padding - 1);
        $end    = min(count($lines) - 1, $line + $padding - 1);
        $result = [];
        for ($i = $start; $i <= $end; $i++) {
            $result[$i + 1] = rtrim($lines[$i]);
        }
        return $result;
    }

    protected function logDebugInfo(): void
    {
        if (!APP_DEBUG) return;
        Logger::debug('Request OK', [
            'uri'     => $_SERVER['REQUEST_URI'] ?? '/',
            'method'  => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'time_ms' => round((microtime(true) - $this->startTime) * 1000, 2),
            'mem_mb'  => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ]);
    }
}