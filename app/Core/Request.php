<?php

namespace Core;

/**
 * Request — Encapsula a Requisição HTTP
 * ─────────────────────────────────────────────────────────────────────────────
 * Fornece acesso seguro e normalizado a todos os dados da requisição:
 * URI, método, GET, POST, arquivos, headers, JSON body, IP.
 *
 * Todos os getters de dados aplicam sanitização básica por padrão.
 * Para dados raw (ex: uploads, JSON), use os métodos específicos.
 */
class Request
{
    protected ?array $jsonBody = null;

    // ── Método e URI ──────────────────────────────────────────────────────────

    /**
     * Retorna o método HTTP.
     * Suporta override via campo POST `_method` (para PUT/DELETE em forms HTML).
     */
    public function method(): string
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($method === 'POST' && isset($_POST['_method'])) {
            $override = strtoupper($_POST['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'])) {
                return $override;
            }
        }

        return $method;
    }

    /** Retorna a URI limpa, sem query string e sem base path */
    public function uri(): string
    {
        $uri      = $_SERVER['REQUEST_URI'] ?? '/';
        $uri      = strtok($uri, '?');                   // Remove query string
        $basePath = dirname($_SERVER['SCRIPT_NAME']);

        if ($basePath !== '/' && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath));
        }

        $uri = '/' . ltrim($uri, '/');
        return $uri !== '/' ? rtrim($uri, '/') : '/';
    }

    public function isGet(): bool    { return $this->method() === 'GET'; }
    public function isPost(): bool   { return $this->method() === 'POST'; }
    public function isPut(): bool    { return $this->method() === 'PUT'; }
    public function isDelete(): bool { return $this->method() === 'DELETE'; }
    public function isAjax(): bool
    {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    }
    public function isJson(): bool
    {
        return str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
    }

    // ── Dados de entrada ──────────────────────────────────────────────────────

    /** Valor sanitizado de $_GET */
    public function get(string $key, mixed $default = null): mixed
    {
        return isset($_GET[$key]) ? $this->sanitize($_GET[$key]) : $default;
    }

    /** Valor sanitizado de $_POST */
    public function post(string $key, mixed $default = null): mixed
    {
        return isset($_POST[$key]) ? $this->sanitize($_POST[$key]) : $default;
    }

    /** Todos os dados POST sanitizados */
    public function all(): array
    {
        return array_map([$this, 'sanitize'], $_POST);
    }

    /** Valor bruto de $_POST (sem sanitização — use com cuidado) */
    public function raw(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    /** Busca em GET e POST, na ordem */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post($key) ?? $this->get($key) ?? $default;
    }

    /** Verifica se campo existe na requisição */
    public function has(string $key): bool
    {
        return isset($_POST[$key]) || isset($_GET[$key]);
    }

    // ── JSON Body ─────────────────────────────────────────────────────────────

    /** Decodifica o body JSON (para APIs REST) */
    public function json(string $key = null, mixed $default = null): mixed
    {
        if ($this->jsonBody === null) {
            $raw = file_get_contents('php://input');
            $this->jsonBody = json_decode($raw, true) ?? [];
        }

        if ($key === null) return $this->jsonBody;
        return $this->jsonBody[$key] ?? $default;
    }

    // ── Arquivos ──────────────────────────────────────────────────────────────

    /** Retorna dados de $_FILES para um campo */
    public function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    public function hasFile(string $key): bool
    {
        return isset($_FILES[$key]) && $_FILES[$key]['error'] !== UPLOAD_ERR_NO_FILE;
    }

    // ── Headers e IP ─────────────────────────────────────────────────────────

    public function header(string $name, string $default = ''): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? $default;
    }

    /** IP real do cliente (considera proxies reversos confiáveis) */
    public function ip(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                return trim(explode(',', $_SERVER[$key])[0]);
            }
        }
        return '0.0.0.0';
    }

    public function userAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    // ── Sanitização ───────────────────────────────────────────────────────────

    protected function sanitize(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map([$this, 'sanitize'], $value);
        }
        return htmlspecialchars(strip_tags(trim((string) $value)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
