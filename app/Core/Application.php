<?php

namespace Core;

use App\Middlewares\SecurityHeadersMiddleware;

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

        // CSP, Permissions-Policy e HSTS — aplicado globalmente, não só em
        // rotas específicas, para cobrir também as páginas de autenticação.
        (new SecurityHeadersMiddleware())->handle($this->request);

        // Sempre roda, mesmo com exit()
        register_shutdown_function([$this, 'logRequest']);
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

            Router::setInstance($this->router);

            require ROUTES_PATH . '/web.php'; // registra as rotas web

            // Carrega routes/api.php e quaisquer versões futuras (routes/api_v2.php,
            // etc.) automaticamente — adicionar uma nova versão da API não exige
            // tocar nesta classe, só criar o arquivo de rotas correspondente.
            foreach (glob(ROUTES_PATH . '/api*.php') ?: [] as $apiRoutesFile) {
                require $apiRoutesFile;
            }

            $this->router->dispatch();
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    /** APP_DEBUG lido com segurança — evita Fatal Error se a constante ainda não existir */
    protected function isDebug(): bool
    {
        return defined('APP_DEBUG') && APP_DEBUG;
    }

    // ── Configuração ─────────────────────────────────────────────────────────

    /**
     * ── MELHORIA #1 (corrige bug real e grave) ────────────────────────────────
     * register_shutdown_function([$this, 'handleShutdown']) estava dentro do
     * bloco `if (APP_DEBUG)` — ou seja, só era registrado em desenvolvimento.
     * handleShutdown() é quem intercepta erros FATAIS do PHP (E_ERROR, E_PARSE
     * etc — os que não são capturáveis por try/catch, tipo chamar um método
     * num null) e os transforma numa página de erro tratada.
     *
     * Em produção, isso nunca era registrado: um erro fatal de verdade não
     * passava pelo handleException() de jeito nenhum. Como display_errors
     * fica desligado em produção, o resultado era uma tela BRANCA — sem log
     * bonito, sem página de erro, sem nada — exatamente o cenário que esta
     * classe inteira existe para evitar. Agora handleShutdown() é registrado
     * sempre, incondicionalmente; só o modo verboso (display_errors,
     * set_error_handler pra warnings/notices) continua exclusivo do debug.
     *
     * Também passei a garantir que storage/logs exista antes de apontar
     * ini_set('error_log', ...) pra lá — sem isso, se o diretório não
     * existisse, os próprios erros do PHP (fora do fluxo desta classe)
     * falhavam silenciosamente ao serem gravados.
     */
    protected function configureErrorHandling(): void
    {
        ini_set('log_errors', 1);

        $logDir = STORAGE_PATH . '/logs/erros_nativos';
        if (is_dir($logDir) || @mkdir($logDir, 0755, true) || is_dir($logDir)) {
            ini_set('error_log', $logDir . '/php_errors.log');
        }

        register_shutdown_function([$this, 'handleShutdown']);

        if (!$this->isDebug()) {
            error_reporting(0);
            ini_set('display_errors', 0);
            return;
        }

        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        set_error_handler([$this, 'handlePhpError']);
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

    /**
     * ── MELHORIA #2 ──────────────────────────────────────────────────────────
     * Esta é a última linha de defesa da aplicação — chamada direto do catch
     * em run(). Se ELA MESMA lançasse uma exceção (ex: um bug futuro nesta
     * classe, ou a própria view de erro falhando — ver MELHORIA #3), nada
     * mais captura isso, e o usuário veria o erro cru do PHP ou tela branca.
     * Agora tem seu próprio try/catch como rede de segurança final: garante
     * uma resposta mínima em vez de deixar qualquer exceção escapar sem
     * controle nenhum desta classe.
     */
    protected function handleException(\Throwable $e): void
    {
        try {
            $httpCode = $this->resolveHttpCode($e);

            $context = [
                'type'    => get_class($e),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'uri'     => $_SERVER['REQUEST_URI']    ?? 'unknown',
                'method'  => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
                'ip'      => $_SERVER['REMOTE_ADDR']    ?? 'unknown',
                'memory'  => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
                'time_ms' => round((microtime(true) - $this->startTime) * 1000, 2),
            ];

            Logger::error($e->getMessage(), $context);

            // ── MELHORIA #4 (corrige bug real) ────────────────────────────────
            // Removida a chamada explícita a logRequest($httpCode) que existia
            // aqui. Dois problemas nela: (1) logRequest() não aceita nenhum
            // parâmetro — o $httpCode passado era silenciosamente ignorado;
            // (2) essa chamada rodava ANTES de http_response_code($httpCode)
            // ser de fato definido (isso só acontece dentro de
            // renderProductionError()/renderDebugPage(), logo abaixo), então
            // o log de requisição registrava o status ERRADO (o anterior à
            // resposta de erro, tipicamente 200). E como register_shutdown_
            // function([$this, 'logRequest']) já roda automaticamente no fim
            // do script — inclusive depois do exit(1) chamado pelas funções
            // de render — cada erro gerava DUAS linhas no log de requisições:
            // uma com o status errado (esta chamada) e outra com o status
            // certo (a do shutdown). A do shutdown sozinha já é suficiente e
            // sempre correta, porque roda depois do http_response_code() real.
            $this->isDebug()
                ? $this->renderDebugPage($e, $context, $httpCode)
                : $this->renderProductionError($httpCode);
        } catch (\Throwable $inner) {
            while (ob_get_level()) { @ob_end_clean(); }
            if (!headers_sent()) {
                http_response_code(500);
            }
            echo '<h1>500 — Erro interno</h1>';
            exit(1);
        }
    }

    /** Converte o tipo/código da exceção para um HTTP status code */
    protected function resolveHttpCode(\Throwable $e): int
    {
        $code = $e->getCode();

        if (is_numeric($code)) {
            $code = (int) $code;

            if ($code >= 400 && $code < 600) {
                return $code;
            }
        }

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

    /**
     * ── MELHORIA #3 ──────────────────────────────────────────────────────────
     * Se a própria view de erro (errors/{code}.php ou errors/generic.php)
     * tivesse um bug, a exceção subia sem controle — durante o tratamento de
     * erro, que é o pior momento possível pra isso acontecer. Agora cai pra
     * um HTML mínimo se a view falhar, em vez de propagar.
     */
    protected function renderProductionError(int $httpCode): void
    {
        while (ob_get_level()) ob_end_clean();
        http_response_code($httpCode);

        try {
            $viewFile = VIEW_PATH . "/errors/{$httpCode}.php";
            if (file_exists($viewFile)) {
                require $viewFile;
            } else {
                require VIEW_PATH . '/errors/generic.php';
            }
        } catch (\Throwable $e) {
            Logger::critical('Falha ao renderizar página de erro de produção', [
                'message' => $e->getMessage(),
            ]);
            echo '<h1>Erro interno</h1>';
        }

        exit(1);
    }

    protected function renderDebugPage(\Throwable $e, array $ctx, int $httpCode): void
    {
        while (ob_get_level()) ob_end_clean();
        http_response_code($httpCode);
        header('Content-Type: text/html; charset=utf-8');

        try {
            $data = [
                'exception'   => $e,
                'context'     => $ctx,
                'httpCode'    => $httpCode,
                'httpMessage' => $this->httpMessages[$httpCode] ?? 'Error',
                'source'      => $this->extractSourceLines($e->getFile(), $e->getLine()),
                'trace'       => $this->buildEnrichedTrace($e),
            ];

            extract($data);
            require VIEW_PATH . '/errors/debug.php';
        } catch (\Throwable $renderError) {
            // A própria página de debug falhando não pode virar uma segunda
            // exceção sem tratamento — cai pro essencial: pelo menos mostra
            // a mensagem original do erro que estava sendo debugado.
            echo '<h1>Erro ao renderizar página de debug</h1>';
            echo '<p><strong>Exceção original:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<p><strong>Falha ao renderizar debug:</strong> ' . htmlspecialchars($renderError->getMessage()) . '</p>';
        }

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
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            $this->handleException(
                new \ErrorException($error['message'], 500, $error['type'], $error['file'], $error['line'])
            );
        }
    }

    // ── Utilidades ────────────────────────────────────────────────────────────

    /**
     * ── MELHORIA #5 ──────────────────────────────────────────────────────────
     * file() pode retornar false (arquivo sumiu entre o is_readable() e a
     * leitura, permissão mudou, etc). Antes isso ia direto pro count()/loop
     * seguinte, gerando warning e comportamento estranho. Agora trata como
     * "sem código-fonte disponível" em vez de propagar o erro.
     */
    protected function extractSourceLines(string $file, int $line, int $padding = 8): array
    {
        if (!is_readable($file)) return [];

        $lines = @file($file);
        if ($lines === false) return [];

        $start  = max(0, $line - $padding - 1);
        $end    = min(count($lines) - 1, $line + $padding - 1);
        $result = [];
        for ($i = $start; $i <= $end; $i++) {
            $result[$i + 1] = rtrim($lines[$i]);
        }
        return $result;
    }

    /**
     * Enriquece cada frame do stack trace com o próprio trecho de código,
     * igual ao card principal — permite expandir qualquer frame na view
     * e ver o contexto daquela chamada, estilo Laravel/Ignition.
     */
    protected function buildEnrichedTrace(\Throwable $e): array
    {
        $frames = [];

        foreach ($e->getTrace() as $i => $frame) {
            $file = $frame['file'] ?? null;
            $line = $frame['line'] ?? null;

            $frames[] = [
                'index'    => $i,
                'file'     => $file,
                'line'     => $line,
                'function' => ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? ''),
                'source'   => ($file && $line) ? $this->extractSourceLines($file, $line, 4) : [],
            ];
        }

        return $frames;
    }

    // ── Log de Requisições ────────────────────────────────────────────────────

    /**
     * Registra a requisição atual em storage/logs/requests-YYYY-MM-DD.log
     * Roda em toda requisição, independente de APP_DEBUG (via
     * register_shutdown_function no construtor — inclusive depois de exit()).
     *
     * ── MELHORIA #6 ──────────────────────────────────────────────────────────
     * mkdir() sem checagem — se falhasse, o error_log() abaixo (que também já
     * falha silenciosamente pra path inválido) resultava em log de requisição
     * perdido sem nenhum aviso. Segue o mesmo padrão já aplicado no restante
     * do projeto (Logger, Session): tenta, e se não der, não trava a resposta
     * por causa disso — é só um log a menos, não motivo pra derrubar a request.
     */
    public function logRequest(): void
    {
        $statusCode = http_response_code() ?: 200;
        $level   = $statusCode >= 400 ? Logger::ERROR : Logger::DEBUG;
        $message = $statusCode >= 400 ? 'Request FAILED' : 'Request OK';
        Logger::request($level, $message, [
            'uri'     => $_SERVER['REQUEST_URI'] ?? '/',
            'method'  => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'status'  => $statusCode,
            'ip'      => $this->request->ip(),
            'time_ms' => round((microtime(true) - $this->startTime) * 1000, 2),
            'mem_mb'  => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ]);
    }
}
