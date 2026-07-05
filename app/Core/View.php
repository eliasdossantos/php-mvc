<?php

namespace Core;

/**
 * View — Renderizador de Templates
 * ─────────────────────────────────────────────────────────────────────────────
 * Usado principalmente em Controller::view(), mas pode ser chamado diretamente
 * em helpers, emails, exports e qualquer lugar fora de um controller.
 *
 * Uso:
 *   View::render('components.sidebar');            // retorna string (componentes)
 *   $html = View::capture('emails.welcome', ['user' => $user]);
 *   View::make('dashboard.index', $data, 'app');    // com layout
 *   View::partial('admin.usuarios.form_fields');    // imprime direto (estilo require)
 *   View::partialOnce('components.datepicker_js');  // imprime só uma vez por request
 *
 * Sections (estilo Blade @section/@yield):
 *   Na view filha (chamadas dentro de tags PHP normais):
 *     View::start('styles');
 *         // <link rel="stylesheet" href="...">
 *     View::end();
 *
 *   No layout (dentro de um bloco de eco, ex: echo View::section):
 *     View::section('styles')
 */
class View
{
    /** Conteúdo já capturado de cada section, indexado por nome */
    protected static array $sections = [];

    /** Pilha de sections abertas (suporta start() aninhado, se necessário) */
    protected static array $sectionStack = [];

    /** Registro de views já impressas via partialOnce(), indexado pelo nome da view */
    protected static array $onceRendered = [];

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

    /**
     * Renderiza uma view parcial diretamente no ponto de chamada.
     *
     * Funciona como um require com suporte a dot-notation e variáveis isoladas.
     * Lança RuntimeException caso a view não exista.
     *
     * Ideal para reutilização de componentes e trechos de HTML.
     *
     * Exemplo:
     * View::partial('components.card', ['item' => $item]);
     */
    public static function partial(string $view, array $data = []): void
    {
        echo static::render($view, $data);
    }

    /**
     * Renderiza uma view parcial apenas uma vez por request.
     *
     * Funciona como require_once, evitando duplicação de conteúdo
     * (scripts, modais, componentes globais etc.).
     *
     * Se a view já tiver sido renderizada, a chamada é ignorada.
     *
     * Exemplo:
     * View::partialOnce('components.modal_confirmacao');
     */
    public static function partialOnce(string $view, array $data = []): void
    {
        if (isset(self::$onceRendered[$view])) {
            return;
        }

        self::$onceRendered[$view] = true;
        echo static::render($view, $data);
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

    /**
     * Limpa todas as sections capturadas e o registro de partialOnce().
     * Chamado automaticamente por make() no início de cada renderização
     * de página, para não vazar estado entre requests (ex: CLI, testes,
     * ou múltiplas chamadas a View::make() no mesmo processo).
     */
    public static function resetSections(): void
    {
        self::$sections = [];
        self::$sectionStack = [];
        self::$onceRendered = [];
    }
}
