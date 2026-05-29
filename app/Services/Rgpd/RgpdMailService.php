<?php
declare(strict_types=1);
namespace App\Services\Rgpd;

use App\Services\Mailer;

final class RgpdMailService
{
    public static function sendSignatureInvite(
        string $toEmail,
        string $residentName,
        string $communityName,
        string $templateName,
        string $signUrl
    ): bool {
        $body = '<h2>Documento pendiente de firma</h2>'
            . '<p>Hola <strong>' . htmlspecialchars($residentName) . '</strong>,</p>'
            . '<p>La comunidad <strong>' . htmlspecialchars($communityName) . '</strong> le solicita firmar:</p>'
            . '<p><em>' . htmlspecialchars($templateName) . '</em></p>'
            . self::ctaButton($signUrl, 'Revisar y firmar', '#1a6b3a')
            . self::fallbackLink($signUrl);

        return Mailer::send(
            $toEmail,
            'INPRO · Firma RGPD — ' . $communityName,
            Mailer::template('Firma RGPD', $body)
        );
    }

    public static function sendSignatureReminder(
        string $toEmail,
        string $residentName,
        string $communityName,
        string $templateName,
        string $signUrl
    ): bool {
        $body = '<h2>Recordatorio: documento pendiente de firma</h2>'
            . '<p>Hola <strong>' . htmlspecialchars($residentName) . '</strong>,</p>'
            . '<p>Le recordamos que aún tiene pendiente firmar el documento <em>'
            . htmlspecialchars($templateName) . '</em> de la comunidad <strong>'
            . htmlspecialchars($communityName) . '</strong>.</p>'
            . '<p style="color:#92400e;background:#fffbeb;padding:12px 14px;border-radius:8px;font-size:14px">'
            . 'Este enlace sustituye al anterior. Use este para acceder y firmar.</p>'
            . self::ctaButton($signUrl, 'Firmar ahora', '#b45309')
            . self::fallbackLink($signUrl);

        return Mailer::send(
            $toEmail,
            'INPRO · Recordatorio firma RGPD — ' . $communityName,
            Mailer::template('Recordatorio firma RGPD', $body)
        );
    }

    private static function ctaButton(string $signUrl, string $label, string $bg): string
    {
        return '<p style="margin:24px 0"><a href="' . htmlspecialchars($signUrl) . '" '
            . 'style="background:' . $bg . ';color:#fff;padding:12px 20px;border-radius:8px;text-decoration:none">'
            . htmlspecialchars($label) . '</a></p>';
    }

    private static function fallbackLink(string $signUrl): string
    {
        return '<p class="small text-muted">Si el botón no funciona, copie este enlace:<br>'
            . htmlspecialchars($signUrl) . '</p>';
    }
}