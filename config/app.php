<?php

/**
 * Configuração Principal da Aplicação
 * ─────────────────────────────────────────────────────────────────────────────
 * Define constantes globais, timezone, charset e sessão.
 * Todos os valores sensíveis vêm do .env — nunca hardcoded aqui.
 */

// ── Ambiente ──────────────────────────────────────────────────────────────────

$env = $_ENV['APP_ENV'] ?? 'production';

if ($env === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// ── Internacionalização ───────────────────────────────────────────────────────

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'America/Sao_Paulo');
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

// ── Sessão segura ─────────────────────────────────────────────────────────────

// Apenas configurar se headers ainda não foram enviados (não estamos em CLI com output)
if (!headers_sent()) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');

    if (filter_var($_ENV['SESSION_SECURE'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
        ini_set('session.cookie_secure', 1);
    }
}

// ── Constantes Globais ────────────────────────────────────────────────────────

define('APP_NAME',  $_ENV['APP_NAME']  ?? 'PHP MVC Boilerplate');
define('APP_ENV',   $_ENV['APP_ENV']   ?? 'production');
define('APP_DEBUG', filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN));
define('APP_KEY',   $_ENV['APP_KEY']   ?? '');

define('APP_URL', _detectAppUrl());

// ── Detecção de URL Base ──────────────────────────────────────────────────────

function _detectAppUrl(): string
{
    $envUrl = trim($_ENV['APP_URL'] ?? '');
    if ($envUrl !== '') return rtrim($envUrl, '/');

    $scheme = 'http';
    if (
        (!empty($_SERVER['HTTPS'])                  && strtolower($_SERVER['HTTPS']) !== 'off') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && str_contains(strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']), 'https')) ||
        (isset($_SERVER['SERVER_PORT'])             && (string)$_SERVER['SERVER_PORT'] === '443')
    ) {
        $scheme = 'https';
    }

    $host = trim(explode(',', (string)(
        $_SERVER['HTTP_X_FORWARDED_HOST'] ??
        $_SERVER['HTTP_HOST']             ??
        $_SERVER['SERVER_NAME']           ??
        'localhost'
    ))[0]);

    // Detecta subpasta (ex: /myapp/public → base = /myapp)
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $base       = '';
    if (preg_match('#^(/.+)/public(?:/|$)#', $scriptName, $m)) {
        $base = rtrim($m[1], '/');
    }

    return $scheme . '://' . $host . $base;
}
