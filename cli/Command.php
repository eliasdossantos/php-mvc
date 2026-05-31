<?php

namespace Cli;

/**
 * Command — Classe Base de Comandos
 * ─────────────────────────────────────────────────────────────────────────────
 * Todos os comandos do CLI herdam desta classe.
 * Fornece utilitários para argumentos, opções, geração de arquivos
 * e escrita de output padronizado.
 */
abstract class Command
{
    /** Argumentos posicionais: ['UserController', ...] */
    protected array $args;

    /** Opções: ['fresh' => true, 'port' => '8080'] */
    protected array $options;

    public function __construct(array $args = [], array $options = [])
    {
        $this->args    = $args;
        $this->options = $options;
    }

    // ── Método principal (implementar em cada comando) ─────────────────────

    /**
     * Executa a lógica do comando.
     * Retorne true para sucesso, false para falha.
     */
    abstract public function handle(): bool;

    // ── Argumentos e opções ────────────────────────────────────────────────

    protected function arg(int $index, mixed $default = null): mixed
    {
        return $this->args[$index] ?? $default;
    }

    protected function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    protected function hasOption(string $key): bool
    {
        return isset($this->options[$key]);
    }

    // ── Geração de arquivos via Stubs ──────────────────────────────────────

    /**
     * Gera um arquivo a partir de um stub, substituindo os placeholders.
     *
     * @param string $stubName  Nome do arquivo stub (sem .stub), ex: 'controller'
     * @param string $destPath  Caminho absoluto do arquivo de destino
     * @param array  $vars      Substituições: ['{{ ClassName }}' => 'UserController']
     * @param bool   $overwrite Sobrescrever se já existir?
     */
    protected function generateFile(
        string $stubName,
        string $destPath,
        array  $vars      = [],
        bool   $overwrite = false
    ): bool {
        // Garante que o diretório de destino existe
        $dir = dirname($destPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Não sobrescreve sem permissão explícita
        if (file_exists($destPath) && !$overwrite) {
            Output::skipped($destPath);
            return false;
        }

        // Carrega o stub
        $stubPath = ROOT_PATH . '/cli/Stubs/' . $stubName . '.stub';
        if (!file_exists($stubPath)) {
            Output::error("Stub não encontrado: {$stubName}.stub");
            return false;
        }

        $content = file_get_contents($stubPath);

        // Substitui todos os placeholders
        foreach ($vars as $placeholder => $value) {
            $content = str_replace($placeholder, $value, $content);
        }

        file_put_contents($destPath, $content);
        Output::created($destPath);
        return true;
    }

    // ── Utilitários de nomes ────────────────────────────────────────────────

    /** Garante PascalCase: 'user_controller' → 'UserController' */
    protected function toPascalCase(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $name)));
    }

    /** PascalCase → snake_case: 'UserController' → 'user_controller' */
    protected function toSnakeCase(string $name): string
    {
        $snake = preg_replace('/([A-Z])/', '_$1', lcfirst($name));
        return strtolower(ltrim($snake, '_'));
    }

    /** PascalCase → kebab-case: 'UserController' → 'user-controller' */
    protected function toKebabCase(string $name): string
    {
        return str_replace('_', '-', $this->toSnakeCase($name));
    }

    /** 'UserController' → 'user' (extrai prefixo do model) */
    protected function toRoutePrefix(string $name): string
    {
        $base = preg_replace('/Controller$|Service$|Repository$|Request$/', '', $name);
        return strtolower($this->toKebabCase($base));
    }

    /** 'Product' → 'products' */
    protected function toTableName(string $modelName): string
    {
        $snake = $this->toSnakeCase($modelName);
        // Pluralização simples
        if (str_ends_with($snake, 'y')) {
            return substr($snake, 0, -1) . 'ies';
        }
        if (str_ends_with($snake, 's') || str_ends_with($snake, 'x') || str_ends_with($snake, 'z')) {
            return $snake . 'es';
        }
        return $snake . 's';
    }

    /** Extrai namespace de sub-pasta: 'Auth/LoginRequest' → ['Auth', 'LoginRequest'] */
    protected function parseNameAndNamespace(string $input): array
    {
        // Normaliza separadores
        $normalized = str_replace('\\', '/', $input);
        $parts      = explode('/', $normalized);
        $name       = array_pop($parts);
        $subNs      = implode('\\', $parts); // '' ou 'Auth' ou 'Auth\Sub'

        return [$this->toPascalCase($name), $subNs];
    }
}
