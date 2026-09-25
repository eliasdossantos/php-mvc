<?php

namespace Core\Api;

/**
 * ApiRequest — Descrição imutável de uma requisição HTTP de saída
 * ─────────────────────────────────────────────────────────────────────────────
 * Objeto de valor consumido pelo ApiClient. Nunca executa nada sozinho —
 * apenas descreve o que deve ser enviado (método, URL, headers, query,
 * corpo, autenticação, timeout). Cada método `with*` retorna uma NOVA
 * instância (imutável), então é seguro montar uma base e derivar variações:
 *
 *   $base = (new ApiRequest('GET', $baseUri))->withBearerToken($token);
 *   $listar  = $base->withPath('/users')->withQuery(['page' => 2]);
 *   $buscar  = $base->withPath('/users/42');
 */
final class ApiRequest
{
    private array $headers = [];
    private array $query   = [];
    private ?string $body  = null;
    private ?string $bodyContentType = null;
    private ?int $timeout  = null;

    public function __construct(
        private string $method,
        private string $url
    ) {
        $this->method = strtoupper($method);
    }

    // ── Getters (usados pelo ApiClient) ─────────────────────────────────────

    public function method(): string
    {
        return $this->method;
    }
    public function url(): string
    {
        return $this->url;
    }
    public function headers(): array
    {
        return $this->headers;
    }
    public function query(): array
    {
        return $this->query;
    }
    public function body(): ?string
    {
        return $this->body;
    }
    public function bodyContentType(): ?string
    {
        return $this->bodyContentType;
    }
    public function timeout(): ?int
    {
        return $this->timeout;
    }

    // ── Builders (retornam nova instância) ──────────────────────────────────

    public function withPath(string $path): self
    {
        $clone = clone $this;
        $clone->url = rtrim($this->url, '/') . '/' . ltrim($path, '/');
        return $clone;
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function withHeaders(array $headers): self
    {
        $clone = clone $this;
        $clone->headers = array_merge($this->headers, $headers);
        return $clone;
    }

    public function withQuery(array $query): self
    {
        $clone = clone $this;
        $clone->query = array_merge($this->query, $query);
        return $clone;
    }

    /** Define o corpo como JSON e ajusta o Content-Type automaticamente */
    public function withJson(array $data): self
    {
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $clone = clone $this;
        $clone->body            = $encoded === false ? '{}' : $encoded;
        $clone->bodyContentType = 'application/json';
        return $clone;
    }

    /** Define o corpo como application/x-www-form-urlencoded */
    public function withFormBody(array $data): self
    {
        $clone = clone $this;
        $clone->body            = http_build_query($data);
        $clone->bodyContentType = 'application/x-www-form-urlencoded';
        return $clone;
    }

    /** Corpo bruto (ex: XML, multipart pré-montado) */
    public function withBody(string $body, string $contentType): self
    {
        $clone = clone $this;
        $clone->body            = $body;
        $clone->bodyContentType = $contentType;
        return $clone;
    }

    public function withBearerToken(string $token): self
    {
        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    public function withBasicAuth(string $username, string $password): self
    {
        return $this->withHeader('Authorization', 'Basic ' . base64_encode($username . ':' . $password));
    }

    public function withTimeout(int $seconds): self
    {
        $clone = clone $this;
        $clone->timeout = $seconds;
        return $clone;
    }
}
