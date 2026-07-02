<?php

namespace Core;

/**
 * View — Renderizador de Templates
 * ─────────────────────────────────────────────────────────────────────────────
 * Usado principalmente em Controller::view(), mas pode ser chamado diretamente
 * em helpers, emails, exports e qualquer lugar fora de um controller.
 *
 * Uso:
 *   View::render('components.sidebar');           // retorna string (componentes)
 *   $html = View::capture('emails.welcome', ['user' => $user]);
 *   View::make('dashboard.index', $data, 'app');   // com layout
 *
 * Sections (estilo Blade @section/@yield):
 *   Na view filha:
 *     <?php View::start('styles'); ?>
 *
<link rel="stylesheet" href="...">
 * <?php View::end(); ?>
 *
 * No layout:
 * <?= View::section('styles') ?>
 */
class View
{
    /** Conteúdo já capturado de cada section, indexado por nome */
    protected static array $sections = [];

    /** Pilha de sections abertas (suporta start() aninhado, se necessário) */
    protected static array $sectionStack = [];

// ── Renderização ──────────────────────────────────────────────────────────

    /**
     * Renderiza uma view e retorna o HTML como string.
     * Usado tanto para componentes (`<?= View::render(...) ?>`)
     * quanto como alias de capture().
     */
    public static function render(string $view, array $data = []): string
    {
        return static::capture($view, $data);
    }

    /** Captura o output de uma view em uma string */
    public static function capture(string $view, array $data = []): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require static::resolve($view);
        return ob_get_clean() ?: '';
    }

    /** Renderiza view com layout (equivalente a Controller::view) */
    public static function make(string $view, array $data = [], string|false|null $layout = 'app'): void
    {
        static::resetSections();

        extract($data, EXTR_SKIP);

        ob_start();
        require static::resolve($view);
        $content = ob_get_clean();

        if ($layout) {
            require static::resolve("layouts.{$layout}");
        } else {
            echo $content;
        }
    }

    public static function exists(string $view): bool
    {
        return file_exists(VIEW_PATH . '/' . str_replace('.', '/', $view) . '.php');
    }

    protected static function resolve(string $view): string
    {
        $path = VIEW_PATH . '/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($path)) {
            throw new \RuntimeException("View [{$view}] não encontrada em [{$path}].");
        }

        return $path;
    }

// ── Sections ──────────────────────────────────────────────────────────────

    /** Inicia a captura de uma section nomeada */
    public static function start(string $name): void
    {
        array_push(self::$sectionStack, $name);
        ob_start();
    }

    /** Finaliza a captura da section aberta mais recentemente */
    public static function end(): void
    {
        $name = array_pop(self::$sectionStack);

        if ($name === null) {
            throw new \RuntimeException('View::end() chamado sem um View::start() correspondente.');
        }

        self::$sections[$name] = ob_get_clean();
    }

    /** Retorna o conteúdo de uma section (ou $default, se não existir) */
    public static function section(string $name, string $default = ''): string
    {
        return self::$sections[$name] ?? $default;
    }

    public static function hasSection(string $name): bool
    {
        return isset(self::$sections[$name]);
    }

    /** Limpa todas as sections capturadas (chamado automaticamente por make()) */
    public static function resetSections(): void
    {
        self::$sections = [];
        self::$sectionStack = [];
    }
}
