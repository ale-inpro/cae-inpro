<?php

declare(strict_types=1);

namespace App\Services\Rgpd;

use Dompdf\Dompdf;
use Dompdf\Options;
use ZipArchive;

final class RgpdBlankPdfZipService
{
    /**
     * @param list<array{filename: string, pdf_bytes: string}> $files
     */
    public static function buildZip(array $files): string
    {
        if ($files === []) {
            throw new \InvalidArgumentException('No hay documentos para empaquetar.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'rgpd_zip_');
        if ($tmp === false) {
            throw new \RuntimeException('No se pudo crear archivo temporal.');
        }

        $zipPath = $tmp . '.zip';
        @rename($tmp, $zipPath);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo crear el ZIP.');
        }

        $used = [];
        foreach ($files as $file) {
            $name = self::uniqueFilename((string) ($file['filename'] ?? 'documento.pdf'), $used);
            $zip->addFromString($name, (string) ($file['pdf_bytes'] ?? ''));
            $used[$name] = true;
        }
        $zip->close();

        $bytes = file_get_contents($zipPath);
        @unlink($zipPath);

        if ($bytes === false) {
            throw new \RuntimeException('No se pudo leer el ZIP generado.');
        }

        return $bytes;
    }

    public static function renderBlankPdf(
        string $communityName,
        string $residentName,
        string $templateName,
        string $renderedHtml
    ): string {
        $safeCommunity = htmlspecialchars($communityName, ENT_QUOTES, 'UTF-8');
        $safeResident = htmlspecialchars($residentName, ENT_QUOTES, 'UTF-8');
        $safeTemplate = htmlspecialchars($templateName, ENT_QUOTES, 'UTF-8');
        $generatedAt = date('d/m/Y H:i');

        $html = <<<HTML
        <!doctype html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #111827; }
                .doc-h h1 { margin: 0 0 4px; font-size: 15px; }
                .doc-h .meta { color: #6b7280; margin-bottom: 12px; }
                .content { border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px; }
                .sig-line { margin-top: 24px; border-top: 1px solid #9ca3af; padding-top: 8px; color: #6b7280; font-size: 10px; }
            </style>
        </head>
        <body>
            <header class="doc-h">
                <h1>{$safeTemplate}</h1>
                <div class="meta">Comunidad: {$safeCommunity} · Vecino: {$safeResident}</div>
                <div class="meta">Plantilla generada: {$generatedAt} · Pendiente de firma</div>
            </header>
            <article class="content">{$renderedHtml}</article>
            <div class="sig-line">Firma del interesado: _______________________________</div>
        </body>
        </html>
        HTML;

        $opt = new Options();
        $opt->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($opt);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    public static function slug(string $value, int $max = 40): string
    {
        $s = preg_replace('/[^A-Za-z0-9_-]+/', '_', trim($value)) ?: 'doc';

        return substr($s, 0, $max);
    }

    /** @param array<string, true> $used */
    private static function uniqueFilename(string $filename, array &$used): string
    {
        $base = $filename !== '' ? $filename : 'documento.pdf';
        if (!str_ends_with(strtolower($base), '.pdf')) {
            $base .= '.pdf';
        }
        if (!isset($used[$base])) {
            return $base;
        }
        $i = 2;
        $stem = preg_replace('/\.pdf$/i', '', $base) ?: 'documento';
        do {
            $candidate = $stem . '_' . $i . '.pdf';
            $i++;
        } while (isset($used[$candidate]));

        return $candidate;
    }
}