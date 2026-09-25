<?php

namespace App\Api\Resources;

/**
 * UserResource — Formato exposto de um User pela API
 * Nunca inclui password, remember_token ou qualquer campo interno.
 */
class UserResource extends ApiResource
{
    public function toArray(): array
    {
        return [
            'id'         => (int) $this->resource->id,
            'name'       => $this->resource->name,
            'email'      => $this->resource->email,
            'role'     => $this->resource->role,
            'active'      => (bool) $this->resource->active,
            'created_at' => $this->resource->created_at ?? null,
        ];
    }
}
