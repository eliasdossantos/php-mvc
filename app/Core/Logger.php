<?php

namespace Core;

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

    public static function debug(string $msg, array $ctx = []): void    { static::log(self::DEBUG,    $msg, $ctx); }
    public static function info(string $msg, array $ctx = []): void     { static::log(self::INFO,     $msg, $ctx); }
    public static function warning(string $msg, array $ctx = []): void  { static::log(self::WARNING,  $msg, $ctx); }
    public static function error(string $msg, array $ctx = []): void    { static::log(self::ERROR,    $msg, $ctx); }
    public static function critical(string $msg, array $ctx = []): void { static::log(self::CRITICAL, $msg, $ctx); }

    // ── Core ──────────────────────────────────────────────────────────────────

    public static function log(string $level, string $message, array $context = []): void
    {
        if ((static::$levelOrder[$level] ?? 0) < (static::$levelOrder[static::$minLevel] ?? 0)) return;

        // Grava em arquivo
        static::writeToFile($level, $message, $context);

        // Exibe no terminal se CLI
        if (php_sapi_name() === 'cli') {
            static::writeToTerminal($level, $message, $context);
        }
    }

    protected static function writeToFile(string $level, string $message, array $context): void
    {
        $logDir  = STORAGE_PATH . '/logs';
        $logFile = $logDir . '/app-' . date('Y-m-d') . '.log';

        if (!is_dir($logDir)) mkdir($logDir, 0755, true);

        $date  = date('Y-m-d H:i:s');
        $icon  = static::$icons[$level] ?? '📝';
        $line  = "[{$date}] [{$level}] {$message}";

        if ($context) {
            // Trata \Throwable no contexto
            $ctx = [];
            foreach ($context as $k => $v) {
                $ctx[$k] = ($v instanceof \Throwable)
                    ? $v->getMessage() . ' in ' . $v->getFile() . ':' . $v->getLine()
                    : $v;
            }
            $line .= ' ' . json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $line .= PHP_EOL;
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }

    protected static function writeToTerminal(string $level, string $message, array $context): void
    {
        $color = static::$colors[$level] ?? '';
        $reset = static::$colors['RESET'];
        $icon  = static::$icons[$level] ?? '📝';

        echo "{$color}{$icon} [" . date('H:i:s') . "] [{$level}] {$message}{$reset}" . PHP_EOL;

        foreach ($context as $k => $v) {
            $val = is_array($v) ? json_encode($v) : (string)$v;
            echo "  \033[90m└─ {$k}: {$val}{$reset}" . PHP_EOL;
        }
    }

    // ── Configuração ─────────────────────────────────────────────────────────

    public static function setMinLevel(string $level): void
    {
        static::$minLevel = strtoupper($level);
    }
}
