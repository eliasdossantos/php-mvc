<?php

namespace Core\Api;

/**
 * ApiResponse — Envelope JSON padronizado para a API própria (/api/v1)
 * ─────────────────────────────────────────────────────────────────────────────
 * Garante que TODO endpoint da API responda no mesmo formato, em vez de cada
 * Controller inventar sua própria estrutura de JSON.
 *
 * Sucesso:
 *   {"success": true, "message": "...", "data": {...}}
 *
 * Lista paginada:
 *   {"success": true, "message": "...", "data": [...], "meta": {"current_page":1,...}}
 *
 * Erro:
 *   {"success": false, "message": "...", "errors": {...}}
 *
 * Normalmente você não chama esta classe diretamente — use os helpers
 * success()/error()/paginated() de App\Api\Controllers\ApiController, que
 * delegam para cá.
 */
class ApiResponse
{
    /** Envia uma resposta de sucesso e encerra a requisição */
    public static function success(
        mixed $data = null,
        string $message = 'Operação realizada com sucesso.',
        int $status = 200,
        array $meta = []
    ): never {
        $payload = ['success' => true, 'message' => $message, 'data' => $data];
        if ($meta) $payload['meta'] = $meta;

        static::send($payload, $status);
    }

    /** Envia uma resposta paginada (a partir do array retornado por Model::paginate()) */
    public static function paginated(
        array $paginateResult,
        string $message = 'Dados encontrados.'
    ): never {
        $meta = [
            'current_page' => $paginateResult['page']      ?? 1,
            'per_page'     => $paginateResult['per_page']  ?? 15,
            'total'        => $paginateResult['total']     ?? 0,
            'last_page'    => $paginateResult['last_page'] ?? 1,
            'from'         => $paginateResult['from']      ?? 0,
            'to'           => $paginateResult['to']        ?? 0,
        ];

        static::success($paginateResult['data'] ?? [], $message, 200, $meta);
    }

    /** Envia uma resposta de erro e encerra a requisição */
    public static function error(
        string $message,
        array $errors = [],
        int $status = 400
    ): never {
        $payload = ['success' => false, 'message' => $message];
        if ($errors) $payload['errors'] = $errors;

        static::send($payload, $status);
    }

    /**
     * Serializa e envia o payload. Mesma proteção contra falha de
     * json_encode() já usada em Core\Controller::json() — evita responder
     * 200 com corpo vazio quando o encode falha.
     */
    protected static function send(array $payload, int $status): never
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
        }

        if ($encoded === false) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao gerar resposta JSON: ' . json_last_error_msg(),
            ]);
            exit;
        }

        http_response_code($status);
        echo $encoded;
        exit;
    }
}
