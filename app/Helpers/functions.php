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

/**
 * ── MELHORIA #1 ──────────────────────────────────────────────────────────────
 * Antes usava APP_URL direto — se a constante não estivesse definida ainda
 * (ex: helper chamado muito cedo no bootstrap, ou em um teste isolado),
 * isso era Fatal Error "Undefined constant APP_URL", derrubando a página
 * inteira. Agora cai pra string vazia nesse caso, gerando uma URL relativa
 * em vez de travar.
 */
function url(string $path = ''): string
{
    $base = defined('APP_URL') ? APP_URL : '';
    $path = ltrim($path, '/');
    return $path === '' ? $base : $base . '/' . $path;
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
    $router = \Core\Router::getInstance();
    if ($router === null) {
        // Fallback seguro: gera URL simples sem parâmetros nomeados
        return url($name);
    }

    // ── MELHORIA #2 ──────────────────────────────────────────────────────────
    // route() é chamado o tempo todo dentro de views (menus, links, forms).
    // Antes, uma rota nomeada errada ou inexistente lançava InvalidArgumentException
    // direto de dentro do template, quebrando a página inteira renderizada até
    // aquele ponto. Agora captura o erro, loga se possível, e devolve um link
    // seguro pra home — a página continua de pé mesmo com um link errado nela.
    try {
        return $router->route($name, $params);
    } catch (\Throwable $e) {
        if (class_exists(\Core\Logger::class)) {
            \Core\Logger::error("route() falhou para \"{$name}\": " . $e->getMessage());
        }
        return url('/');
    }
}

// ── Redirecionamento ──────────────────────────────────────────────────────────

/**
 * ── MELHORIA #3 ──────────────────────────────────────────────────────────────
 * Se algo (um echo esquecido, um espaço antes do <?php) já mandou output antes
 * do redirect, header("Location: ...") não tem efeito e só emite um warning —
 * a página fica "pela metade" sem redirecionar de verdade. Agora, quando os
 * headers já foram enviados, cai pra um redirect via HTML/JS, que funciona
 * mesmo depois de já ter saído conteúdo.
 */
function redirect(string $path): never
{
    $url = str_starts_with($path, 'http') ? $path : url($path);

    if (!headers_sent()) {
        header("Location: {$url}");
        exit;
    }

    echo '<script>window.location.href=' . json_encode($url) . ';</script>'
       . '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES) . '"></noscript>';
    exit;
}

// ── Sessão / Flash / Validação ────────────────────────────────────────────────

function flash(string $key): ?string
{
    return \Core\Session::getFlash($key);
}
function hasFlash(string $key): bool
{
    return \Core\Session::hasFlash($key);
}

function old(string $key, string $default = ''): string
{
    $value = \Core\Session::oldInput($key, $default);

    // ── MELHORIA #4 ──────────────────────────────────────────────────────────
    // Se o valor salvo como "old input" for um array (ex: checkboxes múltiplos,
    // campo[] reenviado sem tratamento), o cast (string) abaixo geraria o
    // warning "Array to string conversion" e imprimiria só "Array". Cai pro
    // default nesse caso em vez de mostrar isso pro usuário.
    if (is_array($value)) {
        $value = $default;
    }

    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Garante que sempre devolve array, mesmo se algo tiver corrompido _errors na sessão */
function errors(): array
{
    $errors = \Core\Session::get('_errors', []);
    return is_array($errors) ? $errors : [];
}
function hasError(string $field): bool
{
    return !empty(errors()[$field]);
}
function error(string $field): string
{
    return errors()[$field][0] ?? '';
}

// ── Segurança ─────────────────────────────────────────────────────────────────

function csrf_field(): string
{
    $t = \Core\Session::csrfToken();
    return "<input type=\"hidden\" name=\"_csrf_token\" value=\"{$t}\">";
}

function csrf_token(): string
{
    return \Core\Session::csrfToken();
}

function e(mixed $value): string
{
    // ── MELHORIA #5 ──────────────────────────────────────────────────────────
    // Objetos sem __toString() (ex: array, stdClass) quebravam o cast (string)
    // com um erro fatal ("Object of class stdClass could not be converted").
    // Agora normaliza pra algo seguro de exibir em vez de derrubar a view.
    if (is_array($value)) {
        $value = json_encode($value, JSON_UNESCAPED_UNICODE);
    } elseif (is_object($value) && !method_exists($value, '__toString')) {
        $value = get_class($value);
    }

    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function method_field(string $method): string
{
    return '<input type="hidden" name="_method" value="' . strtoupper($method) . '">';
}

// ── Autenticação ──────────────────────────────────────────────────────────────

function auth(): bool
{
    return \Core\Auth::check();
}
function user(): ?object
{
    return \Core\Auth::user();
}
function userId(): int
{
    return \Core\Auth::id() ?? 0;
}
function userRole(): string
{
    return \Core\Auth::role();
}
function isRole(string $r): bool
{
    return \Core\Auth::is($r);
}

// ── Ambiente ──────────────────────────────────────────────────────────────────

/**
 * ── MELHORIA #6 (corrige bug real) ────────────────────────────────────────────
 * A versão anterior usava `getenv($key) ?: $default`. O operador ?: trata
 * QUALQUER valor "falsy" como ausente — então uma variável de ambiente com
 * valor "0", "" ou "false" (string) caía pro $default em vez de retornar o
 * valor real configurado. Isso é um bug clássico: ex. FEATURE_X=0 no .env
 * pra desligar uma feature explicitamente, mas env('FEATURE_X', true) ainda
 * retornava true. Agora usa checagem explícita de existência/false.
 */
function env(string $key, mixed $default = null): mixed
{
    if (array_key_exists($key, $_ENV)) {
        return $_ENV[$key];
    }
    if (array_key_exists($key, $_SERVER)) {
        return $_SERVER[$key];
    }

    $value = getenv($key);
    return $value === false ? $default : $value;
}

// ── Strings ───────────────────────────────────────────────────────────────────

function str_limit(string $text, int $length = 100, string $end = '...'): string
{
    $length = max(0, $length); // evita mb_substr com tamanho negativo
    return mb_strlen($text) <= $length ? $text : mb_substr($text, 0, $length) . $end;
}

function slug(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text) ?? '';
    return trim(preg_replace('/[\s-]+/', '-', $text) ?? '', '-');
}

// ── Datas ─────────────────────────────────────────────────────────────────────

/**
 * ── MELHORIA #7 (corrige bug real) ────────────────────────────────────────────
 * strtotime() retorna `false` para uma data inválida ou mal formatada.
 * Antes, esse `false` era passado direto pra date(), que o PHP converte
 * silenciosamente pra int 0 — resultando em "01/01/1970" exibido na tela
 * como se fosse uma data válida, o que é pior que mostrar "—": engana o
 * usuário/o admin achando que aquele registro tem uma data real.
 */
function dateBR(?string $date): string
{
    if (!$date) return '—';
    $timestamp = strtotime($date);
    return $timestamp === false ? '—' : date('d/m/Y', $timestamp);
}

function dateTimeBR(?string $date): string
{
    if (!$date) return '—';
    $timestamp = strtotime($date);
    return $timestamp === false ? '—' : date('d/m/Y H:i', $timestamp);
}

function diffForHumans(?string $date): string
{
    if (!$date) return '';
    $timestamp = strtotime($date);
    if ($timestamp === false) return '';

    $diff = time() - $timestamp;
    if ($diff < 60)     return 'agora mesmo';
    if ($diff < 3600)   return (int)($diff / 60) . ' min atrás';
    if ($diff < 86400)  return (int)($diff / 3600) . 'h atrás';
    if ($diff < 604800) return (int)($diff / 86400) . ' dias atrás';
    return dateBR($date);
}

// ── Números ───────────────────────────────────────────────────────────────────

function formatBytes(int $bytes, int $precision = 1): string
{
    // Preserva o sinal em vez de deixar o loop se comportar de forma
    // inesperada com valores negativos (ex: diffs de espaço em disco).
    $sign  = $bytes < 0 ? '-' : '';
    $bytes = abs($bytes);

    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    $val = (float) $bytes;
    while ($val >= 1024 && $i < count($units) - 1) {
        $val /= 1024;
        $i++;
    }
    return $sign . round($val, $precision) . ' ' . $units[$i];
}

// ── Navegação ─────────────────────────────────────────────────────────────────

/**
 * ── MELHORIA #8 ──────────────────────────────────────────────────────────────
 * parse_url() pode retornar null (URI malformada) — passar null pro
 * str_replace()/str_starts_with() abaixo gera deprecation warning em PHP 8.1+
 * ("Passing null to parameter #... of type string is deprecated"). Agora
 * normaliza pra string vazia antes de qualquer operação.
 */
function isActive(string $path, string $class = 'active'): string
{
    $current  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $basePath = dirname($_SERVER['SCRIPT_NAME'] ?? '/');

    if ($basePath !== '/' && $basePath !== '.' && $basePath !== '') {
        $current = str_replace($basePath, '', $current);
    }

    return str_starts_with((string) $current, '/' . ltrim($path, '/')) ? $class : '';
}

// ── Debug ─────────────────────────────────────────────────────────────────────

function dd(mixed ...$values): never
{
    $s = 'background:#1e293b;color:#f8f8f2;padding:16px 20px;margin:8px;border-radius:8px;'
        . 'font-family:monospace;font-size:13px;overflow:auto;white-space:pre;line-height:1.5;';
    foreach ($values as $v) {
        echo "<pre style=\"{$s}\">";
        var_dump($v);
        echo '</pre>';
    }
    exit;
}

function dump(mixed ...$values): void
{
    $s = 'background:#1e293b;color:#f8f8f2;padding:12px 16px;margin:4px;border-radius:6px;'
        . 'font-family:monospace;font-size:12px;white-space:pre;';
    foreach ($values as $v) {
        echo "<pre style=\"{$s}\">";
        var_dump($v);
        echo '</pre>';
    }
}
