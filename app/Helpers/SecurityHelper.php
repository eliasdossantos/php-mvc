<?php

namespace App\Helpers;

use Core\Request;
use Core\Session;

/**
 * SecurityHelper — Utilitários de Segurança
 * ─────────────────────────────────────────────────────────────────────────────
 * Funções estáticas para operações comuns de segurança:
 * senhas, tokens, sanitização, slugs, mascaramento.
 */
class SecurityHelper
{
    public static function generateToken(int $bytes = 32): string
    {
        return Session::generateToken($bytes);
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function sanitize(string $input): string
    {
        return Request::sanitizeValue($input);
    }

    public static function sanitizeArray(array $data): array
    {
        return Request::sanitizeValue($data);
    }

    public static function isValidEmail(string $email): bool
    {
        return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public static function slug(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        return trim(preg_replace('/[\s-]+/', '-', $text), '-');
    }

    public static function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);
        return substr($local, 0, 2) . str_repeat('*', max(0, strlen($local) - 2)) . '@' . $domain;
    }

    /** Gera UUID v4 */
    public static function uuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
