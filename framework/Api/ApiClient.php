<?php

namespace Framework\Api;

use Framework\Logger;

/**
 * ApiClient — Cliente HTTP centralizado para consumo de APIs externas
 * ─────────────────────────────────────────────────────────────────────────────
 * TODA chamada HTTP de saída (CEP, mapas, pagamento, WhatsApp, IA, etc.) deve
 * passar por aqui. Nenhum Service deve usar curl_*() diretamente — isso
 * garante um único ponto para timeout, logging, tratamento de erro e headers
 * padrão (ex: User-Agent, Accept).
 *
 * Uso típico (dentro de uma classe de Integration — ver
 * app/Services/Integrations/):
 *
 *   $client = new ApiClient('https://viacep.com.br/ws', timeout: 10);
 *   $res    = $client->get('/01001000/json');
 *   if ($res['ok']) {
 *       $cidade = $res['json']['localidade'] ?? null;
 *   }
 *
 * Com autenticação:
 *   $client = new ApiClient('https://api.mercadopago.com');
 *   $res = $client->post('/v1/payments', [
 *       'json'  => ['transaction_amount' => 100],
 *       'token' => $accessToken, // Bearer
 *   ]);
 *
 * Todas as respostas de erro (conexão OU status >= 400, quando $throwOnError
 * não for desativado por chamada) viram ApiException — trate no Service/
 * Integration, nunca deixe subir crua até o Controller.
 */
class ApiClient
{
    public function __construct(
        protected string $baseUri = '',
        protected array $defaultHeaders = [],
        protected int $defaultTimeout = 30
    ) {
    }

    // ── Verbos HTTP ───────────────────────────────────────────────────────────

    public function get(string $uri, array $options = []): array
    {
        return $this->request('GET', $uri, $options);
    }

    public function post(string $uri, array $options = []): array
    {
        return $this->request('POST', $uri, $options);
    }

    public function put(string $uri, array $options = []): array
    {
        return $this->request('PUT', $uri, $options);
    }

    public function patch(string $uri, array $options = []): array
    {
        return $this->request('PATCH', $uri, $options);
    }

    public function delete(string $uri, array $options = []): array
    {
        return $this->request('DELETE', $uri, $options);
    }

    /**
     * Monta um ApiRequest a partir de $options e dispara send().
     *
     * Opções aceitas:
     *   'headers'        => array   Headers extras (mescla com os padrão)
     *   'query'          => array   Query string
     *   'json'           => array   Corpo JSON (define Content-Type automaticamente)
     *   'form'           => array   Corpo x-www-form-urlencoded
     *   'token'          => string  Bearer token
     *   'basic_auth'     => [user, pass]
     *   'timeout'        => int     Segundos (sobrescreve o default da instância)
     *   'throw_on_error' => bool    Default true — lança ApiException se status >= 400
     */
    public function request(string $method, string $uri, array $options = []): array
    {
        $req = new ApiRequest($method, rtrim($this->baseUri, '/') . '/' . ltrim($uri, '/'));
        $req = $req->withHeaders(array_merge(
            ['Accept' => 'application/json', 'User-Agent' => 'php-mvc-ApiClient/1.0'],
            $this->defaultHeaders,
            $options['headers'] ?? []
        ));

        if (!empty($options['query']))      $req = $req->withQuery($options['query']);
        if (!empty($options['json']))       $req = $req->withJson($options['json']);
        if (!empty($options['form']))       $req = $req->withFormBody($options['form']);
        if (!empty($options['token']))      $req = $req->withBearerToken($options['token']);
        if (!empty($options['basic_auth'])) $req = $req->withBasicAuth(...$options['basic_auth']);

        $req = $req->withTimeout((int) ($options['timeout'] ?? $this->defaultTimeout));

        return $this->send($req, (bool) ($options['throw_on_error'] ?? true));
    }

    // ── Execução ──────────────────────────────────────────────────────────────

    /**
     * Executa um ApiRequest já montado via cURL.
     *
     * @return array{status:int, headers:array, body:string, json:array|null, ok:bool}
     */
    public function send(ApiRequest $req, bool $throwOnError = true): array
    {
        $url = $req->url();
        if ($req->query()) {
            $sep = str_contains($url, '?') ? '&' : '?';
            $url .= $sep . http_build_query($req->query());
        }

        $headers = $req->headers();
        if ($req->bodyContentType() !== null && !isset($headers['Content-Type'])) {
            $headers['Content-Type'] = $req->bodyContentType();
        }
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = "{$name}: {$value}";
        }

        $startedAt = microtime(true);
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $req->method(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_HTTPHEADER     => $headerLines,
            CURLOPT_TIMEOUT        => $req->timeout() ?? $this->defaultTimeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $req->timeout() ?? $this->defaultTimeout),
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
        ]);

        if ($req->body() !== null && !in_array($req->method(), ['GET', 'HEAD'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $req->body());
        }

        $raw       = curl_exec($ch);
        $errno     = curl_errno($ch);
        $error     = curl_error($ch);
        $status    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerLen = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $elapsedMs = round((microtime(true) - $startedAt) * 1000, 2);

        // Nunca loga headers (podem conter Authorization) nem o corpo bruto —
        // só metadados suficientes para diagnosticar sem vazar credenciais.
        $logContext = [
            'method' => $req->method(),
            'url'    => $url,
            'status' => $status,
            'ms'     => $elapsedMs,
        ];

        if ($raw === false || $errno !== 0) {
            Logger::error('ApiClient: falha de conexão', $logContext + ['curl_error' => $error]);
            $ex = new ApiException("Falha ao conectar em [{$url}]: {$error}", 0, '', []);
            if ($throwOnError) throw $ex;
            return ['status' => 0, 'headers' => [], 'body' => '', 'json' => null, 'ok' => false];
        }

        $responseHeaders = substr($raw, 0, $headerLen);
        $body            = substr($raw, $headerLen);
        $json            = json_decode($body, true);
        $json            = is_array($json) ? $json : null;

        $ok = $status >= 200 && $status < 300;
        Logger::debug('ApiClient: requisição concluída', $logContext);

        if (!$ok && $throwOnError) {
            Logger::warning('ApiClient: resposta de erro', $logContext);
            throw new ApiException(
                "API externa respondeu com status {$status} para [{$url}]",
                $status,
                $body,
                $json ?? []
            );
        }

        return [
            'status'  => $status,
            'headers' => $this->parseHeaders($responseHeaders),
            'body'    => $body,
            'json'    => $json,
            'ok'      => $ok,
        ];
    }

    protected function parseHeaders(string $raw): array
    {
        $headers = [];
        foreach (explode("\r\n", trim($raw)) as $line) {
            if (!str_contains($line, ':')) continue;
            [$name, $value] = explode(':', $line, 2);
            $headers[trim($name)] = trim($value);
        }
        return $headers;
    }
}
