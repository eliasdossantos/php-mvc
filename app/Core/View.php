<?php

namespace Core;

/**
 * View — Renderizador de Templates
 * ─────────────────────────────────────────────────────────────────────────────
 * Usado principalmente em Controller::view(), mas pode ser chamado diretamente
 * em helpers, emails, exports e qualquer lugar fora de um controller.
 *
 * Uso:
 *   View::render('components.alerts');
 *   $html = View::capture('emails.welcome', ['user' => $user]);
 *   View::make('dashboard.index', $data, 'app');  // com layout
 */
class View
{
    /** Renderiza uma view (sem layout) */
    public static function render(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        require static::resolve($view);
    }

    /** Captura o output de uma view em uma string */
    public static function capture(string $view, array $data = []): string
    {
        ob_start();
        static::render($view, $data);
        return ob_get_clean() ?: '';
    }

    /** Renderiza view com layout (equivalente a Controller::view) */
    public static function make(string $view, array $data = [], string $layout = 'app'): void
    {
        extract($data, EXTR_SKIP);

        if ($layout) {
            ob_start();
            require static::resolve($view);
            $content = ob_get_clean();
            require static::resolve("layouts.{$layout}");
        } else {
            require static::resolve($view);
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
}
