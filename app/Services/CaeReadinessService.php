<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Paso 2: reglas de "listo para generar CAE con IA" (solo lectura).
 * Ver docs/CAE_READINESS_REGLES.md secciones 1 y 4.
 */
final class CaeReadinessService
{
    /** @var list<string> Tipos que pueden subirse como complementarios (ficha técnico, portal, etc.) */
    public const ALL_SUPPORTING_DOC_NAMES = [
        'Certificado de estar al corriente con Hacienda',
        'Certificado de estar al corriente con Seguridad Social',
        'Póliza de Responsabilidad Civil',
        'Certificado de Prevención de Riesgos Laborales',
    ];

    /**
     * Obligatorios solo para generar el CAE con IA (sin certificado PRL del técnico).
     *
     * @var list<string>
     */
    public const REQUIRED_FOR_CAE_GENERATION = [
        'Certificado de estar al corriente con Hacienda',
        'Certificado de estar al corriente con Seguridad Social',
        'Póliza de Responsabilidad Civil',
    ];

    /** @deprecated Usar ALL_SUPPORTING_DOC_NAMES o REQUIRED_FOR_CAE_GENERATION según el caso */
    public const REQUIRED_SUPPORTING_DOC_NAMES = self::ALL_SUPPORTING_DOC_NAMES;

    public const DOCUMENT_TYPE_NAME_HACIENDA = 'Certificado de estar al corriente con Hacienda';

    public const DOCUMENT_TYPE_NAME_PRL = 'Certificado de Prevención de Riesgos Laborales';

    private const HACIENDA_NAME = self::DOCUMENT_TYPE_NAME_HACIENDA;

    /**
     * ID del tipo "Certificado de estar al corriente con Hacienda" si existe activo en BD.
     */
    public static function resolveHaciendaDocumentTypeId(PDO $pdo): ?int
    {
        $stmt = $pdo->prepare('
            SELECT id
            FROM document_types
            WHERE scope = \'technician_cae\'
              AND is_cae_file_type = FALSE
              AND is_active = TRUE
              AND name = :name
            LIMIT 1
        ');
        $stmt->execute(['name' => self::DOCUMENT_TYPE_NAME_HACIENDA]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    /**
     * @return array{
     *   ok: bool,
     *   cae_record_id: int|null,
     *   technician_id: int,
     *   reasons: list<string>,
     *   by_type: array<string, array{ok: bool, codes: list<string>, messages: list<string>}>,
     * }
     */
    public function evaluateForTechnician(PDO $pdo, int $technicianId): array
    {
        if ($technicianId <= 0) {
            return $this->emptyResult(0, null, ['Identificador de técnico no válido.']);
        }

        $caeRecordId = $this->findCurrentCaeRecordId($pdo, $technicianId);
        if ($caeRecordId === null) {
            return $this->emptyResult(
                $technicianId,
                null,
                ['Este técnico no tiene un registro CAE vigente (cae_records.is_current).']
            );
        }

        return $this->evaluateForCaeRecord($pdo, $technicianId, $caeRecordId);
    }

    /**
     * @return array{
     *   ok: bool,
     *   cae_record_id: int|null,
     *   technician_id: int,
     *   reasons: list<string>,
     *   by_type: array<string, array{ok: bool, codes: list<string>, messages: list<string>}>,
     * }
     */
    public function evaluateForCaeRecord(PDO $pdo, int $technicianId, int $caeRecordId): array
    {
        if ($caeRecordId <= 0) {
            return $this->emptyResult($technicianId, null, ['Registro CAE no válido.']);
        }

        $owns = $this->caeRecordBelongsToTechnician($pdo, $caeRecordId, $technicianId);
        if (!$owns) {
            return $this->emptyResult(
                $technicianId,
                $caeRecordId,
                ['El registro CAE no pertenece a este técnico.']
            );
        }

        $hasAeatCols = $this->hasAeatColumns($pdo);

        $typeIdsByName = $this->loadActiveSupportingTypeIdsByName($pdo);
        $missingTypes = array_values(array_diff(self::REQUIRED_FOR_CAE_GENERATION, array_keys($typeIdsByName)));
        if ($missingTypes !== []) {
            return [
                'ok' => false,
                'cae_record_id' => $caeRecordId,
                'technician_id' => $technicianId,
                'reasons' => [
                    'Faltan tipos de documento en la base de datos: ' . implode(', ', $missingTypes)
                    . '. Ejecuta la migración 2026_05_12_rename_doc_types.sql.',
                ],
                'by_type' => [],
            ];
        }

        $docs = $this->loadActiveSupportingDocs($pdo, $caeRecordId, $hasAeatCols);
        $byType = [];
        $reasons = [];

        foreach (self::REQUIRED_FOR_CAE_GENERATION as $typeName) {
            $typeId = $typeIdsByName[$typeName];
            $row = $docs[$typeId] ?? null;

            $entry = ['ok' => true, 'codes' => [], 'messages' => []];

            if ($row === null) {
                $entry['ok'] = false;
                $entry['codes'][] = 'missing_doc';
                $msg = 'Falta el documento: ' . $typeName . '.';
                $entry['messages'][] = $msg;
                $reasons[] = $msg;
                $byType[$typeName] = $entry;
                continue;
            }

            $expiresRaw = $row['expires_at'] ?? null;
            $expiresAt = is_string($expiresRaw) ? trim($expiresRaw) : null;
            if ($expiresAt === null || $expiresAt === '') {
                $entry['ok'] = false;
                $entry['codes'][] = 'expiry_missing';
                $msg = 'El documento «' . $typeName . '» no tiene fecha de caducidad registrada.';
                $entry['messages'][] = $msg;
                $reasons[] = $msg;
            } else {
                $today = new \DateTimeImmutable('today');
                $exp = \DateTimeImmutable::createFromFormat('Y-m-d', substr($expiresAt, 0, 10));
                if ($exp === false) {
                    $entry['ok'] = false;
                    $entry['codes'][] = 'expiry_invalid';
                    $msg = 'El documento «' . $typeName . '» tiene una fecha de caducidad no válida.';
                    $entry['messages'][] = $msg;
                    $reasons[] = $msg;
                } elseif ($exp < $today) {
                    $entry['ok'] = false;
                    $entry['codes'][] = 'expired';
                    $msg = 'El documento «' . $typeName . '» está caducado (caducidad: ' . \App\Support\DateDisplay::date($exp->format('Y-m-d')) . ').';
                    $entry['messages'][] = $msg;
                    $reasons[] = $msg;
                } else {
                    $status = DocumentIntakeAiService::calcStatus($expiresAt, null);
                    if ($status !== 'approved') {
                        $entry['ok'] = false;
                        $entry['codes'][] = 'not_approved_status';
                        $msg = match ($status) {
                            'in_review' => 'El documento «' . $typeName . '» no está en estado aprobado para generación: caduca en 30 días o menos (revisar antes de generar).',
                            'rejected' => 'El documento «' . $typeName . '» no está en estado aprobado para generación (caducado según reglas de estado).',
                            'manual_review' => 'El documento «' . $typeName . '» requiere revisión manual de fechas antes de generar el CAE.',
                            default => 'El documento «' . $typeName . '» no cumple el estado requerido (approved) para generar el CAE.',
                        };
                        $entry['messages'][] = $msg;
                        $reasons[] = $msg;
                    }
                }
            }

            if ($typeName === self::HACIENDA_NAME && $entry['ok']) {
                if (!$hasAeatCols) {
                    $entry['ok'] = false;
                    $entry['codes'][] = 'aeat_schema_missing';
                    $msg = 'No se pueden comprobar datos AEAT: ejecuta database/migrations/2026_05_14_cae_documents_aeat_cotejo.sql.';
                    $entry['messages'][] = $msg;
                    $reasons[] = $msg;
                } else {
                    $codigo = isset($row['aeat_cotejo_codigo']) ? trim((string) $row['aeat_cotejo_codigo']) : '';
                    $huellaOk = $this->dbBoolIsTrue($row['aeat_cotejo_huella_ok'] ?? null);
                    if ($codigo !== '1' || !$huellaOk) {
                        $entry['ok'] = false;
                        $entry['codes'][] = 'aeat_failed';
                        $msg = 'El certificado de Hacienda no supera la verificación AEAT (CSV/cotejo o huella). Sube un certificado válido o espera a la verificación automática al publicar el documento.';
                        $entry['messages'][] = $msg;
                        $reasons[] = $msg;
                    }
                }
            }

            $byType[$typeName] = $entry;
        }

        $ok = $reasons === [];

        return [
            'ok' => $ok,
            'cae_record_id' => $caeRecordId,
            'technician_id' => $technicianId,
            'reasons' => array_values(array_unique($reasons)),
            'by_type' => $byType,
        ];
    }

    /**
     * @param list<string> $reasons
     * @return array{
     *   ok: bool,
     *   cae_record_id: int|null,
     *   technician_id: int,
     *   reasons: list<string>,
     *   by_type: array<string, array{ok: bool, codes: list<string>, messages: list<string>}>,
     * }
     */
    private function emptyResult(int $technicianId, ?int $caeRecordId, array $reasons): array
    {
        return [
            'ok' => false,
            'cae_record_id' => $caeRecordId,
            'technician_id' => $technicianId,
            'reasons' => array_values($reasons),
            'by_type' => [],
        ];
    }

    private function findCurrentCaeRecordId(PDO $pdo, int $technicianId): ?int
    {
        $stmt = $pdo->prepare('
            SELECT id
            FROM cae_records
            WHERE technician_id = :tid
              AND is_current = TRUE
            LIMIT 1
        ');
        $stmt->execute(['tid' => $technicianId]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    private function caeRecordBelongsToTechnician(PDO $pdo, int $caeRecordId, int $technicianId): bool
    {
        $stmt = $pdo->prepare('
            SELECT 1 FROM cae_records
            WHERE id = :id AND technician_id = :tid
            LIMIT 1
        ');
        $stmt->execute(['id' => $caeRecordId, 'tid' => $technicianId]);
        return (bool) $stmt->fetchColumn();
    }

    private function hasAeatColumns(PDO $pdo): bool
    {
        try {
            $stmt = $pdo->prepare("
                SELECT 1
                FROM information_schema.columns
                WHERE table_schema = 'public'
                  AND table_name = 'cae_documents'
                  AND column_name = 'aeat_cotejo_codigo'
                LIMIT 1
            ");
            $stmt->execute();
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, int> nombre canónico => document_type id
     */
    private function loadActiveSupportingTypeIdsByName(PDO $pdo): array
    {
        $placeholders = implode(',', array_fill(0, count(self::REQUIRED_FOR_CAE_GENERATION), '?'));
        $stmt = $pdo->prepare("
            SELECT id, name
            FROM document_types
            WHERE scope = 'technician_cae'
              AND is_cae_file_type = FALSE
              AND is_active = TRUE
              AND name IN ($placeholders)
        ");
        $stmt->execute(self::REQUIRED_FOR_CAE_GENERATION);
        $out = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $name = (string) ($row['name'] ?? '');
            if ($name !== '') {
                $out[$name] = (int) ($row['id'] ?? 0);
            }
        }
        return $out;
    }

    /**
     * @return array<int, array<string, mixed>> document_type_id => fila (último subido si hay varios activos)
     */
    private function loadActiveSupportingDocs(PDO $pdo, int $caeRecordId, bool $includeAeat): array
    {
        $aeatCols = $includeAeat
            ? ', cd.aeat_cotejo_codigo, cd.aeat_cotejo_huella_ok'
            : '';

        $stmt = $pdo->prepare("
            SELECT DISTINCT ON (cd.document_type_id)
                cd.document_type_id,
                cd.expires_at
                {$aeatCols}
            FROM cae_documents cd
            WHERE cd.cae_record_id = :cid
              AND cd.is_active = TRUE
              AND cd.is_cae_file = FALSE
            ORDER BY cd.document_type_id, cd.uploaded_at DESC NULLS LAST, cd.id DESC
        ");
        $stmt->execute(['cid' => $caeRecordId]);
        $byTypeId = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tid = (int) ($row['document_type_id'] ?? 0);
            if ($tid > 0) {
                $byTypeId[$tid] = $row;
            }
        }
        return $byTypeId;
    }

    private function dbBoolIsTrue(mixed $v): bool
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