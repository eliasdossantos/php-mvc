-- ─────────────────────────────────────────────────────────────────────────────
-- Migration 002 — Tabela de rate limit e remember tokens
-- ─────────────────────────────────────────────────────────────────────────────

-- ── Remember tokens (implementa "lembrar de mim" de forma segura) ─────────────
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS remember_token VARCHAR(100) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS remember_token_expires_at DATETIME NULL DEFAULT NULL;

-- ── Limpeza periódica de tokens de reset de senha expirados ──────────────────
-- (não cria tabela nova — apenas garante o índice correto)
CREATE INDEX IF NOT EXISTS idx_expires_used
    ON password_resets (expires_at, used);
