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
     * @return array<string, mixed>|null resultado del verificador, o null si no aplica
     */
    public static function afterSupportingPdfPersisted(PDO $pdo, array $appConfig, int $caeDocumentId, int $documentTypeId, string $originalFilename): ?array
    {
        if ($caeDocumentId <= 0 || $documentTypeId <= 0) {
            return null;
        }

        $ext = strtolower((string) pathinfo($originalFilename, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            return null;
        }

        $haciendaId = CaeReadinessService::resolveHaciendaDocumentTypeId($pdo);
        $viaHacienda = $haciendaId !== null && $documentTypeId === $haciendaId;

        $autoIds = $appConfig['aeat_csv_auto_verify_document_type_ids'] ?? [];
        $viaConfig = is_array($autoIds)
            && $autoIds !== []
            && in_array($documentTypeId, array_map('intval', $autoIds), true);

        if (!$viaHacienda && !$viaConfig) {
            return null;
        }

        try {
            return (new AeatCotejoVerifierService())->verifyDocumentById($caeDocumentId, $pdo, $appConfig);
        } catch (\Throwable $e) {
            error_log('[CaeAeatUploadHook] doc=' . $caeDocumentId . ' ' . $e->getMessage());
            self::persistHookFailure($pdo, $caeDocumentId, $appConfig, $e->getMessage());

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @param array<string, mixed> $appConfig
     */
    private static function persistHookFailure(PDO $pdo, int $documentId, array $appConfig, string $message): void
    {
        try {
            $useMock = !empty($appConfig['aeat_cotejo_use_mock']);
            $stmt = $pdo->prepare('
                UPDATE cae_documents
                SET aeat_cotejo_used_mock = :mock,
                    aeat_cotejo_checked_at = NOW(),
                    aeat_cotejo_descripcion = :desc,
                    aeat_pdf_validation_ok = FALSE,
                    aeat_pdf_validation_errors = :errors,
                    updated_at = NOW()
                WHERE id = :id
            ');
            $stmt->bindValue(':mock', $useMock, PDO::PARAM_BOOL);
            $stmt->bindValue(':desc', mb_substr('Error interno al comprobar Hacienda: ' . $message, 0, 2000));
            $stmt->bindValue(':errors', json_encode([$message], JSON_UNESCAPED_UNICODE));
            $stmt->bindValue(':id', $documentId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (\Throwable $inner) {
            error_log('[CaeAeatUploadHook] persistHookFailure ' . $inner->getMessage());
        }
    }
}
