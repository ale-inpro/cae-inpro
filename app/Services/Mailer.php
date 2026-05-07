<?php

declare(strict_types=1);

namespace App\Services;

use Resend;

final class Mailer
{
    private static ?array $config = null;

    private static function config(): array
    {
        if (self::$config === null) {
            self::$config = require dirname(__DIR__, 2) . '/config/mail.php';
        }
        return self::$config;
    }

    /**
     * Envía un email HTML.
     *
     * @param string|string[] $to  Un email o array de emails destinatarios.
     */
    public static function send(
        string|array $to,
        string $subject,
        string $htmlBody
    ): bool {
        try {
            $cfg    = self::config();
            $client = Resend::client((string) ($cfg['resend_api_key'] ?? ''));

            $toAddresses = is_array($to) ? $to : [$to];

            $intercept = (string) ($cfg['intercept_to'] ?? '');
            if ($intercept !== '') {
                $toAddresses = [$intercept];
            }

            $client->emails->send([
                'from'    => (string) ($cfg['from'] ?? 'onboarding@resend.dev'),
                'to'      => $toAddresses,
                'subject' => $subject,
                'html'    => $htmlBody,
            ]);

            return true;

        } catch (\Throwable) {
            // Silencioso en producción; añade aquí un log si quieres
            return false;
        }
    }

    /**
     * Genera el HTML base con el diseño corporativo de la app.
     */
    public static function template(string $title, string $bodyHtml): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <title>{$title}</title>
            <style>
                body  { font-family: 'Segoe UI', Arial, sans-serif; background:#f4f4f4; margin:0; padding:0; }
                .wrap { max-width:600px; margin:30px auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.08); }
                .hdr  { background:#1a6b3a; color:#fff; padding:22px 30px; }
                .hdr h1 { margin:0; font-size:1.1rem; font-weight:700; letter-spacing:.4px; }
                .bdy  { padding:26px 30px; color:#333; line-height:1.65; }
                .bdy h2 { color:#1a6b3a; margin-top:0; font-size:1rem; }
                .divider { border:none; border-top:1px solid #eee; margin:18px 0; }
                .ftr  { background:#f0f0f0; padding:12px 30px; font-size:.76rem; color:#999; }
            </style>
        </head>
        <body>
            <div class="wrap">
                <div class="hdr"><h1>INPRO · Gestión CAE</h1></div>
                <div class="bdy">{$bodyHtml}</div>
                <div class="ftr">Mensaje automático del sistema. No respondas a este correo.</div>
            </div>
        </body>
        </html>
        HTML;
    }
}