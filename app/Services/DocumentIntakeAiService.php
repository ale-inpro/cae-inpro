<?php

declare(strict_types=1);

namespace App\Services;

use Smalot\PdfParser\Parser;

/**
 * Pipeline de análisis de documentos CAE:
 *   1) Script Python (pdfplumber → Tesseract OCR → GPT-4o Vision)
 *   2) Fallback PHP: texto con smalot/pdfparser → OpenAI text API
 */
final class DocumentIntakeAiService
{
    /**
     * @return array{
     *   status:string,
     *   confidence:float,
     *   issue_date:?string,
     *   expires_at:?string,
     *   notes:string,
     *   extracted_text:string
     * }
     */
    public static function analyze(string $absolutePath, string $mimeType, string $docTypeName): array
    {
        // ── PASO 1: Pipeline Python (más fiable) ──────────────────────────
        $pythonResult = self::analyzeWithPython($absolutePath, $mimeType, $docTypeName);
        if ($pythonResult !== null) {
            return $pythonResult;
        }

        // ── PASO 2: Fallback PHP + OpenAI texto ───────────────────────────
        return self::analyzeWithOpenAI($absolutePath, $mimeType, $docTypeName);
    }

    // ─────────────────────────────────────────────────────────────────────
    // PIPELINE PYTHON
    // ─────────────────────────────────────────────────────────────────────

    private static function analyzeWithPython(
        string $absolutePath,
        string $mimeType,
        string $docTypeName
    ): ?array {
        $scriptPath = dirname(__DIR__, 2) . '/scripts/extract_dates.py';
        if (!is_file($scriptPath)) {
            return null;
        }

        $python = self::findPython();
        if ($python === '') {
            return null;
        }

        $cmd = sprintf(
            '%s %s %s %s %s',
            escapeshellcmd($python),
            escapeshellarg($scriptPath),
            escapeshellarg($absolutePath),
            escapeshellarg($docTypeName),
            escapeshellarg($mimeType)
        );

        $tmpFile    = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cae_extract_' . uniqid() . '.json';
        $returnCode = 0;
        exec($cmd . ' > ' . escapeshellarg($tmpFile) . ' 2>&1', $unused, $returnCode);
        $raw = is_file($tmpFile) ? (string) file_get_contents($tmpFile) : '';
        @unlink($tmpFile);

        // Buscar el primer JSON válido en la salida (ignorar warnings de Python)
        if (!preg_match('/(\{.*\})/s', $raw, $m)) {
            return null;
        }

        $json = json_decode($m[1], true);
        if (!is_array($json) || empty($json['ok'])) {
            return null;
        }

        $status = (string) ($json['status'] ?? 'manual_review');
        if (!in_array($status, ['approved', 'in_review', 'rejected', 'manual_review'], true)) {
            $status = 'manual_review';
        }

        return [
            'status'         => $status,
            'confidence'     => (float) ($json['confidence'] ?? 0.0),
            'issue_date'     => self::normDate($json['issue_date'] ?? null),
            'expires_at'     => self::normDate($json['expires_at'] ?? null),
            'notes'          => '[Python] ' . (string) ($json['notes'] ?? ''),
            'extracted_text' => (string) ($json['extracted_text'] ?? ''),
        ];
    }

    /**
     * Detecta el ejecutable Python disponible en el sistema.
     *
     * Orden de búsqueda:
     *   1. config/app.php → 'python_path'  (recomendado; diferente por entorno)
     *   2. Rutas absolutas conocidas en Windows (por si config está vacío)
     *   3. Nombres genéricos en PATH (funciona en Linux/producción)
     *
     * IMPORTANTE: no usar el stub de WindowsApps — no funciona desde Apache.
     */
    private static function findPython(): string
    {
        // ── 1. Valor explícito en configuración ───────────────────────────
        try {
            $cfg  = require dirname(__DIR__, 2) . '/config/app.php';
            $path = trim((string) ($cfg['python_path'] ?? ''));
            if ($path !== '' && is_file($path)) {
                return $path;
            }
        } catch (\Throwable) {
            // config no disponible, continuar
        }

        // ── 2. Rutas absolutas típicas de Windows ─────────────────────────
        $winPaths = [
            'C:\\Users\\aleja\\AppData\\Local\\Python\\pythoncore-3.14-64\\python.exe',
            'C:\\Users\\aleja\\AppData\\Local\\Programs\\Python\\Python314\\python.exe',
            'C:\\Users\\aleja\\AppData\\Local\\Programs\\Python\\Python313\\python.exe',
            'C:\\Users\\aleja\\AppData\\Local\\Programs\\Python\\Python312\\python.exe',
            'C:\\Python314\\python.exe',
            'C:\\Python313\\python.exe',
            'C:\\Python312\\python.exe',
            'C:\\Python311\\python.exe',
        ];
        foreach ($winPaths as $p) {
            if (is_file($p)) {
                return $p;
            }
        }

        // ── 3. Fallback genérico (Linux/producción con PATH correcto) ─────
        foreach (['/usr/bin/python3', '/usr/local/bin/python3', 'python3', 'python'] as $candidate) {
            $test = [];
            exec(escapeshellcmd($candidate) . ' --version 2>&1', $test);
            if (!empty($test) && str_contains(implode('', $test), 'Python 3')) {
                return $candidate;
            }
        }

        return '';
    }

    // ─────────────────────────────────────────────────────────────────────
    // FALLBACK: PHP + OPENAI TEXTO
    // ─────────────────────────────────────────────────────────────────────

    private static function analyzeWithOpenAI(
        string $absolutePath,
        string $mimeType,
        string $docTypeName
    ): array {
        $text = self::extractText($absolutePath, $mimeType);

        if (trim($text) === '' || mb_strlen(trim($text)) < 40) {
            return self::manualResult(
                'No se pudo extraer texto suficiente (Python no disponible, fallback PHP).',
                $text
            );
        }

        $cfg    = require dirname(__DIR__, 2) . '/config/ai.php';
        $apiKey = (string) ($cfg['openai_api_key'] ?? '');

        if ($apiKey === '') {
            return self::manualResult('IA no configurada (falta API key).', $text);
        }

        $prompt = <<<PROMPT
Analiza este documento de tipo FIJO: {$docTypeName}.
El tipo ya viene dado; no lo decidas tú.

Extrae:
- issue_date  → fecha de expedición/emisión (YYYY-MM-DD o null)
- expires_at  → fecha de caducidad/vencimiento (YYYY-MM-DD o null si NO aparece explícita)
- status      → approved | in_review | rejected | manual_review
- confidence  → número 0.0–1.0
- notes       → texto breve

REGLAS CRÍTICAS:
1. Formato de fechas en español: DD/MM/AAAA. No confundas mes 11 (noviembre) con 01 (enero).
2. Si hay "desde X hasta Y", expires_at = Y.
3. Si pone "validez de 6 meses desde expedición", calcula la fecha concreta.
4. Si el doc dice "al corriente" o "POSITIVO", status = approved.
5. Devuelve SOLO JSON válido.

Texto del documento:
\"\"\"{$text}\"\"\"
PROMPT;

        $payload = [
            'model'       => 'gpt-4o-mini',
            'temperature' => 0,
            'messages'    => [
                ['role' => 'system', 'content' => 'Asistente de análisis documental CAE. Responde solo con JSON.'],
                ['role' => 'user',   'content' => $prompt],
            ],
            'response_format' => [
                'type'        => 'json_schema',
                'json_schema' => [
                    'name'   => 'doc_analysis',
                    'strict' => true,
                    'schema' => [
                        'type'       => 'object',
                        'properties' => [
                            'status'     => ['type' => 'string'],
                            'confidence' => ['type' => 'number'],
                            'issue_date' => ['type' => ['string', 'null']],
                            'expires_at' => ['type' => ['string', 'null']],
                            'notes'      => ['type' => 'string'],
                        ],
                        'required'             => ['status', 'confidence', 'issue_date', 'expires_at', 'notes'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT    => 45,
        ]);

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if (!is_string($raw) || $raw === '' || $err !== '') {
            return self::manualResult('Fallo IA: ' . ($err ?: 'sin respuesta'), $text);
        }

        $resp    = json_decode($raw, true);
        $content = (string) ($resp['choices'][0]['message']['content'] ?? '');
        if (preg_match('/\{.*\}/s', $content, $m)) {
            $content = $m[0];
        }
        $json = json_decode($content, true);

        if (!is_array($json)) {
            return self::manualResult('Respuesta IA no parseable.', $text);
        }

        $status = (string) ($json['status'] ?? 'manual_review');
        if (!in_array($status, ['approved', 'in_review', 'rejected', 'manual_review'], true)) {
            $status = 'manual_review';
        }

        return [
            'status'         => $status,
            'confidence'     => (float) ($json['confidence'] ?? 0.0),
            'issue_date'     => self::normDate($json['issue_date'] ?? null),
            'expires_at'     => self::normDate($json['expires_at'] ?? null),
            'notes'          => '[Fallback OpenAI texto] ' . (string) ($json['notes'] ?? ''),
            'extracted_text' => $text,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────────────────

    /** @return array{status:string,confidence:float,issue_date:null,expires_at:null,notes:string,extracted_text:string} */
    private static function manualResult(string $notes, string $text = ''): array
    {
        return [
            'status'         => 'manual_review',
            'confidence'     => 0.0,
            'issue_date'     => null,
            'expires_at'     => null,
            'notes'          => $notes,
            'extracted_text' => $text,
        ];
    }

        /**
     * Calcula el estado real del documento a partir de las fechas ya conocidas.
     * NUNCA delega esta decisión a la IA; la IA no conoce la fecha actual.
     *
     * Lógica:
     *  - expires_at en el pasado          → rejected  (caducado)
     *  - expires_at en los próximos 30 d  → in_review  (próximo a caducar)
     *  - expires_at en el futuro          → approved
     *  - Sin expires_at pero con issueDate → in_review  (incompleto pero procesable)
     *  - Sin ninguna fecha                → manual_review
     */
    public static function calcStatus(?string $expiresAt, ?string $issueDate): string
    {
        if ($expiresAt !== null && $expiresAt !== '') {
            $today   = new \DateTime('today');
            $expDate = \DateTime::createFromFormat('Y-m-d', $expiresAt);
            if ($expDate === false) {
                return 'manual_review';
            }
            if ($expDate < $today) {
                return 'rejected';                          // documento caducado
            }
            $soon = (clone $today)->modify('+30 days');
            if ($expDate <= $soon) {
                return 'in_review';                         // caduca en ≤30 días
            }
            return 'approved';
        }

        // Sin fecha de caducidad pero sí fecha de emisión: procesable con revisión
        if ($issueDate !== null && $issueDate !== '') {
            return 'in_review';
        }

        return 'manual_review';
    }

    private static function normDate(mixed $v): ?string
    {
        $s = trim((string) $v);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) ? $s : null;
    }

    private static function extractText(string $absolutePath, string $mimeType): string
    {
        if (!is_file($absolutePath)) {
            return '';
        }
        $mime = strtolower($mimeType);
        if (str_contains($mime, 'text/')) {
            return trim((string) file_get_contents($absolutePath));
        }
        if (str_contains($mime, 'pdf')) {
            try {
                $parser = new Parser();
                $pdf    = $parser->parseFile($absolutePath);
                return trim((string) $pdf->getText());
            } catch (\Throwable) {
                return '';
            }
        }
        return '';
    }
}