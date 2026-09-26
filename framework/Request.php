<?php

namespace Framework;

/**
 * Request — Encapsula a Requisição HTTP
 * ─────────────────────────────────────────────────────────────────────────────
 * Fornece acesso seguro e normalizado a todos os dados da requisição:
 * URI, método, GET, POST, arquivos, headers, JSON body, IP.
 *
 * Todos os getters de dados aplicam sanitização básica por padrão.
 * Para dados raw (ex: uploads, JSON), use os métodos específicos.
 *
 * ⚠  IMPORTANTE sobre sanitizeValue(): get()/post()/all() passam todo valor
 * por htmlspecialchars()+strip_tags() automaticamente. Isso é ótimo pra
 * exibir de volta em HTML sem se preocupar, mas significa que o valor NÃO é
 * mais o dado original — para senhas (login/cadastro), tokens, ou qualquer
 * campo onde o byte exato importa, use raw($campo) em vez de post($campo).
 * Uma senha com "<" ou "&" sanitizada na entrada vira outro hash no cadastro
 * e nunca mais bate no login — sempre use raw() pra campos de senha.
 */
class Request
{
    protected ?array $jsonBody = null;

    // ── Método e URI ──────────────────────────────────────────────────────────

    /**
     * Retorna o método HTTP.
     * Suporta override via campo POST `_method` (para PUT/PATCH/DELETE em forms HTML).
     *
     * ── Detalhes de implementação
     * Se `_method` viesse como array (ex: campo de formulário mal montado,
     * `_method[]=PUT`), strtoupper() de um array gera TypeError fatal. Agora
     * só considera o override se for de fato uma string.
     */
    public function method(): string
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($method === 'POST' && isset($_POST['_method']) && is_string($_POST['_method'])) {
            $override = strtoupper($_POST['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $override;
            }
        }

        return $method;
    }

    /**
     * Retorna a URI limpa, sem query string e sem base path.
     *
     * ── Detalhes de implementação
     * $_SERVER['SCRIPT_NAME'] pode não existir em alguns SAPIs/contextos de
     * teste — acessá-lo direto gera "Undefined array key", e dirname(null)
     * é deprecated em PHP 8.1+. A implementação cai pra '/' nesse caso.
     */
    public function uri(): string
    {
        $uri      = $_SERVER['REQUEST_URI'] ?? '/';
        $uri      = strtok($uri, '?');                   // Remove query string
        $basePath = dirname($_SERVER['SCRIPT_NAME'] ?? '/');

        if ($basePath !== '/' && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath));
        }

        $uri = '/' . ltrim($uri, '/');
        return $uri !== '/' ? rtrim($uri, '/') : '/';
    }

    public function isGet(): bool
    {
        return $this->method() === 'GET';
    }
    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }
    public function isPut(): bool
    {
        return $this->method() === 'PUT';
    }
    /** ── NOVO ── faltava: Controller::checkMethod('patch') dependia disso */
    public function isPatch(): bool
    {
        return $this->method() === 'PATCH';
    }
    public function isDelete(): bool
    {
        return $this->method() === 'DELETE';
    }
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
        return isset($_GET[$key]) ? static::sanitizeValue($_GET[$key]) : $default;
    }

    /** Valor sanitizado de $_POST */
    public function post(string $key, mixed $default = null): mixed
    {
        return isset($_POST[$key]) ? static::sanitizeValue($_POST[$key]) : $default;
    }

    /** Todos os dados POST sanitizados */
    public function all(): array
    {
        return array_map([self::class, 'sanitizeValue'], $_POST);
    }

    /** Valor bruto de $_POST (sem sanitização) — use para senhas, tokens, e qualquer campo onde o byte exato importa */
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

    /**
     * Decodifica o body JSON (para APIs REST).
     *
     * ── Detalhes de implementação
     * (1) `?string $key = null` explícito — parâmetro implicitamente nullable
     * é deprecated desde PHP 8.4. (2) file_get_contents() pode retornar false
     * (stream já consumido ou erro de leitura); esse caso é tratado antes de chegar ao
     * json_decode(false, true), que na prática devolve null e cai no `?? []`
     * — funcionava, mas por acidente. A implementação é uma checagem explícita.
     * (3) JSON malformado a implementação é tratado da mesma forma (corpo vazio),
     * documentado no comentário em vez de deixar implícito no `?? []`.
     */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($this->jsonBody === null) {
            $raw = file_get_contents('php://input');
            $decoded = $raw === false ? null : json_decode($raw, true);

            // json_decode retorna null tanto pra "" quanto pra JSON inválido
            // quanto pro literal JSON "null" — nos três casos, tratamos como
            // corpo vazio (array), que é o comportamento mais seguro pros
            // consumidores deste método (evita "Trying to access array offset
            // on null" espalhado pelo código que chama ->json('campo')).
            $this->jsonBody = is_array($decoded) ? $decoded : [];
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

    /**
     * IP real do cliente (considera proxies reversos confiáveis).
     *
     * ⚠  Nota de segurança (não corrigida aqui, só documentada): os headers
     * X-Forwarded-For e CF-Connecting-IP são enviados pelo CLIENTE e só são
     * confiáveis se você tiver certeza que a requisição passa por um proxy
     * que os sobrescreve (Cloudflare, seu load balancer). Se sua aplicação
     * for exposta direto (sem proxy), qualquer um pode forjar esses headers
     * e falsificar o IP — nesse cenário, use REMOTE_ADDR puro.
     */
    public function ip(): string
    {
        $remote = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        $trusted = array_filter(array_map('trim', explode(',', (string)($_ENV['TRUSTED_PROXIES'] ?? ''))));
        $fromTrustedProxy = $remote !== '' && in_array($remote, $trusted, true);

        if ($fromTrustedProxy) {
            foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR'] as $key) {
                if (!empty($_SERVER[$key])) {
                    $candidate = trim(explode(',', (string)$_SERVER[$key])[0]);
                    if (filter_var($candidate, FILTER_VALIDATE_IP)) return $candidate;
                }
            }
        }

        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
    }

    public function userAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    // ── Sanitização ───────────────────────────────────────────────────────────

    /**
     * Sanitiza um valor (string, array recursivo, ou passa intacto se não for string).
     * Público e estático para ser reutilizável por FormRequest e SecurityHelper,
     * evitando três implementações independentes do mesmo algoritmo.
     */
    public static function sanitizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map([self::class, 'sanitizeValue'], $value);
        }

        if (is_string($value)) {
            return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $value;
    }
}
