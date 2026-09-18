<?php

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
