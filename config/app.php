<?php

/**
 * Configuração Principal da Aplicação
 * ─────────────────────────────────────────────────────────────────────────────
 * Define constantes globais, timezone, charset e sessão.
 * Todos os valores sensíveis vêm do .env — nunca hardcoded aqui.
 */

// ── Ambiente ──────────────────────────────────────────────────────────────────

$env   = $_ENV['APP_ENV']   ?? 'production';
$debug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);

// ⚠️  Em produção: APP_DEBUG=false força display_errors=0 independente de $env
if ($debug && $env !== 'production') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

// ── Internacionalização ───────────────────────────────────────────────────────

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'America/Sao_Paulo');
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

// ── Sessão segura ─────────────────────────────────────────────────────────────

if (!headers_sent()) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', $_ENV['SESSION_SAME_SITE'] ?? 'Lax');
    ini_set('session.gc_maxlifetime', (int)($_ENV['SESSION_LIFETIME'] ?? 120) * 60);

    // Força cookie seguro em produção mesmo que a variável esteja errada
    // (regra centralizada em Framework\Session::shouldUseSecureCookies())
    if (\Framework\Session::shouldUseSecureCookies($env)) {
        ini_set('session.cookie_secure', 1);
    }
}

// ── Constantes Globais ────────────────────────────────────────────────────────

define('APP_NAME',  $_ENV['APP_NAME']  ?? 'PHP MVC App');
define('APP_ENV',   $env);
// APP_DEBUG é false em produção, mesmo que .env diga true (dupla segurança)
define('APP_DEBUG', $debug && $env !== 'production');
define('APP_KEY',   $_ENV['APP_KEY']   ?? '');
define('APP_URL',   _detectAppUrl());

// ── Detecção de URL Base ──────────────────────────────────────────────────────

function _detectAppUrl(): string
{
    $envUrl = trim($_ENV['APP_URL'] ?? '');
    if ($envUrl !== '') {
        $parts = parse_url($envUrl);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host']) || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw new \RuntimeException('APP_URL deve ser uma URL HTTP(S) absoluta e válida.');
        }
        return rtrim($envUrl, '/');
    }

    if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
        throw new \RuntimeException('APP_URL é obrigatório em produção.');
    }

    $remote = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    $trustedProxies = array_filter(array_map('trim', explode(',', (string)($_ENV['TRUSTED_PROXIES'] ?? ''))));
    $fromTrustedProxy = $remote !== '' && in_array($remote, $trustedProxies, true);

    $scheme = 'http';
    if (
        (!empty($_SERVER['HTTPS'])                  && strtolower($_SERVER['HTTPS']) !== 'off') ||
        ($fromTrustedProxy && is_string($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null) && strtolower(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]) === 'https') ||
        (isset($_SERVER['SERVER_PORT'])             && (string)$_SERVER['SERVER_PORT'] === '443')
    ) {
        $scheme = 'https';
    }

    $forwardedHost = $fromTrustedProxy && is_string($_SERVER['HTTP_X_FORWARDED_HOST'] ?? null)
        ? $_SERVER['HTTP_X_FORWARDED_HOST']
        : '';
    $host = trim(explode(',', (string)($forwardedHost ?: ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost')))[0]);
    if (!preg_match('/^[a-zA-Z0-9.-]+(?::[0-9]{1,5})?$/', $host)) {
        throw new \RuntimeException('Host HTTP inválido para detectar APP_URL. Configure APP_URL explicitamente.');
    }

    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $base       = '';
    if (preg_match('#^(/.+)/public(?:/|$)#', $scriptName, $m)) {
        $base = rtrim($m[1], '/');
    }

    return $scheme . '://' . $host . $base;
}
