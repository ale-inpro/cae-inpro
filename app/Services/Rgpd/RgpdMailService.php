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
            . '<p style="margin:24px 0"><a href="' . htmlspecialchars($signUrl) . '" '
            . 'style="background:#1a6b3a;color:#fff;padding:12px 20px;border-radius:8px;text-decoration:none">'
            . 'Revisar y firmar</a></p>'
            . '<p class="small text-muted">Si el botón no funciona, copie este enlace:<br>' . htmlspecialchars($signUrl) . '</p>';
        return Mailer::send($toEmail, 'INPRO · Firma RGPD — ' . $communityName, Mailer::template('Firma RGPD', $body));
    }
}