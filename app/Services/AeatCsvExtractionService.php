<?php

declare(strict_types=1);

namespace App\Services;

use Smalot\PdfParser\Parser;

/**
 * Extrae posibles CSV AEAT (16 caracteres alfanuméricos) del texto de un PDF.
 */
final class AeatCsvExtractionService
{
    private const CSV_PATTERN = '/\b([A-Z0-9]{16})\b/';

    /**
     * @return array{
     *   candidates: list<string>,
     *   csv: ?string,
     *   error: ?string
     * }
     */
    public function extractFromPdfPath(string $absolutePath): array
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return ['candidates' => [], 'csv' => null, 'error' => 'Archivo no legible'];
        }

        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            return ['candidates' => [], 'csv' => null, 'error' => 'No es PDF'];
        }

        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($absolutePath);
            $text = strtoupper($pdf->getText());
        } catch (\Throwable $e) {
            return ['candidates' => [], 'csv' => null, 'error' => 'PDF: ' . $e->getMessage()];
        }

        if ($text === '') {
            return ['candidates' => [], 'csv' => null, 'error' => 'Sin texto extraíble'];
        }

        preg_match_all(self::CSV_PATTERN, $text, $m);
        /** @var list<string> $candidates */
        $candidates = array_values(array_unique($m[1] ?? []));

        $csv = count($candidates) === 1 ? $candidates[0] : null;

        return ['candidates' => $candidates, 'csv' => $csv, 'error' => null];
    }

    public function normalizeCsv(string $raw): ?string
    {
        $s = strtoupper(trim($raw));
        if (strlen($s) !== 16 || !ctype_alnum($s)) {
            return null;
        }
        return $s;
    }
}