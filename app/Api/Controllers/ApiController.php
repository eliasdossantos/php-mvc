<?php

namespace App\Api\Controllers;

use Framework\Controller;
use Framework\Api\ApiResponse;
use App\Requests\FormRequest;

/**
 * ApiController — Controller base para todos os endpoints de /api/v1
 * ─────────────────────────────────────────────────────────────────────────────
 * Estende Framework\Controller (reaproveita Request, checkMethod, abort, etc.) e
 * adiciona os helpers de resposta padronizada via Framework\Api\ApiResponse.
 *
 * Controllers de API devem permanecer finos — a mesma regra do MVC web:
 * nenhuma lógica de negócio aqui, apenas orquestração de
 * Request → Service/Repository → Resource → Response.
 */
abstract class ApiController extends Controller
{
    protected function success(
        mixed $data = null,
        string $message = 'Operação realizada com sucesso.',
        int $status = 200,
        array $meta = []
    ): never {
        ApiResponse::success($data, $message, $status, $meta);
    }

    protected function error(
        string $message,
        array $errors = [],
        int $status = 400
    ): never {
        ApiResponse::error($message, $errors, $status);
    }

    /** @param array{data:array,total:int,page:int,per_page:int,last_page:int,from:int,to:int} $paginateResult */
    protected function paginated(array $paginateResult, string $message = 'Dados encontrados.'): never
    {
        ApiResponse::paginated($paginateResult, $message);
    }

    /**
     * Executa um FormRequest e retorna os dados validados.
     * Se a validação falhar, responde 422 com o mapa de erros e encerra —
     * o Controller não precisa repetir esse if em cada action.
     *
     * FormRequest::authorize() falhando já responde 403 sozinho (ver
     * App\Requests\FormRequest::resolve()), inclusive em JSON quando o
     * Content-Type/Accept é application/json — o que toda requisição de API
     * legítima já envia.
     */
    protected function validated(FormRequest $request): array
    {
        if ($request->fails()) {
            $this->error('Os dados enviados são inválidos.', $request->errors(), 422);
        }

        return $request->validated();
    }
}
