<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Database;
use PDO;
use App\Services\Mailer;

final class CaeController extends Controller
{
    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->render('cae.index', ['title' => 'Gestión CAE']);
    }

   /** @param array<string, string> $params */
    public function history(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireAdmin();

        $technicianId = (int) ($params['id'] ?? 0);
        if ($technicianId <= 0) {
            http_response_code(404);
            $this->respond('Técnico no encontrado');
            return;
        }

        $pdo = Database::connection();

        $stmt = $pdo->prepare("
            SELECT id, first_name, last_name, professions, city, email
            FROM technicians
            WHERE id = :id
            AND is_active = TRUE
            LIMIT 1
        ");
        $stmt->execute(['id' => $technicianId]);
        $tech = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tech) {
            http_response_code(404);
            $this->respond('Técnico no encontrado');
            return;
        }

        $stmt = $pdo->prepare("
            SELECT id, status::text AS status, valid_from, valid_until, notes
            FROM cae_records
            WHERE technician_id = :tid
            AND is_current = TRUE
            LIMIT 1
        ");
        $stmt->execute(['tid' => $technicianId]);
        $currentCae = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $today = date('Y-m-d');
        $isCurrentValid = $currentCae && !empty($currentCae['valid_until']) && ((string) $currentCae['valid_until'] >= $today);

        $currentCaeDoc = null;

        if ($currentCae) {
            $stmt = $pdo->prepare("
                SELECT
                    cd.id,
                    cd.original_filename,
                    cd.storage_path,
                    cd.uploaded_at,
                    cd.mime_type,
                    cd.file_size
                FROM cae_documents cd
                WHERE cd.cae_record_id = :cae
                AND cd.is_active = TRUE
                AND cd.is_cae_file = TRUE
                ORDER BY cd.uploaded_at DESC, cd.id DESC
                LIMIT 1
            ");
            $stmt->execute(['cae' => (int) $currentCae['id']]);
            $currentCaeDoc = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $requestableDocTypes = [];
        $stmt = $pdo->query("
            SELECT id, name
            FROM document_types
            WHERE scope = 'technician_cae'
              AND is_active = TRUE
              AND is_cae_file_type = FALSE
            ORDER BY name
        ");
        $requestableDocTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT
                r.id,
                r.documents_requested_json,
                r.custom_message,
                r.status,
                r.sent_at,
                u.full_name AS requested_by_name
            FROM cae_document_requests r
            LEFT JOIN users u ON u.id = r.requested_by_user_id
            WHERE r.technician_id = :tid
            ORDER BY r.created_at DESC
            LIMIT 10
        ");
        $stmt->execute(['tid' => $technicianId]);
        $caeDocRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Cargar docs existentes del técnico para el formulario IA
        $stmt = $pdo->prepare("
            SELECT cd.id, cd.original_filename, cd.mime_type, cd.storage_path
            FROM cae_documents cd
            JOIN cae_records cr ON cr.id = cd.cae_record_id
            WHERE cr.technician_id = :tid
                AND cd.is_active = TRUE
            ORDER BY cd.uploaded_at DESC
            LIMIT 50
        ");
        $stmt->execute(['tid' => $technicianId]);
        $existingCaeDocs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->render('cae.history', [
            'title'           => 'Nueva revisión CAE',
            'baseUrl'         => $this->baseUrl(),
            'areaBaseUrl'     => $this->areaBaseUrl(),
            'tech'            => $tech,
            'currentCae'      => $currentCae,
            'isCurrentValid'  => $isCurrentValid,
            'currentCaeDoc'   => $currentCaeDoc,
            'requestableDocTypes' => $requestableDocTypes,
            'caeDocRequests'  => $caeDocRequests,
            'existingCaeDocs' => $existingCaeDocs,
        ]);
    }

    /** @param array<string, string> $params */
    public function requestDocuments(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireAdmin();

        $technicianId = (int) ($params['id'] ?? 0);
        $returnTo = $this->areaBaseUrl() . '/tecnicos/' . $technicianId . '/cae';

        if ($technicianId <= 0) {
            $this->flash('Técnico no válido.', 'danger', 'Error');
            header('Location: ' . $this->areaBaseUrl() . '/tecnicos');
            exit;
        }

        /** @var array<int, string> $docTypeIds */
        $docTypeIds = array_map('strval', (array) ($_POST['document_type_ids'] ?? []));
        $docTypeIds = array_values(array_filter($docTypeIds, static fn(string $v): bool => ctype_digit($v) && (int) $v > 0));

        $customMessage = trim((string) ($_POST['custom_message'] ?? ''));

        if ($docTypeIds === []) {
            $this->flash('Selecciona al menos un documento para solicitar.', 'warning', 'Aviso');
            header('Location: ' . $returnTo);
            exit;
        }

        $pdo = Database::connection();

        // Técnico
        $stmt = $pdo->prepare("
            SELECT id, first_name, last_name, email
            FROM technicians
            WHERE id = :id AND is_active = TRUE
            LIMIT 1
        ");
        $stmt->execute(['id' => $technicianId]);
        $tech = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tech) {
            $this->flash('Técnico no encontrado.', 'danger', 'Error');
            header('Location: ' . $this->areaBaseUrl() . '/tecnicos');
            exit;
        }

        $techEmail = trim((string) ($tech['email'] ?? ''));
        if ($techEmail === '') {
            $this->flash('El técnico no tiene email configurado.', 'warning', 'Aviso');
            header('Location: ' . $returnTo);
            exit;
        }

        // Tipos de doc válidos
        $in = implode(',', array_fill(0, count($docTypeIds), '?'));
        $sql = "
            SELECT id, name
            FROM document_types
            WHERE id IN ($in)
                AND scope = 'technician_cae'
                AND is_active = TRUE
                AND is_cae_file_type = FALSE
            ORDER BY name
        ";
        $stmt = $pdo->prepare($sql);
        foreach ($docTypeIds as $k => $id) {
            $stmt->bindValue($k + 1, (int) $id, PDO::PARAM_INT);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            $this->flash('No se encontraron tipos de documento válidos.', 'warning', 'Aviso');
            header('Location: ' . $returnTo);
            exit;
        }

        $docsPayload = array_map(static fn(array $r): array => [
            'id' => (int) $r['id'],
            'name' => (string) $r['name'],
        ], $rows);

        // CAE actual (si existe)
        $stmt = $pdo->prepare("
            SELECT id
            FROM cae_records
            WHERE technician_id = :tid
                AND is_current = TRUE
            LIMIT 1
        ");
        $stmt->execute(['tid' => $technicianId]);
        $currentCaeId = (int) ($stmt->fetchColumn() ?: 0);

        $adminName = (string) ($_SESSION['user']['full_name'] ?? 'Administrador');

        $listHtml = '';
        foreach ($docsPayload as $d) {
            $listHtml .= '<li>' . htmlspecialchars((string) $d['name']) . '</li>';
        }

        $extraHtml = $customMessage !== ''
            ? '<p><strong>Mensaje del administrador:</strong><br>' . nl2br(htmlspecialchars($customMessage)) . '</p>'
            : '';

        $techName = trim(((string) ($tech['first_name'] ?? '')) . ' ' . ((string) ($tech['last_name'] ?? '')));

        $body = "
            <h2>Solicitud de documentación CAE</h2>
            <p>Hola <strong>" . htmlspecialchars($techName !== '' ? $techName : 'técnico/a') . "</strong>,</p>
            <p>Para gestionar tu CAE, necesitamos que nos envíes la siguiente documentación:</p>
            <ul>{$listHtml}</ul>
            {$extraHtml}
            <hr class='divider'>
            <p>Solicitud enviada por: <strong>" . htmlspecialchars($adminName) . "</strong></p>
            <p>Gracias por tu colaboración.</p>
        ";

        $html = Mailer::template('Solicitud de documentos CAE', $body);
        $sentOk = Mailer::send($techEmail, 'Solicitud de documentos para CAE', $html);

        $status = $sentOk ? 'sent' : 'failed';
        $emailError = $sentOk ? null : 'No se pudo enviar el email mediante proveedor.';

        $stmt = $pdo->prepare("
            INSERT INTO cae_document_requests
            (
                technician_id, cae_record_id, requested_by_user_id,
                documents_requested_json, custom_message, status, email_error,
                sent_at, created_at, updated_at
            )
            VALUES
            (
                :tid, :cae_id, :uid,
                CAST(:docs AS jsonb), :msg, :status, :err,
                NOW(), NOW(), NOW()
            )
        ");

        $stmt->execute([
            'tid'    => $technicianId,
            'cae_id' => $currentCaeId > 0 ? $currentCaeId : null,
            'uid'    => (int) ($_SESSION['user']['id'] ?? 0),
            'docs'   => json_encode($docsPayload, JSON_UNESCAPED_UNICODE),
            'msg'    => $customMessage !== '' ? $customMessage : null,
            'status' => $status,
            'err'    => $emailError,
        ]);

        if ($sentOk) {
            $this->flash('Solicitud enviada por email al técnico correctamente.', 'success', 'Correcto');
        } else {
            $this->flash('Se registró la solicitud, pero el email no pudo enviarse.', 'warning', 'Aviso');
        }

        header('Location: ' . $returnTo);
        exit;
    }

        /** @param array<string, string> $params */
    public function store(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireAdmin();

        $technicianId = (int) ($params['id'] ?? 0);
        $status = trim((string) ($_POST['status'] ?? 'pending_docs'));
        $validFrom = trim((string) ($_POST['valid_from'] ?? ''));
        $validUntil = trim((string) ($_POST['valid_until'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));

        $defaultReturn = $this->areaBaseUrl() . '/tecnicos/' . $technicianId . '/cae';
        $returnTo = trim((string) ($_POST['return_to'] ?? $defaultReturn));

        if ($technicianId <= 0) {
            $this->flash('Técnico no válido.', 'danger', 'Error');
            header('Location: ' . $this->areaBaseUrl() . '/tecnicos');
            exit;
        }

        $pdo = Database::connection();

        $stmt = $pdo->prepare("SELECT id FROM technicians WHERE id = :id AND is_active = TRUE LIMIT 1");
        $stmt->execute(['id' => $technicianId]);
        if (!(int) $stmt->fetchColumn()) {
            $this->flash('Técnico no encontrado.', 'danger', 'Error');
            header('Location: ' . $this->areaBaseUrl() . '/tecnicos');
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT id, valid_from, valid_until, status::text AS status, notes
            FROM cae_records
            WHERE technician_id = :tid
            AND is_current = TRUE
            LIMIT 1
        ");
        $stmt->execute(['tid' => $technicianId]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $today = date('Y-m-d');
        $currentValid = $current && !empty($current['valid_until']) && ((string) $current['valid_until'] >= $today);

        // Si no llegan fechas, usamos valores por defecto (hoy + 3 meses)
        if ($validFrom === '') {
            $validFrom = $today;
        }
        if ($validUntil === '') {
            $validUntil = date('Y-m-d', strtotime($validFrom . ' +3 months'));
        }

        if ($validFrom > $validUntil) {
            $this->flash('La fecha "válido desde" no puede ser mayor que "válido hasta".', 'warning', 'Aviso');
            header('Location: ' . $returnTo);
            exit;
        }

        // Comprueba si el CAE actual tiene archivo principal
        $hasMainFile = false;
        if ($current) {
            $stmt = $pdo->prepare("
                SELECT 1
                FROM cae_documents
                WHERE cae_record_id = :cae
                AND is_active = TRUE
                AND is_cae_file = TRUE
                LIMIT 1
            ");
            $stmt->execute(['cae' => (int) $current['id']]);
            $hasMainFile = (bool) $stmt->fetchColumn();
        }

        // Regla de negocio de estados según archivo principal
        $allowedStatuses = $hasMainFile
            ? ['pending_docs', 'in_review', 'approved', 'rejected', 'expired']
            : ['pending_docs', 'in_review'];

        if (!in_array($status, $allowedStatuses, true)) {
            $this->flash(
                $hasMainFile
                    ? 'Estado de CAE no válido.'
                    : 'Sin archivo CAE principal solo se permite Pendiente o En revisión.',
                'warning',
                'Aviso'
            );
            header('Location: ' . $returnTo);
            exit;
        }

        if ($current && $currentValid) {
            // Actualiza CAE vigente (incluyendo fechas, como pediste)
            $stmt = $pdo->prepare("
                UPDATE cae_records
                SET status = :status,
                    valid_from = :valid_from,
                    valid_until = :valid_until,
                    notes = :notes,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                'status' => $status,
                'valid_from' => $validFrom,
                'valid_until' => $validUntil,
                'notes' => $notes,
                'id' => (int) $current['id'],
            ]);

            $this->flash('CAE vigente guardado correctamente.', 'success', 'Correcto');
            header('Location: ' . $this->areaBaseUrl() . '/tecnicos/' . $technicianId . '#pane-hist');
            exit;
        }

        // Si no existe vigente o está caducado => crea nueva revisión actual
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                UPDATE cae_records
                SET is_current = FALSE,
                    updated_at = NOW()
                WHERE technician_id = :tid
                AND is_current = TRUE
            ");
            $stmt->execute(['tid' => $technicianId]);

            $stmt = $pdo->prepare("
                INSERT INTO cae_records
                (technician_id, status, issue_date, valid_from, valid_until, notes, is_current, created_at, updated_at)
                VALUES
                (:tid, :status, CURRENT_DATE, :valid_from, :valid_until, :notes, TRUE, NOW(), NOW())
            ");
            $stmt->execute([
                'tid' => $technicianId,
                'status' => $status,
                'valid_from' => $validFrom,
                'valid_until' => $validUntil,
                'notes' => $notes,
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $this->flash('No se pudo crear el CAE vigente.', 'danger', 'Error');
            header('Location: ' . $returnTo);
            exit;
        }

        $this->flash('CAE vigente creado correctamente.', 'success', 'Correcto');
        header('Location: ' . $this->areaBaseUrl() . '/tecnicos/' . $technicianId . '#pane-hist');
        exit;
    }

    /** @param array<string, string> $params */
    public function update(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireAdmin();

        $caeId = (int) ($params['id'] ?? 0);
        $status = trim((string) ($_POST['status'] ?? 'pending_docs'));
        $validFrom = trim((string) ($_POST['valid_from'] ?? ''));
        $validUntil = trim((string) ($_POST['valid_until'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));

        if ($caeId <= 0 || $validFrom === '' || $validUntil === '') {
            $this->flash('Datos incompletos para actualizar CAE.', 'danger', 'Error');
            header('Location: ' . $this->areaBaseUrl() . '/tecnicos');
            exit;
        }

        $allowed = ['pending_docs', 'in_review', 'approved', 'rejected', 'expired'];
        if (!in_array($status, $allowed, true)) {
            $this->flash('Estado de CAE no válido.', 'warning', 'Aviso');
            header('Location: ' . $this->areaBaseUrl() . '/tecnicos');
            exit;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            UPDATE cae_records
            SET status = :status,
                valid_from = :valid_from,
                valid_until = :valid_until,
                notes = :notes,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'status' => $status,
            'valid_from' => $validFrom,
            'valid_until' => $validUntil,
            'notes' => $notes,
            'id' => $caeId,
        ]);

        $this->flash('CAE actualizado correctamente.', 'success', 'Correcto');
        header('Location: ' . $this->areaBaseUrl() . '/tecnicos');
        exit;
    }

    /** @param array<string, string> $params */
    public function destroy(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireAdmin();

        $caeId = (int) ($params['id'] ?? 0);
        if ($caeId <= 0) {
            $this->flash('ID de CAE no válido.', 'danger', 'Error');
            header('Location: ' . $this->areaBaseUrl() . '/tecnicos');
            exit;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            UPDATE cae_records
            SET status = 'expired',
                is_current = FALSE,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['id' => $caeId]);

        $this->flash('CAE retirado correctamente.', 'success', 'Correcto');
        header('Location: ' . $this->areaBaseUrl() . '/tecnicos');
        exit;
    }

    /** @param array<string, string> $params */
    public function uploadDocument(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireAdmin();

        $caeId = (int) ($params['id'] ?? 0);
        $uploadMode = trim((string) ($_POST['upload_mode'] ?? 'cae_main'));
        $documentTypeId = (int) ($_POST['document_type_id'] ?? 0);
        $returnTo = (string) ($_POST['return_to'] ?? ($this->areaBaseUrl() . '/tecnicos'));

        if ($caeId <= 0 || empty($_FILES['document_file'])) {
            $this->flash('Datos incompletos para subir el archivo CAE.', 'danger', 'Error');
            header('Location: ' . $returnTo);
            exit;
        }

        $file = $_FILES['document_file'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->flash('Error al subir el archivo.', 'danger', 'Error');
            header('Location: ' . $returnTo);
            exit;
        }

        $maxBytes = 10 * 1024 * 1024; // 10MB
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxBytes) {
            $this->flash('El archivo debe ser mayor a 0 y menor de 10MB.', 'warning', 'Aviso');
            header('Location: ' . $returnTo);
            exit;
        }

        $pdo = Database::connection();

        // Valida CAE
        $stmt = $pdo->prepare("SELECT id FROM cae_records WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $caeId]);
        if (!(int) $stmt->fetchColumn()) {
            $this->flash('CAE no encontrado.', 'danger', 'Error');
            header('Location: ' . $returnTo);
            exit;
        }

        $allowedModes = ['cae_main', 'supporting'];
        if (!in_array($uploadMode, $allowedModes, true)) {
            $this->flash('Modo de subida no válido.', 'warning', 'Aviso');
            header('Location: ' . $returnTo);
            exit;
        }

        $docTypeId = 0;
        $isMainFile = ($uploadMode === 'cae_main');
        if ($isMainFile) {
            // Tipo reservado para el archivo principal del CAE.
            $stmt = $pdo->prepare("
                SELECT id
                FROM document_types
                WHERE scope = 'technician_cae'
                AND is_active = TRUE
                AND is_cae_file_type = TRUE
                LIMIT 1
            ");
            $stmt->execute();
            $docTypeId = (int) ($stmt->fetchColumn() ?: 0);

            if ($docTypeId <= 0) {
                $this->flash('No existe configurado el tipo de documento principal de CAE.', 'danger', 'Error');
                header('Location: ' . $returnTo);
                exit;
            }
        } else {
            if ($documentTypeId <= 0) {
                $this->flash('Debes seleccionar un tipo de documento.', 'warning', 'Aviso');
                header('Location: ' . $returnTo);
                exit;
            }
            $stmt = $pdo->prepare("
                SELECT id
                FROM document_types
                WHERE id = :id
                AND scope = 'technician_cae'
                AND is_active = TRUE
                AND is_cae_file_type = FALSE
                LIMIT 1
            ");
            $stmt->execute(['id' => $documentTypeId]);
            $docTypeId = (int) ($stmt->fetchColumn() ?: 0);
            if ($docTypeId <= 0) {
                $this->flash('Tipo de documento no válido para documentos complementarios.', 'warning', 'Aviso');
                header('Location: ' . $returnTo);
                exit;
            }
        }

        $originalName = (string) ($file['name'] ?? 'file');
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName) ?: 'cae.pdf';
        $finalName = uniqid('cae_', true) . '_' . $safeName;

        $targetDir = dirname(__DIR__, 3) . '/public/uploads/cae/' . $caeId;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $fullPath = $targetDir . '/' . $finalName;
        if (!move_uploaded_file((string) $file['tmp_name'], $fullPath)) {
            $this->flash('No se pudo guardar el archivo en el servidor.', 'danger', 'Error');
            header('Location: ' . $returnTo);
            exit;
        }

        $relativePath = '/uploads/cae/' . $caeId . '/' . $finalName;
        $mime = (string) ($file['type'] ?? 'application/octet-stream');

        $pdo->beginTransaction();
        try {
            if ($isMainFile) {
                // Sustituir archivo principal activo anterior.
                $stmt = $pdo->prepare("
                    UPDATE cae_documents
                    SET is_active = FALSE,
                        updated_at = NOW()
                    WHERE cae_record_id = :cae_id
                    AND is_active = TRUE
                    AND is_cae_file = TRUE
                ");
                $stmt->execute(['cae_id' => $caeId]);
            }

            $stmt = $pdo->prepare("
                INSERT INTO cae_documents
                (cae_record_id, document_type_id, original_filename, storage_path, mime_type, file_size, uploaded_by_user_id, uploaded_at, is_active, is_cae_file, created_at, updated_at)
                VALUES
                (:cae_id, :doc_type, :orig, :path, :mime, :size, :user_id, NOW(), TRUE, :is_cae_file, NOW(), NOW())
            ");
            $stmt->execute([
                'cae_id' => $caeId,
                'doc_type' => $docTypeId,
                'orig' => $originalName,
                'path' => $relativePath,
                'mime' => $mime,
                'size' => $size,
                'user_id' => (int) ($_SESSION['user']['id'] ?? 0),
                'is_cae_file' => ($isMainFile ? 'true' : 'false'),
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[uploadDocument ERROR] ' . $e->getMessage());
            $this->flash('No se pudo guardar el documento: ' . $e->getMessage(), 'danger', 'Error');
            header('Location: ' . $returnTo);
            exit;
        }

        $this->flash(
            $isMainFile ? 'Archivo CAE subido/sustituido correctamente.' : 'Documento complementario subido correctamente.',
            'success',
            'Correcto'
        );
        header('Location: ' . $returnTo);
        exit;
    }

    /** @param array<string, string> $params */
    public function deleteDocument(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireAdmin();

        $documentId = (int) ($params['documentId'] ?? 0);
        $returnTo   = (string) ($_POST['return_to'] ?? ($this->areaBaseUrl() . '/tecnicos'));

        if ($documentId <= 0) {
            $this->flash('ID de documento no válido.', 'danger', 'Error');
            header('Location: ' . $returnTo);
            exit;
        }

        $pdo  = Database::connection();

        // Obtener info del documento antes de eliminarlo
        $stmt = $pdo->prepare("
            SELECT id, is_cae_file, cae_record_id
            FROM cae_documents
            WHERE id = :id AND is_active = TRUE
            LIMIT 1
        ");
        $stmt->execute(['id' => $documentId]);
        $docRow = $stmt->fetch(PDO::FETCH_ASSOC);

        // Marcar como inactivo
        $stmt = $pdo->prepare("
            UPDATE cae_documents
            SET is_active = FALSE, updated_at = NOW()
            WHERE id = :id AND is_active = TRUE
        ");
        $stmt->execute(['id' => $documentId]);

        if ($stmt->rowCount() > 0) {
            // Si era el archivo principal del CAE, cambiar estado a 'pending'
            if ($docRow && $this->boolFromPg($docRow['is_cae_file'] ?? false)) {
                $pdo->prepare("
                    UPDATE cae_records
                    SET status = 'pending', updated_at = NOW()
                    WHERE id = :cae_id AND is_current = TRUE
                ")->execute(['cae_id' => (int) $docRow['cae_record_id']]);
            }
            $this->flash('Documento CAE eliminado. Estado del CAE actualizado a Pendiente.', 'success', 'Correcto');
        } else {
            $this->flash('No se encontró el documento o ya estaba eliminado.', 'warning', 'Aviso');
        }

        header('Location: ' . $returnTo);
        exit;
    }

    /** @param array<string, string> $params */
    public function downloadDocument(array $params = []): void
    {
        $this->assertAreaAccess();

        $documentId = (int) ($params['documentId'] ?? 0);
        if ($documentId <= 0) {
            http_response_code(404);
            $this->respond('Documento no encontrado');
            return;
        }

        $pdo = Database::connection();
        $role = (string) ($_SESSION['user']['role'] ?? '');
        $managerCompanyId = $this->currentUserManagerCompanyId($pdo);

        if ($role === 'admin') {
            $stmt = $pdo->prepare("
                SELECT
                    cd.id,
                    cd.original_filename,
                    cd.storage_path,
                    cd.mime_type
                FROM cae_documents cd
                JOIN cae_records cr ON cr.id = cd.cae_record_id
                WHERE cd.id = :id
                AND cd.is_active = TRUE
                LIMIT 1
            ");
            $stmt->execute(['id' => $documentId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT
                    cd.id,
                    cd.original_filename,
                    cd.storage_path,
                    cd.mime_type
                FROM cae_documents cd
                JOIN cae_records cr ON cr.id = cd.cae_record_id
                JOIN manager_company_technician mct ON mct.technician_id = cr.technician_id
                WHERE cd.id = :id
                AND cd.is_active = TRUE
                AND mct.manager_company_id = :mc
                AND mct.status = 'active'
                LIMIT 1
            ");
            $stmt->execute([
                'id' => $documentId,
                'mc' => $managerCompanyId,
            ]);
        }

        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$doc) {
            http_response_code(403);
            $this->respond('Sin permisos para descargar este documento');
            return;
        }

        $storagePath = (string) ($doc['storage_path'] ?? '');
        $absolutePath = dirname(__DIR__, 3) . '/public' . $storagePath;

        if ($storagePath === '' || !is_file($absolutePath)) {
            http_response_code(404);
            $this->respond('Archivo no disponible');
            return;
        }

        $filename = (string) ($doc['original_filename'] ?? 'documento.pdf');
        $mime = (string) ($doc['mime_type'] ?? 'application/octet-stream');

        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . (string) filesize($absolutePath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: public');

        readfile($absolutePath);
        exit;
    }

    private function currentUserManagerCompanyId(PDO $pdo): int
    {
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        if ($userId <= 0) return 0;

        $stmt = $pdo->prepare("SELECT manager_company_id FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $userId]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function requireAdmin(): void
    {
        if (($_SESSION['user']['role'] ?? '') !== 'admin') {
            http_response_code(403);
            $this->respond('Acceso denegado');
            exit;
        }
    }
}