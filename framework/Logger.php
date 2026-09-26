<?php

namespace Framework;

/**
 * Logger — Sistema de Logs
 * ─────────────────────────────────────────────────────────────────────────────
 * Compatível com PSR-3 (mesmos nomes de método).
 * Grava em arquivo diário + exibe colorido no terminal (CLI).
 *
 * Níveis: DEBUG → INFO → WARNING → ERROR → CRITICAL
 *
 * Uso:
 *   Logger::info('Usuário logado', ['user_id' => 42]);
 *   Logger::error('Falha na conexão', ['host' => 'db01']);
 *   Logger::debug('Query executada', ['sql' => $sql, 'time_ms' => 12]);
 */
class Logger
{
    const DEBUG    = 'DEBUG';
    const INFO     = 'INFO';
    const WARNING  = 'WARNING';
    const ERROR    = 'ERROR';
    const CRITICAL = 'CRITICAL';

    /** Nível mínimo de log (ignora abaixo deste) */
    protected static string $minLevel = self::DEBUG;

    protected static array $levelOrder = [
        self::DEBUG    => 0,
        self::INFO     => 1,
        self::WARNING  => 2,
        self::ERROR    => 3,
        self::CRITICAL => 4,
    ];

    protected static array $colors = [
        self::DEBUG    => "\033[36m",  // Ciano
        self::INFO     => "\033[32m",  // Verde
        self::WARNING  => "\033[33m",  // Amarelo
        self::ERROR    => "\033[31m",  // Vermelho
        self::CRITICAL => "\033[35m",  // Magenta
        'RESET'        => "\033[0m",
    ];

    protected static array $icons = [
        self::DEBUG    => '🔍',
        self::INFO     => 'ℹ️ ',
        self::WARNING  => '⚠️ ',
        self::ERROR    => '❌',
        self::CRITICAL => '💀',
    ];

    // ── Interface Pública ─────────────────────────────────────────────────────

    public static function debug(string $msg, array $ctx = []): void
    {
        static::log(self::DEBUG,    $msg, $ctx);
    }
    public static function info(string $msg, array $ctx = []): void
    {
        static::log(self::INFO,     $msg, $ctx);
    }
    public static function warning(string $msg, array $ctx = []): void
    {
        static::log(self::WARNING,  $msg, $ctx);
    }
    public static function error(string $msg, array $ctx = []): void
    {
        static::log(self::ERROR,    $msg, $ctx);
    }
    public static function critical(string $msg, array $ctx = []): void
    {
        static::log(self::CRITICAL, $msg, $ctx);
    }

    /** Registra telemetria HTTP no arquivo diário separado de requests. */
    public static function request(string $level, string $message, array $context = []): void
    {
        $order    = static::$levelOrder[$level] ?? PHP_INT_MAX;
        $minOrder = static::$levelOrder[static::$minLevel] ?? 0;
        if ($order < $minOrder) return;

        static::writeToFile($level, $message, $context, 'requests');
    }

    // ── Core ──────────────────────────────────────────────────────────────────

    /**
     * ── Detalhes de implementação
     * Um $level desconhecido (ex: chamada direta Logger::log('warn', ...)
     * com typo) caía no `?? 0`, era tratado como DEBUG, e se minLevel fosse
     * mais alto que DEBUG, a mensagem era descartada SEM nenhum registro —
     * o pior cenário possível pra um logger: perder uma mensagem sem deixar
     * rastro. A implementação um nível desconhecido nunca é filtrado (assume prioridade
     * máxima) — prefere logar a mais a arriscar perder algo importante.
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        $order    = static::$levelOrder[$level] ?? PHP_INT_MAX;
        $minOrder = static::$levelOrder[static::$minLevel] ?? 0;
        if ($order < $minOrder) return;

        // Grava em arquivo
        static::writeToFile($level, $message, $context, 'app');

        // Exibe no terminal se CLI
        if (php_sapi_name() === 'cli') {
            static::writeToTerminal($level, $message, $context);
        }
    }

    /**
     * Normaliza um valor de contexto pra algo seguro de logar.
     *
     * ── Tratamento de erros
     * writeToFile() já tratava \Throwable especificamente, mas writeToTerminal()
     * não — fazia (string)$v direto. Isso funciona por acidente com
     * Exception/Error (têm __toString nativo, mas despeja o stack trace
     * inteiro no terminal) e QUEBRA COM FATAL ERROR pra qualquer outro objeto
     * sem __toString (ex: stdClass, ou qualquer objeto de domínio passado por
     * engano no contexto) — "Object of class X could not be converted to
     * string". A implementação os dois caminhos (arquivo e terminal) usam a mesma
     * normalização.
     */
    protected static function normalizeContextValue(mixed $v): mixed
    {
        if ($v instanceof \Throwable) {
            return $v->getMessage() . ' in ' . $v->getFile() . ':' . $v->getLine();
        }
        if (is_object($v) && !method_exists($v, '__toString')) {
            return '[objeto ' . get_class($v) . ']';
        }
        if (is_resource($v)) {
            return '[resource]';
        }
        return $v;
    }

    protected static function writeToFile(
        string $level,
        string $message,
        array $context,
        string $filePrefix = 'app'
    ): void
    {
        $directories = [
            'app'      => 'erros_aplicacao',
            'requests' => 'requisicao',
        ];
        $subdirectory = $directories[$filePrefix] ?? 'erros_aplicacao';
        $logDir  = STORAGE_PATH . '/logs/' . $subdirectory;
        $logFile = $logDir . '/' . $filePrefix . '-' . date('Y-m-d') . '.log';

        // ── Tratamento de erros
        // mkdir() sem checagem + file_put_contents() com @ (suprime warning):
        // se o diretório de logs não pudesse ser criado (permissão, disco
        // cheio), a mensagem desaparecia SEM NENHUM registro em lugar nenhum —
        // justamente quando um problema de disco seria a informação mais
        // importante de se ter. A implementação cai pro error_log() nativo do PHP como
        // último recurso, que vai pro log do servidor web/PHP-FPM/CLI e quase
        // sempre existe independente da configuração da aplicação.
        if (!is_dir($logDir) && !@mkdir($logDir, 0755, true) && !is_dir($logDir)) {
            error_log("[Logger] não foi possível criar {$logDir} — mensagem original: [{$level}] {$message}");
            return;
        }

        $date = date('Y-m-d H:i:s');
        $line = "[{$date}] [{$level}] {$message}";

        if ($context) {
            $ctx = array_map([static::class, 'normalizeContextValue'], $context);
            $encoded = json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            // json_encode pode falhar (false) com dados não serializáveis
            // (ex: NAN, referência circular) — esse caso é tratado como falha de serialização
            // vazia de `false` concatenada, apagando o contexto em silêncio.
            $line .= ' ' . ($encoded !== false ? $encoded : '[contexto não serializável: ' . json_last_error_msg() . ']');
        }

        $line .= PHP_EOL;

        $written = @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        if ($written === false) {
            error_log("[Logger] falha ao escrever em {$logFile} — mensagem original: [{$level}] {$message}");
        }
    }

    protected static function writeToTerminal(string $level, string $message, array $context): void
    {
        $color = static::$colors[$level] ?? '';
        $reset = static::$colors['RESET'];
        $icon  = static::$icons[$level] ?? '📝';

        echo "{$color}{$icon} [" . date('H:i:s') . "] [{$level}] {$message}{$reset}" . PHP_EOL;

        foreach ($context as $k => $v) {
            $v   = static::normalizeContextValue($v);
            $val = is_array($v) ? json_encode($v) : (string) $v;
            echo "  \033[90m└─ {$k}: {$val}{$reset}" . PHP_EOL;
        }
    }

    // ── Configuração ─────────────────────────────────────────────────────────

    /**
     * ── Tratamento de erros
     * Um nível inválido (typo, ex: "WARNNIG") era aceito sem checagem. Como
     * log() usa `$levelOrder[static::$minLevel] ?? 0`, um minLevel inexistente
     * silenciosamente virava ordem 0 (equivalente a DEBUG) — ou seja, chamar
     * setMinLevel('WARNNIG') achando que ia silenciar logs abaixo de WARNING
     * na verdade fazia o OPOSTO: liberava geral, incluindo DEBUG. Agora
     * ignora valores desconhecidos (mantém o nível anterior) e avisa via
     * error_log em vez de mudar o comportamento de log da aplicação inteira
     * sem ninguém perceber.
     */
    public static function setMinLevel(string $level): void
    {
        $level = strtoupper($level);

        if (!isset(static::$levelOrder[$level])) {
            error_log("[Logger] nível de log desconhecido: \"{$level}\". Mantendo o nível atual (" . static::$minLevel . ').');
            return;
        }

        static::$minLevel = $level;
    }
}
