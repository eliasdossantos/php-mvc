<?php

if (!function_exists('emptyDataMessage')) {
    /**
     * Retorna uma mensagem para quando não houver dados.
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
