<?php

namespace Cli;

use Framework\Database\Database;
use Framework\Database\Seeder;
use PDO;
use PDOException;
use ReflectionClass;
use RuntimeException;
use Throwable;

/**
 * SeedRunner — resolve e executa classes Seeder a partir de database/seeds.
 * O bootstrap, a conexão singleton e a transação permanecem centralizados aqui.
 */
class SeedRunner
{
    private ?PDO $pdo;
    private ?Database $database = null;

    public function __construct(?PDO $pdo = null)
    {
        // A injeção PDO também permite testar o fluxo legado sem abrir banco real.
        $this->pdo = $pdo;
    }

    /** @return array{seeders:int} */
    public function runMain(?callable $onLine = null): array
    {
        $masterPath = $this->seedDirectory() . '/DatabaseSeeder.php';
        if (!is_file($masterPath)) {
            throw new RuntimeException('Seeder principal não encontrado: database/seeds/DatabaseSeeder.php.');
        }

        $plan = $this->includeFile($masterPath);
        if (!is_array($plan) || !array_is_list($plan)) {
            throw new RuntimeException('DatabaseSeeder.php deve retornar uma lista ordenada de nomes de Seeder.');
        }

        // Resolve todos os arquivos primeiro para evitar execução parcial por um
        // nome ausente no fim do plano. A execução, depois, respeita a ordem da lista.
        $files = [];
        foreach ($plan as $reference) {
            if (!is_string($reference) || trim($reference) === '') {
                throw new RuntimeException('Cada item de DatabaseSeeder.php deve ser uma string/classe de Seeder.');
            }

            [$name, $className, $fallbackClass] = $this->normalizeSeederReference($reference);
            if ($name === 'DatabaseSeeder') {
                throw new RuntimeException('DatabaseSeeder não pode incluir a si próprio na sequência.');
            }

            $files[] = [
                'name' => $name,
                'file' => $this->resolveSeederFile($name),
                'class' => $className,
                'fallback' => $fallbackClass,
            ];
        }

        return $this->executeFiles($files, $onLine);
    }

    /** @return array{seeders:int} */
    public function runAll(?callable $onLine = null): array
    {
        $files = glob($this->seedDirectory() . '/*Seeder.php') ?: [];
        $files = array_values(array_filter(
            $files,
            static fn (string $file): bool => basename($file) !== 'DatabaseSeeder.php'
        ));
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        $entries = [];
        foreach ($files as $file) {
            $name = basename($file, '.php');
            [$name, $className, $fallbackClass] = $this->normalizeSeederReference($name);
            $entries[] = [
                'name' => $name,
                'file' => $file,
                'class' => $className,
                'fallback' => $fallbackClass,
            ];
        }

        return $this->executeFiles($entries, $onLine);
    }

    /** @return array{seeders:int} */
    public function runOne(string $reference, ?callable $onLine = null): array
    {
        [$name, $className, $fallbackClass] = $this->normalizeSeederReference($reference);
        if ($name === 'DatabaseSeeder') {
            return $this->runMain($onLine);
        }

        return $this->executeFiles([[
            'name' => $name,
            'file' => $this->resolveSeederFile($name),
            'class' => $className,
            'fallback' => $fallbackClass,
        ]], $onLine);
    }

    /** @param list<array{name:string,file:string,class:string,fallback:?string}> $files
     *  @return array{seeders:int}
     */
    private function executeFiles(array $files, ?callable $onLine): array
    {
        $executed = 0;

        foreach ($files as $entry) {
            $this->report($onLine, '[SEED] ' . $entry['name']);
            $this->executeFile($entry['file'], $entry['class'], $entry['fallback'], $onLine);
            $executed++;
        }

        return ['seeders' => $executed];
    }

    private function executeFile(string $file, string $className, ?string $fallbackClass, ?callable $onLine): void
    {
        // Require o arquivo diretamente para suportar a estrutura database/seeds
        // mesmo sem adicionar um namespace PSR-4 específico ao autoload do projeto.
        // Classes já carregadas não são incluídas de novo; arquivos-array legados
        // continuam sendo incluídos a cada execução para obter seu retorno.
        $class = $this->findLoadedClass($className, $fallbackClass);
        $payload = null;
        if ($class === null) {
            $payload = $this->includeFile($file);
            $class = $this->findLoadedClass($className, $fallbackClass);
        }

        if ($class !== null) {
            if (!is_subclass_of($class, Seeder::class)) {
                throw new RuntimeException("{$class} deve estender Framework\\Database\\Seeder.");
            }

            $this->runClassSeeder($class);
            $this->report($onLine, '  [OK] run() concluído e transação confirmada.');
            return;
        }

        // Compatibilidade com arquivos antigos que retornam dados ou executam
        // código procedural ao serem incluídos. Seeders novos devem ser classes.
        if ($payload === null || $payload === 1) {
            $this->report($onLine, '  [LEGACY] Arquivo procedural incluído.');
            return;
        }
        if (!is_array($payload)) {
            throw new RuntimeException(basename($file) . ' deve declarar uma classe Seeder ou retornar um array legado.');
        }

        [$table, $records] = $this->parsePayload($payload, basename($file, '.php'));
        if ($records === []) {
            $this->report($onLine, '  [INFO] Nenhum registro definido.');
            return;
        }

        $pdo = $this->connection();
        $insert = fn (...$_args): array => $this->insertRecords($pdo, $table, $records, basename($file), $onLine);
        if ($this->database !== null) {
            $this->database->transaction($insert);
        } else {
            // Caminho de compatibilidade usado por testes/integrações que injetam PDO.
            $this->withPdoTransaction($pdo, $insert);
        }
    }

    private function runClassSeeder(string $class): void
    {
        // Seeders class-based podem instanciar Models, que dependem do bootstrap
        // e da mesma conexão singleton. Bootstrap/carregamento são feitos só uma vez.
        if ($this->database === null) {
            if ($this->pdo === null) {
                (new Migrator())->bootstrapEnvironment();
            }
            $this->database = Database::getInstance();
        }

        $reflection = new ReflectionClass($class);
        if (!$reflection->isInstantiable()) {
            throw new RuntimeException("A classe Seeder {$class} não pode ser instanciada.");
        }
        $constructor = $reflection->getConstructor();
        if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
            throw new RuntimeException("A classe Seeder {$class} deve possuir construtor sem parâmetros obrigatórios.");
        }

        /** @var Seeder $seeder */
        $seeder = $reflection->newInstance();
        $this->database->transaction(static function (Database $_database) use ($seeder): void {
            $seeder->run();
        });
    }

    private function findLoadedClass(string $className, ?string $fallbackClass): ?string
    {
        if (class_exists($className, false)) {
            return $className;
        }
        if ($fallbackClass !== null && class_exists($fallbackClass, false)) {
            return $fallbackClass;
        }
        return null;
    }

    /** @return array{0:string,1:array} */
    private function parsePayload(array $payload, string $seederName): array
    {
        if (array_key_exists('table', $payload) || array_key_exists('records', $payload)) {
            if (!isset($payload['table']) || !is_string($payload['table']) || !array_key_exists('records', $payload) || !is_array($payload['records']) || !array_is_list($payload['records'])) {
                throw new RuntimeException("{$seederName} deve retornar 'table' e uma lista 'records'.");
            }
            $table = $payload['table'];
            $records = $payload['records'];
        } else {
            if (!array_is_list($payload)) {
                throw new RuntimeException("{$seederName} deve retornar uma lista de registros ou o descriptor table/records.");
            }
            $table = $this->tableFromSeederName($seederName);
            $records = $payload;
        }

        $this->assertIdentifier($table, 'tabela');
        return [$table, $records];
    }

    private function prepareRecord(array $record, string $seederName): array
    {
        foreach ($record as $column => $value) {
            if (!is_string($column)) {
                throw new RuntimeException("{$seederName}: nomes de coluna devem ser strings.");
            }

            // Mantém o comportamento anterior apenas para Seeders array legados.
            // Classes novas devem escolher explicitamente como gerar o hash.
            if ($column === 'password' && is_string($value)) {
                $passwordInfo = password_get_info($value);
                if (($passwordInfo['algoName'] ?? 'unknown') === 'unknown') {
                    $hash = password_hash($value, PASSWORD_DEFAULT);
                    if ($hash === false) {
                        throw new RuntimeException("{$seederName}: não foi possível gerar o hash da senha.");
                    }
                    $value = $hash;
                }
            }

            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            } elseif (is_array($value) || (is_object($value) && !$value instanceof \Stringable) || is_resource($value)) {
                throw new RuntimeException("{$seederName}: o valor da coluna '{$column}' deve ser escalar, null, DateTimeInterface ou Stringable.");
            } elseif ($value instanceof \Stringable) {
                $value = (string) $value;
            }

            $record[$column] = $value;
        }

        return $record;
    }

    private function connection(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        if ($this->database === null) {
            (new Migrator())->bootstrapEnvironment();
            $this->database = Database::getInstance();
        }
        $this->pdo = $this->database->getPdo();
        return $this->pdo;
    }

    /** @return array{0:int,1:int} */
    private function insertRecords(PDO $pdo, string $table, array $records, string $seederName, ?callable $onLine): array
    {
        $inserted = 0;
        $skipped = 0;

        foreach ($records as $index => $record) {
            if (!is_array($record) || $record === [] || array_is_list($record)) {
                throw new RuntimeException(sprintf('%s: registro %d deve ser um array associativo não vazio.', $seederName, $index + 1));
            }

            $record = $this->prepareRecord($record, $seederName);
            $columns = array_keys($record);
            foreach ($columns as $column) {
                $this->assertIdentifier((string) $column, 'coluna');
            }
            $columnSql = implode(', ', array_map(static fn (string $column): string => '`' . $column . '`', $columns));
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $sql = "INSERT INTO `{$table}` ({$columnSql}) VALUES ({$placeholders})";
            $statement = $pdo->prepare($sql);
            if ($statement === false) {
                throw new RuntimeException("Não foi possível preparar o INSERT da tabela {$table}.");
            }

            try {
                $statement->execute(array_values($record));
                $inserted++;
                $this->report($onLine, '  [OK] Registro ' . ($index + 1) . ' inserido.');
            } catch (PDOException $exception) {
                if (!$this->isDuplicateKey($exception)) {
                    throw $exception;
                }
                $skipped++;
                $this->report($onLine, '  [SKIP] Registro ' . ($index + 1) . ' ignorado: chave única duplicada.');
            }
        }

        return [$inserted, $skipped];
    }

    private function withPdoTransaction(PDO $pdo, callable $callback): mixed
    {
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction && !$pdo->beginTransaction()) {
            throw new RuntimeException('Não foi possível iniciar a transação do Seeder.');
        }

        try {
            $result = $callback();
            if ($ownsTransaction && !$pdo->commit()) {
                throw new RuntimeException('Não foi possível confirmar a transação do Seeder.');
            }
            return $result;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function seedDirectory(): string
    {
        if (!defined('ROOT_PATH')) {
            throw new RuntimeException('ROOT_PATH não está definido; execute o Seeder pelo CLI do projeto.');
        }

        $directory = ROOT_PATH . '/database/seeds';
        if (!is_dir($directory)) {
            throw new RuntimeException('Diretório database/seeds não encontrado.');
        }

        return $directory;
    }

    private function resolveSeederFile(string $name): string
    {
        $file = $this->seedDirectory() . '/' . $name . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("Seeder não encontrado: {$name} (esperado em database/seeds/{$name}.php).");
        }
        return $file;
    }

    /** @return array{0:string,1:string,2:?string} Nome do arquivo, classe e fallback global. */
    private function normalizeSeederReference(string $reference): array
    {
        $reference = trim($reference);
        if ($reference === '') {
            throw new RuntimeException('Nome de Seeder inválido: a referência não pode ser vazia.');
        }

        $withoutExtension = preg_replace('/\.php$/i', '', $reference) ?? $reference;
        $isQualifiedClass = str_contains($withoutExtension, '\\') && !str_contains($withoutExtension, '/');
        $name = basename(str_replace('\\', '/', $withoutExtension));
        if (!str_ends_with($name, 'Seeder')) {
            $name .= 'Seeder';
        }
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*Seeder$/D', $name)) {
            throw new RuntimeException("Nome de Seeder inválido: {$reference}");
        }

        if ($isQualifiedClass) {
            $parts = explode('\\', trim($withoutExtension, '\\'));
            foreach ($parts as $part) {
                if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $part)) {
                    throw new RuntimeException("Nome de classe Seeder inválido: {$reference}");
                }
            }
            array_pop($parts);
            $className = ($parts ? implode('\\', $parts) . '\\' : '') . $name;
            $fallbackClass = null;
        } else {
            $className = 'Database\\Seeders\\' . $name;
            $fallbackClass = $name;
        }

        return [$name, $className, $fallbackClass];
    }

    private function tableFromSeederName(string $seederName): string
    {
        $modelName = preg_replace('/Seeder$/', '', $seederName) ?? $seederName;
        $snake = strtolower(ltrim(preg_replace('/([A-Z])/', '_$1', lcfirst($modelName)) ?? $modelName, '_'));

        if (str_ends_with($snake, 'y')) {
            return substr($snake, 0, -1) . 'ies';
        }
        if (str_ends_with($snake, 's') || str_ends_with($snake, 'x') || str_ends_with($snake, 'z')) {
            return $snake . 'es';
        }
        return $snake . 's';
    }

    private function assertIdentifier(string $identifier, string $kind): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $identifier)) {
            throw new RuntimeException("Identificador de {$kind} inválido: {$identifier}");
        }
    }

    private function isDuplicateKey(PDOException $exception): bool
    {
        return (int) ($exception->errorInfo[1] ?? 0) === 1062;
    }

    private function includeFile(string $file): mixed
    {
        return (static function (string $path): mixed {
            return require $path;
        })($file);
    }

    private function report(?callable $onLine, string $message): void
    {
        if ($onLine !== null) {
            $onLine($message);
        }
    }
}
