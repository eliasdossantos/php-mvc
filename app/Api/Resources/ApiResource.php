<?php

namespace App\Api\Resources;

/**
 * ApiResource — Base para transformação de Models em JSON
 * ─────────────────────────────────────────────────────────────────────────────
 * Um Model NUNCA deve virar JSON diretamente (json_encode($model)) — isso
 * expõe qualquer coluna nova adicionada no banco automaticamente, incluindo
 * campos sensíveis (password, tokens, dados internos).
 *
 * Uma Resource declara explicitamente o que é exposto pela API.
 *
 * Como criar:
 *   class UserResource extends ApiResource {
 *       public function toArray(): array {
 *           return [
 *               'id'    => $this->resource->id,
 *               'name'  => $this->resource->name,
 *               'email' => $this->resource->email,
 *           ];
 *       }
 *   }
 *
 * Uso no Controller:
 *   return UserResource::make($user);           // um registro
 *   return UserResource::collection($users);     // vários
 */
abstract class ApiResource
{
    public function __construct(protected object $resource)
    {
    }

    /** Define os campos expostos pela API para este recurso */
    abstract public function toArray(): array;

    /** Transforma um único registro */
    public static function make(object $resource): array
    {
        return (new static($resource))->toArray();
    }

    /** Transforma uma coleção de registros */
    public static function collection(array $resources): array
    {
        return array_map(static fn(object $r) => (new static($r))->toArray(), $resources);
    }
}
