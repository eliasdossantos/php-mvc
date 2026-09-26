<?php

namespace App\Repositories;

use Framework\Repository;
use App\Models\ApiToken;

class ApiTokenRepository extends Repository
{
    protected string $modelClass = ApiToken::class;

    public function issue(int $userId, string $name = 'api', ?string $expiresAt = null): array
    {
        /** @var ApiToken $model */
        $model = $this->model();
        return $model->issue($userId, $name, $expiresAt);
    }

    public function findValidByPlainToken(string $plain): object|false
    {
        /** @var ApiToken $model */
        $model = $this->model();
        return $model->findValidByPlainToken($plain);
    }

    public function touchLastUsed(int $id): void
    {
        /** @var ApiToken $model */
        $model = $this->model();
        $model->touchLastUsed($id);
    }

    public function revoke(int $id): bool
    {
        /** @var ApiToken $model */
        $model = $this->model();
        return $model->revoke($id);
    }

    public function revokeAllForUser(int $userId): bool
    {
        /** @var ApiToken $model */
        $model = $this->model();
        return $model->revokeAllForUser($userId);
    }
}
