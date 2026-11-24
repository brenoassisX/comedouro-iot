<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Envio de e-mail de texto simples. Usa mail() por enquanto; para produção,
 * troque o corpo de enviar() por PHPMailer/SMTP sem mexer nos controllers.
 */
final class Mailer
{
    public static function enviar(string $para, string $assunto, string $corpo): bool
    {
        $cfg = (require dirname(__DIR__) . '/Config/config.php')['mail'];

        // Também evita header injection (quebras de linha no endereço)
        if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (($cfg['driver'] ?? 'mail') === 'log') {
            error_log("[Mailer:log] Para: {$para} | Assunto: {$assunto}\n{$corpo}");
            return true;
        }

        $headers = [
            'From: =?UTF-8?B?' . base64_encode($cfg['from_nome']) . '?= <' . $cfg['from'] . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        $ok = mail($para, '=?UTF-8?B?' . base64_encode($assunto) . '?=', $corpo, implode("\r\n", $headers));
        if (!$ok) {
            error_log('[Mailer] mail() falhou ao enviar para ' . $para);
        }
        return $ok;
    }
}
