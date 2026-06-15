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
 *   login   → 5 tentativas por 15 min por IP+email
 *   forgot  → 3 tentativas por 60 min por IP
 *   api     → 60 requisições por 1 min por IP
 *   default → 30 requisições por 1 min por IP
 */
class RateLimitMiddleware
{
    protected array $profiles = [
        'login'   => ['max' => 5,  'window' => 900,  'key' => 'ip_email'], // 15 min
        'forgot'  => ['max' => 3,  'window' => 3600, 'key' => 'ip'],       // 60 min
        'api'     => ['max' => 60, 'window' => 60,   'key' => 'ip'],       // 1 min
        'default' => ['max' => 30, 'window' => 60,   'key' => 'ip'],       // 1 min
    ];

    public function handle(Request $request, string $profile = 'default'): void
    {
        $config  = $this->profiles[$profile] ?? $this->profiles['default'];
        $key     = $this->buildKey($request, $profile, $config['key']);
        $data    = $this->load($key);
        $now     = time();

        // Reseta a janela se expirou
        if ($now - ($data['window_start'] ?? 0) > $config['window']) {
            $data = ['attempts' => 0, 'window_start' => $now];
        }

        $data['attempts']++;
        $this->save($key, $data, $config['window'] + 60);

        if ($data['attempts'] > $config['max']) {
            $retryAfter = ($data['window_start'] + $config['window']) - $now;
            $this->throttle($request, $retryAfter, $config);
        }
    }

    // ── Internos ──────────────────────────────────────────────────────────────

    protected function buildKey(Request $request, string $profile, string $keyType): string
    {
        $ip = $request->ip();

        if ($keyType === 'ip_email') {
            // Para login: combina IP + email para evitar bloquear um IP que
            // tenta vários e-mails diferentes (credential stuffing por IP)
            // E para evitar bloquear usuário legítimo por atacante com mesmo IP
            $email = strtolower(trim($_POST['email'] ?? ''));
            return 'rl_' . $profile . '_' . md5($ip . '|' . $email);
        }

        return 'rl_' . $profile . '_' . md5($ip);
    }

    protected function storePath(string $key): string
    {
        $dir = STORAGE_PATH . '/cache/ratelimit';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        return $dir . '/' . $key . '.json';
    }

    protected function load(string $key): array
    {
        $path = $this->storePath($key);
        if (!file_exists($path)) return [];
        $data = @json_decode(file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    protected function save(string $key, array $data, int $ttl): void
    {
        $data['expires'] = time() + $ttl;
        @file_put_contents(
            $this->storePath($key),
            json_encode($data),
            LOCK_EX
        );
    }

    protected function throttle(Request $request, int $retryAfter, array $config): void
    {
        $retryAfter = max(1, $retryAfter);

        http_response_code(429);
        header('Retry-After: ' . $retryAfter);

        if ($request->isAjax() || $request->isJson()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success'     => false,
                'message'     => 'Muitas tentativas. Aguarde ' . ceil($retryAfter / 60) . ' minuto(s) e tente novamente.',
                'retry_after' => $retryAfter,
            ]);
            exit;
        }

        $minutes = ceil($retryAfter / 60);
        Session::flash('error', "Muitas tentativas. Aguarde {$minutes} minuto(s) antes de tentar novamente.");

        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? url('/')));
        exit;
    }
}
