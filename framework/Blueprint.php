<?php

namespace Framework;

/**
 * Blueprint — Definição fluente de colunas para migrations
 * ─────────────────────────────────────────────────────────────────────────────
 * Inspirado no Schema Builder do Laravel, adaptado ao Framework\Database (PDO puro).
 * Gera o SQL de CREATE TABLE / ALTER TABLE a partir das chamadas fluentes.
 *
 * Os tipos de coluna abaixo estão agrupados como no phpMyAdmin:
 *   NÚMEROS · TEXTO · DATA E HORA · BOOLEANO · ESTRUTURADOS/ESPECIAIS · CHAVES
 *
 * Uso típico dentro de uma migration:
 *   Schema::create('produtos', function (Blueprint $table) {
 *       $table->id();
 *       $table->string('nome', 150);
 *       $table->text('descricao')->nullable();
 *       $table->decimal('preco', 10, 2);
 *       $table->foreignId('categoria_id')->constrained('categorias')->onDelete('cascade');
 *       $table->boolean('ativo')->default(1);
 *       $table->timestamps();
 *   });
 *
 * Modificadores encadeáveis em qualquer coluna:
 *   ->nullable()              permite NULL (por padrão toda coluna é NOT NULL)
 *   ->default($valor)         define um valor padrão
 *   ->constrained('tabela')   (só em foreignId) cria a chave estrangeira
 */
class Blueprint
{
    private string $table;
    private array $columns = [];
    private array $indexes = [];
    private array $foreignKeys = [];
    private array $dropColumns = [];

    public function __construct(string $table, bool $isAlter = false)
    {
        $this->table = $table;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // NÚMEROS — inteiros, decimais e ponto flutuante
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * TINYINT — inteiro bem pequeno (-128 a 127, ou 0 a 255 se unsigned)
     * Bom para: flags numéricas, prioridade, nota de 0 a 10
     * Uso: $table->tinyInteger('prioridade');
     */
    public function tinyInteger(string $name): Column
    {
        return $this->addColumn($name, "TINYINT");
    }

    /** Versão sem sinal do TINYINT (só positivos, 0 a 255) */
    public function unsignedTinyInteger(string $name): Column
    {
        return $this->addColumn($name, "TINYINT UNSIGNED");
    }

    /**
     * SMALLINT — inteiro médio (-32.768 a 32.767, ou até 65.535 unsigned)
     * Bom para: estoque, quantidade de itens, ano com sinal
     * Uso: $table->smallInteger('estoque');
     */
    public function smallInteger(string $name): Column
    {
        return $this->addColumn($name, "SMALLINT");
    }

    /** Versão sem sinal do SMALLINT (0 a 65.535) */
    public function unsignedSmallInteger(string $name): Column
    {
        return $this->addColumn($name, "SMALLINT UNSIGNED");
    }

    /**
     * MEDIUMINT — inteiro (-8.388.608 a 8.388.607, ou até ~16M unsigned)
     * Bom para: contadores de visualização, pontuação acumulada
     * Uso: $table->mediumInteger('visualizacoes');
     */
    public function mediumInteger(string $name): Column
    {
        return $this->addColumn($name, "MEDIUMINT");
    }

    /** Versão sem sinal do MEDIUMINT (0 a ~16 milhões) */
    public function unsignedMediumInteger(string $name): Column
    {
        return $this->addColumn($name, "MEDIUMINT UNSIGNED");
    }

    /**
     * INT — inteiro padrão (-2,1 bi a 2,1 bi, ou até 4,2 bi unsigned)
     * Bom para: a maioria dos números inteiros do dia a dia, quantidades
     * Uso: $table->integer('quantidade');
     */
    public function integer(string $name): Column
    {
        return $this->addColumn($name, "INT");
    }

    /** Versão sem sinal do INT — o tipo padrão usado por id() e foreignId() */
    public function unsignedInteger(string $name): Column
    {
        return $this->addColumn($name, "INT UNSIGNED");
    }

    /**
     * BIGINT — inteiro grande (até ~9 quintilhões)
     * Bom para: IDs de tabelas gigantes, contadores que não têm teto realista
     * Uso: $table->bigInteger('total_processado');
     */
    public function bigInteger(string $name): Column
    {
        return $this->addColumn($name, "BIGINT");
    }

    /** Versão sem sinal do BIGINT — usado internamente por foreignId() */
    public function unsignedBigInteger(string $name): Column
    {
        return $this->addColumn($name, "BIGINT UNSIGNED");
    }

    /**
     * DECIMAL — número exato com casas decimais fixas
     * Use SEMPRE pra dinheiro/preço — ao contrário de FLOAT/DOUBLE, não tem
     * erro de arredondamento (ex: 0.1 + 0.2 dá exatamente 0.3, não 0.30000004)
     * Uso: $table->decimal('preco', 10, 2);  // até 99999999.99
     */
    public function decimal(string $name, int $precision = 10, int $scale = 2): Column
    {
        return $this->addColumn($name, "DECIMAL({$precision},{$scale})");
    }

    /**
     * FLOAT — número de ponto flutuante aproximado (4 bytes)
     * Bom para: coordenadas geográficas, médias, medidas científicas
     * Evite pra dinheiro — use decimal() nesse caso
     * Uso: $table->float('latitude', 10, 6);
     */
    public function float(string $name, int $precision = 8, int $scale = 2): Column
    {
        return $this->addColumn($name, "FLOAT({$precision},{$scale})");
    }

    /**
     * DOUBLE — ponto flutuante de precisão dupla (8 bytes), mais preciso que FLOAT
     * Bom para: cálculos científicos que precisam de mais casas decimais
     * Uso: $table->double('peso_exato', 12, 6);
     */
    public function double(string $name, int $precision = 8, int $scale = 2): Column
    {
        return $this->addColumn($name, "DOUBLE({$precision},{$scale})");
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEXTO
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * CHAR — texto de tamanho FIXO (sempre ocupa N caracteres, completa com espaço)
     * Bom para: sigla de estado, código de tamanho fixo, hash MD5 (32)
     * Uso: $table->char('uf', 2);
     */
    public function char(string $name, int $length = 1): Column
    {
        return $this->addColumn($name, "CHAR({$length})");
    }

    /**
     * VARCHAR — texto de tamanho VARIÁVEL até o limite informado
     * Bom para: nome, email, título — a maioria dos campos de texto curto
     * Uso: $table->string('nome', 100);
     */
    public function string(string $name, int $length = 255): Column
    {
        return $this->addColumn($name, "VARCHAR({$length})");
    }

    /**
     * TEXT — texto longo (até ~65 mil caracteres)
     * Bom para: descrição de produto, comentário, corpo de mensagem
     * Uso: $table->text('descricao');
     */
    public function text(string $name): Column
    {
        return $this->addColumn($name, "TEXT");
    }

    /**
     * MEDIUMTEXT — texto bem longo (até ~16 milhões de caracteres)
     * Bom para: conteúdo de artigo extenso, corpo de e-mail em HTML
     * Uso: $table->mediumText('conteudo');
     */
    public function mediumText(string $name): Column
    {
        return $this->addColumn($name, "MEDIUMTEXT");
    }

    /**
     * LONGTEXT — texto gigante (até ~4 bilhões de caracteres)
     * Bom para: JSON grande, log completo, backup de conteúdo
     * Uso: $table->longText('log_completo');
     */
    public function longText(string $name): Column
    {
        return $this->addColumn($name, "LONGTEXT");
    }

    // ═══════════════════════════════════════════════════════════════════════
    // DATA E HORA
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * DATE — apenas data (AAAA-MM-DD), sem hora
     * Bom para: data de nascimento, data de vencimento
     * Uso: $table->date('data_nascimento');
     */
    public function date(string $name): Column
    {
        return $this->addColumn($name, "DATE");
    }

    /**
     * TIME — apenas hora (HH:MM:SS), sem data
     * Bom para: horário de funcionamento, duração de uma etapa
     * Uso: $table->time('horario_abertura');
     */
    public function time(string $name): Column
    {
        return $this->addColumn($name, "TIME");
    }

    /**
     * YEAR — apenas o ano (4 dígitos)
     * Bom para: ano de fabricação, ano letivo
     * Uso: $table->year('ano_fabricacao');
     */
    public function year(string $name): Column
    {
        return $this->addColumn($name, "YEAR");
    }

    /**
     * DATETIME — data + hora (AAAA-MM-DD HH:MM:SS), sem timezone, sem limite de range
     * Bom para: created_at, agendamentos, qualquer data/hora no seu próprio fuso
     * Uso: $table->dateTime('entregue_em');
     */
    public function dateTime(string $name): Column
    {
        return $this->addColumn($name, "DATETIME");
    }

    /**
     * TIMESTAMP — data + hora, faixa menor que DATETIME (1970–2038), mas é o
     * tipo convencional quando você quer atualização automática via ON UPDATE
     * Uso: $table->timestamp('verificado_em');
     */
    public function timestamp(string $name): Column
    {
        return $this->addColumn($name, "TIMESTAMP");
    }

    // ═══════════════════════════════════════════════════════════════════════
    // BOOLEANO
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * TINYINT(1) usado como booleano — MySQL não tem tipo BOOLEAN nativo,
     * TINYINT(1) (0 = falso, 1 = verdadeiro) é a convenção padrão
     * Uso: $table->boolean('ativo')->default(1);
     */
    public function boolean(string $name): Column
    {
        return $this->addColumn($name, "TINYINT(1)");
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ESTRUTURADOS / ESPECIAIS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * JSON — armazena um objeto/array JSON, com validação nativa do MySQL
     * Bom para: preferências do usuário, metadados flexíveis, configs dinâmicas
     * Uso: $table->json('preferencias');
     */
    public function json(string $name): Column
    {
        return $this->addColumn($name, "JSON");
    }

    /**
     * ENUM — texto restrito a uma lista fixa de valores pré-definidos
     * Bom para: status (pendente/pago/cancelado), categoria fixa
     * Uso: $table->enum('status', ['pendente', 'pago', 'cancelado']);
     */
    public function enum(string $name, array $values): Column
    {
        $list = implode(',', array_map(fn($v) => "'{$v}'", $values));
        return $this->addColumn($name, "ENUM({$list})");
    }

    /**
     * UUID — string de 36 caracteres, para identificadores únicos universais
     * Bom para: IDs públicos que não podem ser sequenciais/previsíveis
     * Uso: $table->uuid('id_publico');
     */
    public function uuid(string $name): Column
    {
        return $this->addColumn($name, "CHAR(36)");
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CHAVES E COLUNAS DE CONVENÇÃO
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Chave primária auto-incrementável (INT UNSIGNED)
     * Uso: $table->id();  // sempre a primeira linha da migration
     */
    public function id(string $name = 'id'): Column
    {
        return $this->addColumn($name, "INT UNSIGNED AUTO_INCREMENT PRIMARY KEY");
    }

    /**
     * Cria created_at + updated_at com valores padrão automáticos
     * Uso: $table->timestamps();  // geralmente a última linha
     */
    public function timestamps(): void
    {
        $this->dateTime('created_at')->default('CURRENT_TIMESTAMP', raw: true);
        $this->dateTime('updated_at')
             ->default('CURRENT_TIMESTAMP', raw: true)
             ->rawExtra('ON UPDATE CURRENT_TIMESTAMP');
    }

    /**
     * deleted_at nullable, para soft delete (compatível com Model::$softDelete)
     * Uso: $table->softDeletes();
     */
    public function softDeletes(): Column
    {
        return $this->dateTime('deleted_at')->nullable();
    }

    /**
     * Chave estrangeira por convenção: categoria_id -> tabela categorias, coluna id
     * Uso: $table->foreignId('categoria_id')->constrained('categorias')->onDelete('cascade');
     */
    public function foreignId(string $name): Column
    {
        $col = $this->unsignedInteger($name);
        $col->isForeignId = true;
        return $col;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ÍNDICES
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Índice comum, acelera buscas/filtros pela(s) coluna(s) informada(s)
     * Uso: $table->index('categoria_id');
     */
    public function index(string|array $columns, ?string $name = null): void
    {
        $columns = (array) $columns;
        $name ??= 'idx_' . $this->table . '_' . implode('_', $columns);
        $this->indexes[] = "INDEX {$name} (" . implode(', ', $columns) . ")";
    }

    /**
     * Índice único — impede valores duplicados na(s) coluna(s)
     * Uso: $table->unique('email');
     */
    public function unique(string|array $columns, ?string $name = null): void
    {
        $columns = (array) $columns;
        $name ??= 'uniq_' . $this->table . '_' . implode('_', $columns);
        $this->indexes[] = "UNIQUE KEY {$name} (" . implode(', ', $columns) . ")";
    }

    // ── Remoção (usado com Schema::table) ───────────────────────────────

    public function dropColumn(string|array $columns): void
    {
        foreach ((array) $columns as $col) {
            $this->dropColumns[] = $col;
        }
    }

    // ── Internos usados pelo Schema ──────────────────────────────────────

    private function addColumn(string $name, string $type): Column
    {
        $column = new Column($name, $type);
        $this->columns[$name] = $column;
        return $column;
    }

    public function toCreateSql(): string
    {
        $lines = [];
        foreach ($this->columns as $column) {
            $lines[] = $column->toSql();
            if ($column->isForeignId) {
                $this->foreignKeys[] = $column->toForeignKeySql($this->table);
            }
        }
        $lines = array_merge($lines, $this->indexes, $this->foreignKeys);

        return "CREATE TABLE IF NOT EXISTS {$this->table} (\n    "
             . implode(",\n    ", $lines)
             . "\n) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;";
    }

    /** @return string[] lista de statements ALTER TABLE a executar em sequência */
    public function toAlterSql(): array
    {
        $statements = [];

        foreach ($this->columns as $column) {
            $statements[] = "ALTER TABLE {$this->table} ADD COLUMN {$column->toSql()};";
            if ($column->isForeignId) {
                $statements[] = "ALTER TABLE {$this->table} ADD {$column->toForeignKeySql($this->table)};";
            }
        }

        foreach ($this->dropColumns as $col) {
            $statements[] = "ALTER TABLE {$this->table} DROP COLUMN {$col};";
        }

        foreach ($this->indexes as $idx) {
            $statements[] = "ALTER TABLE {$this->table} ADD {$idx};";
        }

        return $statements;
    }
}
