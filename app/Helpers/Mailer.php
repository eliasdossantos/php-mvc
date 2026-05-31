<?php

namespace App\Helpers;

/**
 * Mailer — Envio de E-mails Transacionais
 * ─────────────────────────────────────────────────────────────────────────────
 * Wrapper simples sobre PHPMailer com suporte a 3 drivers:
 *
 *   smtp → envia de verdade (produção)
 *   log  → grava em storage/logs/mail.log (homologação)
 *   dev  → não envia, retorna conteúdo no array de retorno (desenvolvimento)
 *
 * Configuração via config/mail.php + .env
 *
 * Uso:
 *   $mailer = new Mailer();
 *   $result = $mailer->send(
 *       to:      'destinatario@example.com',
 *       subject: 'Assunto do e-mail',
 *       body:    '<h1>Olá!</h1><p>Conteúdo HTML aqui.</p>',
 *       altBody: 'Versão em texto puro.'
 *   );
 *
 *   if ($result['success']) { ... }
 */
class Mailer
{
    private array $config;

    public function __construct()
    {
        $this->config = require CONFIG_PATH . '/mail.php';
    }

    /**
     * Envia um e-mail.
     *
     * @return array{success: bool, message: string, dev_body?: string}
     */
    public function send(
        string $to,
        string $subject,
        string $body,
        string $altBody = '',
        string $toName  = ''
    ): array {
        return match ($this->config['driver']) {
            'smtp' => $this->sendSmtp($to, $toName, $subject, $body, $altBody),
            'log'  => $this->sendLog($to, $subject, $body),
            default => $this->sendDev($to, $subject, $body),
        };
    }

    // ── Drivers ───────────────────────────────────────────────────────────────

    private function sendSmtp(string $to, string $toName, string $subject, string $body, string $altBody): array
    {
        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            return ['success' => false, 'message' => 'PHPMailer não instalado. Execute: composer require phpmailer/phpmailer'];
        }

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $smtp = $this->config['smtp'];

            $mail->isSMTP();
            $mail->Host       = $smtp['host'];
            $mail->SMTPAuth   = !empty($smtp['username']);
            $mail->Username   = $smtp['username'];
            $mail->Password   = $smtp['password'];
            $mail->SMTPSecure = $smtp['encryption'] === 'ssl'
                ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int) $smtp['port'];
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom($this->config['from_email'], $this->config['from_name']);
            $mail->addAddress($to, $toName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = $altBody ?: strip_tags($body);

            $mail->send();
            return ['success' => true, 'message' => 'E-mail enviado.'];
        } catch (\Exception $e) {
            \Core\Logger::error('Falha ao enviar e-mail', ['to' => $to, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function sendLog(string $to, string $subject, string $body): array
    {
        $logDir  = STORAGE_PATH . '/logs';
        $logFile = $logDir . '/mail.log';
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);

        $entry = sprintf(
            "[%s] TO: %s | SUBJECT: %s\n%s\n%s\n",
            date('Y-m-d H:i:s'), $to, $subject, $body, str_repeat('-', 80)
        );

        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
        return ['success' => true, 'message' => 'E-mail gravado em log.'];
    }

    private function sendDev(string $to, string $subject, string $body): array
    {
        \Core\Logger::debug('Mailer [dev] — e-mail não enviado', ['to' => $to, 'subject' => $subject]);
        return ['success' => true, 'message' => 'Dev mode: e-mail não enviado.', 'dev_body' => $body];
    }
}
