<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Database;
use PDO;
use App\Services\Mailer;
use App\Services\DocumentIntakeAiService;
use App\Services\CaeDocumentSlotService;
use App\Services\CaeAeatUploadHook;
use App\Services\HaciendaDocumentIntakeService;
use App\Services\PortalDocumentRequestStatusService;
use App\Services\DocumentIntakePresentationService;
use App\Services\AeatCotejoVerifierService;

final class TechnicianPortalController extends Controller
{
    /** @param array<string, string> $params */
    public function show(array $params = []): void
    {
        $token = trim((string) ($params['token'] ?? ''));
        [$state, $data] = $this->resolveToken($token);

        if ($state === 'form') {
            $request = is_array($data['request'] ?? null) ? $data['request'] : [];
            $tid = (int) ($request['technician_id'] ?? 0);
            $pdo = Database::connection();
            $caeRecordId = $this->resolvePortalCaeRecordId($pdo, $request, $tid);
            $requestedDocs = is_array($data['docs'] ?? null) ? $data['docs'] : [];

            $data['docStatuses'] = PortalDocumentRequestStatusService::buildPortalShowStatuses(
                $pdo,
                $caeRecordId,
                $requestedDocs,
                $this->pullPortalDocFlash($token)
            );
            $data['showUploadFeedback'] = false;
        }

        $this->renderPortal($state, array_merge($data, ['token' => $token]));
    }

    /** @param array<string, string> $params */
    public function upload(array $params = []): void
    {
        $token = trim((string) ($params['token'] ?? ''));
        header('Location: ' . $this->baseUrl() . '/portal/' . rawurlencode($token));
        exit;
    }

        /** @param array<string, string> $params */
        public function uploadDocument(array $params = []): void
        {
            $token = trim((string) ($params['token'] ?? ''));
            $docTypeId = (int) ($params['docTypeId'] ?? 0);
            [$state, $data] = $this->resolveToken($token);
    
            if ($state !== 'form') {
                $this->renderPortal($state, array_merge($data, ['token' => $token]));
                return;
            }
    
            $request = $data['request'];
            $tid = (int) $request['technician_id'];
            $pdo = Database::connection();
            $requestedDocs = is_array($data['docs'] ?? null) ? $data['docs'] : [];
            $caeRecordId = $this->resolvePortalCaeRecordId($pdo, $request, $tid);
    
            if (!$this->isRequestedDocType($requestedDocs, $docTypeId)) {
                $this->renderPortal('form', array_merge($data, [
                    'token' => $token,
                    'showUploadFeedback' => true,
                    'formError' => 'Este documento no forma parte de la solicitud.',
                    'docStatuses' => PortalDocumentRequestStatusService::buildPortalShowStatuses($pdo, $caeRecordId, $requestedDocs),
                ]));
                return;
            }
    
            if ($caeRecordId > 0 && PortalDocumentRequestStatusService::isDocTypeValidForPortal($pdo, $caeRecordId, $docTypeId)) {
                $this->renderPortal('form', array_merge($data, [
                    'token' => $token,
                    'showUploadFeedback' => true,
                    'formError' => 'Este documento ya está validado. No puedes volver a enviarlo.',
                    'docStatuses' => PortalDocumentRequestStatusService::buildPortalShowStatuses($pdo, $caeRecordId, $requestedDocs),
                ]));
                return;
            }
    
            if ($caeRecordId <= 0) {
                $ins = $pdo->prepare("
                    INSERT INTO cae_records (technician_id, status, is_current, created_at, updated_at)
                    VALUES (:tid, 'pending', TRUE, NOW(), NOW())
                    RETURNING id
                ");
                $ins->execute(['tid' => $tid]);
                $caeRecordId = (int) $ins->fetchColumn();
                $pdo->prepare("
                    UPDATE cae_document_requests
                    SET cae_record_id = :cae_id, updated_at = NOW()
                    WHERE id = :id
                ")->execute([
                    'cae_id' => $caeRecordId,
                    'id' => (int) $request['id'],
                ]);
            }
    
            $file = $_FILES['file'] ?? null;
            if (!is_array($file)) {
                $this->renderPortal('form', array_merge($data, [
                    'token' => $token,
                    'showUploadFeedback' => true,
                    'formError' => 'Selecciona un archivo antes de enviar.',
                    'docStatuses' => PortalDocumentRequestStatusService::buildPortalShowStatuses($pdo, $caeRecordId, $requestedDocs),
                ]));
                return;
            }
    
            $batchResults = [];
            $batch = $this->processPortalUploadedFile(
                $pdo,
                $caeRecordId,
                $tid,
                $docTypeId,
                $file,
                $this->appConfig()
            );
            if ($batch !== null) {
                $batchResults[$docTypeId] = $batch;
            } else {
                $batchResults[$docTypeId] = [
                    'state' => 'error',
                    'message' => 'No se pudo procesar el archivo.',
                    'filename' => (string) ($file['name'] ?? ''),
                ];
            }
    
            $this->finishPortalFlow($token, $data, $pdo, $caeRecordId, $requestedDocs, $batchResults, $tid, $request);
        }

    /** @param array<string, string> $params */
    public function uploadHaciendaByCsv(array $params = []): void
    {
        $token = trim((string) ($params['token'] ?? ''));
        [$state, $data] = $this->resolveToken($token);

        if ($state !== 'form') {
            $this->renderPortal($state, array_merge($data, ['token' => $token]));
            return;
        }

        $request = $data['request'];
        $tid = (int) $request['technician_id'];
        $pdo = Database::connection();
        $caeRecordId = $this->resolvePortalCaeRecordId($pdo, $request, $tid);
        $requestedDocs = is_array($data['docs'] ?? null) ? $data['docs'] : [];

        $documentTypeId = (int) ($_POST['document_type_id'] ?? 0);
        $csvRaw = strtoupper(trim((string) ($_POST['manual_aeat_csv'] ?? '')));

        $haciendaId = null;
        foreach ($requestedDocs as $doc) {
            if ((int) ($doc['id'] ?? 0) === $documentTypeId) {
                $haciendaId = $documentTypeId;
                break;
            }
        }

        if ($haciendaId === null || $documentTypeId <= 0) {
            $this->renderPortal('form', array_merge($data, [
                'token' => $token,
                'showUploadFeedback' => true,
                'formError' => 'Tipo de documento no válido para esta solicitud.',
                'docStatuses' => PortalDocumentRequestStatusService::buildNeutralPortalStatuses($requestedDocs),
            ]));
            return;
        }

        $docTypeName = $this->resolveDocTypeName($pdo, $documentTypeId);
        if ($docTypeName === null || !HaciendaDocumentIntakeService::isHaciendaDocumentTypeName($docTypeName)) {
            $this->renderPortal('form', array_merge($data, [
                'token' => $token,
                'showUploadFeedback' => true,
                'formError' => 'Solo puedes usar CSV en el certificado de Hacienda.',
                'docStatuses' => PortalDocumentRequestStatusService::buildNeutralPortalStatuses($requestedDocs),
            ]));
            return;
        }

        if ($csvRaw === '' || !preg_match('/^[A-Z0-9]{16}$/', $csvRaw)) {
            $this->renderPortal('form', array_merge($data, [
                'token' => $token,
                'showUploadFeedback' => true,
                'formError' => 'Indica un CSV válido (16 caracteres alfanuméricos).',
                'docStatuses' => PortalDocumentRequestStatusService::buildNeutralPortalStatuses($requestedDocs),
            ]));
            return;
        }

        if ($caeRecordId <= 0) {
            $ins = $pdo->prepare("
                INSERT INTO cae_records (technician_id, status, is_current, created_at, updated_at)
                VALUES (:tid, 'pending', TRUE, NOW(), NOW())
                RETURNING id
            ");
            $ins->execute(['tid' => $tid]);
            $caeRecordId = (int) $ins->fetchColumn();
            $pdo->prepare("
                UPDATE cae_document_requests
                SET cae_record_id = :cae_id, updated_at = NOW()
                WHERE id = :id
            ")->execute([
                'cae_id' => $caeRecordId,
                'id' => (int) $request['id'],
            ]);
        }

        if ($caeRecordId > 0 && PortalDocumentRequestStatusService::isDocTypeValidForPortal($pdo, $caeRecordId, $documentTypeId)) {
            $this->renderPortal('form', array_merge($data, [
                'token' => $token,
                'showUploadFeedback' => true,
                'formError' => 'El certificado de Hacienda ya está validado.',
                'docStatuses' => PortalDocumentRequestStatusService::buildPortalShowStatuses($pdo, $caeRecordId, $requestedDocs),
            ]));
            return;
        }

        $priorActiveDocId = PortalDocumentRequestStatusService::resolveActiveSupportingDocumentId(
            $pdo,
            $caeRecordId,
            $documentTypeId
        );

        try {
            $result = (new AeatCotejoVerifierService())->publishHaciendaFromCsv(
                $caeRecordId,
                $documentTypeId,
                $csvRaw,
                $pdo,
                $this->appConfig(),
                0
            );
        } catch (\Throwable $e) {
            error_log('[TechnicianPortalController::uploadHaciendaByCsv] ' . $e->getMessage());
            $this->renderPortal('form', array_merge($data, [
                'token' => $token,
                'showUploadFeedback' => true,
                'formError' => 'No se pudo obtener el certificado de Hacienda. Inténtelo de nuevo o contacte al administrador.',
                'docStatuses' => PortalDocumentRequestStatusService::buildPortalShowStatuses($pdo, $caeRecordId, $requestedDocs),
            ]));
            return;
        }

        $batchResults = [];
        if (empty($result['document_id'])) {
            $batchResults[$documentTypeId] = [
                'state' => 'invalid',
                'message' => (string) ($result['error'] ?? 'No se pudo obtener el certificado de Hacienda.'),
                'filename' => 'CSV: ' . $csvRaw,
            ];
        } else {
            $check = PortalDocumentRequestStatusService::finalizePortalPublishedDocument(
                $pdo,
                (int) $result['document_id'],
                $priorActiveDocId
            );
            $batchResults[$documentTypeId] = [
                'state' => $check['valid'] ? 'valid' : 'invalid',
                'message' => $check['message'],
                'filename' => 'Certificado obtenido por CSV',
            ];
        }

        $this->finishPortalFlow(
            $token,
            $data,
            $pdo,
            $caeRecordId,
            $requestedDocs,
            $batchResults,
            $tid,
            $request,
            empty($result['document_id']) ? (string) ($result['error'] ?? '') : ''
        );
    }

    /** @param array<string, string> $params */
    public function confirmHaciendaCsv(array $params = []): void
    {
        $token = trim((string) ($params['token'] ?? ''));
        [$state, $data] = $this->resolveToken($token);

        if ($state !== 'form') {
            $this->renderPortal($state, array_merge($data, ['token' => $token]));
            return;
        }

        $request = $data['request'];
        $tid = (int) $request['technician_id'];
        $pdo = Database::connection();
        $caeRecordId = $this->resolvePortalCaeRecordId($pdo, $request, $tid);
        $requestedDocs = is_array($data['docs'] ?? null) ? $data['docs'] : [];
        $appCfg = $this->appConfig();

        $intakeId = (int) ($_POST['intake_id'] ?? 0);
        $manualAeatCsv = strtoupper(trim((string) ($_POST['manual_aeat_csv'] ?? '')));

        if ($intakeId <= 0) {
            $this->renderPortal('form', array_merge($data, [
                'token' => $token,
                'showUploadFeedback' => true,
                'formError' => 'No se encontró el documento pendiente de confirmación.',
                'docStatuses' => PortalDocumentRequestStatusService::buildPortalShowStatuses($pdo, $caeRecordId, $requestedDocs),
            ]));
            return;
        }

        $stmt = $pdo->prepare("
            SELECT i.id, i.cae_record_id, i.document_type_id, i.original_filename, i.storage_path,
                    i.mime_type, i.file_size, i.extracted_aeat_csv, i.status, i.source_channel,
                    dt.name AS document_name
            FROM cae_document_intake i
            JOIN document_types dt ON dt.id = i.document_type_id
            WHERE i.id = :id
                AND i.technician_id = :tid
                AND i.source_channel = 'portal_upload'
                AND i.status = 'pending_manual'
            LIMIT 1
        ");
        $stmt->execute(['id' => $intakeId, 'tid' => $tid]);
        $intake = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$intake || (int) ($intake['cae_record_id'] ?? 0) !== $caeRecordId) {
            $this->renderPortal('form', array_merge($data, [
                'token' => $token,
                'showUploadFeedback' => true,
                'formError' => 'El documento pendiente no es válido o ya fue procesado.',
                'docStatuses' => PortalDocumentRequestStatusService::buildPortalShowStatuses($pdo, $caeRecordId, $requestedDocs),
            ]));
            return;
        }

        $docTypeName = (string) ($intake['document_name'] ?? '');
        if (!HaciendaDocumentIntakeService::isHaciendaDocumentTypeName($docTypeName)) {
            $this->renderPortal('form', array_merge($data, [
                'token' => $token,
                'showUploadFeedback' => true,
                'formError' => 'Solo puedes confirmar CSV en certificados de Hacienda.',
                'docStatuses' => PortalDocumentRequestStatusService::buildPortalShowStatuses($pdo, $caeRecordId, $requestedDocs),
            ]));
            return;
        }

        $haciendaIntake = new HaciendaDocumentIntakeService();
        $resolvedCsv = $haciendaIntake->resolveCsvForApproval(
            isset($intake['extracted_aeat_csv']) ? (string) $intake['extracted_aeat_csv'] : null,
            $manualAeatCsv
        );

        if ($resolvedCsv === null) {
            $this->renderPortal('form', array_merge($data, [
                'token' => $token,
                'showUploadFeedback' => true,
                'formError' => 'Indica un CSV válido (16 caracteres alfanuméricos).',
                'docStatuses' => PortalDocumentRequestStatusService::buildPortalShowStatuses($pdo, $caeRecordId, $requestedDocs),
            ]));
            return;
        }

        $documentTypeId = (int) ($intake['document_type_id'] ?? 0);

        if ($caeRecordId > 0 && PortalDocumentRequestStatusService::isDocTypeValidForPortal($pdo, $caeRecordId, $documentTypeId)) {
            $this->renderPortal('form', array_merge($data, [
                'token' => $token,
                'showUploadFeedback' => true,
                'formError' => 'El certificado de Hacienda ya está validado.',
                'docStatuses' => PortalDocumentRequestStatusService::buildPortalShowStatuses($pdo, $caeRecordId, $requestedDocs),
            ]));
            return;
        }

        $priorActiveDocId = PortalDocumentRequestStatusService::resolveActiveSupportingDocumentId(
            $pdo,
            $caeRecordId,
            $documentTypeId
        );

        $approvedCaeDocId = 0;

        try {
            $approvedCaeDocId = (int) CaeDocumentSlotService::replaceActiveSupportingSlot(
                $pdo,
                $caeRecordId,
                $documentTypeId,
                function () use ($pdo, $intake, $resolvedCsv): int {
                    $stmt = $pdo->prepare("
                        INSERT INTO cae_documents
                        (cae_record_id, document_type_id, original_filename, storage_path, mime_type, file_size,
                            uploaded_by_user_id, uploaded_at, is_active, is_cae_file, expires_at, extracted_aeat_csv, created_at, updated_at)
                        VALUES
                        (:cae_id, :doc_type, :orig, :path, :mime, :size,
                            NULL, NOW(), TRUE, FALSE, NULL, :extracted_aeat_csv, NOW(), NOW())
                    ");
                    $stmt->execute([
                        'cae_id' => (int) ($intake['cae_record_id'] ?? 0),
                        'doc_type' => (int) ($intake['document_type_id'] ?? 0),
                        'orig' => (string) ($intake['original_filename'] ?? ''),
                        'path' => (string) ($intake['storage_path'] ?? ''),
                        'mime' => (string) ($intake['mime_type'] ?? 'application/octet-stream'),
                        'size' => (int) ($intake['file_size'] ?? 0),
                        'extracted_aeat_csv' => $resolvedCsv,
                    ]);

                    return (int) $pdo->lastInsertId();
                }
            );

            $pdo->prepare("
                UPDATE cae_document_intake
                SET status = 'approved_manual',
                    requires_manual_review = FALSE,
                    manual_aeat_csv = :manual_csv,
                    extracted_aeat_csv = COALESCE(extracted_aeat_csv, :manual_csv),
                    reviewed_by_user_id = NULL,
                    reviewed_at = NOW(),
                    updated_at = NOW()
                WHERE id = :id
            ")->execute([
                'manual_csv' => $resolvedCsv,
                'id' => $intakeId,
            ]);
        } catch (\Throwable $e) {
            error_log('[TechnicianPortalController::confirmHaciendaCsv] ' . $e->getMessage());
            $this->renderPortal('form', array_merge($data, [
                'token' => $token,
                'showUploadFeedback' => true,
                'formError' => 'No se pudo publicar el certificado de Hacienda.',
                'docStatuses' => PortalDocumentRequestStatusService::buildPortalShowStatuses($pdo, $caeRecordId, $requestedDocs),
            ]));
            return;
        }

        CaeAeatUploadHook::afterSupportingPdfPersisted(
            $pdo,
            $appCfg,
            $approvedCaeDocId,
            $documentTypeId,
            (string) ($intake['original_filename'] ?? '')
        );

        $check = PortalDocumentRequestStatusService::finalizePortalPublishedDocument(
            $pdo,
            $approvedCaeDocId,
            $priorActiveDocId
        );

        if (!$check['valid']) {
            $pdo->prepare("
                UPDATE cae_document_intake
                SET status = 'pending_manual',
                    requires_manual_review = TRUE,
                    reviewed_by_user_id = NULL,
                    reviewed_at = NULL,
                    updated_at = NOW()
                WHERE id = :id
            ")->execute(['id' => $intakeId]);
        }

        $batchResults = [
            $documentTypeId => [
                'state' => $check['valid'] ? 'valid' : 'invalid',
                'message' => $check['message'],
                'filename' => (string) ($intake['original_filename'] ?? ''),
            ],
        ];

        $this->finishPortalFlow(
            $token,
            $data,
            $pdo,
            $caeRecordId,
            $requestedDocs,
            $batchResults,
            $tid,
            $request,
            $check['valid'] ? '' : $check['message']
        );
    }

        /** @return array<int, array{state?: string, message?: string, filename?: string, intake_id?: int, suggested_csv?: string}> */
        private function pullPortalDocFlash(string $token): array
        {
            if ($token === '') {
                return [];
            }
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
    
            $all = $_SESSION['portal_doc_flash'][$token] ?? [];
            unset($_SESSION['portal_doc_flash'][$token]);
    
            return is_array($all) ? $all : [];
        }
    
        /** @param array{state?: string, message?: string, filename?: string, intake_id?: int, suggested_csv?: string} $payload */
        private function rememberPortalDocFlash(string $token, int $docTypeId, array $payload): void
        {
            if ($token === '' || $docTypeId <= 0) {
                return;
            }
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
    
            $_SESSION['portal_doc_flash'][$token][$docTypeId] = $payload;
        }
    
    /** @param array<string, mixed> $data */
    private function finishPortalFlow(
        string $token,
        array $data,
        PDO $pdo,
        int $caeRecordId,
        array $requestedDocs,
        array $batchResults,
        int $tid,
        array $request,
        string $formError = ''
    ): void {
        foreach ($batchResults as $docTypeId => $batch) {
            $state = (string) ($batch['state'] ?? '');
            if (in_array($state, ['invalid', 'error', 'pending', 'confirm_csv'], true)) {
                $this->rememberPortalDocFlash($token, (int) $docTypeId, $batch);
            }
        }

        $docStatuses = PortalDocumentRequestStatusService::evaluateRequestedDocuments(
            $pdo,
            $caeRecordId,
            $requestedDocs,
            $batchResults
        );
        $allValid = PortalDocumentRequestStatusService::allRequestedAreValid($docStatuses);
        $summary = PortalDocumentRequestStatusService::summarize($docStatuses);
        $techName = trim((string) ($request['display_name'] ?? ''));

        $hasNewValidUpload = false;
        foreach ($batchResults as $batch) {
            if (($batch['state'] ?? '') === 'valid') {
                $hasNewValidUpload = true;
                break;
            }
        }

        if ($hasNewValidUpload) {
            $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($admins as $adminId) {
                $this->createNotification(
                    (int) $adminId,
                    'cae_docs_uploaded',
                    $allValid ? 'Documentos CAE completados' : 'Documentos CAE recibidos',
                    $allValid
                        ? "El técnico {$techName} ha completado todos los documentos solicitados por el portal."
                        : "El técnico {$techName} ha enviado un documento válido por el portal.",
                    ['technician_id' => $tid, 'cae_record_id' => $caeRecordId]
                );
            }
        }

        if ($allValid) {
            $pdo->prepare("
                UPDATE cae_document_requests
                SET token_used_at = NOW(), status = 'completed', completed_at = NOW(), updated_at = NOW()
                WHERE id = :id
            ")->execute(['id' => (int) $request['id']]);

            $adminEmail = (string) ($pdo->query("SELECT email FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn() ?: '');
            if ($adminEmail !== '') {
                $adminLink = $this->baseUrl() . '/admin/tecnicos/' . $tid . '#pane-docs';
                $bodyHtml = "
                    <h2>Documentos CAE recibidos</h2>
                    <p>El técnico <strong>" . htmlspecialchars($techName) . "</strong> ha completado
                        la documentación solicitada por el portal.</p>
                    <p><a href='" . htmlspecialchars($adminLink) . "' style='color:#059669'>Ver documentos del técnico →</a></p>
                ";
                Mailer::send(
                    $adminEmail,
                    'Documentos CAE recibidos — ' . $techName,
                    Mailer::template('Documentos recibidos', $bodyHtml)
                );
            }

            $this->renderPortal('success', [
                'token' => $token,
                'techName' => $techName,
            ]);
            return;
        }

        $this->renderPortal('form', array_merge($data, [
            'token' => $token,
            'showUploadFeedback' => true,
            'docStatuses' => $docStatuses,
            'batchSummary' => $summary,
            'formError' => $formError,
        ]));
    }

        /** @param list<array{id?: int|string, name?: string}> $requestedDocs */
        private function isRequestedDocType(array $requestedDocs, int $docTypeId): bool
        {
            foreach ($requestedDocs as $doc) {
                if ((int) ($doc['id'] ?? 0) === $docTypeId) {
                    return true;
                }
            }
    
            return false;
        }
    
        /**
         * @param array<string, mixed> $file
         * @param array<string, mixed> $appCfg
         * @return array{state: string, message: string, filename: string, intake_id?: int, suggested_csv?: string|null}|null
         */
        private function processPortalUploadedFile(
            PDO $pdo,
            int $caeRecordId,
            int $tid,
            int $docTypeId,
            array $file,
            array $appCfg
        ): ?array {
            $originalName = (string) ($file['name'] ?? '');
            $tmpPath = (string) ($file['tmp_name'] ?? '');
            $fileError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            $mimeType = (string) ($file['type'] ?? 'application/octet-stream');
            $fileSize = (int) ($file['size'] ?? 0);
    
            if ($fileError !== UPLOAD_ERR_OK || $tmpPath === '' || !is_uploaded_file($tmpPath)) {
                return [
                    'state' => 'error',
                    'message' => 'No se pudo recibir el archivo. Vuelve a intentarlo.',
                    'filename' => $originalName,
                ];
            }
    
            $docTypeName = $this->resolveDocTypeName($pdo, $docTypeId);
            if ($docTypeName === null) {
                return [
                    'state' => 'error',
                    'message' => 'Tipo de documento no válido. Contacta con el administrador.',
                    'filename' => $originalName,
                ];
            }
    
            $ext = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
            $safeName = date('Ymd_His') . '_' . uniqid() . ($ext !== '' ? '.' . $ext : '');
            $dir = dirname(__DIR__, 3) . '/public/uploads/cae-docs/' . $tid;
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            if (!move_uploaded_file($tmpPath, $dir . '/' . $safeName)) {
                return [
                    'state' => 'error',
                    'message' => 'No se pudo guardar el archivo en el servidor.',
                    'filename' => $originalName,
                ];
            }
    
            $storagePath = '/uploads/cae-docs/' . $tid . '/' . $safeName;
            $fullPath = $dir . '/' . $safeName;
    
            if (HaciendaDocumentIntakeService::isHaciendaDocumentTypeName($docTypeName)) {
                return $this->processPortalHaciendaPdfUpload(
                    $pdo,
                    $caeRecordId,
                    $tid,
                    $docTypeId,
                    $docTypeName,
                    $originalName,
                    $storagePath,
                    $mimeType,
                    $fileSize,
                    $appCfg
                );
            }
    
            return $this->processPortalStandardDocUpload(
                $pdo,
                $caeRecordId,
                $docTypeId,
                $docTypeName,
                $originalName,
                $storagePath,
                $mimeType,
                $fileSize,
                $appCfg
            );
        }
    
        /**
         * @param array<string, mixed> $appCfg
         * @return array{state: string, message: string, filename: string, intake_id?: int, suggested_csv?: string|null}
         */
        private function processPortalHaciendaPdfUpload(
            PDO $pdo,
            int $caeRecordId,
            int $tid,
            int $docTypeId,
            string $docTypeName,
            string $originalName,
            string $storagePath,
            string $mimeType,
            int $fileSize,
            array $appCfg
        ): array {
            $haciendaIntake = new HaciendaDocumentIntakeService();
            $csvEval = $haciendaIntake->evaluateUploadedPdf(
                dirname(__DIR__, 3) . '/public' . $storagePath
            );
            $detectedCsv = $csvEval['csv'];
            $prefillCsv = isset($csvEval['prefill_csv']) ? (string) $csvEval['prefill_csv'] : null;
            $needsManual = (bool) $csvEval['needs_manual'];
    
            if ($needsManual) {
                $pdo->prepare("
                    UPDATE cae_document_intake
                    SET status = 'rejected',
                        requires_manual_review = FALSE,
                        reviewed_at = NOW(),
                        updated_at = NOW()
                    WHERE cae_record_id = :cae_id
                      AND document_type_id = :dtype
                      AND source_channel = 'portal_upload'
                      AND status = 'pending_manual'
                ")->execute([
                    'cae_id' => $caeRecordId,
                    'dtype' => $docTypeId,
                ]);
            }
    
            $intakeStmt = $pdo->prepare("
                INSERT INTO cae_document_intake
                (technician_id, cae_record_id, document_type_id, original_filename, storage_path, mime_type, file_size,
                source_channel, uploaded_by_user_id, extracted_text, ai_status, ai_confidence, ai_issue_date, ai_expires_at, ai_notes,
                extracted_aeat_csv, status, requires_manual_review, created_at, updated_at)
                VALUES
                (:technician_id, :cae_record_id, :document_type_id, :original_filename, :storage_path, :mime_type, :file_size,
                'portal_upload', NULL, NULL, :ai_status, NULL, NULL, NULL, :ai_notes,
                :extracted_aeat_csv, :status, :requires_manual_review, NOW(), NOW())
                RETURNING id
            ");
            $intakeStmt->execute([
                'technician_id' => $tid,
                'cae_record_id' => $caeRecordId,
                'document_type_id' => $docTypeId,
                'original_filename' => $originalName,
                'storage_path' => $storagePath,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'ai_status' => $needsManual ? 'manual_review' : 'approved',
                'ai_notes' => (string) $csvEval['notes'],
                'extracted_aeat_csv' => $detectedCsv ?? ($prefillCsv !== '' && $prefillCsv !== null ? $prefillCsv : null),
                'status' => $needsManual ? 'pending_manual' : 'approved_auto',
                'requires_manual_review' => $needsManual ? 'true' : 'false',
            ]);
            $intakeId = (int) $intakeStmt->fetchColumn();
    
            if ($needsManual) {
                return [
                    'state' => 'confirm_csv',
                    'message' => PortalDocumentRequestStatusService::portalIntakeMessage(
                        $docTypeName,
                        (string) $csvEval['notes']
                    ),
                    'filename' => $originalName,
                    'intake_id' => $intakeId,
                    'suggested_csv' => $prefillCsv ?? $detectedCsv,
                ];
            }
    
            if ($detectedCsv === null) {
                $pdo->prepare("
                    UPDATE cae_document_intake
                    SET status = 'rejected',
                        requires_manual_review = FALSE,
                        reviewed_at = NOW(),
                        updated_at = NOW()
                    WHERE id = :id
                ")->execute(['id' => $intakeId]);
                PortalDocumentRequestStatusService::discardPortalIntakeFile($storagePath);
                return [
                    'state' => 'invalid',
                    'message' => 'No se pudo leer el CSV del certificado. Sube un PDF más legible o usa envío por CSV.',
                    'filename' => $originalName,
                ];
            }
    
            $priorActiveDocId = PortalDocumentRequestStatusService::resolveActiveSupportingDocumentId(
                $pdo,
                $caeRecordId,
                $docTypeId
            );

            try {
                $newDocId = (int) CaeDocumentSlotService::replaceActiveSupportingSlot(
                    $pdo,
                    $caeRecordId,
                    $docTypeId,
                    function () use (
                        $pdo,
                        $caeRecordId,
                        $docTypeId,
                        $originalName,
                        $storagePath,
                        $mimeType,
                        $fileSize,
                        $detectedCsv
                    ): int {
                        $pdo->prepare("
                            INSERT INTO cae_documents
                            (cae_record_id, document_type_id, original_filename, storage_path,
                            mime_type, file_size, uploaded_by_user_id,
                            uploaded_at, is_active, is_cae_file, expires_at, extracted_aeat_csv, created_at, updated_at)
                            VALUES
                            (:cae_id, :dtype, :orig, :path,
                            :mime, :size, NULL,
                            NOW(), TRUE, FALSE, NULL, :extracted_aeat_csv, NOW(), NOW())
                        ")->execute([
                            'cae_id' => $caeRecordId,
                            'dtype' => $docTypeId,
                            'orig' => $originalName,
                            'path' => $storagePath,
                            'mime' => $mimeType,
                            'size' => $fileSize,
                            'extracted_aeat_csv' => $detectedCsv,
                        ]);
    
                        return (int) $pdo->lastInsertId();
                    }
                );
                CaeAeatUploadHook::afterSupportingPdfPersisted(
                    $pdo,
                    $appCfg,
                    $newDocId,
                    $docTypeId,
                    $originalName
                );
                $check = PortalDocumentRequestStatusService::finalizePortalPublishedDocument(
                    $pdo,
                    $newDocId,
                    $priorActiveDocId
                );
    
                if (!$check['valid']) {
                    PortalDocumentRequestStatusService::discardPortalIntakeFile($storagePath);
                    return [
                        'state' => 'invalid',
                        'message' => $check['message'],
                        'filename' => $originalName,
                    ];
                }
    
                return [
                    'state' => 'valid',
                    'message' => $check['message'],
                    'filename' => $originalName,
                ];
            } catch (\Throwable $e) {
                error_log('[TechnicianPortalController::processPortalHaciendaPdfUpload] ' . $e->getMessage());
                PortalDocumentRequestStatusService::discardPortalIntakeFile($storagePath);
                return [
                    'state' => 'error',
                    'message' => 'No se pudo publicar el certificado de Hacienda.',
                    'filename' => $originalName,
                ];
            }
        }
    
        /**
         * @param array<string, mixed> $appCfg
         * @return array{state: string, message: string, filename: string}
         */
        private function processPortalStandardDocUpload(
            PDO $pdo,
            int $caeRecordId,
            int $docTypeId,
            string $docTypeName,
            string $originalName,
            string $storagePath,
            string $mimeType,
            int $fileSize,
            array $appCfg
        ): array {
            $fullPath = dirname(__DIR__, 3) . '/public' . $storagePath;
            $analysis = DocumentIntakeAiService::analyze($fullPath, $mimeType, $docTypeName);
            $aiStatus = (string) ($analysis['status'] ?? 'manual_review');
            $confidence = (float) ($analysis['confidence'] ?? 0.0);
            $issueDate = (string) ($analysis['issue_date'] ?? '');
            $expiresAt = (string) ($analysis['expires_at'] ?? '');
            $notes = (string) ($analysis['notes'] ?? '');
    
            $issueDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $issueDate) ? $issueDate : null;
            $expiresAt = preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiresAt) ? $expiresAt : null;
            if ($expiresAt === null) {
                $expiresAt = $this->autoExpireFromIssue($docTypeName, $issueDate);
            }
    
            $aiStatus = DocumentIntakeAiService::calcStatus($expiresAt, $issueDate);
            $needsManual = ($aiStatus === 'manual_review' || $expiresAt === null);
    
            if ($needsManual) {
                PortalDocumentRequestStatusService::discardPortalIntakeFile($storagePath);
                $present = DocumentIntakePresentationService::presentPendingIntake([
                    'ai_status' => $aiStatus,
                    'ai_confidence' => $confidence,
                    'ai_expires_at' => $expiresAt,
                    'ai_notes' => $notes,
                ]);
    
                return [
                    'state' => 'invalid',
                    'message' => PortalDocumentRequestStatusService::portalIntakeMessage(
                        $docTypeName,
                        (string) ($present['reason'] ?? '')
                    ),
                    'filename' => $originalName,
                ];
            }

            $priorActiveDocId = PortalDocumentRequestStatusService::resolveActiveSupportingDocumentId(
                $pdo,
                $caeRecordId,
                $docTypeId
            );
    
            try {
                $newDocId = (int) CaeDocumentSlotService::replaceActiveSupportingSlot(
                    $pdo,
                    $caeRecordId,
                    $docTypeId,
                    function () use (
                        $pdo,
                        $caeRecordId,
                        $docTypeId,
                        $originalName,
                        $storagePath,
                        $mimeType,
                        $fileSize,
                        $expiresAt
                    ): int {
                        $pdo->prepare("
                            INSERT INTO cae_documents
                            (cae_record_id, document_type_id, original_filename, storage_path,
                            mime_type, file_size, uploaded_by_user_id,
                            uploaded_at, is_active, is_cae_file, expires_at, created_at, updated_at)
                            VALUES
                            (:cae_id, :dtype, :orig, :path,
                            :mime, :size, NULL,
                            NOW(), TRUE, FALSE, :expires_at, NOW(), NOW())
                        ")->execute([
                            'cae_id' => $caeRecordId,
                            'dtype' => $docTypeId,
                            'orig' => $originalName,
                            'path' => $storagePath,
                            'mime' => $mimeType,
                            'size' => $fileSize,
                            'expires_at' => $expiresAt,
                        ]);
    
                        return (int) $pdo->lastInsertId();
                    }
                );
                CaeAeatUploadHook::afterSupportingPdfPersisted(
                    $pdo,
                    $appCfg,
                    $newDocId,
                    $docTypeId,
                    $originalName
                );
                $check = PortalDocumentRequestStatusService::finalizePortalPublishedDocument(
                    $pdo,
                    $newDocId,
                    $priorActiveDocId
                );
    
                if (!$check['valid']) {
                    PortalDocumentRequestStatusService::discardPortalIntakeFile($storagePath);
                    return [
                        'state' => 'invalid',
                        'message' => $check['message'],
                        'filename' => $originalName,
                    ];
                }
    
                return [
                    'state' => 'valid',
                    'message' => $check['message'],
                    'filename' => $originalName,
                ];
            } catch (\Throwable $e) {
                error_log('[TechnicianPortalController::processPortalStandardDocUpload] ' . $e->getMessage());
                PortalDocumentRequestStatusService::discardPortalIntakeFile($storagePath);
                return [
                    'state' => 'error',
                    'message' => 'No se pudo publicar el documento.',
                    'filename' => $originalName,
                ];
            }
        }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function resolveToken(string $token): array
    {
        if ($token === '') {
            return ['error', ['msg' => 'Enlace no válido.']];
        }

        $pdo  = Database::connection();
        $stmt = $pdo->prepare("
            SELECT r.id, r.technician_id, r.cae_record_id,
                   r.documents_requested_json, r.custom_message,
                   r.token_expires_at, r.token_used_at,
                   t.display_name
            FROM cae_document_requests r
            JOIN technicians t ON t.id = r.technician_id
            WHERE r.upload_token = :token
            LIMIT 1
        ");
        $stmt->execute(['token' => $token]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            return ['error', ['msg' => 'El enlace no es válido o no existe.']];
        }
        if ($request['token_used_at'] !== null) {
            return ['used', ['msg' => 'Este enlace ya fue utilizado. Los documentos han sido enviados correctamente. ¡Gracias!']];
        }
        if ($request['token_expires_at'] !== null && strtotime((string) $request['token_expires_at']) < time()) {
            return ['error', ['msg' => 'Este enlace ha caducado. Contacta con el administrador para solicitar uno nuevo.']];
        }

        $docs     = json_decode((string) ($request['documents_requested_json'] ?? '[]'), true) ?: [];
        $techName = trim((string) ($request['display_name'] ?? ''));

        return ['form', [
            'request'  => $request,
            'docs'     => $docs,
            'techName' => $techName,
        ]];
    }

    /** @param array<string, mixed> $data */
    private function renderPortal(string $state, array $data): void
    {
        $this->render('portal.upload', array_merge($data, [
            'title'   => 'Portal de documentos CAE',
            'state'   => $state,
            'baseUrl' => $this->baseUrl(),
        ]), 'layouts.portal');
    }

    private function resolveDocTypeName(PDO $pdo, int $docTypeId): ?string
    {
        if ($docTypeId <= 0) {
            return null;
        }
        $stmt = $pdo->prepare("
            SELECT name
            FROM document_types
            WHERE id = :id
              AND scope = 'technician_cae'
              AND is_active = TRUE
              AND is_cae_file_type = FALSE
            LIMIT 1
        ");
        $stmt->execute(['id' => $docTypeId]);
        $name = $stmt->fetchColumn();
        return is_string($name) && $name !== '' ? $name : null;
    }

    private function autoExpireFromIssue(string $docTypeName, ?string $issueDate): ?string
    {
        if ($issueDate === null || $issueDate === '') {
            return null;
        }
        return match ($docTypeName) {
            'Certificado de estar al corriente con Hacienda',
            'Certificado de estar al corriente con Seguridad Social'
                => date('Y-m-d', strtotime($issueDate . ' +6 months')),
            'Póliza de Responsabilidad Civil'
                => date('Y-m-d', strtotime($issueDate . ' +1 year')),
            'Certificado de Prevención de Riesgos Laborales'
                => null,
            default => null,
        };
    }

    /**
     * Misma resolución de CAE record que en upload(), para evaluar estados en GET.
     *
     * @param array<string, mixed> $request
     */
    private function resolvePortalCaeRecordId(PDO $pdo, array $request, int $technicianId): int
    {
        $caeRecordId = (int) ($request['cae_record_id'] ?? 0);
        if ($caeRecordId > 0) {
            return $caeRecordId;
        }

        if ($technicianId <= 0) {
            return 0;
        }

        $stmt = $pdo->prepare("
            SELECT id
            FROM cae_records
            WHERE technician_id = :tid
            AND is_current = TRUE
            LIMIT 1
        ");
        $stmt->execute(['tid' => $technicianId]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function markLatestPortalIntakePublishFailed(
        PDO $pdo,
        int $caeRecordId,
        int $documentTypeId,
        string $message
    ): void {
        try {
            $pdo->prepare("
                UPDATE cae_document_intake
                SET status = 'pending_manual',
                    ai_status = 'manual_review',
                    requires_manual_review = TRUE,
                    ai_notes = :notes,
                    updated_at = NOW()
                WHERE id = (
                    SELECT id
                    FROM cae_document_intake
                    WHERE cae_record_id = :cae_id
                      AND document_type_id = :dtype
                      AND source_channel = 'portal_upload'
                    ORDER BY created_at DESC
                    LIMIT 1
                )
            ")->execute([
                'notes' => $message,
                'cae_id' => $caeRecordId,
                'dtype' => $documentTypeId,
            ]);
        } catch (\Throwable $e) {
            error_log('[TechnicianPortalController::markLatestPortalIntakePublishFailed] ' . $e->getMessage());
        }
    }
}