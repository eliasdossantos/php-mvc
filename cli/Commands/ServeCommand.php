<?php

namespace Cli\Commands;

use Cli\Command;
use Cli\Output;

/**
 * serve — Inicia o servidor de desenvolvimento PHP built-in
 *
 * Uso:
 *   php mvc serve                  ← localhost:8000
 *   php mvc serve --port=8080      ← porta customizada
 *   php mvc serve --host=0.0.0.0   ← aceita conexões externas (ex: rede local)
 */
class ServeCommand extends Command
{
    public function handle(): bool
    {
        $host      = $this->option('host', 'localhost');
        $port      = (int)$this->option('port', 8000);
        $publicDir = ROOT_PATH . DIRECTORY_SEPARATOR . 'public';

        if (!is_dir($publicDir)) {
            Output::error("Diretório public/ não encontrado em: {$publicDir}");
            return false;
        }

        // Garante que a porta está livre
        $socket = @fsockopen($host, $port, $errno, $errstr, 1);
        if ($socket) {
            fclose($socket);
            Output::error("A porta {$port} já está em uso.");
            Output::line("  Tente outra porta: <comment>php mvc serve --port=" . ($port + 1) . "</comment>");
            return false;
        }

        $appUrl  = "http://{$host}:{$port}";
        $appName = $_ENV['APP_NAME'] ?? 'PHP MVC Base';

        Output::newline();
        fwrite(STDOUT, "  🚀  {$appName} rodando em: {$appUrl}" . PHP_EOL);
        fwrite(STDOUT, "  📁  Document root: public/" . PHP_EOL);
        fwrite(STDOUT, "  🛑  Pressione Ctrl+C para parar." . PHP_EOL);
        Output::newline();

        // Comando do servidor built-in do PHP
        $cmd = sprintf(
            '%s -S %s:%d -t %s',
            PHP_BINARY,
            escapeshellarg($host),
            $port,
            escapeshellarg($publicDir)
        );

        // Executa o servidor — bloqueia até Ctrl+C
        passthru($cmd, $exitCode);

        return $exitCode === 0;
    }
}
