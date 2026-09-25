<?php

namespace App\Api\Controllers;

use App\Repositories\UserRepository;
use App\Api\Resources\UserResource;

/**
 * UserApiController — GET /api/v1/users, GET /api/v1/users/{id}
 * ─────────────────────────────────────────────────────────────────────────────
 * Exemplo de referência de recurso REST completo sobre a arquitetura da API:
 * ApiAuthMiddleware → Controller (fino) → Repository (existente, compartilhado
 * com a Web) → UserResource (nunca expõe password/tokens).
 *
 * Copie este padrão para novos recursos da API.
 */
class UserApiController extends ApiController
{
    protected UserRepository $users;

    public function __construct()
    {
        parent::__construct();
        $this->users = new UserRepository();
    }

    /** GET /api/v1/users?page=1&per_page=15 */
    public function index(): void
    {
        $page    = max(1, (int) $this->request->get('page', 1));
        $perPage = min(100, max(1, (int) $this->request->get('per_page', 15)));

        $result = $this->users->paginate($perPage, $page);
        $result['data'] = UserResource::collection($result['data']);

        $this->paginated($result);
    }

    /** GET /api/v1/users/{id} */
    public function show(int $id): void
    {
        $user = $this->users->findById($id);

        if (!$user) {
            $this->error('Usuário não encontrado.', [], 404);
        }

        $this->success(UserResource::make($user));
    }
}
