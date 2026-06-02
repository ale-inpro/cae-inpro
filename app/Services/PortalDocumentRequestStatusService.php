<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class PortalDocumentRequestStatusService
{
    /**
     * @param list<array{id?: int|string, name?: string}> $requestedDocs
     * @param array<int, array{state?: string, message?: string, filename?: string}> $batchResults
     * @return list<array{
     *   doc_type_id: int,
     *   doc_type_name: string,
     *   state: string,
     *   label: string,
     *   badge: string,
     *   message: string,
     *   filename: string,
     *   intake_id: ?int,
     *   suggested_csv: ?string,
     * }>
     */
    public static function evaluateRequestedDocuments(
        PDO $pdo,
        int $caeRecordId,
        array $requestedDocs,
        array $batchResults = []
    ): array {
        $hasAeat = self::tableHasAeatColumns($pdo);
        $out = [];

        foreach ($requestedDocs as $doc) {
            $typeId = (int) ($doc['id'] ?? 0);
            $typeName = trim((string) ($doc['name'] ?? ''));
            if ($typeId <= 0) {
                continue;
            }

            $entry = [
                'doc_type_id' => $typeId,
                'doc_type_name' => $typeName,
                'state' => 'missing',
                'label' => 'Pendiente',
                'badge' => 'text-bg-secondary',
                'message' => 'Aún no se ha recibido un archivo para este documento.',
                'filename' => '',
            ];

            $published = self::loadActivePublishedRow($pdo, $caeRecordId, $typeId);
            if ($published !== null) {
                $validity = CaeDocumentValidityService::evaluateSupportingRow($pdo, $published, $hasAeat);
                if (!empty($validity['valid_for_cae'])) {
                    $entry = self::applyState(
                        $entry,
                        'valid',
                        'Documento recibido y validado correctamente.',
                        (string) ($published['original_filename'] ?? '')
                    );
                } else {
                    $reason = DocumentIntakePresentationService::validityPrimaryReason($validity);
                    $entry = self::applyState(
                        $entry,
                        'invalid',
                        $reason !== '—'
                            ? $reason
                            : 'El documento no cumple los requisitos. Sube otro archivo.',
                        (string) ($published['original_filename'] ?? '')
                    );
                }
            } else {

                if ($published === null && ($entry['state'] ?? '') === 'missing') {
                    $intake = self::loadLatestPendingPortalIntake($pdo, $caeRecordId, $typeId);
                    if ($intake !== null && !HaciendaDocumentIntakeService::isHaciendaDocumentTypeName($typeName)) {
                        $intake = null;
                    }
                    if ($intake !== null) {
                        $present = DocumentIntakePresentationService::presentPendingIntake($intake);
                        $reason = (string) ($present['reason'] ?? '');
                        $message = self::portalIntakeMessage($typeName, $reason);
                        $lower = mb_strtolower($message);

                        if (HaciendaDocumentIntakeService::isHaciendaDocumentTypeName($typeName)) {
                            $state = 'confirm_csv';
                        } elseif (str_contains($lower, 'no se pudo publicar') || str_contains($lower, 'inténtelo de nuevo')) {
                            $state = 'error';
                        } else {
                            $state = 'pending';
                        }

                        $entry = self::applyState(
                            $entry,
                            $state,
                            $message,
                            (string) ($intake['original_filename'] ?? '')
                        );
                        $entry['intake_id'] = (int) ($intake['id'] ?? 0);
                        $entry['suggested_csv'] = self::normalizeSuggestedCsv(
                            (string) ($intake['extracted_aeat_csv'] ?? '')
                        );
                    }
                }
            }

            if (isset($batchResults[$typeId])) {
                $batch = $batchResults[$typeId];
                $state = trim((string) ($batch['state'] ?? ''));
                if ($state !== '') {
                    $entry = self::applyState(
                        $entry,
                        $state,
                        trim((string) ($batch['message'] ?? $entry['message'])),
                        trim((string) ($batch['filename'] ?? $entry['filename']))
                    );

                    if (isset($batch['intake_id'])) {
                        $entry['intake_id'] = (int) $batch['intake_id'];
                    }
                    if (isset($batch['suggested_csv']) && (string) $batch['suggested_csv'] !== '') {
                        $entry['suggested_csv'] = (string) $batch['suggested_csv'];
                    }
                }
            }

            $out[] = $entry;
        }

        return $out;
    }

    /** @param list<array{state?: string}> $statuses */
    public static function allRequestedAreValid(array $statuses): bool
    {
        if ($statuses === []) {
            return false;
        }

        foreach ($statuses as $row) {
            if (($row['state'] ?? '') !== 'valid') {
                return false;
            }
        }

        return true;
    }

    /** @param list<array{state?: string}> $statuses */
    public static function summarize(array $statuses): array
    {
        $counts = [
            'valid' => 0,
            'invalid' => 0,
            'pending' => 0,
            'confirm_csv' => 0,
            'missing' => 0,
            'error' => 0,
        ];

        foreach ($statuses as $row) {
            $state = (string) ($row['state'] ?? '');
            if (isset($counts[$state])) {
                $counts[$state]++;
            }
        }

        return $counts;
    }

    public static function portalIntakeMessage(string $docTypeName, string $rawReason): string
    {
        $human = trim(DocumentIntakePresentationService::humanizeNotes($rawReason));
        $lower = mb_strtolower($human);

        if (str_contains($lower, 'no se pudo publicar') || str_contains($lower, 'inténtelo de nuevo')) {
            return $human;
        }

        if (HaciendaDocumentIntakeService::isHaciendaDocumentTypeName($docTypeName)) {
            // Igual que admin: mostrar motivo real del intake si existe.
            if ($human !== '') {
                return $human;
            }

            return 'No se ha podido leer el CSV del PDF. Sube un certificado oficial donde el código de 16 caracteres sea claramente visible.';
        }

        return $human !== ''
            ? $human . ' Sube otro archivo más legible si es necesario.'
            : 'No se han podido validar las fechas del documento. Comprueba que el PDF sea legible y muestre la fecha de caducidad.';
    }

    /**
     * @return array{valid: bool, message: string}
     */
    public static function evaluatePublishedDocument(PDO $pdo, int $documentId): array
    {
        $row = self::loadDocumentRowById($pdo, $documentId);
        if ($row === null) {
            return [
                'valid' => false,
                'message' => 'No se pudo comprobar el documento publicado.',
            ];
        }

        $validity = CaeDocumentValidityService::evaluateSupportingRow(
            $pdo,
            $row,
            self::tableHasAeatColumns($pdo)
        );

        $reason = DocumentIntakePresentationService::validityPrimaryReason($validity);

        return [
            'valid' => !empty($validity['valid_for_cae']),
            'message' => $reason !== '—'
                ? $reason
                : 'El documento no cumple los requisitos. Sube otro archivo.',
        ];
    }

    public static function tableHasAeatColumns(PDO $pdo): bool
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

    /** @return array<string, mixed>|null */
    private static function loadActivePublishedRow(PDO $pdo, int $caeRecordId, int $documentTypeId): ?array
    {
        $hasAeat = self::tableHasAeatColumns($pdo);
        $aeatSelect = $hasAeat
            ? ', cd.aeat_cotejo_codigo, cd.aeat_cotejo_checked_at, cd.aeat_pdf_validation_ok, cd.aeat_pdf_validation_errors'
            : '';

        $stmt = $pdo->prepare("
            SELECT
                cd.id,
                cd.document_type_id,
                dt.name AS document_name,
                cd.original_filename,
                cd.expires_at
                {$aeatSelect}
            FROM cae_documents cd
            JOIN document_types dt ON dt.id = cd.document_type_id
            WHERE cd.cae_record_id = :cae_id
              AND cd.document_type_id = :dtype
              AND cd.is_active = TRUE
              AND cd.is_cae_file = FALSE
            ORDER BY cd.uploaded_at DESC
            LIMIT 1
        ");
        $stmt->execute([
            'cae_id' => $caeRecordId,
            'dtype' => $documentTypeId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    private static function loadDocumentRowById(PDO $pdo, int $documentId): ?array
    {
        $hasAeat = self::tableHasAeatColumns($pdo);
        $aeatSelect = $hasAeat
            ? ', cd.aeat_cotejo_codigo, cd.aeat_cotejo_checked_at, cd.aeat_pdf_validation_ok, cd.aeat_pdf_validation_errors'
            : '';

        $stmt = $pdo->prepare("
            SELECT
                cd.id,
                cd.document_type_id,
                dt.name AS document_name,
                cd.original_filename,
                cd.expires_at
                {$aeatSelect}
            FROM cae_documents cd
            JOIN document_types dt ON dt.id = cd.document_type_id
            WHERE cd.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $documentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    private static function loadLatestPendingPortalIntake(PDO $pdo, int $caeRecordId, int $documentTypeId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT
                i.id,
                i.original_filename,
                i.extracted_aeat_csv,
                i.ai_status,
                i.ai_confidence,
                i.ai_expires_at,
                i.ai_notes
            FROM cae_document_intake i
            WHERE i.cae_record_id = :cae_id
                AND i.document_type_id = :dtype
                AND i.source_channel = 'portal_upload'
                AND i.status = 'pending_manual'
            ORDER BY i.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([
            'cae_id' => $caeRecordId,
            'dtype' => $documentTypeId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private static function applyState(array $entry, string $state, string $message, string $filename = ''): array
    {
        $meta = match ($state) {
            'valid' => ['label' => 'Válido', 'badge' => 'text-bg-success'],
            'invalid' => ['label' => 'No válido', 'badge' => 'text-bg-danger'],
            'pending' => ['label' => 'No validado', 'badge' => 'text-bg-warning text-dark'],
            'error' => ['label' => 'Error', 'badge' => 'text-bg-danger'],
            'confirm_csv' => ['label' => 'Confirma CSV', 'badge' => 'text-bg-warning text-dark'],
            default => ['label' => 'Pendiente', 'badge' => 'text-bg-secondary'],
        };

        $entry['state'] = $state;
        $entry['label'] = $meta['label'];
        $entry['badge'] = $meta['badge'];
        $entry['message'] = $message;
        if ($filename !== '') {
            $entry['filename'] = $filename;
        }

        return $entry;
    }

        /**
     * Lista neutra para el GET del portal: sin mensajes de documentos antiguos en BD.
     *
     * @param list<array{id?: int|string, name?: string}> $requestedDocs
     * @return list<array{
     *   doc_type_id: int,
     *   doc_type_name: string,
     *   state: string,
     *   label: string,
     *   badge: string,
     *   message: string,
     *   filename: string
     * }>
     */
    public static function buildNeutralPortalStatuses(array $requestedDocs): array
    {
        $out = [];
        foreach ($requestedDocs as $doc) {
            $typeId = (int) ($doc['id'] ?? 0);
            if ($typeId <= 0) {
                continue;
            }
            $out[] = [
                'doc_type_id' => $typeId,
                'doc_type_name' => trim((string) ($doc['name'] ?? '')),
                'state' => 'missing',
                'label' => 'Pendiente',
                'badge' => 'text-bg-secondary',
                'message' => '',
                'filename' => '',
            ];
        }
        return $out;
    }

    /**
     * Estados para GET/POST del portal:
     * - válidos publicados en BD
     * - Hacienda con intake pending → confirm_csv
     * - flash de último intento fallido (sin guardar en BD)
     *
     * @param list<array{id?: int|string, name?: string}> $requestedDocs
     * @param array<int, array{state?: string, message?: string, filename?: string, intake_id?: int, suggested_csv?: string}> $flashAttempts
     * @return list<array<string, mixed>>
     */
    public static function buildPortalShowStatuses(
        PDO $pdo,
        int $caeRecordId,
        array $requestedDocs,
        array $flashAttempts = []
    ): array {
        $statuses = self::evaluateRequestedDocuments($pdo, $caeRecordId, $requestedDocs, []);

        return self::mergeFlashAttempts($statuses, $flashAttempts);
    }

    /**
     * @param list<array<string, mixed>> $statuses
     * @param array<int, array{state?: string, message?: string, filename?: string, intake_id?: int, suggested_csv?: string}> $flashAttempts
     * @return list<array<string, mixed>>
     */
    public static function mergeFlashAttempts(array $statuses, array $flashAttempts): array
    {
        if ($flashAttempts === []) {
            return $statuses;
        }

        foreach ($statuses as $i => $entry) {
            $typeId = (int) ($entry['doc_type_id'] ?? 0);
            if ($typeId <= 0 || !isset($flashAttempts[$typeId])) {
                continue;
            }
            if (($entry['state'] ?? '') === 'valid') {
                continue;
            }

            $flash = $flashAttempts[$typeId];
            $state = trim((string) ($flash['state'] ?? ''));
            if ($state === '') {
                continue;
            }

            $statuses[$i] = self::applyState(
                $entry,
                $state,
                trim((string) ($flash['message'] ?? $entry['message'] ?? '')),
                trim((string) ($flash['filename'] ?? $entry['filename'] ?? ''))
            );

            if (isset($flash['intake_id'])) {
                $statuses[$i]['intake_id'] = (int) $flash['intake_id'];
            }
            if (isset($flash['suggested_csv']) && (string) $flash['suggested_csv'] !== '') {
                $statuses[$i]['suggested_csv'] = (string) $flash['suggested_csv'];
            }
        }

        return $statuses;
    }

    public static function isDocTypeValidForPortal(PDO $pdo, int $caeRecordId, int $documentTypeId): bool
    {
        if ($caeRecordId <= 0 || $documentTypeId <= 0) {
            return false;
        }

        $published = self::loadActivePublishedRow($pdo, $caeRecordId, $documentTypeId);
        if ($published === null) {
            return false;
        }

        $validity = CaeDocumentValidityService::evaluateSupportingRow(
            $pdo,
            $published,
            self::tableHasAeatColumns($pdo)
        );

        return !empty($validity['valid_for_cae']);
    }

    public static function resolveActiveSupportingDocumentId(
        PDO $pdo,
        int $caeRecordId,
        int $documentTypeId
    ): int {
        if ($caeRecordId <= 0 || $documentTypeId <= 0) {
            return 0;
        }

        $stmt = $pdo->prepare('
            SELECT id
            FROM cae_documents
            WHERE cae_record_id = :cae_id
              AND document_type_id = :dtype
              AND is_active = TRUE
              AND is_cae_file = FALSE
            LIMIT 1
        ');
        $stmt->execute([
            'cae_id' => $caeRecordId,
            'dtype' => $documentTypeId,
        ]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * Portal: si el intento publicado no es válido, eliminarlo.
     * Opcionalmente reactiva el documento que estaba activo antes del replace.
     *
     * @return array{valid: bool, message: string}
     */
    public static function finalizePortalPublishedDocument(
        PDO $pdo,
        int $documentId,
        int $reactivatePriorId = 0
    ): array {
        $check = self::evaluatePublishedDocument($pdo, $documentId);
        if ($check['valid']) {
            return $check;
        }

        self::discardPortalDocument($pdo, $documentId);

        if ($reactivatePriorId > 0) {
            $verify = $pdo->prepare('
                SELECT id
                FROM cae_documents
                WHERE id = :id
                  AND is_active = FALSE
                  AND is_cae_file = FALSE
                LIMIT 1
            ');
            $verify->execute(['id' => $reactivatePriorId]);
            if ($verify->fetchColumn()) {
                $pdo->prepare('
                    UPDATE cae_documents
                    SET is_active = TRUE, updated_at = NOW()
                    WHERE id = :id
                ')->execute(['id' => $reactivatePriorId]);
            }
        }

        return $check;
    }

    public static function discardPortalDocument(PDO $pdo, int $documentId): void
    {
        if ($documentId <= 0) {
            return;
        }

        $stmt = $pdo->prepare('SELECT storage_path FROM cae_documents WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $documentId]);
        $path = (string) ($stmt->fetchColumn() ?: '');

        $pdo->prepare('DELETE FROM cae_documents WHERE id = :id')->execute(['id' => $documentId]);

        if ($path !== '') {
            $full = dirname(__DIR__, 2) . '/public' . $path;
            if (is_file($full)) {
                @unlink($full);
            }
        }
    }

    public static function discardPortalIntakeFile(?string $storagePath): void
    {
        $storagePath = trim((string) $storagePath);
        if ($storagePath === '') {
            return;
        }

        $full = dirname(__DIR__, 2) . '/public' . $storagePath;
        if (is_file($full)) {
            @unlink($full);
        }
    }

    private static function normalizeSuggestedCsv(string $raw): ?string
    {
        $csv = (new AeatCsvExtractionService())->normalizeCsv($raw);

        return $csv;
    }
}