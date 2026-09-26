<?php

namespace Framework;

/**
 * View — Renderizador de Templates
 * ─────────────────────────────────────────────────────────────────────────────
 * Usado principalmente em Controller::view(), mas pode ser chamado diretamente
 * em helpers, emails, exports e qualquer lugar fora de um controller.
 *
 * Uso:
 *   View::render('components.sidebar');            // retorna string (componentes)
 *   $html = View::capture('emails.welcome', ['user' => $user]);
 *   View::make('dashboard.index', $data, 'main');    // com layout
 *   View::partial('admin.users.form_fields');    // imprime direto (estilo require)
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

    /**
     * Captura o output de uma view em uma string.
     *
     * ── Tratamento de erros
     * Se a view lançasse uma exceção no meio do require, o ob_start() aberto
     * antes dele nunca era fechado — o buffer ficava pendurado. Como capture()
     * é usado fora de requisições HTTP também (View::render() em componentes,
     * emails, exports), um erro numa view de e-mail, por exemplo, deixava
     * lixo de buffer que podia vazar pra saída de outra coisa rodando no
     * mesmo processo (um worker de fila, um script CLI de longa duração).
     * A implementação fecha o(s) buffer(s) aberto(s) por este método antes de repropagar.
     */
    public static function capture(string $view, array $data = []): string
    {
        extract($data, EXTR_SKIP);

        $bufferLevelBefore = ob_get_level();
        ob_start();

        try {
            require static::resolve($view);
        } catch (\Throwable $e) {
            while (ob_get_level() > $bufferLevelBefore) {
                ob_end_clean();
            }
            throw $e;
        }

        return ob_get_clean() ?: '';
    }

    /**
     * Renderiza view com layout (equivalente a Controller::view).
     *
     * ── Detalhes de implementação
     * Mesma proteção de capture(): a view em si roda dentro de um buffer
     * monitorado, fechado corretamente se ela lançar exceção. O require do
     * layout roda fora de qualquer ob_start() deste método (o buffer da view
     * já foi fechado antes), então não precisa da mesma proteção.
     */
    public static function make(string $view, array $data = [], string|false|null $layout = 'main'): void
    {
        static::resetSections();

        extract($data, EXTR_SKIP);

        $bufferLevelBefore = ob_get_level();
        ob_start();

        try {
            require static::resolve($view);
        } catch (\Throwable $e) {
            while (ob_get_level() > $bufferLevelBefore) {
                ob_end_clean();
            }
            throw $e;
        }

        $content = ob_get_clean() ?: '';

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
        $view = str_replace(['..', '\\'], '', $view);
        return file_exists(VIEW_PATH . '/' . str_replace('.', '/', $view) . '.php');
    }

    /**
     * ── Detalhes de implementação
     * Remove ".." antes de montar o caminho — proteção básica contra
     * directory traversal caso $view algum dia venha de um valor dinâmico
     * (ex: nome de componente montado a partir de input) em vez de sempre
     * hardcoded pelo desenvolvedor.
     */
    protected static function resolve(string $view): string
    {
        $view = str_replace(['..', '\\'], '', $view);
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

    /**
     * Finaliza a captura da section aberta mais recentemente.
     *
     * ── Detalhes de implementação
     * ob_get_clean() retorna `false` se não houver buffer ativo (ex: start()/
     * end() desbalanceados por algum bug em outro lugar do código). Antes,
     * isso guardava `false` em $sections[$name] — e section() tem tipo de
     * retorno declarado `string`, então um `false` ali vira TypeError na
     * hora de usar aquela section no layout. A implementação cai pra string vazia.
     */
    public static function end(): void
    {
        $name = array_pop(self::$sectionStack);

        if ($name === null) {
            throw new \RuntimeException('View::end() chamado sem um View::start() correspondente.');
        }

        self::$sections[$name] = ob_get_clean() ?: '';
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
