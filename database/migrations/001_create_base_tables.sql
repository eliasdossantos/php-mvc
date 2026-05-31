-- ─────────────────────────────────────────────────────────────────────────────
-- Migration 001 — Tabelas base do boilerplate
-- Execute: php database/migrate.php
-- ─────────────────────────────────────────────────────────────────────────────

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ── Usuários ──────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100)  NOT NULL,
    email      VARCHAR(180)  NOT NULL UNIQUE,
    password   VARCHAR(255)  NOT NULL,
    role       VARCHAR(40)   NOT NULL DEFAULT 'member',
    active     TINYINT(1)    NOT NULL DEFAULT 1,
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email  (email),
    INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Recuperação de senha ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS password_resets (
    id         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(180)  NOT NULL,
    token      VARCHAR(100)  NOT NULL UNIQUE,
    expires_at DATETIME      NOT NULL,
    used       TINYINT(1)    NOT NULL DEFAULT 0,
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;

-- ─────────────────────────────────────────────────────────────────────────────
-- Adicione suas tabelas de negócio abaixo ou crie novas migrations (002_, 003_…)
-- ─────────────────────────────────────────────────────────────────────────────
