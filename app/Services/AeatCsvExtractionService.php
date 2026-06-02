<?php

declare(strict_types=1);

namespace App\Services;

use Smalot\PdfParser\Parser;

/**
 * Extrae CSV AEAT (16 caracteres) de PDFs Hacienda.
 * Pipeline: Smalot (PDF nativo) → Python (pdfplumber/OCR/Vision).
 */
final class AeatCsvExtractionService
{
    private const CSV_PATTERN = '/\b([A-Z0-9]{16})\b/';

    /**
     * @return array{
     *   candidates: list<string>,
     *   csv: ?string,
     *   suggested_csv: ?string,
     *   extraction_method: ?string,
     *   extraction_reliable: bool,
     *   notes: ?string,
     *   error: ?string
     * }
     */
    public function extractFromPdfPath(string $absolutePath): array
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return $this->fail('Archivo no legible');
        }

        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            return $this->fail('No es PDF');
        }

        $native = $this->extractWithSmalot($absolutePath);
        if ($native['csv'] !== null) {
            return array_merge($native, [
                'suggested_csv' => $native['csv'],
                'extraction_method' => 'pdf_native',
                'extraction_reliable' => true,
                'notes' => 'CSV detectado automáticamente (PDF nativo): ' . $native['csv'],
                'error' => null,
            ]);
        }

        $python = $this->extractWithPython($absolutePath);
        if ($python !== null) {
            if ($python['csv'] !== null) {
                return $python;
            }

            if ($native['candidates'] !== []) {
                $python['candidates'] = array_values(array_unique(array_merge(
                    $python['candidates'],
                    $native['candidates']
                )));
            }

            return $python;
        }

        if (($native['error'] ?? null) !== null) {
            return array_merge($native, [
                'suggested_csv' => null,
                'extraction_method' => null,
                'extraction_reliable' => false,
                'notes' => null,
            ]);
        }

        if ($native['candidates'] === []) {
            return array_merge($native, [
                'suggested_csv' => null,
                'extraction_method' => 'pdf_native',
                'extraction_reliable' => false,
                'notes' => null,
                'error' => null,
            ]);
        }

        return [
            'candidates' => $native['candidates'],
            'csv' => null,
            'suggested_csv' => $native['candidates'][0] ?? null,
            'extraction_method' => 'pdf_native',
            'extraction_reliable' => false,
            'notes' => 'Varios candidatos CSV (' . implode(', ', $native['candidates']) . '). Indica el correcto manualmente.',
            'error' => null,
        ];
    }

    public function normalizeCsv(string $raw): ?string
    {
        $s = strtoupper(preg_replace('/\s+/', '', trim($raw)) ?? '');
        if (strlen($s) !== 16 || !ctype_alnum($s)) {
            return null;
        }

        return $s;
    }

    /**
     * @return array{candidates: list<string>, csv: ?string, error: ?string}
     */
    private function extractWithSmalot(string $absolutePath): array
    {
        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($absolutePath);
            $text = strtoupper(trim((string) $pdf->getText()));
        } catch (\Throwable $e) {
            return ['candidates' => [], 'csv' => null, 'error' => 'PDF: ' . $e->getMessage()];
        }

        if ($text === '') {
            return ['candidates' => [], 'csv' => null, 'error' => 'Sin texto extraíble'];
        }

        return $this->parseCandidatesFromText($text);
    }

    /**
     * @return array{candidates: list<string>, csv: ?string, error: ?string}
     */
    private function parseCandidatesFromText(string $text): array
    {
        preg_match_all(self::CSV_PATTERN, strtoupper($text), $m);
        /** @var list<string> $candidates */
        $candidates = array_values(array_unique($m[1] ?? []));

        return [
            'candidates' => $candidates,
            'csv' => count($candidates) === 1 ? $candidates[0] : null,
            'error' => null,
        ];
    }

    /**
     * @return array{
     *   candidates: list<string>,
     *   csv: ?string,
     *   suggested_csv: ?string,
     *   extraction_method: ?string,
     *   notes: ?string,
     *   error: ?string
     * }|null
     */
    private function extractWithPython(string $absolutePath): ?array
    {
        $scriptPath = dirname(__DIR__, 2) . '/scripts/extract_aeat_csv.py';
        if (!is_file($scriptPath)) {
            return null;
        }

        $python = $this->findPython();
        if ($python === '') {
            return null;
        }

        $cmd = sprintf(
            '%s %s %s %s',
            escapeshellcmd($python),
            escapeshellarg($scriptPath),
            escapeshellarg($absolutePath),
            escapeshellarg('application/pdf')
        );

        $tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cae_csv_' . uniqid('', true) . '.json';
        $returnCode = 0;
        exec($cmd . ' > ' . escapeshellarg($tmpFile) . ' 2>&1', $unused, $returnCode);
        $raw = is_file($tmpFile) ? (string) file_get_contents($tmpFile) : '';
        @unlink($tmpFile);

        if ($raw === '' || !preg_match('/(\{.*\})/s', $raw, $m)) {
            return null;
        }

        $json = json_decode($m[1], true);
        if (!is_array($json) || empty($json['ok'])) {
            return null;
        }

        $csv = isset($json['csv']) ? $this->normalizeCsv((string) $json['csv']) : null;
        $suggested = isset($json['suggested_csv']) ? $this->normalizeCsv((string) $json['suggested_csv']) : null;

        /** @var list<string> $candidates */
        $candidates = [];
        foreach ((array) ($json['candidates'] ?? []) as $item) {
            $norm = $this->normalizeCsv((string) $item);
            if ($norm !== null) {
                $candidates[] = $norm;
            }
        }
        $candidates = array_values(array_unique($candidates));

        return [
            'candidates' => $candidates,
            'csv' => $csv,
            'suggested_csv' => $suggested ?? ($candidates[0] ?? null),
            'extraction_method' => isset($json['extraction_method']) ? (string) $json['extraction_method'] : null,
            'extraction_reliable' => !empty($json['extraction_reliable']),
            'notes' => isset($json['notes']) ? trim((string) $json['notes']) : null,
            'error' => null,
        ];
    }

    private function findPython(): string
    {
        try {
            $cfg = require dirname(__DIR__, 2) . '/config/app.php';
            $path = trim((string) ($cfg['python_path'] ?? ''));
            if ($path !== '' && is_file($path)) {
                return $path;
            }
        } catch (\Throwable) {
        }

        $winPaths = [
            'C:\\Users\\aleja\\AppData\\Local\\Python\\pythoncore-3.14-64\\python.exe',
            'C:\\Users\\aleja\\AppData\\Local\\Programs\\Python\\Python314\\python.exe',
            'C:\\Users\\aleja\\AppData\\Local\\Programs\\Python\\Python313\\python.exe',
            'C:\\Users\\aleja\\AppData\\Local\\Programs\\Python\\Python312\\python.exe',
            'C:\\Python314\\python.exe',
            'C:\\Python313\\python.exe',
            'C:\\Python312\\python.exe',
        ];
        foreach ($winPaths as $p) {
            if (is_file($p)) {
                return $p;
            }
        }

        foreach (['/usr/bin/python3', '/usr/local/bin/python3', 'python3', 'python'] as $candidate) {
            $test = [];
            exec(escapeshellcmd($candidate) . ' --version 2>&1', $test);
            if (!empty($test) && str_contains(implode('', $test), 'Python 3')) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * @return array{
     *   candidates: list<string>,
     *   csv: ?string,
     *   suggested_csv: ?string,
     *   extraction_method: ?string,
     *   notes: ?string,
     *   error: ?string
     * }
     */
    private function fail(string $error): array
    {
        return [
            'candidates' => [],
            'csv' => null,
            'suggested_csv' => null,
            'extraction_method' => null,
            'extraction_reliable' => false,
            'notes' => null,
            'error' => $error,
        ];
    }
}