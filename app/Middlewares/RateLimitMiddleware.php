<?php

namespace App\Middlewares;

use Core\Request;
use Core\Session;

/**
 * RateLimitMiddleware — Proteção contra força bruta e abuso de endpoints
 * ─────────────────────────────────────────────────────────────────────────────
 * Limita o número de requisições por janela de tempo usando armazenamento
 * em arquivo (sem dependência de Redis/Memcached).
 *
 * Uso nas rotas:
 *   $router->post('/auth/login', [...], ['RateLimitMiddleware:login']);
 *   $router->post('/auth/forgot-password', [...], ['RateLimitMiddleware:forgot']);
 *
 * Perfis disponíveis (configurados em $profiles abaixo):
 *   login    → 5 tentativas por 15 min por IP+email
 *   register → 5 tentativas por 60 min por IP
 *   forgot   → 3 tentativas por 60 min por IP
 *   api      → 60 requisições por 1 min por IP
 *   default  → 30 requisições por 1 min por IP
 */
class RateLimitMiddleware
{
    protected array $profiles = [
        'login'    => ['max' => 5,  'window' => 900,  'key' => 'ip_email'], // 15 min
        'register' => ['max' => 5,  'window' => 3600, 'key' => 'ip'],       // 60 min
        'forgot'   => ['max' => 3,  'window' => 3600, 'key' => 'ip'],       // 60 min
        'api'      => ['max' => 60, 'window' => 60,   'key' => 'ip'],       // 1 min
        'default'  => ['max' => 30, 'window' => 60,   'key' => 'ip'],       // 1 min
    ];

    public function handle(Request $request, string $profile = 'default'): void
    {
        $config = $this->profiles[$profile] ?? $this->profiles['default'];
        $key    = $this->buildKey($request, $profile, $config['key']);
        $now    = time();

        $data = $this->hit($key, $config['window']);

        if ($data['attempts'] > $config['max']) {
            $retryAfter = ($data['window_start'] + $config['window']) - $now;
            $this->throttle($request, $retryAfter, $config);
        }
    }

    // ── Internos ──────────────────────────────────────────────────────────────

    /**
     * ── MELHORIA #1 (corrige bug real) ────────────────────────────────────────
     * A versão anterior usava load()+save() como duas operações separadas:
     * lê o arquivo, incrementa em memória, escreve de volta. Sob requisições
     * concorrentes (exatamente o cenário que um rate limiter de login precisa
     * segurar), duas requisições podem ler "attempts: 3" ao mesmo tempo, cada
     * uma incrementar pra 4 e escrever — perdendo um dos incrementos. Um
     * atacante rodando tentativas em paralelo furava o limite.
     *
     * Agora tudo acontece dentro de uma única seção travada com flock():
     * abre (ou cria) o arquivo, trava, lê, decide se a janela expirou,
     * incrementa, escreve e destrava — sem brecha entre ler e escrever.
     */
    protected function hit(string $key, int $window): array
    {
        $path = $this->storePath($key);
        $now  = time();

        $handle = @fopen($path, 'c+');
        if ($handle === false) {
            // Não conseguiu abrir/criar o arquivo de controle (permissão de
            // disco, disco cheio, etc). Falha aberta: deixa passar em vez de
            // bloquear todo mundo por um problema de infraestrutura — pior
            // deixar passar uma tentativa a mais do que travar o login pra
            // todo mundo por um erro de disco.
            if (class_exists(\Core\Logger::class)) {
                \Core\Logger::error("RateLimitMiddleware: não foi possível abrir {$path}");
            }
            return ['attempts' => 1, 'window_start' => $now];
        }

        flock($handle, LOCK_EX); // trava até fechar — ninguém mais lê/escreve esse arquivo nesse meio-tempo

        $raw  = stream_get_contents($handle);
        $data = $raw !== false && $raw !== '' ? json_decode($raw, true) : null;
        $data = is_array($data) ? $data : [];

        // Reseta a janela se expirou
        if ($now - ($data['window_start'] ?? 0) > $window) {
            $data = ['attempts' => 0, 'window_start' => $now];
        }

        $data['attempts'] = ($data['attempts'] ?? 0) + 1;
        $data['expires']  = $now + $window + 60;

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($data));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        return $data;
    }

    protected function buildKey(Request $request, string $profile, string $keyType): string
    {
        $ip = $request->ip();

        if ($keyType === 'ip_email') {
            // Para login: combina IP + email para evitar bloquear um IP que
            // tenta vários e-mails diferentes (credential stuffing por IP)
            // E para evitar bloquear usuário legítimo por atacante com mesmo IP
            //
            // ── MELHORIA #2 (corrige bug real) ──────────────────────────────
            // Se "email" viesse como array (ex: corpo "email[]=a&email[]=b"),
            // trim() num array é TypeError fatal — um POST malformado
            // derrubava a própria proteção contra força bruta. Agora só usa
            // o valor se for de fato uma string.
            $emailRaw = $_POST['email'] ?? '';
            $email    = is_string($emailRaw) ? strtolower(trim($emailRaw)) : '';
            return 'rl_' . $profile . '_' . md5($ip . '|' . $email);
        }

        return 'rl_' . $profile . '_' . md5($ip);
    }

    protected function storePath(string $key): string
    {
        $dir = STORAGE_PATH . '/cache/ratelimit';

        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            // O !is_dir($dir) checado de novo depois do mkdir cobre a corrida
            // entre dois processos tentando criar o mesmo diretório ao mesmo
            // tempo (um deles "falha" mas o diretório já existe de verdade).
            if (class_exists(\Core\Logger::class)) {
                \Core\Logger::error("RateLimitMiddleware: não foi possível criar o diretório {$dir}");
            }
        }

        return $dir . '/' . $key . '.json';
    }

    protected function throttle(Request $request, int $retryAfter, array $config): void
    {
        $retryAfter = max(1, $retryAfter);

        // ── MELHORIA #3 ──────────────────────────────────────────────────────
        // Guarda headers_sent() antes de cada header(), mesmo padrão já
        // aplicado no resto do projeto — evita warning se algo (raro, mas
        // possível numa cadeia de middlewares) já tiver mandado output antes.
        if (!headers_sent()) {
            http_response_code(429);
            header('Retry-After: ' . $retryAfter);
        }

        if ($request->isAjax() || $request->isJson()) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode([
                'success'     => false,
                'message'     => 'Muitas tentativas. Aguarde ' . ceil($retryAfter / 60) . ' minuto(s) e tente novamente.',
                'retry_after' => $retryAfter,
            ]);
            exit;
        }

        $minutes = ceil($retryAfter / 60);
        Session::flash('error', "Muitas tentativas. Aguarde {$minutes} minuto(s) antes de tentar novamente.");

        $fallback = $_SERVER['HTTP_REFERER'] ?? url('/');
        if (!headers_sent()) {
            header('Location: ' . $fallback);
        } else {
            echo '<script>window.location.href=' . json_encode($fallback) . ';</script>';
        }
        exit;
    }
}
