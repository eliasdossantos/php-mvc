<?php

namespace Cli;

/**
 * Output — Saída colorida para o terminal
 * ─────────────────────────────────────────────────────────────────────────────
 * Abstrai a formatação de texto no terminal com cores ANSI.
 * Detecta automaticamente se o terminal suporta cores e desabilita
 * em ambientes sem suporte (pipes, Windows sem ANSICON).
 *
 * Tags suportadas no texto:
 *   <info>texto</info>       → verde
 *   <comment>texto</comment> → amarelo
 *   <error>texto</error>     → vermelho
 *   <dim>texto</dim>         → cinza
 */
class Output
{
    // Códigos ANSI
    private const RESET   = "\033[0m";
    private const BOLD    = "\033[1m";
    private const DIM     = "\033[2m";
    private const GREEN   = "\033[32m";
    private const YELLOW  = "\033[33m";
    private const BLUE    = "\033[34m";
    private const MAGENTA = "\033[35m";
    private const CYAN    = "\033[36m";
    private const WHITE   = "\033[97m";
    private const RED     = "\033[31m";
    private const BG_GREEN  = "\033[42m";
    private const BG_RED    = "\033[41m";
    private const BG_BLUE   = "\033[44m";
    private const BG_YELLOW = "\033[43m";

    /** Verifica suporte a cores uma única vez */
    private static ?bool $supportsColor = null;

    // ── Métodos de saída ──────────────────────────────────────────────────────

    public static function banner(): void
    {
        self::newline();
        self::writeln(self::color(self::BOLD . self::MAGENTA, ' PHP MVC Base') .
                      self::color(self::DIM, ' v' . CLI_VERSION));
        self::writeln(self::color(self::DIM, ' Framework CLI — digite "php mvc list" para ver os comandos'));
        self::newline();
    }

    public static function title(string $text): void
    {
        self::writeln(self::color(self::BOLD . self::YELLOW, " {$text}"));
        self::newline();
    }

    public static function subtitle(string $text): void
    {
        self::writeln(self::color(self::DIM, "  {$text}"));
    }

    public static function command(string $cmd, string $desc): void
    {
        $cmd  = str_pad($cmd, 32);
        $line = "    " . self::color(self::GREEN, $cmd) . self::color(self::DIM, $desc);
        self::writeln($line);
    }

    /** Mensagem de sucesso ✅ */
    public static function success(string $message): void
    {
        self::writeln("  " . self::color(self::BG_GREEN . self::WHITE, ' DONE ') .
                      "  " . self::color(self::GREEN, $message));
    }

    /** Mensagem de erro ❌ */
    public static function error(string $message): void
    {
        self::writeln("  " . self::color(self::BG_RED . self::WHITE, ' ERRO ') .
                      "  " . self::color(self::RED, $message));
    }

    /** Mensagem de aviso ⚠️ */
    public static function warn(string $message): void
    {
        self::writeln("  " . self::color(self::BG_YELLOW . self::WHITE, ' AVISO') .
                      "  " . self::color(self::YELLOW, $message));
    }

    /** Informação azul */
    public static function info(string $message): void
    {
        self::writeln("  " . self::color(self::CYAN, "→") . "  {$message}");
    }

    /** Texto cinza/apagado */
    public static function dim(string $message): void
    {
        self::writeln(self::color(self::DIM, $message));
    }

    /** Linha genérica com suporte a tags */
    public static function line(string $message): void
    {
        self::writeln("  " . self::parseTags($message));
    }

    /** Arquivo criado */
    public static function created(string $path): void
    {
        $relative = str_replace(ROOT_PATH . DIRECTORY_SEPARATOR, '', $path);
        self::writeln("  " . self::color(self::GREEN, '✓') .
                      "  " . self::color(self::DIM, 'Criado: ') .
                      self::color(self::CYAN, $relative));
    }

    /** Arquivo pulado (já existe) */
    public static function skipped(string $path): void
    {
        $relative = str_replace(ROOT_PATH . DIRECTORY_SEPARATOR, '', $path);
        self::writeln("  " . self::color(self::YELLOW, '⚠') .
                      "  " . self::color(self::DIM, 'Existe:  ') .
                      self::color(self::YELLOW, $relative) .
                      self::color(self::DIM, ' (ignorado)'));
    }

    public static function newline(): void
    {
        fwrite(STDOUT, PHP_EOL);
    }

    // ── Internos ──────────────────────────────────────────────────────────────

    private static function writeln(string $text): void
    {
        fwrite(STDOUT, $text . PHP_EOL);
    }

    private static function color(string $code, string $text): string
    {
        if (!self::supportsColor()) return $text;
        return $code . $text . self::RESET;
    }

    private static function parseTags(string $text): string
    {
        if (!self::supportsColor()) {
            return preg_replace('/<\/?(?:info|comment|error|dim)>/', '', $text);
        }

        $text = str_replace(['<info>','</info>'],       [self::GREEN,   self::RESET], $text);
        $text = str_replace(['<comment>','</comment>'], [self::YELLOW,  self::RESET], $text);
        $text = str_replace(['<error>','</error>'],     [self::RED,     self::RESET], $text);
        $text = str_replace(['<dim>','</dim>'],         [self::DIM,     self::RESET], $text);
        return $text;
    }

    private static function supportsColor(): bool
    {
        if (self::$supportsColor !== null) return self::$supportsColor;

        // Desabilitado explicitamente
        if (isset($_SERVER['NO_COLOR']) || getenv('NO_COLOR') !== false) {
            return self::$supportsColor = false;
        }

        // Windows: verifica ANSICON ou Windows Terminal
        if (DIRECTORY_SEPARATOR === '\\') {
            return self::$supportsColor = (
                getenv('ANSICON') !== false ||
                getenv('ConEmuANSI') === 'ON' ||
                getenv('TERM_PROGRAM') === 'vscode' ||
                getenv('WT_SESSION') !== false
            );
        }

        // Unix/Linux/Mac: verifica se STDOUT é um terminal
        return self::$supportsColor = function_exists('posix_isatty') && posix_isatty(STDOUT);
    }
}
