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
                $msg = 'No se pueden comprobar datos AEAT en base de datos (falta migración o columnas).';
                $messages[] = $msg;

                return self::pack(false, 'No válido', $codes, $messages, [$msg, $vigenciaCtx]);
            }

            $codigo = trim((string) ($row['aeat_cotejo_codigo'] ?? ''));
            $huellaOk = self::dbBoolIsTrue($row['aeat_cotejo_huella_ok'] ?? null);
            $aeDesc = trim((string) ($row['aeat_cotejo_descripcion'] ?? ''));
            $aeAt = trim((string) ($row['aeat_cotejo_checked_at'] ?? ''));
            $mock = !empty($row['aeat_cotejo_used_mock']);

            if ($codigo !== '1' || !$huellaOk) {
                $codes[] = 'aeat_failed';
                $msg = 'Certificado de Hacienda: la verificación AEAT (CSV/cotejo o huella del PDF) no se ha superado.';
                $messages[] = $msg;
                $detail = [$msg, $vigenciaCtx];
                $aeLine = 'AEAT: código ' . ($codigo !== '' ? $codigo : '—')
                    . ($aeDesc !== '' ? ' — ' . $aeDesc : '');
                if ($aeLine !== 'AEAT: código —') {
                    $detail[] = $aeLine;
                }
                if ($aeAt !== '') {
                    $detail[] = 'Última comprobación AEAT: ' . DateDisplay::dateTime($aeAt);
                }
                if ($mock) {
                    $detail[] = 'Entorno de prueba (mock): en producción debe configurarse certificado y endpoint reales.';
                }

                return self::pack(false, 'No válido', $codes, $messages, $detail);
            }

            $detail = [
                'Documento de Hacienda válido: vigencia en orden y AEAT correcta (código 1, huella OK).',
                $vigenciaCtx,
            ];
            if ($aeAt !== '') {
                $detail[] = 'Comprobación AEAT: ' . DateDisplay::dateTime($aeAt);
            }
            if ($mock) {
                $detail[] = 'AEAT en modo mock / prueba.';
            }

            $messages[] = 'Listo.';

            return self::pack(true, 'Válido', $codes, $messages, $detail);
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
}
