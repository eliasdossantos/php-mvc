<?php

namespace Cli;

use Cli\Commands\MakeControllerCommand;
use Cli\Commands\MakeModelCommand;
use Cli\Commands\MakeRequestCommand;
use Cli\Commands\MakeServiceCommand;
use Cli\Commands\MakeRepositoryCommand;
use Cli\Commands\MakeSeedCommand;
use Cli\Commands\MakeViewCommand;
use Cli\Commands\MigrateCommand;
use Cli\Commands\ServeCommand;

/**
 * CLI Kernel
 * ─────────────────────────────────────────────────────────────────────────────
 * Orquestra o CLI do framework:
 *   - Faz o parse dos argumentos da linha de comando
 *   - Registra todos os comandos disponíveis
 *   - Roteia para o comando correto
 *   - Exibe ajuda e lista de comandos
 *
 * Para adicionar um novo comando:
 *   1. Crie a classe em cli/Commands/MeuComandoCommand.php
 *   2. Registre em $this->commands abaixo
 */
class Kernel
{
    /** @var array<string, class-string<Command>> */
    private array $commands = [];

    /** Argumentos da linha de comando */
    private array $argv;

    /** Nome do comando solicitado */
    private string $commandName = '';

    /** Argumentos posicionais (após o nome do comando) */
    private array $args = [];

    /** Opções (--flag, --key=value) */
    private array $options = [];

    public function __construct(array $argv)
    {
        $this->argv = $argv;
        $this->parseArgs();
        $this->registerCommands();
    }

    // ── Execução ──────────────────────────────────────────────────────────────

    /**
     * Ponto de entrada. Retorna o código de saída (0 = sucesso).
     */
    public function run(): int
    {
        Output::banner();

        if (empty($this->commandName) || in_array($this->commandName, ['help', '--help', '-h'])) {
            $this->showHelp();
            return 0;
        }

        if ($this->commandName === 'list') {
            $this->showList();
            return 0;
        }

        if (!isset($this->commands[$this->commandName])) {
            Output::error("Comando \"{$this->commandName}\" não encontrado.");
            Output::line("  Execute <comment>php mvc list</comment> para ver os comandos disponíveis.");
            Output::newline();
            return 1;
        }

        $class   = $this->commands[$this->commandName];
        $command = new $class($this->args, $this->options);

        try {
            $result = $command->handle();
            $elapsed = round((microtime(true) - CLI_START) * 1000);
            Output::newline();
            Output::dim("  Concluído em {$elapsed}ms");
            Output::newline();
            return $result ? 0 : 1;
        } catch (\Throwable $e) {
            Output::newline();
            Output::error("Erro ao executar \"{$this->commandName}\": " . $e->getMessage());
            if (isset($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                Output::dim("  " . $e->getFile() . ':' . $e->getLine());
            }
            Output::newline();
            return 1;
        }
    }

    // ── Registro de comandos ──────────────────────────────────────────────────

    private function registerCommands(): void
    {
        $this->commands = [
            // Geradores de código
            'make:controller' => MakeControllerCommand::class,
            'make:model'      => MakeModelCommand::class,
            'make:request'    => MakeRequestCommand::class,
            'make:service'    => MakeServiceCommand::class,
            'make:repository' => MakeRepositoryCommand::class,
            'make:seed'       => MakeSeedCommand::class,
            'make:view'       => MakeViewCommand::class,

            // Utilitários
            'migrate'         => MigrateCommand::class,
            'serve'           => ServeCommand::class,
        ];
    }

    // ── Parse de argumentos ───────────────────────────────────────────────────

    /**
     * Interpreta $argv no formato:
     *   php mvc <comando> [arg1] [arg2] [--flag] [--key=value]
     */
    private function parseArgs(): void
    {
        $args = array_slice($this->argv, 1); // remove "mvc"

        if (empty($args)) return;

        $this->commandName = array_shift($args);
        $positional        = [];

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--')) {
                $arg = substr($arg, 2);
                if (str_contains($arg, '=')) {
                    [$key, $val]          = explode('=', $arg, 2);
                    $this->options[$key]  = $val;
                } else {
                    $this->options[$arg] = true;
                }
            } elseif (str_starts_with($arg, '-')) {
                $this->options[substr($arg, 1)] = true;
            } else {
                $positional[] = $arg;
            }
        }

        $this->args = $positional;
    }

    // ── Ajuda e listagem ──────────────────────────────────────────────────────

    private function showHelp(): void
    {
        Output::title("Uso");
        Output::line("  <comment>php mvc</comment> <info><comando></info> [argumentos] [opções]");
        Output::newline();
        $this->showList();
    }

    private function showList(): void
    {
        $groups = [
            'Geradores de código' => [
                'make:controller {Nome}' => 'Cria um Controller com métodos CRUD',
                'make:model {Nome}'      => 'Cria um Model com estrutura base',
                'make:request {Nome}'    => 'Cria um FormRequest com validações',
                'make:service {Nome}'    => 'Cria um Service com estrutura base',
                'make:repository {Nome}' => 'Cria um Repository com estrutura base',
                'make:seed {Nome}'       => 'Cria um Seeder com exemplo funcional',
                'make:view {nome}'       => 'Cria as views index/show/create/edit',
            ],
            'Banco de dados' => [
                'migrate'          => 'Executa todas as migrations pendentes',
                'migrate --fresh'  => 'Recria o banco do zero (DROP + migrate)',
            ],
            'Servidor' => [
                'serve'              => 'Inicia o servidor local na porta 8000',
                'serve --port=8080'  => 'Inicia o servidor em porta específica',
            ],
            'Informações' => [
                'list'   => 'Lista todos os comandos disponíveis',
                'help'   => 'Exibe esta mensagem de ajuda',
            ],
        ];

        Output::title("Comandos disponíveis");

        foreach ($groups as $group => $cmds) {
            Output::subtitle($group);
            foreach ($cmds as $cmd => $desc) {
                Output::command($cmd, $desc);
            }
            Output::newline();
        }
    }
}
