<?php

/**
 * Helpers Globais
 * ─────────────────────────────────────────────────────────────────────────────
 * Funções disponíveis em qualquer lugar da aplicação, sem namespace.
 * Carregadas via "files" no composer.json.
 *
 * Apenas utilitários genéricos aqui.
 * Helpers específicos de módulo: app/Helpers/{NomeModulo}Helper.php
 */

// ── URLs ──────────────────────────────────────────────────────────────────────

function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return $path === '' ? APP_URL : APP_URL . '/' . $path;
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function storageUrl(string $path): string
{
    return url('storage/' . ltrim($path, '/'));
}

function route(string $name, array $params = []): string
{
    global $router;
    return $router?->route($name, $params) ?? url($name);
}

// ── Redirecionamento ──────────────────────────────────────────────────────────

function redirect(string $path): never
{
    $url = str_starts_with($path, 'http') ? $path : url($path);
    header("Location: {$url}");
    exit;
}

// ── Sessão / Flash / Validação ────────────────────────────────────────────────

function flash(string $key): ?string       { return \Core\Session::getFlash($key); }
function hasFlash(string $key): bool       { return \Core\Session::hasFlash($key); }

function old(string $key, string $default = ''): string
{
    return htmlspecialchars((string) \Core\Session::oldInput($key, $default), ENT_QUOTES, 'UTF-8');
}

function errors(): array             { return \Core\Session::get('_errors', []); }
function hasError(string $field): bool { return !empty(\Core\Session::get('_errors')[$field]); }
function error(string $field): string  { return \Core\Session::get('_errors')[$field][0] ?? ''; }

// ── Segurança ─────────────────────────────────────────────────────────────────

function csrf_field(): string
{
    $t = \Core\Session::csrfToken();
    return "<input type=\"hidden\" name=\"_csrf_token\" value=\"{$t}\">";
}

function csrf_token(): string { return \Core\Session::csrfToken(); }

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function method_field(string $method): string
{
    return '<input type="hidden" name="_method" value="' . strtoupper($method) . '">';
}

// ── Autenticação ──────────────────────────────────────────────────────────────

function auth(): bool         { return \Core\Auth::check(); }
function user(): ?object      { return \Core\Auth::user(); }
function userId(): int        { return \Core\Auth::id() ?? 0; }
function userRole(): string   { return \Core\Auth::role(); }
function isRole(string $r): bool { return \Core\Auth::is($r); }

// ── Ambiente ──────────────────────────────────────────────────────────────────

function env(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default;
}

// ── Strings ───────────────────────────────────────────────────────────────────

function str_limit(string $text, int $length = 100, string $end = '...'): string
{
    return mb_strlen($text) <= $length ? $text : mb_substr($text, 0, $length) . $end;
}

function slug(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    return trim(preg_replace('/[\s-]+/', '-', $text), '-');
}

// ── Datas ─────────────────────────────────────────────────────────────────────

function dateBR(?string $date): string
{
    if (!$date) return '—';
    return date('d/m/Y', strtotime($date));
}

function dateTimeBR(?string $date): string
{
    if (!$date) return '—';
    return date('d/m/Y H:i', strtotime($date));
}

function diffForHumans(?string $date): string
{
    if (!$date) return '';
    $diff = time() - strtotime($date);
    if ($diff < 60)     return 'agora mesmo';
    if ($diff < 3600)   return (int)($diff / 60) . ' min atrás';
    if ($diff < 86400)  return (int)($diff / 3600) . 'h atrás';
    if ($diff < 604800) return (int)($diff / 86400) . ' dias atrás';
    return dateBR($date);
}

// ── Números ───────────────────────────────────────────────────────────────────

function formatBytes(int $bytes, int $precision = 1): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0; $val = (float) $bytes;
    while ($val >= 1024 && $i < count($units) - 1) { $val /= 1024; $i++; }
    return round($val, $precision) . ' ' . $units[$i];
}

// ── Navegação ─────────────────────────────────────────────────────────────────

function isActive(string $path, string $class = 'active'): string
{
    $current  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $basePath = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
    if ($basePath !== '/') $current = str_replace($basePath, '', $current);
    return str_starts_with((string)$current, '/' . ltrim($path, '/')) ? $class : '';
}

// ── Debug ─────────────────────────────────────────────────────────────────────

function dd(mixed ...$values): never
{
    $s = 'background:#1e293b;color:#f8f8f2;padding:16px 20px;margin:8px;border-radius:8px;'
       . 'font-family:monospace;font-size:13px;overflow:auto;white-space:pre;line-height:1.5;';
    foreach ($values as $v) { echo "<pre style=\"{$s}\">"; var_dump($v); echo '</pre>'; }
    exit;
}

function dump(mixed ...$values): void
{
    $s = 'background:#1e293b;color:#f8f8f2;padding:12px 16px;margin:4px;border-radius:6px;'
       . 'font-family:monospace;font-size:12px;white-space:pre;';
    foreach ($values as $v) { echo "<pre style=\"{$s}\">"; var_dump($v); echo '</pre>'; }
}
