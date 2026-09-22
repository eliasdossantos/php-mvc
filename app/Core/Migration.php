<?php

namespace Core;

/**
 * Migration — Classe base para migrations no estilo Laravel
 * ─────────────────────────────────────────────────────────────────────────────
 * Cada arquivo de migration deve retornar uma instância anônima desta classe,
 * implementando up() (aplicar) e down() (desfazer).
 *
 * Convenção de nome de arquivo: YYYY_MM_DD_HHMMSS_descricao.php
 * Ex: 2026_09_20_120000_create_produtos_table.php
 */
abstract class Migration
{
    abstract public function up(): void;
    abstract public function down(): void;
}
