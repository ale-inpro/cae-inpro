<?php

declare(strict_types=1);

namespace App\Services;

use Smalot\PdfParser\Parser;

/**
 * Parsea y valida el PDF oficial devuelto por AEAT tras cotejo exitoso (codigo=1).
 * Fuente de verdad: contenido del PDF AEAT, no el escaneo subido.
 */
final class AeatHaciendaOfficialPdfValidator
{
    private const NIF_PATTERN = '/\b([0-9]{8}[A-Z]|[KLMXYZ][0-9]{7}[A-Z]|[ABCDEFGHJNPQRSUVW][0-9]{7}[0-9A-J])\b/i';

    /**
     * @return array{
     *   ok: bool,
     *   errors: list<string>,
     *   tax_id: ?string,
     *   is_positive: ?bool,
     *   issue_date: ?string,
     *   expires_at: ?string,
     *   text_length: int
     * }
     */
    public function validatePdfBytes(string $pdfBytes, string $expectedTaxId): array
    {
        $expected = self::normalizeTaxId($expectedTaxId);
        if ($expected === '') {
            return [
                'ok' => false,
                'errors' => ['El técnico no tiene identificador fiscal (tax_id) registrado.'],
                'tax_id' => null,
                'is_positive' => null,
                'issue_date' => null,
                'expires_at' => null,
                'text_length' => 0,
            ];
        }

        if ($pdfBytes === '') {
            return $this->fail(['AEAT no devolvió contenido PDF (binario vacío).'], null, null, null, null, 0);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'aeat_');
        if ($tmp === false) {
            return $this->fail(['No se pudo crear fichero temporal para analizar el PDF AEAT.'], null, null, null, null, 0);
        }

        try {
            file_put_contents($tmp, $pdfBytes);
            $text = $this->extractText($tmp);
        } finally {
            @unlink($tmp);
        }

        if ($text === '') {
            return $this->fail(
                ['El PDF oficial AEAT no contiene texto extraíble (¿escaneo/imagen sin OCR?).'],
                null,
                null,
                null,
                null,
                0
            );
        }

        $upper = strtoupper($text);
        $errors = [];

        $foundTaxIds = $this->extractTaxIds($upper);
        $parsedTaxId = $this->pickBestTaxId($upper, $foundTaxIds);
        if ($parsedTaxId === null) {
            $errors[] = 'No se ha detectado NIF/CIF en el certificado.';
        } elseif ($parsedTaxId !== $expected) {
            $errors[] = 'El NIF/CIF del certificado (' . $parsedTaxId . ') no coincide con el del técnico (' . $expected . ').';
        }

        $isPositive = $this->detectPositiveResult($upper);
        if ($isPositive === false) {
            $errors[] = 'El certificado no es positivo / indica que NO está al corriente con Hacienda.';
        } elseif ($isPositive === null) {
            $errors[] = 'No se ha podido determinar si el certificado es positivo (al corriente).';
        }

        $issueDate = $this->extractIssueDate($upper);
        $expiresAt = $this->extractDateNearKeywords($upper, [
            'FECHA DE CADUCIDAD',
            'FECHA CADUCIDAD',
            'VALIDO HASTA',
            'VÁLIDO HASTA',
            'VIGENCIA HASTA',
        ]);

        if ($expiresAt === null && $issueDate !== null && $this->mentionsSixMonthValidity($upper)) {
            $expiresAt = $this->addMonths($issueDate, 6);
        }

        if ($expiresAt === null) {
            $expiresAt = $this->extractLatestFutureDate($upper);
        }

        if ($expiresAt === null) {
            $errors[] = 'No se ha detectado fecha de caducidad/vigencia en el certificado.';
        } else {
            $today = new \DateTimeImmutable('today');
            $exp = \DateTimeImmutable::createFromFormat('Y-m-d', $expiresAt);
            if ($exp === false) {
                $errors[] = 'La fecha de caducidad del certificado no es interpretable.';
            } elseif ($exp < $today) {
                $errors[] = 'El certificado está caducado (vigencia hasta ' . $exp->format('d/m/Y') . ').';
            }
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'tax_id' => $parsedTaxId,
            'is_positive' => $isPositive,
            'issue_date' => $issueDate,
            'expires_at' => $expiresAt,
            'text_length' => strlen($text),
        ];
    }

    public static function normalizeTaxId(string $raw): string
    {
        return strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', $raw));
    }

    /**
     * @return list<string>
     */
    public static function decodeErrorsJson(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [trim($json)];
        }
        $out = [];
        foreach ($decoded as $item) {
            $s = trim((string) $item);
            if ($s !== '') {
                $out[] = $s;
            }
        }
        return $out;
    }

    private function extractText(string $absolutePath): string
    {
        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($absolutePath);
            return trim((string) $pdf->getText());
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * @return list<string>
     */
    private function extractTaxIds(string $upperText): array
    {
        if (!preg_match_all(self::NIF_PATTERN, $upperText, $m)) {
            return [];
        }
        $ids = [];
        foreach ($m[1] as $raw) {
            $n = self::normalizeTaxId((string) $raw);
            if ($n !== '') {
                $ids[$n] = true;
            }
        }
        return array_keys($ids);
    }

    /**
     * @param list<string> $candidates
     */
    private function pickBestTaxId(string $upperText, array $candidates): ?string
    {
        if (preg_match('/(?:NIF|CIF|IDENTIFICADOR|N\.I\.F\.|N\.I\.F)\s*:?\s*([0-9]{8}[A-Z]|[KLMXYZ][0-9]{7}[A-Z]|[ABCDEFGHJNPQRSUVW][0-9]{7}[0-9A-J])/i', $upperText, $m)) {
            return self::normalizeTaxId($m[1]);
        }
        if (count($candidates) === 1) {
            return $candidates[0];
        }
        return $candidates[0] ?? null;
    }

    private function detectPositiveResult(string $upperText): ?bool
    {
        if (preg_match('/\bNEGATIVO\b/', $upperText)) {
            return false;
        }
        if (preg_match('/NO\s+EST[AÁ]\s+AL\s+CORRIENTE/', $upperText)) {
            return false;
        }
        if (preg_match('/\bPOSITIVO\b/', $upperText)) {
            return true;
        }
        if (preg_match('/EST[AÁ]\s+AL\s+CORRIENTE/', $upperText)) {
            return true;
        }
        if (str_contains($upperText, 'AL CORRIENTE DE PAGO')) {
            return true;
        }
        return null;
    }

    private function extractIssueDate(string $upperText): ?string
    {
        $fromKeywords = $this->extractDateNearKeywords($upperText, [
            'FECHA DE EMISION',
            'FECHA EMISION',
            'FECHA DE EXPEDICION',
            'FECHA EXPEDICION',
            'CON FECHA',
        ]);
        if ($fromKeywords !== null) {
            return $fromKeywords;
        }

        return $this->extractSpanishTextDate($upperText);
    }

    private function mentionsSixMonthValidity(string $upperText): bool
    {
        return (bool) preg_match(
            '/VALIDEZ\s+DE\s+SEIS\s+MESES|SEIS\s+MESES\s+CONTADOS|6\s+MESES\s+CONTADOS/i',
            $upperText
        );
    }

    private function addMonths(string $isoDate, int $months): ?string
    {
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $isoDate);
        if ($dt === false) {
            return null;
        }
        $modified = $dt->modify('+' . $months . ' months');

        return $modified->format('Y-m-d');
    }

    private function extractSpanishTextDate(string $upperText): ?string
    {
        $months = [
            'ENERO' => 1, 'FEBRERO' => 2, 'MARZO' => 3, 'ABRIL' => 4,
            'MAYO' => 5, 'JUNIO' => 6, 'JULIO' => 7, 'AGOSTO' => 8,
            'SEPTIEMBRE' => 9, 'SETIEMBRE' => 9, 'OCTUBRE' => 10,
            'NOVIEMBRE' => 11, 'DICIEMBRE' => 12,
        ];

        if (!preg_match(
            '/\b(\d{1,2})\s+DE\s+(ENERO|FEBRERO|MARZO|ABRIL|MAYO|JUNIO|JULIO|AGOSTO|SEPTIEMBRE|SETIEMBRE|OCTUBRE|NOVIEMBRE|DICIEMBRE)\s+DE\s+(\d{4})\b/i',
            $upperText,
            $m
        )) {
            return null;
        }

        $monthName = strtoupper($m[2]);
        if (!isset($months[$monthName])) {
            return null;
        }

        return $this->toIsoDate((int) $m[1], $months[$monthName], (int) $m[3]);
    }

    /**
     * @param list<string> $keywordsUpper
     */
    private function extractDateNearKeywords(string $upperText, array $keywordsUpper): ?string
    {
        foreach ($keywordsUpper as $kw) {
            $pos = mb_strpos($upperText, $kw);
            if ($pos === false) {
                continue;
            }
            $chunk = mb_substr($upperText, $pos, 120);
            $date = $this->firstDateInChunk($chunk);
            if ($date !== null) {
                return $date;
            }
        }
        return null;
    }

    private function extractLatestFutureDate(string $upperText): ?string
    {
        if (!preg_match_all('/\b(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})\b/', $upperText, $m, PREG_SET_ORDER)) {
            return null;
        }
        $best = null;
        foreach ($m as $match) {
            $iso = $this->toIsoDate((int) $match[1], (int) $match[2], (int) $match[3]);
            if ($iso === null) {
                continue;
            }
            if ($best === null || $iso > $best) {
                $best = $iso;
            }
        }
        return $best;
    }

    private function firstDateInChunk(string $chunk): ?string
    {
        if (preg_match('/\b(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})\b/', $chunk, $m)) {
            return $this->toIsoDate((int) $m[1], (int) $m[2], (int) $m[3]);
        }
        return null;
    }

    private function toIsoDate(int $d, int $m, int $y): ?string
    {
        if (!checkdate($m, $d, $y)) {
            return null;
        }
        return sprintf('%04d-%02d-%02d', $y, $m, $d);
    }

    /**
     * @param list<string> $errors
     * @return array{ok: false, errors: list<string>, tax_id: ?string, is_positive: ?bool, issue_date: ?string, expires_at: ?string, text_length: int}
     */
    private function fail(array $errors, ?string $taxId, ?bool $isPositive, ?string $issue, ?string $expires, int $len): array
    {
        return [
            'ok' => false,
            'errors' => $errors,
            'tax_id' => $taxId,
            'is_positive' => $isPositive,
            'issue_date' => $issue,
            'expires_at' => $expires,
            'text_length' => $len,
        ];
    }
}