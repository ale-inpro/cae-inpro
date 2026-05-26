<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\DateDisplay;

/**
 * Textos de cara al usuario para intake y validez de complementarios.
 * No exponer prefijos de pipeline ([Python], [Vision], etc.).
 */
final class DocumentIntakePresentationService
{
    /**
     * Limpia notas antes de guardar o al mostrar (registros antiguos en BD).
     */
    public static function humanizeNotes(?string $raw): string
    {
        $s = trim((string) $raw);
        if ($s === '') {
            return 'No se ha podido validar el documento de forma automática.';
        }

        // Quitar prefijos técnicos antiguos ([Python], [Vision gpt-4o], etc.)
        do {
            $prev = $s;
            $s = preg_replace('/\[(?:Python|Vision[^\]]*|Fallback[^\]]*)\]\s*/iu', '', $s) ?? $s;
            $s = trim($s);
        } while ($s !== $prev && $s !== '');

        $lower = mb_strtolower($s);
        $map = [
            'no se encontró información relevante' => 'No se han detectado fechas ni datos de vigencia en el archivo.',
            'no se encontro informacion relevante' => 'No se han detectado fechas ni datos de vigencia en el archivo.',
            'vision api error' => 'No se ha podido analizar el documento automáticamente.',
            'vision fallback' => 'No se ha podido analizar el documento automáticamente.',
            'api key no disponible' => 'El análisis automático no está disponible en este momento.',
            'no se pudo convertir' => 'El formato del archivo no permite extraer fechas automáticamente.',
            'no se pudo extraer texto' => 'No se ha podido leer el contenido del PDF.',
            'respuesta ia no parseable' => 'El análisis automático no ha devuelto un resultado utilizable.',
        ];
        foreach ($map as $needle => $replacement) {
            if (str_contains($lower, $needle)) {
                return $replacement;
            }
        }

        if (strlen($s) > 220) {
            $s = mb_substr($s, 0, 217) . '…';
        }

        return $s;
    }

    /** @return array{label: string, badge: string} */
    public static function intakeStatusMeta(string $status): array
    {
        return match ($status) {
            'approved' => ['label' => 'Válido (automático)', 'badge' => 'text-bg-success'],
            'in_review' => ['label' => 'Revisar fechas', 'badge' => 'text-bg-warning text-dark'],
            'rejected' => ['label' => 'No válido (automático)', 'badge' => 'text-bg-danger'],
            default => ['label' => 'Revisión manual', 'badge' => 'text-bg-warning text-dark'],
        };
    }

    public static function confidenceLabel(?float $confidence): string
    {
        if ($confidence === null) {
            return '—';
        }
        $c = max(0.0, min(1.0, $confidence));
        if ($c >= 0.75) {
            return 'Alta';
        }
        if ($c >= 0.4) {
            return 'Media';
        }
        if ($c > 0.0) {
            return 'Baja';
        }
        return 'No calculada';
    }

    public static function formatExpiryLabel(?string $date): string
    {
        $d = trim((string) $date);
        if ($d === '') {
            return 'Sin fecha detectada';
        }
        $formatted = DateDisplay::date($d, '');
        return $formatted !== '' ? $formatted : 'Sin fecha detectada';
    }

    /**
     * Para filas de cae_document_intake en revisión manual.
     *
     * @param array<string, mixed> $row
     * @return array{
     *   status_label: string,
     *   status_badge: string,
     *   reason: string,
     *   confidence_label: string,
     *   expiry_label: string
     * }
     */
    public static function presentPendingIntake(array $row): array
    {
        $status = (string) ($row['ai_status'] ?? 'manual_review');
        $meta = self::intakeStatusMeta($status);

        return [
            'status_label' => $meta['label'],
            'status_badge' => $meta['badge'],
            'reason' => self::humanizeNotes($row['ai_notes'] ?? null),
            'confidence_label' => self::confidenceLabel(isset($row['ai_confidence']) ? (float) $row['ai_confidence'] : null),
            'expiry_label' => self::formatExpiryLabel($row['ai_expires_at'] ?? null),
        ];
    }

    /**
     * Primera línea de motivo para documentos publicados (cae_validity).
     *
     * @param array<string, mixed>|null $caeValidity
     */
    public static function validityPrimaryReason(?array $caeValidity): string
    {
        if (!is_array($caeValidity)) {
            return '—';
        }
        $summary = trim((string) ($caeValidity['summary'] ?? ''));
        if ($summary !== '') {
            return DateDisplay::humanizeText($summary);
        }
        $lines = $caeValidity['detail_lines'] ?? [];
        if (is_array($lines) && isset($lines[0])) {
            return DateDisplay::humanizeText(trim((string) $lines[0]));
        }
        return DateDisplay::humanizeText(trim((string) ($caeValidity['label'] ?? '—')));
    }

    /**
     * Segunda línea opcional (vigencia / AEAT) sin repetir el badge.
     *
     * @param array<string, mixed>|null $caeValidity
     */
    public static function validitySecondaryLine(?array $caeValidity, ?string $expiresAt): string
    {
        $parts = [];
        $exp = trim((string) $expiresAt);
        if ($exp !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $exp)) {
            $parts[] = 'Vigencia hasta ' . self::formatExpiryLabel($exp);
        }
        if (!is_array($caeValidity)) {
            return DateDisplay::humanizeText(implode(' · ', $parts));
        }
        $lines = $caeValidity['detail_lines'] ?? [];
        if (is_array($lines)) {
            foreach ($lines as $i => $line) {
                if ($i === 0) {
                    continue;
                }
                $t = trim((string) $line);
                if ($t !== '' && !str_starts_with(mb_strtolower($t), 'vigencia del documento')) {
                    $parts[] = DateDisplay::humanizeText($t);
                }
            }
        }
        $line = implode(' · ', array_slice($parts, 0, 2));

        return DateDisplay::humanizeText($line);
    }
}