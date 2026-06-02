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
     * Extrae CSV del PDF. Auto-aprueba si la lectura es fiable (sin llamar a AEAT en subida).
     *
     * @return array{
     *   csv: ?string,
     *   prefill_csv: ?string,
     *   needs_manual: bool,
     *   notes: string,
     *   candidates: list<string>,
     *   extraction_method: ?string
     * }
     */
    public function evaluateUploadedPdf(string $absolutePdfPath): array
    {
        $ex = (new AeatCsvExtractionService())->extractFromPdfPath($absolutePdfPath);
        $extractedCsv = isset($ex['csv']) ? (string) $ex['csv'] : null;
        $extractedCsv = $extractedCsv !== '' ? $extractedCsv : null;
        $candidates = is_array($ex['candidates'] ?? null) ? $ex['candidates'] : [];
        $suggested = isset($ex['suggested_csv']) ? trim((string) $ex['suggested_csv']) : '';
        $suggested = $suggested !== '' ? $suggested : null;
        $method = isset($ex['extraction_method']) ? trim((string) $ex['extraction_method']) : null;
        $method = $method !== '' ? $method : null;

        $candidate = $extractedCsv ?? $suggested;

        if ($candidate === null) {
            if (($ex['error'] ?? null) !== null && ($ex['error'] ?? '') !== '') {
                $msg = 'No se pudo leer CSV del PDF: ' . (string) $ex['error'];
            } elseif (count($candidates) === 0) {
                $msg = 'No se encontró ningún CSV en el PDF. Indícalo manualmente al aprobar (16 caracteres).';
            } else {
                $msg = 'Varios candidatos CSV (' . implode(', ', $candidates) . '). Indica el CSV correcto manualmente.';
            }

            return [
                'csv' => null,
                'prefill_csv' => $suggested ?? ($candidates[0] ?? null),
                'needs_manual' => true,
                'notes' => $msg,
                'candidates' => $candidates,
                'extraction_method' => $method,
            ];
        }

        if ($this->isExtractionReliable($ex, $candidate, $candidates)) {
            $notes = 'CSV detectado';
            if ($method !== null) {
                $notes .= ' (' . $method . ')';
            }
            $notes .= ': ' . $candidate;

            return [
                'csv' => $candidate,
                'prefill_csv' => $candidate,
                'needs_manual' => false,
                'notes' => $notes,
                'candidates' => $candidates !== [] ? $candidates : [$candidate],
                'extraction_method' => $method,
            ];
        }

        $notes = 'Confirma el CSV del pie del certificado (16 caracteres).';
        if ($method === 'vision_gpt4o') {
            $notes = 'CSV leído por visión IA; confirma que coincide con el pie del documento.';
        } elseif ($method !== null && str_starts_with($method, 'tesseract')) {
            $notes = 'CSV leído por OCR; confirma los 16 caracteres en el documento.';
        }
        $exNotes = trim((string) ($ex['notes'] ?? ''));
        if ($exNotes !== '') {
            $notes .= ' ' . $exNotes;
        }

        return [
            'csv' => null,
            'prefill_csv' => $candidate,
            'needs_manual' => true,
            'notes' => $notes,
            'candidates' => $candidates !== [] ? $candidates : [$candidate],
            'extraction_method' => $method,
        ];
    }

    /**
     * @param array<string, mixed> $ex
     * @param list<string> $candidates
     */
    private function isExtractionReliable(array $ex, string $csv, array $candidates): bool
    {
        if ($csv === '' || count($candidates) !== 1) {
            return false;
        }

        $method = trim((string) ($ex['extraction_method'] ?? ''));

        if ($method === 'pdf_native' || $method === 'pdfplumber') {
            return true;
        }

        return !empty($ex['extraction_reliable']);
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