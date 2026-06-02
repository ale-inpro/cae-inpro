<?php

declare(strict_types=1);

namespace App\Services\Rgpd;

use Dompdf\Dompdf;
use Dompdf\Options;

final class RgpdSignedPdfService
{
    /**
     * @param list<array<string,mixed>> $docs
     */
    public static function renderResidentSignedBundle(
        string $communityName,
        string $residentName,
        array $docs
    ): string {
        $html = self::buildHtml($communityName, $residentName, $docs);

        $opt = new Options();
        $opt->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($opt);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * @param list<array<string,mixed>> $docs
     */
    public static function renderCommunitySignedBundle(
        string $communityName,
        array $docs
    ): string {
        $html = self::buildCommunityHtml($communityName, $docs);

        $opt = new Options();
        $opt->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($opt);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * @param list<array<string,mixed>> $docs
     */
    private static function buildHtml(string $communityName, string $residentName, array $docs): string
    {
        $safeCommunity = htmlspecialchars($communityName, ENT_QUOTES, 'UTF-8');
        $safeResident = htmlspecialchars($residentName, ENT_QUOTES, 'UTF-8');
        $generatedAt = date('d/m/Y H:i');

        $blocks = '';
        foreach ($docs as $idx => $doc) {
            $templateName = htmlspecialchars((string) ($doc['template_name'] ?? 'Documento RGPD'), ENT_QUOTES, 'UTF-8');
            $status = (string) ($doc['status'] ?? 'pending');
            $signedAt = htmlspecialchars((string) ($doc['signed_at_label'] ?? '—'), ENT_QUOTES, 'UTF-8');
            $ip = htmlspecialchars((string) ($doc['signer_ip'] ?? '—'), ENT_QUOTES, 'UTF-8');
            $ua = htmlspecialchars((string) ($doc['signer_user_agent'] ?? '—'), ENT_QUOTES, 'UTF-8');
            $bodyHtml = (string) ($doc['rendered_html'] ?? '');

            $pageBreak = $idx > 0 ? 'page-break-before: always;' : '';

            $signatureHtml = '';
            $sigDataUri = trim((string) ($doc['signature_data_uri'] ?? ''));
            if ($sigDataUri !== '') {
                $signatureHtml = '<div class="sig-box"><img src="' . $sigDataUri . '" alt="Firma" /></div>';
            } elseif ($status === 'paper') {
                $signatureHtml = '<div class="sig-paper">Firmado en papel</div>';
            } else {
                $signatureHtml = '<div class="sig-paper">Sin imagen de firma</div>';
            }

            $blocks .= <<<HTML
            <section class="doc" style="{$pageBreak}">
                <header class="doc-h">
                    <h1>{$templateName}</h1>
                    <div class="meta">Comunidad: {$safeCommunity} · Vecino: {$safeResident}</div>
                </header>

                <article class="content">{$bodyHtml}</article>

                <footer class="evidence">
                    <h3>Evidencias de firma</h3>
                    <table>
                        <tr><td><strong>Estado:</strong></td><td>{$status}</td></tr>
                        <tr><td><strong>Fecha firma:</strong></td><td>{$signedAt}</td></tr>
                        <tr><td><strong>IP:</strong></td><td>{$ip}</td></tr>
                        <tr><td><strong>User-Agent:</strong></td><td>{$ua}</td></tr>
                    </table>
                    {$signatureHtml}
                </footer>
            </section>
            HTML;
        }

        return <<<HTML
        <!doctype html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #111827; }
                .cover { margin-bottom: 16px; padding-bottom: 8px; border-bottom: 2px solid #059669; }
                .cover h1 { margin: 0; font-size: 16px; color: #065f46; }
                .cover .meta { color: #6b7280; margin-top: 4px; }
                .doc-h h1 { margin: 0 0 4px; font-size: 15px; color: #111827; }
                .doc-h .meta { color: #6b7280; margin-bottom: 12px; }
                .content { border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px; }
                .evidence { margin-top: 14px; border-top: 1px solid #e5e7eb; padding-top: 10px; }
                .evidence h3 { margin: 0 0 8px; font-size: 12px; }
                .evidence table { width: 100%; border-collapse: collapse; }
                .evidence td { padding: 2px 0; vertical-align: top; }
                .sig-box { margin-top: 10px; border: 1px dashed #9ca3af; padding: 8px; width: 260px; }
                .sig-box img { max-width: 240px; max-height: 90px; }
                .sig-paper { margin-top: 10px; padding: 6px 8px; background: #f3f4f6; border-radius: 4px; display: inline-block; }
            </style>
        </head>
        <body>
            <div class="cover">
                <h1>Documentos firmados RGPD</h1>
                <div class="meta">Comunidad: {$safeCommunity}</div>
                <div class="meta">Vecino: {$safeResident}</div>
                <div class="meta">Generado: {$generatedAt}</div>
            </div>
            {$blocks}
        </body>
        </html>
        HTML;
    }

        /**
     * @param list<array<string,mixed>> $docs
     */
    private static function buildCommunityHtml(string $communityName, array $docs): string
    {
        $safeCommunity = htmlspecialchars($communityName, ENT_QUOTES, 'UTF-8');
        $generatedAt = date('d/m/Y H:i');

        $blocks = '';
        foreach ($docs as $idx => $doc) {
            $templateName = htmlspecialchars((string) ($doc['template_name'] ?? 'Documento RGPD'), ENT_QUOTES, 'UTF-8');
            $residentName = htmlspecialchars((string) ($doc['resident_name'] ?? '—'), ENT_QUOTES, 'UTF-8');
            $status = (string) ($doc['status'] ?? 'pending');
            $signedAt = htmlspecialchars((string) ($doc['signed_at_label'] ?? '—'), ENT_QUOTES, 'UTF-8');
            $ip = htmlspecialchars((string) ($doc['signer_ip'] ?? '—'), ENT_QUOTES, 'UTF-8');
            $ua = htmlspecialchars((string) ($doc['signer_user_agent'] ?? '—'), ENT_QUOTES, 'UTF-8');
            $bodyHtml = (string) ($doc['rendered_html'] ?? '');

            $pageBreak = $idx > 0 ? 'page-break-before: always;' : '';

            $signatureHtml = '';
            $sigDataUri = trim((string) ($doc['signature_data_uri'] ?? ''));
            if ($sigDataUri !== '') {
                $signatureHtml = '<div class="sig-box"><img src="' . $sigDataUri . '" alt="Firma" /></div>';
            } elseif ($status === 'paper') {
                $signatureHtml = '<div class="sig-paper">Firmado en papel</div>';
            } else {
                $signatureHtml = '<div class="sig-paper">Sin imagen de firma</div>';
            }

            $blocks .= <<<HTML
            <section class="doc" style="{$pageBreak}">
                <header class="doc-h">
                    <h1>{$templateName}</h1>
                    <div class="meta">Comunidad: {$safeCommunity} · Vecino: {$residentName}</div>
                </header>

                <article class="content">{$bodyHtml}</article>

                <footer class="evidence">
                    <h3>Evidencias de firma</h3>
                    <table>
                        <tr><td><strong>Estado:</strong></td><td>{$status}</td></tr>
                        <tr><td><strong>Fecha firma:</strong></td><td>{$signedAt}</td></tr>
                        <tr><td><strong>IP:</strong></td><td>{$ip}</td></tr>
                        <tr><td><strong>User-Agent:</strong></td><td>{$ua}</td></tr>
                    </table>
                    {$signatureHtml}
                </footer>
            </section>
            HTML;
        }

        return <<<HTML
        <!doctype html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #111827; }
                .cover { margin-bottom: 16px; padding-bottom: 8px; border-bottom: 2px solid #059669; }
                .cover h1 { margin: 0; font-size: 16px; color: #065f46; }
                .cover .meta { color: #6b7280; margin-top: 4px; }
                .doc-h h1 { margin: 0 0 4px; font-size: 15px; color: #111827; }
                .doc-h .meta { color: #6b7280; margin-bottom: 12px; }
                .content { border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px; }
                .evidence { margin-top: 14px; border-top: 1px solid #e5e7eb; padding-top: 10px; }
                .evidence h3 { margin: 0 0 8px; font-size: 12px; }
                .evidence table { width: 100%; border-collapse: collapse; }
                .evidence td { padding: 2px 0; vertical-align: top; }
                .sig-box { margin-top: 10px; border: 1px dashed #9ca3af; padding: 8px; width: 260px; }
                .sig-box img { max-width: 240px; max-height: 90px; }
                .sig-paper { margin-top: 10px; padding: 6px 8px; background: #f3f4f6; border-radius: 4px; display: inline-block; }
            </style>
        </head>
        <body>
            <div class="cover">
                <h1>Documentos firmados RGPD · Comunidad</h1>
                <div class="meta">Comunidad: {$safeCommunity}</div>
                <div class="meta">Generado: {$generatedAt}</div>
            </div>
            {$blocks}
        </body>
        </html>
        HTML;
    }
}