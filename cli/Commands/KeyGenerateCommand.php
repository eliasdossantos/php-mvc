<?php

namespace Cli\Commands;

use Cli\Command;
use Cli\Output;

/**
 * key:generate — Gera uma chave de aplicação segura
 *
 * Uso:
 *   php mvc key:generate
 *
 * Funcionalidades:
 *   - Gera chave aleatória criptograficamente segura com random_bytes()
 *   - Exatamente 32 caracteres (em hexadecimal)
 *   - Localiza automaticamente o arquivo .env na raiz do projeto
 *   - Atualiza a variável APP_KEY ou a adiciona ao final
 *   - Exibe mensagem de sucesso ou erro
 */
class KeyGenerateCommand extends Command
{
    /**
     * Executa a geração da chave de aplicação
     */
    public function handle(): bool
    {
        $envPath = ROOT_PATH . '/.env';

        // Valida se arquivo .env existe
        if (!file_exists($envPath)) {
            Output::error("Arquivo .env não encontrado em: {$envPath}");
            Output::line("  Crie o arquivo .env a partir de .env.example ou manualmente.");
            return false;
        }

        // Gera chave segura de 32 caracteres
        $appKey = $this->generateSecureKey();

        Output::info("Gerando chave de aplicação…");

        // Atualiza ou adiciona a variável APP_KEY
        if ($this->updateEnvFile($envPath, $appKey)) {
            Output::success("Chave de aplicação gerada com sucesso!");
            Output::line("  <dim>APP_KEY={$appKey}</dim>");
            return true;
        }

        Output::error("Falha ao atualizar o arquivo .env");
        return false;
    }

    /**
     * Gera uma chave aleatória segura de 32 caracteres (hexadecimal)
     *
     * @return string Chave em formato hexadecimal
     */
    private function generateSecureKey(): string
    {
        // random_bytes() retorna 16 bytes (256 bits)
        // bin2hex() converte para 32 caracteres hexadecimais
        $randomBytes = random_bytes(16);
        return bin2hex($randomBytes);
    }

    /**
     * Atualiza ou adiciona a variável APP_KEY ao arquivo .env
     *
     * @param string $envPath Caminho absoluto do arquivo .env
     * @param string $appKey  Valor da chave gerada
     * @return bool True se bem-sucedido, false caso contrário
     */
    private function updateEnvFile(string $envPath, string $appKey): bool
    {
        try {
            $content = file_get_contents($envPath);

            // Verifica se APP_KEY já existe
            if (preg_match('/^APP_KEY\s*=\s*.*$/m', $content)) {
                // Substitui o valor existente
                $content = preg_replace(
                    '/^APP_KEY\s*=\s*.*$/m',
                    "APP_KEY={$appKey}",
                    $content
                );
            } else {
                // Adiciona ao final do arquivo (com quebra de linha se necessário)
                if ($content !== '' && substr($content, -1) !== "\n") {
                    $content .= "\n";
                }
                $content .= "APP_KEY={$appKey}\n";
            }

            // Escreve o conteúdo atualizado
            file_put_contents($envPath, $content);
            return true;
        } catch (\Throwable $e) {
            Output::error("Erro ao escrever no arquivo .env: " . $e->getMessage());
            return false;
        }
    }
}