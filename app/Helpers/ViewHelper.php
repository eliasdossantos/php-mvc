<?php

/**
 * ViewHelper — Funções globais de apoio pra views
 * ─────────────────────────────────────────────────────────────────────────────
 * Helpers genéricos, reutilizáveis em qualquer projeto PHP MVC — nada aqui
 * é específico de um domínio (loja, delivery, etc). Todos seguem o mesmo
 * padrão: guardados com function_exists() e escapando saída HTML por padrão.
 */

// ── Mensagens e validação ──────────────────────────────────────────────────

if (!function_exists('emptyDataMessage')) {
    /**
     * Retorna uma mensagem formatada para ser exibida quando não houver dados.
     *
     * A mensagem é escapada com htmlspecialchars para evitar a
     * interpretação de HTML e possíveis problemas de XSS.
     *
     * @param string $message Mensagem que será exibida.
     * @return string HTML contendo a mensagem formatada.
     */
    function emptyDataMessage(
        string $message = 'Não há dados para serem exibidos.'
    ): string {
        return sprintf(
            '<span class="text-danger">%s</span>',
            htmlspecialchars(
                $message,
                ENT_QUOTES,
                'UTF-8'
            )
        );
    }
}

if (!function_exists('inputClass')) {
    /**
     * Retorna as classes CSS de um campo de formulário.
     *
     * Adiciona automaticamente a classe "is-invalid" quando
     * o campo possui um erro de validação.
     *
     * @param string $field Nome do campo que será verificado.
     * @param string $class Classes CSS adicionais do campo.
     * @return string Classes CSS do elemento.
     */
    function inputClass(
        string $field,
        string $class = 'form-control'
    ): string {
        return $class . (hasError($field) ? ' is-invalid' : '');
    }
}

if (!function_exists('erroInput')) {
    /**
     * Retorna a mensagem de erro de validação de um campo.
     *
     * Caso o campo não possua erro, retorna uma string vazia.
     * Quando houver erro, retorna o HTML utilizado pelo Bootstrap
     * para exibir a mensagem de validação.
     *
     * @param string $field Nome do campo que será verificado.
     * @param string $class Classes CSS adicionais para a mensagem de erro.
     * @return string HTML da mensagem de erro ou string vazia.
     */
    function erroInput(
        string $field,
        string $class = ''
    ): string {
        if (!hasError($field)) {
            return '';
        }

        return sprintf(
            '<div class="invalid-feedback %s">%s</div>',
            $class,
            e(error($field))
        );
    }
}

// ── Estado de formulário (selected / checked / disabled) ────────────────────

if (!function_exists('selected')) {
    /**
     * Retorna o atributo `selected` para uma <option>, se o valor bater com
     * o valor atual. Comparação frouxa (==) por padrão — útil pra comparar
     * '1' vindo do banco com 1 inteiro do PHP; passe $strict=true se
     * precisar de comparação exata (===).
     *
     * Uso: <option value="1" <?= selected($categoriaAtual, 1) ?>>Pizzas</option>
     */
    function selected(mixed $value, mixed $current, bool $strict = false): string
    {
        $matches = $strict ? $value === $current : $value == $current;
        return $matches ? 'selected' : '';
    }
}

if (!function_exists('checked')) {
    /**
     * Retorna o atributo `checked` para um checkbox/radio, se o valor bater
     * com o valor atual. Mesma regra de comparação de selected().
     *
     * Uso: <input type="checkbox" value="1" <?= checked($ativo, 1) ?>>
     */
    function checked(mixed $value, mixed $current, bool $strict = false): string
    {
        $matches = $strict ? $value === $current : $value == $current;
        return $matches ? 'checked' : '';
    }
}

if (!function_exists('disabledIf')) {
    /**
     * Retorna o atributo `disabled` se a condição for verdadeira.
     * Uso: <button <?= disabledIf($estoqueZerado) ?>>Adicionar</button>
     */
    function disabledIf(mixed $condition): string
    {
        return $condition ? 'disabled' : '';
    }
}

if (!function_exists('readonlyIf')) {
    /** Igual disabledIf(), mas com o atributo `readonly`. */
    function readonlyIf(mixed $condition): string
    {
        return $condition ? 'readonly' : '';
    }
}

if (!function_exists('oldOr')) {
    /**
     * Valor pra pré-preencher um campo: usa o old input (se o formulário foi
     * resubmetido após um erro de validação), senão cai pro valor atual do
     * registro — útil em formulários de edição, onde o campo já vem
     * preenchido com o dado salvo. Diferente de old($campo, $default), que
     * só conhece um default estático, não o valor de um registro existente.
     *
     * Uso: <input value="<?= e(oldOr('nome', $produto->nome ?? '')) ?>">
     */
    function oldOr(string $field, mixed $fallback = null): mixed
    {
        $temOldInput = class_exists(\Core\Session::class)
            && \Core\Session::has('_old_input')
            && array_key_exists($field, (array) \Core\Session::get('_old_input', []));

        return $temOldInput ? old($field) : $fallback;
    }
}

if (!function_exists('oldSelected')) {
    /**
     * Combina oldOr() com selected() — pré-seleciona a <option> certa tanto
     * depois de um erro de validação quanto num formulário de edição.
     * Uso: <option value="1" <?= oldSelected('categoria_id', 1, $produto->categoria_id ?? null) ?>>
     */
    function oldSelected(string $field, mixed $value, mixed $fallback = null): string
    {
        return selected($value, oldOr($field, $fallback));
    }
}

if (!function_exists('oldChecked')) {
    /** Combina oldOr() com checked() — mesma ideia de oldSelected() pra checkbox/radio. */
    function oldChecked(string $field, mixed $value, mixed $fallback = null): string
    {
        return checked($value, oldOr($field, $fallback));
    }
}

// ── Classes CSS condicionais ─────────────────────────────────────────────────

if (!function_exists('classNames')) {
    /**
     * Monta uma lista de classes CSS a partir de um array condicional —
     * mesma ideia do "classnames"/"clsx" do mundo JS. Chaves com valor
     * truthy entram na lista; itens sem chave (índice numérico) são classes
     * sempre presentes.
     *
     * Uso:
     *   <div class="<?= classNames(['card', 'card--ativo' => $ativo, 'card--erro' => hasError('nome')]) ?>">
     */
    function classNames(array $classes): string
    {
        $result = [];
        foreach ($classes as $class => $condition) {
            if (is_int($class)) {
                $result[] = $condition; // entrada sem condição — sempre incluída
            } elseif ($condition) {
                $result[] = $class;
            }
        }
        return implode(' ', array_filter($result, fn($c) => $c !== '' && $c !== null));
    }
}

if (!function_exists('attr')) {
    /**
     * Renderiza um atributo HTML só se o valor não for vazio/null/false —
     * evita atributo="" solto no HTML quando o dado não existe.
     * Uso: <input <?= attr('placeholder', $produto->nome ?? null) ?>>
     */
    function attr(string $name, mixed $value): string
    {
        if ($value === null || $value === false || $value === '') {
            return '';
        }
        return $name . '="' . e((string) $value) . '"';
    }
}

// ── Paginação ─────────────────────────────────────────────────────────────

if (!function_exists('paginationLinks')) {
    /**
     * Renderiza uma navegação de paginação (Bootstrap) a partir do array
     * retornado por Model::paginate() / Repository::paginate(), com janela
     * de páginas próximas + reticências pra não listar centenas de páginas.
     *
     * @param array  $pagination ['page' => int, 'last_page' => int, ...]
     * @param string $baseUrl    URL base sem a query string de página (ex: url('produtos'))
     * @param string $pageParam  Nome do parâmetro de página na query string
     * @param int    $window     Quantas páginas mostrar de cada lado da atual
     */
    function paginationLinks(array $pagination, string $baseUrl, string $pageParam = 'page', int $window = 2): string
    {
        $page     = max(1, (int) ($pagination['page'] ?? 1));
        $lastPage = max(1, (int) ($pagination['last_page'] ?? 1));

        if ($lastPage <= 1) return '';

        $urlFor = static function (int $p) use ($baseUrl, $pageParam): string {
            $sep = str_contains($baseUrl, '?') ? '&' : '?';
            return $baseUrl . $sep . $pageParam . '=' . $p;
        };

        $link = static function (int $p, string $label, bool $disabled = false, bool $active = false) use ($urlFor): string {
            $classes = classNames(['page-item', 'disabled' => $disabled, 'active' => $active]);
            $href    = $disabled ? '#' : e($urlFor($p));
            return "<li class=\"{$classes}\"><a class=\"page-link\" href=\"{$href}\">{$label}</a></li>";
        };

        $ellipsis = '<li class="page-item disabled"><span class="page-link">…</span></li>';

        $html  = '<nav aria-label="Paginação"><ul class="pagination">';
        $html .= $link(max(1, $page - 1), '&laquo;', $page <= 1);

        $start = max(1, $page - $window);
        $end   = min($lastPage, $page + $window);

        if ($start > 1) {
            $html .= $link(1, '1');
            if ($start > 2) $html .= $ellipsis;
        }

        for ($p = $start; $p <= $end; $p++) {
            $html .= $link($p, (string) $p, false, $p === $page);
        }

        if ($end < $lastPage) {
            if ($end < $lastPage - 1) $html .= $ellipsis;
            $html .= $link($lastPage, (string) $lastPage);
        }

        $html .= $link(min($lastPage, $page + 1), '&raquo;', $page >= $lastPage);
        $html .= '</ul></nav>';

        return $html;
    }
}

// ── Formatação ────────────────────────────────────────────────────────────

if (!function_exists('money')) {
    /**
     * Formata um valor numérico como moeda brasileira (R$ 1.234,56).
     * Espera um valor já numérico — se vier de input do usuário, valide/
     * converta antes de chamar (não é o papel de um helper de exibição).
     */
    function money(float|int|string $value, string $prefix = 'R$ '): string
    {
        return $prefix . number_format((float) $value, 2, ',', '.');
    }
}

if (!function_exists('pluralize')) {
    /**
     * Escolhe a forma singular ou plural com base na contagem. Não é um
     * pluralizador linguístico completo — só resolve o caso comum de exibir
     * "1 item" vs "3 itens" sem repetir a lógica em toda view.
     *
     * Uso: pluralize(3, 'item', 'itens')        // "itens"
     *      pluralize(1, 'item', 'itens', true)  // "1 item"
     */
    function pluralize(int $count, string $singular, string $plural, bool $withNumber = false): string
    {
        $word = $count === 1 ? $singular : $plural;
        return $withNumber ? "{$count} {$word}" : $word;
    }
}

// ── Avatares e imagens ────────────────────────────────────────────────────

if (!function_exists('initials')) {
    /**
     * Extrai as iniciais de um nome, pra usar em avatares placeholder.
     * "João da Silva" -> "JS" (primeira + última palavra, ignorando
     * conectores curtos como "da"/"de"/"dos"/"e").
     */
    function initials(string $name, int $max = 2): string
    {
        $ignore = ['da', 'de', 'do', 'das', 'dos', 'e'];
        $parts  = array_values(array_filter(
            preg_split('/\s+/', trim($name)) ?: [],
            fn($p) => $p !== '' && !in_array(mb_strtolower($p), $ignore, true)
        ));

        if (empty($parts)) return '';

        $chosen  = $max >= count($parts) ? $parts : [$parts[0], $parts[count($parts) - 1]];
        $letters = array_map(fn($p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice($chosen, 0, $max));

        return implode('', $letters);
    }
}

if (!function_exists('imageOr')) {
    /**
     * Retorna a URL do asset se o arquivo existir fisicamente em public/,
     * senão retorna a URL de um fallback — evita <img> quebrada quando o
     * caminho salvo no banco não corresponde mais a um arquivo real (ex:
     * imagem apagada manualmente do disco). Se a constante PUBLIC_PATH não
     * estiver definida no projeto, sempre cai pro fallback (degrada de
     * forma segura em vez de quebrar).
     *
     * Uso: <img src="<?= imageOr($produto->imagem, 'img/placeholder.png') ?>">
     */
    function imageOr(?string $path, string $fallback): string
    {
        if (!empty($path) && defined('PUBLIC_PATH') && file_exists(PUBLIC_PATH . '/' . ltrim($path, '/'))) {
            return asset($path);
        }
        return asset($fallback);
    }
}
