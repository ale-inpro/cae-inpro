<?php

declare(strict_types=1);

namespace App\Services;

final class HaciendaDocumentIntakeService
{
    public static function isHaciendaDocumentTypeName(string $docTypeName): bool
    {
        return trim($docTypeName) === CaeReadinessService::DOCUMENT_TYPE_NAME_HACIENDA;
    }

    /**
     * @return array{
     *   csv: ?string,
     *   needs_manual: bool,
     *   notes: string,
     *   candidates: list<string>
     * }
     */
    public function evaluateUploadedPdf(string $absolutePdfPath): array
    {
        $ex = (new AeatCsvExtractionService())->extractFromPdfPath($absolutePdfPath);
        $csv = isset($ex['csv']) ? (string) $ex['csv'] : null;
        $candidates = is_array($ex['candidates'] ?? null) ? $ex['candidates'] : [];

        if (($ex['error'] ?? null) !== null && ($ex['error'] ?? '') !== '') {
            return [
                'csv' => null,
                'needs_manual' => true,
                'notes' => 'No se pudo leer CSV del PDF: ' . (string) $ex['error'],
                'candidates' => $candidates,
            ];
        }

        if ($csv === null) {
            $msg = count($candidates) === 0
                ? 'No se encontró ningún CSV en el PDF. Indícalo manualmente al aprobar (16 caracteres).'
                : 'Varios candidatos CSV (' . implode(', ', $candidates) . '). Indica el CSV correcto manualmente.';

            return [
                'csv' => null,
                'needs_manual' => true,
                'notes' => $msg,
                'candidates' => $candidates,
            ];
        }

        return [
            'csv' => $csv,
            'needs_manual' => false,
            'notes' => 'CSV detectado automáticamente: ' . $csv,
            'candidates' => $candidates,
        ];
    }

    public function resolveCsvForApproval(?string $detectedCsv, string $manualRaw): ?string
    {
        $manual = (new AeatCsvExtractionService())->normalizeCsv($manualRaw);
        if ($manual !== null) {
            return $manual;
        }
        if ($detectedCsv !== null && $detectedCsv !== '') {
            return (new AeatCsvExtractionService())->normalizeCsv($detectedCsv);
        }

        return null;
    }
}