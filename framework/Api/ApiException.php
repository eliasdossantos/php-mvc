<?php

namespace Framework\Api;

/**
 * ApiException — Erro de comunicação com uma API externa
 * ─────────────────────────────────────────────────────────────────────────────
 * Lançada pelo ApiClient quando:
 *  - a conexão falha (timeout, DNS, TLS, etc);
 *  - a resposta HTTP vem com status de erro (4xx/5xx) e $throwOnError=true.
 *
 * Carrega o status HTTP (quando disponível) e o corpo bruto da resposta,
 * para que quem chamar possa decidir como tratar sem precisar re-parsear nada.
 */
class ApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        protected int $statusCode = 0,
        protected string $responseBody = '',
        protected array $responseJson = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function responseBody(): string
    {
        return $this->responseBody;
    }

    /** Corpo da resposta já decodificado, se era JSON válido */
    public function responseJson(): array
    {
        return $this->responseJson;
    }

    /** true quando a exceção veio de falha de transporte (sem resposta HTTP nenhuma) */
    public function isConnectionError(): bool
    {
        return $this->statusCode === 0;
    }
}
