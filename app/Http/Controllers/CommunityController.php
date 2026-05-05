<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Database;
use PDO;

final class CommunityController extends Controller
{
    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        $this->assertAreaAccess();

        $pdo = Database::connection();
        $role = (string) ($_SESSION['user']['role'] ?? '');
        $managerCompanyId = $this->currentUserManagerCompanyId($pdo);

        if ($role === 'admin') {
            $sql = "
                SELECT
                    c.id,
                    c.name,
                    c.address,
                    c.city,
                    COALESCE(rr.status::text, 'pending') AS risk_status
                FROM communities c
                LEFT JOIN community_risk_reports rr
                    ON rr.community_id = c.id
                WHERE c.is_active = TRUE
                ORDER BY c.name
            ";
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $sql = "
                SELECT
                    c.id,
                    c.name,
                    c.address,
                    c.city,
                    COALESCE(rr.status::text, 'pending') AS risk_status
                FROM communities c
                LEFT JOIN community_risk_reports rr
                    ON rr.community_id = c.id
                WHERE c.is_active = TRUE
                  AND c.manager_company_id = :mc
                ORDER BY c.name
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['mc' => $managerCompanyId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $this->render('communities.index', [
            'title' => 'Comunidades',
            'baseUrl' => $this->baseUrl(),
            'area' => $this->currentArea(),
            'areaBaseUrl' => $this->areaBaseUrl(),
            'communities' => $rows,
        ]);
    }

    /** @param array<string, string> $params */
    public function show(array $params = []): void
    {
        $this->assertAreaAccess();

        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(404);
            $this->respond('Comunidad no encontrada');
            return;
        }

        $pdo = Database::connection();
        $role = (string) ($_SESSION['user']['role'] ?? '');
        $managerCompanyId = $this->currentUserManagerCompanyId($pdo);

        if ($role === 'admin') {
            $stmt = $pdo->prepare("
                SELECT id, name, cif, address, city, province, postal_code, contact_name, contact_phone, contact_email, manager_company_id
                FROM communities
                WHERE id = :id
                  AND is_active = TRUE
                LIMIT 1
            ");
            $stmt->execute(['id' => $id]);
        } else {
            $stmt = $pdo->prepare("
                SELECT id, name, cif, address, city, province, postal_code, contact_name, contact_phone, contact_email, manager_company_id
                FROM communities
                WHERE id = :id
                  AND is_active = TRUE
                  AND manager_company_id = :mc
                LIMIT 1
            ");
            $stmt->execute(['id' => $id, 'mc' => $managerCompanyId]);
        }

        $community = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$community) {
            http_response_code(404);
            $this->respond('Comunidad no encontrada');
            return;
        }

        $stmt = $pdo->prepare("
            SELECT status::text AS status, report_filename, report_path, completed_at, notes
            FROM community_risk_reports
            WHERE community_id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $riskReport = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $stmt = $pdo->prepare("
            SELECT
                t.id,
                t.first_name,
                t.last_name,
                t.professions,
                COALESCE(c.status::text, 'pending_docs') AS cae_status
            FROM community_technician ct
            JOIN technicians t ON t.id = ct.technician_id
            LEFT JOIN cae_records c
                ON c.technician_id = t.id
               AND c.is_current = TRUE
            WHERE ct.community_id = :id
              AND ct.status = 'assigned'
            ORDER BY t.last_name, t.first_name
        ");
        $stmt->execute(['id' => $id]);
        $communityTechnicians = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT
                cd.id,
                dt.name AS document_name,
                cd.original_filename,
                cd.storage_path,
                cd.uploaded_at
            FROM community_documents cd
            JOIN document_types dt ON dt.id = cd.document_type_id
            WHERE cd.community_id = :id
              AND cd.is_active = TRUE
            ORDER BY cd.uploaded_at DESC
        ");
        $stmt->execute(['id' => $id]);
        $communityDocuments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $availableTechnicians = [];
        if ($this->currentArea() === 'admin') {
            $stmt = $pdo->prepare("
                SELECT
                    t.id,
                    t.first_name,
                    t.last_name,
                    t.professions
                FROM manager_company_technician mct
                JOIN technicians t ON t.id = mct.technician_id
                LEFT JOIN community_technician ct
                    ON ct.community_id = :cid
                AND ct.technician_id = t.id
                AND ct.status = 'assigned'
                WHERE mct.manager_company_id = :mc
                AND mct.status = 'active'
                AND t.is_active = TRUE
                AND ct.id IS NULL
                ORDER BY t.last_name, t.first_name
            ");
            $stmt->execute([
                'cid' => $id,
                'mc' => (int) ($community['manager_company_id'] ?? 0),
            ]);
            $availableTechnicians = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $communityDocTypes = [];
        if ($this->currentArea() === 'admin') {
            $stmt = $pdo->query("
                SELECT id, name
                FROM document_types
                WHERE scope = 'community_basic'
                AND is_active = TRUE
                ORDER BY name
            ");
            $communityDocTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $this->render('communities.show', [
            'title' => 'Detalle Comunidad',
            'baseUrl' => $this->baseUrl(),
            'area' => $this->currentArea(),
            'areaBaseUrl' => $this->areaBaseUrl(),
            'community' => $community,
            'riskReport' => $riskReport,
            'communityTechnicians' => $communityTechnicians,
            'communityDocuments' => $communityDocuments,
            'availableTechnicians' => $availableTechnicians,
            'communityDocTypes' => $communityDocTypes,
        ]);
    }

    public function create(): void
    {
        $this->assertAreaAccess();
        $this->requireAdmin();

        $this->render('communities.form', [
            'title' => 'Nueva comunidad',
            'areaBaseUrl' => $this->areaBaseUrl(),
            'mode' => 'create',
            'community' => [],
            'errors' => [],
        ]);
    }

    public function store(): void
    {
        $this->assertAreaAccess();
        $this->requireAdmin();

        $data = $this->extractCommunityInput();
        $errors = $this->validateCommunityInput($data);

        if ($errors !== []) {
            $this->render('communities.form', [
                'title' => 'Nueva comunidad',
                'areaBaseUrl' => $this->areaBaseUrl(),
                'mode' => 'create',
                'community' => $data,
                'errors' => $errors,
            ]);
            return;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            INSERT INTO communities
            (manager_company_id, name, cif, address, city, province, postal_code, contact_name, contact_phone, contact_email, is_active, created_at, updated_at)
            VALUES
            (:manager_company_id, :name, :cif, :address, :city, :province, :postal_code, :contact_name, :contact_phone, :contact_email, TRUE, NOW(), NOW())
            RETURNING id
        ");
        $stmt->execute($data);
        $newId = (int) $stmt->fetchColumn();

        $this->flash('Comunidad creada correctamente.', 'success', 'Correcto');
        header('Location: ' . $this->areaBaseUrl() . '/comunidades/' . $newId);
        exit;
    }

    /** @param array<string, string> $params */
    public function edit(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireAdmin();

        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(404);
            $this->respond('Comunidad no encontrada');
            return;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            SELECT id, manager_company_id, name, cif, address, city, province, postal_code, contact_name, contact_phone, contact_email
            FROM communities
            WHERE id = :id
              AND is_active = TRUE
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $community = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$community) {
            http_response_code(404);
            $this->respond('Comunidad no encontrada');
            return;
        }

        $returnTo = trim((string) ($_GET['return_to'] ?? ''));
        if ($returnTo === '') {
            $returnTo = $this->areaBaseUrl() . '/comunidades/' . $id . '#c-info';
        }

        $this->render('communities.form', [
            'title' => 'Editar comunidad',
            'areaBaseUrl' => $this->areaBaseUrl(),
            'mode' => 'edit',
            'community' => $community,
            'errors' => [],
            'returnTo' => $returnTo,
        ]);
    }

    /** @param array<string, string> $params */
    public function update(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireAdmin();

        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(404);
            $this->respond('Comunidad no encontrada');
            return;
        }

        $data = $this->extractCommunityInput();
        $errors = $this->validateCommunityInput($data, $id);

        $returnTo = trim((string) ($_POST['return_to'] ?? ''));
        if ($returnTo === '') {
            $returnTo = $this->areaBaseUrl() . '/comunidades/' . $id . '#c-info';
        }

        if ($errors !== []) {
            $data['id'] = (string) $id;
            $this->render('communities.form', [
                'title' => 'Editar comunidad',
                'areaBaseUrl' => $this->areaBaseUrl(),
                'mode' => 'edit',
                'community' => $data,
                'errors' => $errors,
                'returnTo' => $returnTo,
            ]);
            return;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            UPDATE communities
            SET manager_company_id = :manager_company_id,
                name = :name,
                cif = :cif,
                address = :address,
                city = :city,
                province = :province,
                postal_code = :postal_code,
                contact_name = :contact_name,
                contact_phone = :contact_phone,
                contact_email = :contact_email,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(array_merge($data, ['id' => $id]));

        $this->flash('Comunidad actualizada correctamente.', 'success', 'Correcto');
        header('Location: ' . $returnTo);
        exit;
    }

    /** @param array<string, string> $params */
    public function destroy(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireAdmin();

        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            $this->flash('ID de comunidad no válido.', 'danger', 'Error');
            header('Location: ' . $this->areaBaseUrl() . '/comunidades');
            exit;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            UPDATE communities
            SET is_active = FALSE,
                updated_at = NOW()
            WHERE id = :id
              AND is_active = TRUE
        ");
        $stmt->execute(['id' => $id]);

        if ($stmt->rowCount() > 0) {
            $this->flash('Comunidad desactivada correctamente.', 'success', 'Correcto');
        } else {
            $this->flash('No se pudo desactivar la comunidad o ya estaba desactivada.', 'warning', 'Aviso');
        }

        header('Location: ' . $this->areaBaseUrl() . '/comunidades');
        exit;
    }

    /** @param array<string, string> $params */
    public function assignTechnician(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireAdmin();

        $communityId = (int) ($params['id'] ?? 0);
        $techId = (int) ($params['techId'] ?? 0);

        if ($communityId <= 0 || $techId <= 0) {
            $this->flash('Parámetros inválidos para asignación.', 'danger', 'Error');
            header('Location: ' . $this->areaBaseUrl() . '/comunidades');
            exit;
        }

        $pdo = Database::connection();

        // Verifica comunidad activa
        $stmt = $pdo->prepare("
            SELECT id, manager_company_id
            FROM communities
            WHERE id = :id
            AND is_active = TRUE
            LIMIT 1
        ");
        $stmt->execute(['id' => $communityId]);
        $community = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$community) {
            $this->flash('Comunidad no encontrada.', 'danger', 'Error');
            header('Location: ' . $this->areaBaseUrl() . '/comunidades');
            exit;
        }

        // Verifica técnico activo y asociado a la misma empresa gestora
        $stmt = $pdo->prepare("
            SELECT t.id
            FROM technicians t
            JOIN manager_company_technician mct ON mct.technician_id = t.id
            WHERE t.id = :tid
            AND t.is_active = TRUE
            AND mct.manager_company_id = :mc
            AND mct.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([
            'tid' => $techId,
            'mc' => (int) $community['manager_company_id'],
        ]);
        $validTech = $stmt->fetchColumn();

        if (!$validTech) {
            $this->flash('El técnico no pertenece a esta gestora o no está activo.', 'warning', 'Aviso');
            header('Location: ' . $this->areaBaseUrl() . '/comunidades/' . $communityId . '#c-tech');
            exit;
        }

        // Si ya existía relación, la reactiva; si no, inserta
        $stmt = $pdo->prepare("
            SELECT id
            FROM community_technician
            WHERE community_id = :cid
            AND technician_id = :tid
            LIMIT 1
        ");
        $stmt->execute(['cid' => $communityId, 'tid' => $techId]);
        $relationId = (int) ($stmt->fetchColumn() ?: 0);

        if ($relationId > 0) {
            $stmt = $pdo->prepare("
                UPDATE community_technician
                SET status = 'assigned',
                    assigned_at = NOW(),
                    unassigned_at = NULL,
                    assigned_by_user_id = :uid,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                'uid' => (int) ($_SESSION['user']['id'] ?? 0),
                'id' => $relationId,
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO community_technician
                (community_id, technician_id, assigned_by_user_id, assigned_at, status, created_at, updated_at)
                VALUES
                (:cid, :tid, :uid, NOW(), 'assigned', NOW(), NOW())
            ");
            $stmt->execute([
                'cid' => $communityId,
                'tid' => $techId,
                'uid' => (int) ($_SESSION['user']['id'] ?? 0),
            ]);
        }

        $this->flash('Técnico asignado correctamente.', 'success', 'Correcto');
        header('Location: ' . $this->areaBaseUrl() . '/comunidades/' . $communityId . '#c-tech');
        exit;
    }

    /** @param array<string, string> $params */
    public function unassignTechnician(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireAdmin();

        $communityId = (int) ($params['id'] ?? 0);
        $techId = (int) ($params['techId'] ?? 0);

        if ($communityId <= 0 || $techId <= 0) {
            $this->flash('Parámetros inválidos para desasignación.', 'danger', 'Error');
            header('Location: ' . $this->areaBaseUrl() . '/comunidades');
            exit;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            UPDATE community_technician
            SET status = 'unassigned',
                unassigned_at = NOW(),
                updated_at = NOW()
            WHERE community_id = :cid
            AND technician_id = :tid
            AND status = 'assigned'
        ");
        $stmt->execute(['cid' => $communityId, 'tid' => $techId]);

        if ($stmt->rowCount() > 0) {
            $this->flash('Técnico desasignado correctamente.', 'success', 'Correcto');
        } else {
            $this->flash('No se encontró una asignación activa para ese técnico.', 'warning', 'Aviso');
        }

        header('Location: ' . $this->areaBaseUrl() . '/comunidades/' . $communityId . '#c-tech');
        exit;
    }

    /** @param array<string, string> $params */
    public function uploadDocument(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireAdmin();

        $communityId = (int) ($params['id'] ?? 0);
        $documentTypeId = (int) ($_POST['document_type_id'] ?? 0);
        $returnTo = (string) ($_POST['return_to'] ?? ($this->areaBaseUrl() . '/comunidades'));

        if ($communityId <= 0 || $documentTypeId <= 0 || empty($_FILES['document_file'])) {
            $this->flash('Datos incompletos para subir documento.', 'danger', 'Error');
            header('Location: ' . $returnTo);
            exit;
        }

        $file = $_FILES['document_file'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->flash('Error al subir el archivo.', 'danger', 'Error');
            header('Location: ' . $returnTo);
            exit;
        }

        $maxBytes = 10 * 1024 * 1024;
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxBytes) {
            $this->flash('El archivo debe ser mayor a 0 y menor de 10MB.', 'warning', 'Aviso');
            header('Location: ' . $returnTo);
            exit;
        }

        $pdo = Database::connection();

        $stmt = $pdo->prepare("SELECT id FROM communities WHERE id = :id AND is_active = TRUE LIMIT 1");
        $stmt->execute(['id' => $communityId]);
        if (!(int) $stmt->fetchColumn()) {
            $this->flash('Comunidad no encontrada.', 'danger', 'Error');
            header('Location: ' . $returnTo);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT id
            FROM document_types
            WHERE id = :id
            AND scope = 'community_basic'
            AND is_active = TRUE
            LIMIT 1
        ");
        $stmt->execute(['id' => $documentTypeId]);
        if (!(int) $stmt->fetchColumn()) {
            $this->flash('Tipo de documento no válido para comunidad.', 'warning', 'Aviso');
            header('Location: ' . $returnTo);
            exit;
        }

        $originalName = (string) ($file['name'] ?? 'file');
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName) ?: 'documento.pdf';
        $finalName = uniqid('com_', true) . '_' . $safeName;

        $targetDir = dirname(__DIR__, 3) . '/public/uploads/communities/' . $communityId;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $fullPath = $targetDir . '/' . $finalName;
        if (!move_uploaded_file((string) $file['tmp_name'], $fullPath)) {
            $this->flash('No se pudo guardar el archivo en el servidor.', 'danger', 'Error');
            header('Location: ' . $returnTo);
            exit;
        }

        $relativePath = '/uploads/communities/' . $communityId . '/' . $finalName;
        $mime = (string) ($file['type'] ?? 'application/octet-stream');

        $stmt = $pdo->prepare("
            INSERT INTO community_documents
            (community_id, document_type_id, original_filename, storage_path, mime_type, file_size, uploaded_by_user_id, uploaded_at, is_active, created_at, updated_at)
            VALUES
            (:community_id, :doc_type, :orig, :path, :mime, :size, :user_id, NOW(), TRUE, NOW(), NOW())
        ");
        $stmt->execute([
            'community_id' => $communityId,
            'doc_type' => $documentTypeId,
            'orig' => $originalName,
            'path' => $relativePath,
            'mime' => $mime,
            'size' => $size,
            'user_id' => (int) ($_SESSION['user']['id'] ?? 0),
        ]);

        $this->flash('Documento subido correctamente.', 'success', 'Correcto');
        header('Location: ' . $returnTo);
        exit;
    }

    /** @param array<string, string> $params */
    public function deleteDocument(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireAdmin();

        $docId = (int) ($params['docId'] ?? 0);
        $returnTo = (string) ($_POST['return_to'] ?? ($this->areaBaseUrl() . '/comunidades'));

        if ($docId <= 0) {
            $this->flash('ID de documento no válido.', 'danger', 'Error');
            header('Location: ' . $returnTo);
            exit;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            UPDATE community_documents
            SET is_active = FALSE,
                updated_at = NOW()
            WHERE id = :id
            AND is_active = TRUE
        ");
        $stmt->execute(['id' => $docId]);

        if ($stmt->rowCount() > 0) {
            $this->flash('Documento eliminado correctamente.', 'success', 'Correcto');
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

        $docId = (int) ($params['docId'] ?? 0);
        if ($docId <= 0) {
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
                FROM community_documents cd
                JOIN communities c ON c.id = cd.community_id
                WHERE cd.id = :id
                AND cd.is_active = TRUE
                AND c.is_active = TRUE
                LIMIT 1
            ");
            $stmt->execute(['id' => $docId]);
        } else {
            $stmt = $pdo->prepare("
                SELECT
                    cd.id,
                    cd.original_filename,
                    cd.storage_path,
                    cd.mime_type
                FROM community_documents cd
                JOIN communities c ON c.id = cd.community_id
                WHERE cd.id = :id
                AND cd.is_active = TRUE
                AND c.is_active = TRUE
                AND c.manager_company_id = :mc
                LIMIT 1
            ");
            $stmt->execute([
                'id' => $docId,
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

    /** @return array<string, string> */
    private function extractCommunityInput(): array
    {
        return [
            'manager_company_id' => trim((string) ($_POST['manager_company_id'] ?? '')),
            'name' => trim((string) ($_POST['name'] ?? '')),
            'cif' => trim((string) ($_POST['cif'] ?? '')),
            'address' => trim((string) ($_POST['address'] ?? '')),
            'city' => trim((string) ($_POST['city'] ?? '')),
            'province' => trim((string) ($_POST['province'] ?? '')),
            'postal_code' => trim((string) ($_POST['postal_code'] ?? '')),
            'contact_name' => trim((string) ($_POST['contact_name'] ?? '')),
            'contact_phone' => trim((string) ($_POST['contact_phone'] ?? '')),
            'contact_email' => trim((string) ($_POST['contact_email'] ?? '')),
        ];
    }

    /** @param array<string, string> $data
     *  @return array<string, string>
     */
    private function validateCommunityInput(array $data, int $editingId = 0): array
    {
        $errors = [];
        if ($data['manager_company_id'] === '' || !ctype_digit($data['manager_company_id'])) {
            $errors['manager_company_id'] = 'Empresa gestora obligatoria';
        }
        if ($data['name'] === '') {
            $errors['name'] = 'Nombre obligatorio';
        }
        if ($data['contact_email'] !== '' && !filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['contact_email'] = 'Email de contacto no válido';
        }

        if ($data['cif'] !== '') {
            $pdo = Database::connection();
            $stmt = $pdo->prepare("
                SELECT id FROM communities WHERE cif = :cif LIMIT 1
            ");
            $stmt->execute(['cif' => $data['cif']]);
            $existingId = (int) ($stmt->fetchColumn() ?: 0);

            if ($existingId > 0 && $existingId !== $editingId) {
                $errors['cif'] = 'Ya existe una comunidad con ese CIF';
            }
        }

        return $errors;
    }

    private function requireAdmin(): void
    {
        if (($_SESSION['user']['role'] ?? '') !== 'admin') {
            http_response_code(403);
            $this->respond('Acceso denegado');
            exit;
        }
    }

    private function currentUserManagerCompanyId(PDO $pdo): int
    {
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        if ($userId <= 0) return 0;

        $stmt = $pdo->prepare("SELECT manager_company_id FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $userId]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }
}