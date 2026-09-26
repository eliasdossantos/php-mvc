<?php

namespace Framework;

/**
 * Column — Representa uma coluna dentro de um Blueprint
 * ─────────────────────────────────────────────────────────────────────────────
 * Suporta modificadores fluentes: nullable(), default(), constrained() (FK).
 * Usada internamente pelo Blueprint; você não instancia isso diretamente.
 */
class Column
{
    public bool $isForeignId = false;

    private string $name;
    private string $type;
    private bool $nullable = false;
    private mixed $defaultValue = null;
    private bool $hasDefault = false;
    private bool $defaultIsRaw = false;
    private string $rawExtra = '';
    private ?string $referencedTable = null;
    private string $referencedColumn = 'id';
    private string $onDeleteAction = 'RESTRICT';
    private string $onUpdateAction = 'RESTRICT';

    public function __construct(string $name, string $type)
    {
        $this->name = $name;
        $this->type = $type;
    }

    public function nullable(bool $value = true): static
    {
        $this->nullable = $value;
        return $this;
    }

    /** @param bool $raw se true, $value é inserido cru no SQL (ex: CURRENT_TIMESTAMP) */
    public function default(mixed $value, bool $raw = false): static
    {
        $this->hasDefault = true;
        $this->defaultValue = $value;
        $this->defaultIsRaw = $raw;
        return $this;
    }

    /** Adiciona SQL extra ao final da coluna (ex: "ON UPDATE CURRENT_TIMESTAMP") */
    public function rawExtra(string $sql): static
    {
        $this->rawExtra = $sql;
        return $this;
    }

    /**
     * Define a FK por convenção: categoria_id -> tabela "categorias", coluna "id".
     * Pode sobrescrever a tabela/coluna referenciada explicitamente.
     */
    public function constrained(?string $table = null, string $column = 'id'): static
    {
        $this->referencedTable = $table ?? $this->guessTableName();
        $this->referencedColumn = $column;
        return $this;
    }

    public function onDelete(string $action): static
    {
        $this->onDeleteAction = strtoupper($action);
        return $this;
    }

    public function onUpdate(string $action): static
    {
        $this->onUpdateAction = strtoupper($action);
        return $this;
    }

    private function guessTableName(): string
    {
        // categoria_id -> categorias | pedido_id -> pedidos
        $base = preg_replace('/_id$/', '', $this->name);
        return $base . 's';
    }

    public function toSql(): string
    {
        $sql = "{$this->name} {$this->type}";
        $sql .= $this->nullable ? " NULL" : " NOT NULL";

        if ($this->hasDefault) {
            $sql .= $this->defaultIsRaw
                ? " DEFAULT {$this->defaultValue}"
                : " DEFAULT " . (is_string($this->defaultValue) ? "'{$this->defaultValue}'" : $this->defaultValue);
        }

        if ($this->rawExtra !== '') {
            $sql .= " {$this->rawExtra}";
        }

        return $sql;
    }

    public function toForeignKeySql(string $table): string
    {
        $constraint = "fk_{$table}_{$this->name}";
        return "CONSTRAINT {$constraint} FOREIGN KEY ({$this->name}) "
             . "REFERENCES {$this->referencedTable}({$this->referencedColumn}) "
             . "ON DELETE {$this->onDeleteAction} ON UPDATE {$this->onUpdateAction}";
    }
}
