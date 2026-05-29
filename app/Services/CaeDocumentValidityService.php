<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\DateDisplay;
use PDO;

/**
 * Un solo criterio de negocio para complementarios publicados (cae_documents):
 * "Válido" / "No válido" = vigencia por fecha + (si es Hacienda) cotejo AEAT.
 * Alineado con CaeReadinessService por tipo.
 *
 * detail_lines: primero el motivo humano principal; la fecha de fin del documento va como contexto cuando aplica.
 */
final class CaeDocumentValidityService
{
    /**
     * @param array<string, mixed> $row id, document_type_id, document_name, expires_at; opcional aeat_*
     * @return array{
     *   valid_for_cae: bool,
     *   label: string,
     *   summary: string,
     *   codes: list<string>,
     *   messages: list<string>,
     *   detail_lines: list<string>
     * }
     */
    public static function evaluateSupportingRow(PDO $pdo, array $row, bool $hasAeatColumns): array
    {
        $typeId = (int) ($row['document_type_id'] ?? 0);
        $typeName = trim((string) ($row['document_name'] ?? ''));
        $haciendaId = CaeReadinessService::resolveHaciendaDocumentTypeId($pdo);
        $isHacienda = ($haciendaId !== null && $typeId === $haciendaId)
            || $typeName === CaeReadinessService::DOCUMENT_TYPE_NAME_HACIENDA;

        $codes = [];
        $messages = [];

        $expiresRaw = isset($row['expires_at']) ? trim((string) $row['expires_at']) : '';

        if ($expiresRaw === '' && $isHacienda) {
            if (!$hasAeatColumns) {
                $codes[] = 'aeat_schema_missing';
                $msg = 'No se puede comprobar el certificado de Hacienda (falta configuración en base de datos).';
                return self::pack(false, 'No válido', $codes, [$msg], [$msg]);
            }

            $codigo = trim((string) ($row['aeat_cotejo_codigo'] ?? ''));
            $pdfOk = self::dbBoolIsTrue($row['aeat_pdf_validation_ok'] ?? null);
            $validationErrors = AeatHaciendaOfficialPdfValidator::decodeErrorsJson(
                isset($row['aeat_pdf_validation_errors']) ? (string) $row['aeat_pdf_validation_errors'] : null
            );
            $aeAt = trim((string) ($row['aeat_cotejo_checked_at'] ?? ''));

            if ($aeAt === '') {
                $codes[] = 'hacienda_pending';
                $msg = 'Pendiente de comprobación del certificado de Hacienda.';
                return self::pack(false, 'No válido', $codes, [$msg], [$msg]);
            }

            if ($codigo !== '1') {
                $codes[] = 'hacienda_cotejo_failed';
                $msg = 'No se ha podido obtener el certificado de Hacienda.';
                return self::pack(false, 'No válido', $codes, [$msg], [$msg]);
            }

            if (!$pdfOk) {
                $codes[] = 'hacienda_invalid';
                $msg = self::humanizeHaciendaError($validationErrors)
                    ?? 'El certificado de Hacienda no cumple los requisitos.';
                return self::pack(false, 'No válido', $codes, [$msg], [$msg]);
            }

            $msg = 'Sin fecha de caducidad registrada para este documento.';
            return self::pack(false, 'No válido', ['expiry_missing'], [$msg], [$msg]);
        }

        if ($expiresRaw === '') {
            $codes[] = 'expiry_missing';
            $msg = 'Sin fecha de caducidad registrada para este documento.';
            $messages[] = $msg;

            return self::pack(
                false,
                'No válido',
                $codes,
                $messages,
                [$msg]
            );
        }

        $today = new \DateTimeImmutable('today');
        $exp = \DateTimeImmutable::createFromFormat('Y-m-d', substr($expiresRaw, 0, 10));
        if ($exp === false) {
            $codes[] = 'expiry_invalid';
            $msg = 'La fecha de caducidad del documento no es válida o no se reconoce.';
            $messages[] = $msg;

            return self::pack(false, 'No válido', $codes, $messages, [$msg]);
        }

        $expLabel = DateDisplay::date($exp->format('Y-m-d'));
        $vigenciaCtx = 'Vigencia del documento (fecha de fin indicada): ' . $expLabel;

        if ($exp < $today) {
            $codes[] = 'expired';
            $msg = 'El documento está caducado: la fecha de fin (' . $expLabel . ') es anterior a hoy.';
            $messages[] = $msg;

            return self::pack(false, 'No válido', $codes, $messages, [$msg]);
        }

        $calc = DocumentIntakeAiService::calcStatus($expiresRaw, null);
        if ($calc !== 'approved') {
            $codes[] = 'not_approved_status';
            $msg = match ($calc) {
                'in_review' => 'Queda poco margen de vigencia: caduca en 30 días o menos; hay que revisarlo antes de generar.',
                'rejected' => 'Según las reglas de vigencia del sistema, este documento no cuenta como aprobado (revisar fechas o el propio certificado).',
                'manual_review' => 'Las fechas del documento requieren revisión manual antes de poder considerarlo válido.',
                default => 'No cumple el estado de vigencia requerido (aprobado).',
            };
            $messages[] = $msg;
            $detail = [$msg, $vigenciaCtx];

            return self::pack(false, 'No válido', $codes, $messages, $detail);
        }

        if ($isHacienda) {
            if (!$hasAeatColumns) {
                $codes[] = 'aeat_schema_missing';
                $msg = 'No se puede comprobar el certificado de Hacienda (falta configuración en base de datos).';
                return self::pack(false, 'No válido', $codes, [$msg], [$msg, $vigenciaCtx]);
            }

            $codigo = trim((string) ($row['aeat_cotejo_codigo'] ?? ''));
            $pdfOk = self::dbBoolIsTrue($row['aeat_pdf_validation_ok'] ?? null);
            $validationErrors = AeatHaciendaOfficialPdfValidator::decodeErrorsJson(
                isset($row['aeat_pdf_validation_errors']) ? (string) $row['aeat_pdf_validation_errors'] : null
            );
            $aeAt = trim((string) ($row['aeat_cotejo_checked_at'] ?? ''));

            if ($aeAt === '') {
                $codes[] = 'hacienda_pending';
                $msg = 'Pendiente de comprobación del certificado de Hacienda.';
                return self::pack(false, 'No válido', $codes, [$msg], [$msg, $vigenciaCtx]);
            }

            if ($codigo !== '1') {
                $codes[] = 'hacienda_cotejo_failed';
                $msg = 'No se ha podido obtener el certificado de Hacienda.';
                return self::pack(false, 'No válido', $codes, [$msg], [$msg, $vigenciaCtx]);
            }

            if (!$pdfOk) {
                $codes[] = 'hacienda_invalid';
                $msg = self::humanizeHaciendaError($validationErrors)
                    ?? 'El certificado de Hacienda no cumple los requisitos.';
                return self::pack(false, 'No válido', $codes, [$msg], [$msg, $vigenciaCtx]);
            }

            $messages[] = 'Listo.';
            return self::pack(true, 'Válido', $codes, $messages, [
                'Documento válido según vigencia registrada.',
                $vigenciaCtx,
            ]);
        }

        $messages[] = 'Listo.';
        $detail = [
            'Documento válido según vigencia registrada.',
            $vigenciaCtx,
        ];

        return self::pack(true, 'Válido', $codes, $messages, $detail);
    }

    /**
     * @param list<string> $codes
     * @param list<string> $messages
     * @param list<string> $detailLines
     * @return array{valid_for_cae: bool, label: string, summary: string, codes: list<string>, messages: list<string>, detail_lines: list<string>}
     */
    private static function pack(bool $ok, string $label, array $codes, array $messages, array $detailLines): array
    {
        return [
            'valid_for_cae' => $ok,
            'label' => $label,
            'summary' => $messages !== [] ? $messages[0] : $label,
            'codes' => array_values(array_unique($codes)),
            'messages' => array_values($messages),
            'detail_lines' => array_values($detailLines),
        ];
    }

    private static function dbBoolIsTrue(mixed $v): bool
    {
        if ($v === true || $v === 1) {
            return true;
        }
        if ($v === false || $v === null) {
            return false;
        }
        $s = strtolower(trim((string) $v));

        return in_array($s, ['t', 'true', '1', 'yes', 'on'], true);
    }

    /**
     * @param list<string> $errors
     */
    private static function humanizeHaciendaError(array $errors): ?string
    {
        if ($errors === []) {
            return null;
        }

        $first = trim($errors[0]);
        $lower = mb_strtolower($first);

        if (str_contains($lower, 'caducado')) {
            if (preg_match('/(\d{2}\/\d{2}\/\d{4})/', $first, $m)) {
                return 'El documento está caducado: la fecha de fin (' . $m[1] . ') es anterior a hoy.';
            }
            return 'El documento está caducado.';
        }

        if (str_contains($lower, 'nif') || str_contains($lower, 'cif')) {
            return 'El NIF/CIF del certificado no coincide con el del técnico.';
        }

        if (str_contains($lower, 'positivo') || str_contains($lower, 'corriente')) {
            return 'El certificado indica que no está al corriente con Hacienda.';
        }

        if (str_contains($lower, 'fecha de caducidad') || str_contains($lower, 'vigencia')) {
            return 'No se ha detectado una fecha de vigencia válida en el certificado.';
        }

        $clean = trim((string) preg_replace('/\bAEAT\b/i', 'certificado', $first));
        return $clean !== '' ? $clean : null;
    }

}
