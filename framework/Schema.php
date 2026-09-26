<?php

namespace Framework;

/**
 * Schema — Facade estática para criação/alteração de tabelas via Blueprint
 * ─────────────────────────────────────────────────────────────────────────────
 * Uso dentro de uma migration:
 *
 *   Schema::create('produtos', function (Blueprint $table) {
 *       $table->id();
 *       $table->string('nome');
 *       $table->timestamps();
 *   });
 *
 *   Schema::table('produtos', function (Blueprint $table) {
 *       $table->string('sku')->nullable();
 *   });
 *
 *   Schema::dropIfExists('produtos');
 */
class Schema
{
    public static function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);
        Database::getInstance()->execMigration($blueprint->toCreateSql());
    }

    public static function table(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table, isAlter: true);
        $callback($blueprint);

        foreach ($blueprint->toAlterSql() as $statement) {
            Database::getInstance()->execMigration($statement);
        }
    }

    public static function dropIfExists(string $table): void
    {
        Database::getInstance()->execMigration("DROP TABLE IF EXISTS {$table};");
    }

    public static function drop(string $table): void
    {
        Database::getInstance()->execMigration("DROP TABLE {$table};");
    }
}
