<?php

namespace Core;

/**
 * Service Base
 * ─────────────────────────────────────────────────────────────────────────────
 * Camada de lógica de negócio. Services orquestram operações complexas
 * usando Repositories, validações e outros serviços.
 *
 * Controllers são finos: apenas recebem request, delegam para Service,
 * retornam response.
 *
 * Services NÃO:
 *  - Acessam diretamente $_POST, $_GET, $_SESSION
 *  - Fazem redirect ou output HTML
 *  - Conhecem o protocolo HTTP
 *
 * Como criar:
 *   class UserService extends Service {
 *       private UserRepository $users;
 *
 *       public function __construct() {
 *           $this->users = new UserRepository();
 *       }
 *
 *       public function register(array $data): array {
 *           // validação + criação + envio de email
 *           return ['success' => true, 'user_id' => $id];
 *       }
 *   }
 */
abstract class Service
{
    /**
     * Executa callback dentro de uma transação de banco de dados.
     * Faz commit se OK, rollback se exceção.
     */
    protected function transaction(callable $fn): mixed
    {
        return Database::getInstance()->transaction($fn);
    }

    /**
     * Lança exceção de validação com mensagens.
     * O Controller deve capturar e tratar.
     */
    protected function fail(string $message, array $errors = []): never
    {
        throw new \RuntimeException($message, 422);
    }
}
