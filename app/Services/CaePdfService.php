<?php

declare(strict_types=1);

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

final class CaePdfService
{
    /** @param array<string,mixed> $tech @param array<string,mixed> $draft */
    public static function render(string $outputAbsolutePath, array $tech, array $draft): void
    {
        $html = self::template($tech, $draft);

        $opt = new Options();
        $opt->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($opt);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        file_put_contents($outputAbsolutePath, $dompdf->output());
    }

    /** @param array<string,mixed> $tech @param array<string,mixed> $draft */
    private static function template(array $tech, array $draft): string
    {
        $estado = (string) ($draft['conclusion_estado'] ?? 'in_review');
        $resumen = nl2br(htmlspecialchars((string) ($draft['resumen'] ?? '')));
        $obs = (array) ($draft['observaciones'] ?? []);
        $falt = (array) ($draft['faltantes'] ?? []);
        $campos = (array) ($draft['campos'] ?? []);

        $obsHtml = '';
        foreach ($obs as $o) $obsHtml .= '<li>' . htmlspecialchars((string) $o) . '</li>';
        $faltHtml = '';
        foreach ($falt as $f) $faltHtml .= '<li>' . htmlspecialchars((string) $f) . '</li>';

        $nombre = htmlspecialchars((string) ($campos['tecnico_nombre'] ?? $tech['full_name'] ?? ''));
        $email = htmlspecialchars((string) ($campos['tecnico_email'] ?? $tech['email'] ?? ''));
        $prof = htmlspecialchars((string) ($campos['profesion'] ?? $tech['professions'] ?? ''));
        $desde = htmlspecialchars((string) ($campos['valido_desde'] ?? date('Y-m-d')));
        $hasta = htmlspecialchars((string) ($campos['valido_hasta'] ?? date('Y-m-d', strtotime('+3 months'))));
        $generatedAt = date('Y-m-d H:i');
        $badgeClass = $estado === 'approved' ? 'ok' : 'warn';

        return <<<HTML
        <!doctype html>
        <html lang="es">
        <head>
        <meta charset="UTF-8">
        <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; color:#1f2937; font-size:12px; }
        .hdr { border-bottom:2px solid #0f766e; margin-bottom:16px; padding-bottom:8px; }
        .hdr h1 { margin:0; font-size:18px; color:#0f766e; }
        .meta { font-size:11px; color:#6b7280; }
        .card { border:1px solid #e5e7eb; border-radius:8px; padding:10px; margin-bottom:10px; }
        h2 { margin:0 0 8px; font-size:14px; color:#111827; }
        .badge { display:inline-block; padding:3px 8px; border-radius:999px; background:#e5e7eb; font-size:10px; }
        .ok { background:#d1fae5; color:#065f46; }
        .warn { background:#fef3c7; color:#92400e; }
        .foot { margin-top:24px; font-size:10px; color:#6b7280; border-top:1px solid #e5e7eb; padding-top:8px; }
        table { width:100%; border-collapse: collapse; }
        td { padding:4px 0; vertical-align:top; }
        </style>
        </head>
        <body>
          <div class="hdr">
            <h1>Certificado CAE</h1>
            <div class="meta">INPRO · Generado: {$generatedAt}</div>
          </div>

          <div class="card">
            <h2>Datos del técnico</h2>
            <table>
              <tr><td><strong>Nombre:</strong></td><td>{$nombre}</td></tr>
              <tr><td><strong>Email:</strong></td><td>{$email}</td></tr>
              <tr><td><strong>Profesión:</strong></td><td>{$prof}</td></tr>
              <tr><td><strong>Válido desde:</strong></td><td>{$desde}</td></tr>
              <tr><td><strong>Válido hasta:</strong></td><td>{$hasta}</td></tr>
            </table>
          </div>

          <div class="card">
            <h2>Conclusión</h2>
            <p><span class="badge {$badgeClass}">{$estado}</span></p>
            <p>{$resumen}</p>
          </div>

          <div class="card">
            <h2>Observaciones</h2>
            <ul>{$obsHtml}</ul>
          </div>

          <div class="card">
            <h2>Documentación pendiente / faltante</h2>
            <ul>{$faltHtml}</ul>
          </div>

          <div class="foot">
            Documento generado con asistencia de IA y validación administrativa.
          </div>
        </body>
        </html>
        HTML;
    }
}