<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Un solo complementario activo por (cae_record, document_type).
 * Desactiva filas previas antes del INSERT del nuevo soporte (is_cae_file = FALSE).
 */
final class CaeDocumentSlotService
{
    /**
     * Desactiva filas complementarias previas del mismo tipo. Si el PDO ya tiene
     * transacción abierta, no abre una nueva (p. ej. CaeController::uploadDocument).
     */
    public static function deactivatePriorSupportingDocuments(PDO $pdo, int $caeRecordId, int $documentTypeId): void
    {
        if ($caeRecordId <= 0 || $documentTypeId <= 0) {
            return;
        }

        $stmt = $pdo->prepare('
            UPDATE cae_documents
            SET is_active = FALSE,
                updated_at = NOW()
            WHERE cae_record_id = :cid
              AND document_type_id = :dtype
              AND is_cae_file = FALSE
              AND is_active = TRUE
        ');
        $stmt->execute([
            'cid' => $caeRecordId,
            'dtype' => $documentTypeId,
        ]);
    }

    /**
     * Unidad atómica: deactivate + INSERT del nuevo soporte activo.
     * Si PDO ya está en transacción, forma parte de ella (sin SAVEPOINT).
     * Si no, abre COMMIT solo para este bloque (p. ej. portal técnico).
     *
     * @template T
     * @param callable():T $insertAfterDeactivate ejecuta solo el INSERT (y devuelve p. ej. lastInsertId)
     * @return T
     */
    public static function replaceActiveSupportingSlot(PDO $pdo, int $caeRecordId, int $documentTypeId, callable $insertAfterDeactivate): mixed
    {
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            self::deactivatePriorSupportingDocuments($pdo, $caeRecordId, $documentTypeId);
            $result = $insertAfterDeactivate();
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return $result;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}