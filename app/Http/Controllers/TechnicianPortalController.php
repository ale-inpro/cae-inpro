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

final class TechnicianPortalController extends Controller
{
    /** @param array<string, string> $params */
    public function show(array $params = []): void
    {
        $token = trim((string) ($params['token'] ?? ''));
        [$state, $data] = $this->resolveToken($token);
        $this->renderPortal($state, array_merge($data, ['token' => $token]));
    }

    /** @param array<string, string> $params */
    public function upload(array $params = []): void
    {
        $token = trim((string) ($params['token'] ?? ''));
        [$state, $data] = $this->resolveToken($token);

        if ($state !== 'form') {
            $this->renderPortal($state, array_merge($data, ['token' => $token]));
            return;
        }

        $request = $data['request'];
        $tid     = (int) $request['technician_id'];
        $pdo     = Database::connection();
        // Obtener o crear cae_record para vincular los docs
        $caeRecordId = (int) ($request['cae_record_id'] ?? 0);
        if ($caeRecordId <= 0) {
            $stmt = $pdo->prepare("SELECT id FROM cae_records WHERE technician_id = :tid AND is_current = TRUE LIMIT 1");
            $stmt->execute(['tid' => $tid]);
            $caeRecordId = (int) ($stmt->fetchColumn() ?: 0);
        }
        if ($caeRecordId <= 0) {
            $ins = $pdo->prepare("
                INSERT INTO cae_records (technician_id, status, is_current, created_at, updated_at)
                VALUES (:tid, 'pending', TRUE, NOW(), NOW())
                RETURNING id
            ");
            $ins->execute(['tid' => $tid]);
            $caeRecordId = (int) $ins->fetchColumn();
        }

        $appCfg = $this->appConfig();

        $files    = $_FILES['files'] ?? [];
        $uploaded = 0;
        $errors   = [];

        if (!empty($files['name']) && is_array($files['name'])) {
            foreach ($files['name'] as $docTypeId => $originalName) {
                $docTypeId    = (int) $docTypeId;
                $originalName = (string) $originalName;
                $tmpPath      = (string) ($files['tmp_name'][$docTypeId] ?? '');
                $fileError    = (int) ($files['error'][$docTypeId] ?? UPLOAD_ERR_NO_FILE);
                $mimeType     = (string) ($files['type'][$docTypeId] ?? 'application/octet-stream');
                $fileSize     = (int) ($files['size'][$docTypeId] ?? 0);
                if ($fileError === UPLOAD_ERR_NO_FILE || $tmpPath === '') {
                    continue;
                }
                if ($fileError !== UPLOAD_ERR_OK || !is_uploaded_file($tmpPath)) {
                    $errors[] = "Error al recibir «{$originalName}».";
                    continue;
                }

                $ext      = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
                $safeName = date('Ymd_His') . '_' . uniqid() . ($ext !== '' ? '.' . $ext : '');
                $dir      = dirname(__DIR__, 3) . '/public/uploads/cae-docs/' . $tid;
                if (!is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }

                if (!move_uploaded_file($tmpPath, $dir . '/' . $safeName)) {
                    $errors[] = "No se pudo guardar «{$originalName}».";
                    continue;
                }

                $storagePath = '/uploads/cae-docs/' . $tid . '/' . $safeName;
                $docTypeName = $this->resolveDocTypeName($pdo, $docTypeId);
                if ($docTypeName === null) {
                    $errors[] = "Tipo de documento no válido para «{$originalName}».";
                    continue;
                }

                $analysis = DocumentIntakeAiService::analyze($dir . '/' . $safeName, $mimeType, $docTypeName);
                $aiStatus = (string) ($analysis['status'] ?? 'manual_review');
                $confidence = (float) ($analysis['confidence'] ?? 0.0);
                $issueDate = (string) ($analysis['issue_date'] ?? '');
                $expiresAt = (string) ($analysis['expires_at'] ?? '');
                $notes = (string) ($analysis['notes'] ?? '');
                $extractedText = (string) ($analysis['extracted_text'] ?? '');

                $issueDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $issueDate) ? $issueDate : null;
                $expiresAt = preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiresAt) ? $expiresAt : null;
                if ($expiresAt === null) {
                    $expiresAt = $this->autoExpireFromIssue($docTypeName, $issueDate);
                }

                // Estado calculado por PHP (fechas reales), no por la IA
                $aiStatus = DocumentIntakeAiService::calcStatus($expiresAt, $issueDate);

                $needsManual = ($aiStatus === 'manual_review' || $expiresAt === null);

                $pdo->prepare("
                    INSERT INTO cae_document_intake
                    (technician_id, cae_record_id, document_type_id, original_filename, storage_path, mime_type, file_size,
                    source_channel, uploaded_by_user_id, extracted_text, ai_status, ai_confidence, ai_issue_date, ai_expires_at, ai_notes,
                    status, requires_manual_review, created_at, updated_at)
                    VALUES
                    (:technician_id, :cae_record_id, :document_type_id, :original_filename, :storage_path, :mime_type, :file_size,
                    'portal_upload', NULL, :extracted_text, :ai_status, :ai_confidence, :ai_issue_date, :ai_expires_at, :ai_notes,
                    :status, :requires_manual_review, NOW(), NOW())
                ")->execute([
                    'technician_id' => $tid,
                    'cae_record_id' => $caeRecordId,
                    'document_type_id' => $docTypeId,
                    'original_filename' => $originalName,
                    'storage_path' => $storagePath,
                    'mime_type' => $mimeType,
                    'file_size' => $fileSize,
                    'extracted_text' => $extractedText !== '' ? $extractedText : null,
                    'ai_status' => in_array($aiStatus, ['approved', 'in_review', 'rejected', 'manual_review'], true) ? $aiStatus : 'manual_review',
                    'ai_confidence' => $confidence,
                    'ai_issue_date' => $issueDate,
                    'ai_expires_at' => $expiresAt,
                    'ai_notes' => $notes !== '' ? $notes : null,
                    'status' => $needsManual ? 'pending_manual' : 'approved_auto',
                    'requires_manual_review' => $needsManual ? 'true' : 'false',
                ]);

                if (!$needsManual) {
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
                    } catch (\Throwable $e) {
                        error_log('[TechnicianPortalController::upload] slot ' . $e->getMessage());
                        $errors[] = 'No se pudo publicar el documento complementario para «'
                            . $originalName
                            . '». Inténtelo de nuevo o contacte al administrador.';
                        continue;
                    }
                }

                $uploaded++;
            }
        }

        if ($uploaded === 0) {
            $this->renderPortal('form', array_merge($data, [
                'token'     => $token,
                'formError' => empty($errors)
                    ? 'No se recibió ningún archivo. Sube al menos un documento.'
                    : implode(' ', $errors),
            ]));
            return;
        }

        // Marcar token como usado
        $pdo->prepare("UPDATE cae_document_requests SET token_used_at = NOW(), updated_at = NOW() WHERE id = :id")
            ->execute(['id' => (int) $request['id']]);

        // Notificar admins en la app
        $techName = trim(((string) ($request['first_name'] ?? '')) . ' ' . ((string) ($request['last_name'] ?? '')));
        $admins   = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($admins as $adminId) {
            $this->createNotification(
                (int) $adminId,
                'cae_docs_uploaded',
                'Documentos CAE recibidos',
                "El técnico {$techName} ha subido {$uploaded} documento(s) a través del portal.",
                ['technician_id' => $tid, 'cae_record_id' => $caeRecordId]
            );
        }

        // Email al admin
        $adminEmail = (string) ($pdo->query("SELECT email FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn() ?: '');
        if ($adminEmail !== '') {
            $adminLink = $this->baseUrl() . '/admin/tecnicos/' . $tid . '#pane-docs';
            $bodyHtml  = "
                <h2>Documentos CAE recibidos</h2>
                <p>El técnico <strong>" . htmlspecialchars($techName) . "</strong> ha subido
                   <strong>{$uploaded}</strong> documento(s) a través del portal.</p>
                <p><a href='" . htmlspecialchars($adminLink) . "' style='color:#059669'>Ver documentos del técnico →</a></p>
            ";
            Mailer::send(
                $adminEmail,
                'Documentos CAE recibidos — ' . $techName,
                Mailer::template('Documentos recibidos', $bodyHtml)
            );
        }

        $this->renderPortal('success', [
            'token'    => $token,
            'uploaded' => $uploaded,
            'techName' => $techName,
        ]);
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
                   t.first_name, t.last_name
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
        $techName = trim(((string) ($request['first_name'] ?? '')) . ' ' . ((string) ($request['last_name'] ?? '')));

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
}