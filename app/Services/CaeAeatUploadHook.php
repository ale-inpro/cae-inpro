<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Tras publicar un documento complementario (cae_documents), dispara AEAT cuando toca:
 * siempre tipo Hacienda (PDF); además otros type_ids opcionales en config.
 *
 * Ver docs/CAE_READINESS_REGLES.md §6 / paso 4.
 */
final class CaeAeatUploadHook
{
    /**
     * @param array<string, mixed> $appConfig resultado de config/app.php
     */
    public static function afterSupportingPdfPersisted(PDO $pdo, array $appConfig, int $caeDocumentId, int $documentTypeId, string $originalFilename): void
    {
        if ($caeDocumentId <= 0 || $documentTypeId <= 0) {
            return;
        }

        $ext = strtolower((string) pathinfo($originalFilename, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            return;
        }

        $haciendaId = CaeReadinessService::resolveHaciendaDocumentTypeId($pdo);
        $viaHacienda = $haciendaId !== null && $documentTypeId === $haciendaId;

        $autoIds = $appConfig['aeat_csv_auto_verify_document_type_ids'] ?? [];
        $viaConfig = is_array($autoIds)
            && $autoIds !== []
            && in_array($documentTypeId, array_map('intval', $autoIds), true);

        if (!$viaHacienda && !$viaConfig) {
            return;
        }

        try {
            (new AeatCotejoVerifierService())->verifyDocumentById($caeDocumentId, $pdo, $appConfig);
        } catch (\Throwable $e) {
            error_log('[CaeAeatUploadHook] ' . $e->getMessage());
        }
    }
}
