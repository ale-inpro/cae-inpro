<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\CaeDocumentValidityService;
use PDO;

final class TechnicianController extends Controller
{
    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        $this->assertAreaAccess();

        $pdo = Database::connection();
        $role = (string) ($_SESSION['user']['role'] ?? '');
        $managerCompanyId = $this->currentUserManagerCompanyId($pdo);
        $focus = trim((string) ($_GET['focus'] ?? ''));
        $validFocus = ['cae_pending', 'cae_overdue', 'docreq_open', 'docreq_expired'];

        // Admin: recordar último filtro
        if ($role === 'admin') {
            if ($focus === '' && isset($_SESSION['admin_tech_focus']) && in_array((string) $_SESSION['admin_tech_focus'], $validFocus, true)) {
                $focus = (string) $_SESSION['admin_tech_focus'];
            } elseif ($focus === 'all') {
                unset($_SESSION['admin_tech_focus']);
                $focus = '';
            } elseif (in_array($focus, $validFocus, true)) {
                $_SESSION['admin_tech_focus'] = $focus;
            }
        }

        if ($role === 'admin') {
            $focusWhere = '';
            $params = [];
        
            switch ($focus) {
                case 'cae_pending':
                    $focusWhere = " AND COALESCE(c.status::text, 'pending_docs') IN ('pending', 'pending_docs', 'in_review')";
                    break;
        
                case 'cae_overdue':
                    $focusWhere = " AND COALESCE(c.status::text, 'pending_docs') IN ('pending', 'pending_docs', 'in_review')
                                    AND COALESCE(c.updated_at, c.created_at) < NOW() - INTERVAL '7 days'";
                    break;
        
                case 'docreq_open':
                    $focusWhere = " AND EXISTS (
                                        SELECT 1
                                        FROM cae_document_requests r
                                        WHERE r.technician_id = t.id
                                          AND r.status = 'sent'
                                          AND r.token_used_at IS NULL
                                    )";
                    break;
        
                case 'docreq_expired':
                    $focusWhere = " AND EXISTS (
                                        SELECT 1
                                        FROM cae_document_requests r
                                        WHERE r.technician_id = t.id
                                          AND r.status = 'sent'
                                          AND r.token_used_at IS NULL
                                          AND r.token_expires_at IS NOT NULL
                                          AND r.token_expires_at < NOW()
                                    )";
                    break;
        
                default:
                    $focus = '';
                    break;
            }
        
            $sql = "
                SELECT
                    t.id,
                    t.display_name,
                    t.tax_id,
                    t.entity_type,
                    t.professions,
                    t.city,
                    t.email,
                    COALESCE(c.status::text, 'pending_docs') AS cae_status
                FROM technicians t
                LEFT JOIN cae_records c
                    ON c.technician_id = t.id
                   AND c.is_current = TRUE
                WHERE t.is_active = TRUE
                {$focusWhere}
                ORDER BY t.display_name
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $focus = ''; // gestor sin filtros desde dashboard admin
            $sql = "
                SELECT
                    t.id,
                    t.display_name,
                    t.tax_id,
                    t.entity_type,
                    t.professions,
                    t.city,
                    t.email,
                    COALESCE(c.status::text, 'pending_docs') AS cae_status
                FROM manager_company_technician mct
                JOIN technicians t
                    ON t.id = mct.technician_id
                LEFT JOIN cae_records c
                    ON c.technician_id = t.id
                   AND c.is_current = TRUE
                WHERE mct.manager_company_id = :mc
                  AND mct.status = 'active'
                  AND t.is_active = TRUE
                ORDER BY t.display_name
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['mc' => $managerCompanyId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $pendingAssocCount = 0;
        if ($role === 'admin') {
            $pendingAssocCount = (int) $pdo->query("
                SELECT COUNT(*) FROM technician_association_requests WHERE status = 'pending'
            ")->fetchColumn();
        }

        $this->render('technicians.index', [
            'title' => 'Técnicos',
            'baseUrl' => $this->baseUrl(),
            'area' => $this->currentArea(),
            'areaBaseUrl' => $this->areaBaseUrl(),
            'technicians' => $rows,
            'focus' => $focus,
            'pendingAssocCount' => $pendingAssocCount,
        ]);
    }

    /** @param array<string, string> $params */
    public function show(array $params = []): void
    {
        $this->assertAreaAccess();

        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(404);
            $this->respond('Técnico no encontrado');
            return;
        }

        $pdo = Database::connection();
        $role = (string) ($_SESSION['user']['role'] ?? '');
        $managerCompanyId = $this->currentUserManagerCompanyId($pdo);

        if ($role === 'admin') {
            $stmt = $pdo->prepare("
                SELECT id, entity_type, tax_id, display_name, email, phone, professions, city, province, postal_code, address
                FROM technicians
                WHERE id = :id AND is_active = TRUE
                LIMIT 1
            ");
            $stmt->execute(['id' => $id]);
        } else {
            $stmt = $pdo->prepare("
                SELECT t.id, t.entity_type, t.tax_id, t.display_name, t.email, t.phone, t.professions, t.city, t.province, t.postal_code, t.address
                FROM technicians t
                JOIN manager_company_technician mct ON mct.technician_id = t.id
                WHERE t.id = :id
                  AND t.is_active = TRUE
                  AND mct.manager_company_id = :mc
                  AND mct.status = 'active'
                LIMIT 1
            ");
            $stmt->execute(['id' => $id, 'mc' => $managerCompanyId]);
        }

        $tech = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tech) {
            http_response_code(404);
            $this->respond('Técnico no encontrado');
            return;
        }

        $stmt = $pdo->prepare("
            SELECT id, status::text AS status, issue_date, valid_from, valid_until, notes
            FROM cae_records
            WHERE technician_id = :id
              AND is_current = TRUE
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $currentCae = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $stmt = $pdo->prepare("
            SELECT
                cr.id,
                cr.status::text AS status,
                cr.valid_from,
                cr.valid_until,
                cr.notes,
                cr.is_current,
                (
                    SELECT cd.id
                    FROM cae_documents cd
                    WHERE cd.cae_record_id = cr.id
                      AND cd.is_active = TRUE
                      AND cd.is_cae_file = TRUE
                    ORDER BY cd.uploaded_at DESC
                    LIMIT 1
                ) AS latest_doc_id,
                (
                    SELECT cd.storage_path
                    FROM cae_documents cd
                    WHERE cd.cae_record_id = cr.id
                      AND cd.is_active = TRUE
                      AND cd.is_cae_file = TRUE
                    ORDER BY cd.uploaded_at DESC
                    LIMIT 1
                ) AS latest_doc_path,
                (
                    SELECT cd.original_filename
                    FROM cae_documents cd
                    WHERE cd.cae_record_id = cr.id
                      AND cd.is_active = TRUE
                      AND cd.is_cae_file = TRUE
                    ORDER BY cd.uploaded_at DESC
                    LIMIT 1
                ) AS latest_doc_name
            FROM cae_records cr
            WHERE cr.technician_id = :id
            ORDER BY cr.valid_from DESC, cr.id DESC
        ");
        $stmt->execute(['id' => $id]);
        $caeHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $hasExpiresAt = false;
        try {
            $colStmt = $pdo->prepare("
                SELECT 1
                FROM information_schema.columns
                WHERE table_schema = 'public'
                  AND table_name = 'cae_documents'
                  AND column_name = 'expires_at'
                LIMIT 1
            ");
            $colStmt->execute();
            $hasExpiresAt = (bool) $colStmt->fetchColumn();
        } catch (\Throwable) {
            $hasExpiresAt = false;
        }

        $hasAeatCotejo = false;
        try {
            $colStmt = $pdo->prepare("
                SELECT 1
                FROM information_schema.columns
                WHERE table_schema = 'public'
                  AND table_name = 'cae_documents'
                  AND column_name = 'aeat_cotejo_codigo'
                LIMIT 1
            ");
            $colStmt->execute();
            $hasAeatCotejo = (bool) $colStmt->fetchColumn();
        } catch (\Throwable) {
            $hasAeatCotejo = false;
        }

        $expiresSelect = $hasExpiresAt ? 'cd.expires_at' : 'NULL::date AS expires_at';
        $aeatSelect = $hasAeatCotejo
            ? ', cd.aeat_cotejo_codigo, cd.aeat_cotejo_huella_ok, cd.aeat_cotejo_descripcion, cd.aeat_cotejo_checked_at, cd.aeat_cotejo_used_mock, cd.aeat_pdf_validation_ok, cd.aeat_pdf_validation_errors, cd.aeat_replaced_upload'
            : '';

        $stmt = $pdo->prepare("
            SELECT
                cd.id,
                cd.document_type_id,
                dt.name AS document_name,
                cd.original_filename,
                cd.storage_path,
                cd.uploaded_at,
                {$expiresSelect}
                {$aeatSelect}
            FROM cae_documents cd
            JOIN document_types dt ON dt.id = cd.document_type_id
            JOIN cae_records cr ON cr.id = cd.cae_record_id
            WHERE cr.technician_id = :tid
              AND cr.is_current = TRUE
              AND cd.is_active = TRUE
              AND cd.is_cae_file = FALSE
            ORDER BY cd.uploaded_at DESC
        ");
        $stmt->execute(['tid' => $id]);
        $caeDocuments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($caeDocuments as $i => $docRow) {
            $caeDocuments[$i]['cae_validity'] = CaeDocumentValidityService::evaluateSupportingRow(
                $pdo,
                $docRow,
                $hasAeatCotejo
            );
        }

        $activeSupportingFilenameByDocTypeId = [];
        foreach ($caeDocuments as $docRow) {
            $dtype = (int) ($docRow['document_type_id'] ?? 0);
            if ($dtype > 0 && !isset($activeSupportingFilenameByDocTypeId[$dtype])) {
                $activeSupportingFilenameByDocTypeId[$dtype] =
                    (string) ($docRow['original_filename'] ?? '');
            }
        }

        $pendingIntakeDocs = [];
        if ($role === 'admin') {
            $stmt = $pdo->prepare("
                SELECT
                    i.id,
                    i.original_filename,
                    i.storage_path,
                    i.ai_status,
                    i.ai_confidence,
                    i.ai_issue_date,
                    i.ai_expires_at,
                    i.ai_notes,
                    i.created_at,
                    i.extracted_aeat_csv,
                    dt.name AS document_name
                FROM cae_document_intake i
                JOIN document_types dt ON dt.id = i.document_type_id
                WHERE i.technician_id = :tid
                  AND i.status = 'pending_manual'
                ORDER BY i.created_at DESC
            ");
            $stmt->execute(['tid' => $id]);
            $pendingIntakeDocs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($pendingIntakeDocs as $i => $row) {
                $pendingIntakeDocs[$i]['_present'] = \App\Services\DocumentIntakePresentationService::presentPendingIntake($row);
            }
        }

        $caeDocTypes = [];
        if ($this->currentArea() === 'admin') {
            $stmt = $pdo->query("
                SELECT id, name
                FROM document_types
                WHERE scope = 'technician_cae'
                    AND is_active = TRUE
                    AND is_cae_file_type = FALSE
                    AND name IN (
                        'Certificado de estar al corriente con Hacienda',
                        'Certificado de estar al corriente con Seguridad Social',
                        'Póliza de Responsabilidad Civil',
                        'Certificado de Prevención de Riesgos Laborales'
                    )
                    ORDER BY CASE name
                        WHEN 'Certificado de estar al corriente con Hacienda' THEN 1
                        WHEN 'Certificado de estar al corriente con Seguridad Social' THEN 2
                        WHEN 'Póliza de Responsabilidad Civil' THEN 3
                        WHEN 'Certificado de Prevención de Riesgos Laborales' THEN 4
                        ELSE 99
                    END
            ");
            $caeDocTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $this->render('technicians.show', [
            'title' => 'Ficha Técnico',
            'baseUrl' => $this->baseUrl(),
            'area' => $this->currentArea(),
            'areaBaseUrl' => $this->areaBaseUrl(),
            'tech' => $tech,
            'currentCae' => $currentCae,
            'caeHistory' => $caeHistory,
            'caeDocuments' => $caeDocuments,
            'pendingIntakeDocs' => $pendingIntakeDocs,
            'caeDocTypes' => $caeDocTypes,
            'activeSupportingFilenameByDocTypeId' => $activeSupportingFilenameByDocTypeId,
        ]);
    }

    public function create(): void
    {
        $this->assertAreaAccess();
        $this->requireAdmin();

        $this->render('technicians.form', [
            'title' => 'Nuevo técnico',
            'areaBaseUrl' => $this->areaBaseUrl(),
            'mode' => 'create',
            'tech' => [],
            'errors' => [],
        ]);
    }

    public function store(): void
    {
        $this->assertAreaAccess();
        $this->requireAdmin();

        $data = $this->extractTechnicianInput();
        $errors = $this->validateTechnicianInput($data);

        if ($errors !== []) {
            $this->render('technicians.form', [
                'title' => 'Nuevo técnico',
                'areaBaseUrl' => $this->areaBaseUrl(),
                'mode' => 'create',
                'tech' => $data,
                'errors' => $errors,
            ]);
            return;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            INSERT INTO technicians
            (entity_type, tax_id, display_name, email, phone, professions, city, province, postal_code, address, is_active, created_at, updated_at)
            VALUES
            (:entity_type, :tax_id, :display_name, :email, :phone, :professions, :city, :province, :postal_code, :address, TRUE, NOW(), NOW())
            RETURNING id
        ");
        $stmt->execute($data);
        $newId = (int) $stmt->fetchColumn();

        $this->flash('Técnico creado correctamente.', 'success', 'Correcto');
        header('Location: ' . $this->areaBaseUrl() . '/tecnicos/' . $newId);
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
            $this->respond('Técnico no encontrado');
            return;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            SELECT id, entity_type, tax_id, display_name, email, phone, professions, city, province, postal_code, address
            FROM technicians
            WHERE id = :id
              AND is_active = TRUE
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $tech = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tech) {
            http_response_code(404);
            $this->respond('Técnico no encontrado');
            return;
        }

        $returnTo = trim((string) ($_GET['return_to'] ?? ''));
        if ($returnTo === '') {
            $returnTo = $this->areaBaseUrl() . '/tecnicos/' . $id . '#pane-info';
        }

        $this->render('technicians.form', [
            'title' => 'Editar técnico',
            'areaBaseUrl' => $this->areaBaseUrl(),
            'mode' => 'edit',
            'tech' => $tech,
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
            $this->respond('Técnico no encontrado');
            return;
        }

        $data = $this->extractTechnicianInput();
        $errors = $this->validateTechnicianInput($data, $id);

        $returnTo = trim((string) ($_POST['return_to'] ?? ''));
        if ($returnTo === '') {
            $returnTo = $this->areaBaseUrl() . '/tecnicos/' . $id . '#pane-info';
        }

        if ($errors !== []) {
            $data['id'] = (string) $id;
            $this->render('technicians.form', [
                'title' => 'Editar técnico',
                'areaBaseUrl' => $this->areaBaseUrl(),
                'mode' => 'edit',
                'tech' => $data,
                'errors' => $errors,
                'returnTo' => $returnTo,
            ]);
            return;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            UPDATE technicians
            SET entity_type = :entity_type,
                tax_id = :tax_id,
                display_name = :display_name,
                email = :email,
                phone = :phone,
                professions = :professions,
                city = :city,
                province = :province,
                postal_code = :postal_code,
                address = :address,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(array_merge($data, ['id' => $id]));

        $this->flash('Técnico actualizado correctamente.', 'success', 'Correcto');
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
            $this->flash('ID de técnico no válido.', 'danger', 'Error');
            header('Location: ' . $this->areaBaseUrl() . '/tecnicos');
            exit;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare("
            UPDATE technicians
            SET is_active = FALSE,
                updated_at = NOW()
            WHERE id = :id
                AND is_active = TRUE
        ");
        $stmt->execute(['id' => $id]);

        if ($stmt->rowCount() > 0) {
            $this->flash('Técnico desactivado correctamente.', 'success', 'Correcto');
        } else {
            $this->flash('No se pudo desactivar el técnico o ya estaba desactivado.', 'warning', 'Aviso');
        }

        header('Location: ' . $this->areaBaseUrl() . '/tecnicos');
        exit;
    }

    /** @return array<string, string> */
    private function extractTechnicianInput(): array
    {
        $entityType = trim((string) ($_POST['entity_type'] ?? 'individual'));
        if (!in_array($entityType, ['individual', 'company'], true)) {
            $entityType = 'individual';
        }

        return [
            'entity_type' => $entityType,
            'tax_id' => $this->normalizeTaxId(trim((string) ($_POST['tax_id'] ?? ''))),
            'display_name' => trim((string) ($_POST['display_name'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'professions' => trim((string) ($_POST['professions'] ?? '')),
            'city' => trim((string) ($_POST['city'] ?? '')),
            'province' => trim((string) ($_POST['province'] ?? '')),
            'postal_code' => trim((string) ($_POST['postal_code'] ?? '')),
            'address' => trim((string) ($_POST['address'] ?? '')),
        ];
    }

    /** @param array<string, string> $data
     *  @return array<string, string>
     */
    private function validateTechnicianInput(array $data, int $editingId = 0): array
    {
        $errors = [];

        if (!in_array($data['entity_type'] ?? '', ['individual', 'company'], true)) {
            $errors['entity_type'] = 'Tipo de entidad no válido';
        }
        if (($data['display_name'] ?? '') === '') {
            $errors['display_name'] = ($data['entity_type'] ?? '') === 'company'
                ? 'Razón social obligatoria'
                : 'Nombre completo obligatorio';
        }
        if (($data['tax_id'] ?? '') === '') {
            $errors['tax_id'] = ($data['entity_type'] ?? '') === 'company'
                ? 'CIF obligatorio'
                : 'NIF/DNI/NIE obligatorio';
        }
        if (($data['professions'] ?? '') === '') {
            $errors['professions'] = 'Profesión obligatoria';
        }

        if (($data['email'] ?? '') !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email no válido';
        }

        if (($data['tax_id'] ?? '') !== '') {
            $pdo = Database::connection();
            $stmt = $pdo->prepare("
                SELECT id FROM technicians WHERE tax_id = :tax_id LIMIT 1
            ");
            $stmt->execute(['tax_id' => $data['tax_id']]);
            $existingId = (int) ($stmt->fetchColumn() ?: 0);

            if ($existingId > 0 && $existingId !== $editingId) {
                $errors['tax_id'] = 'Ya existe un técnico con ese identificador fiscal';
            }
        }

        return $errors;
    }

    private function normalizeTaxId(string $raw): string
    {
        return strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', $raw));
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

    private function requireGestor(): void
    {
        if (($_SESSION['user']['role'] ?? '') !== 'gestor') {
            http_response_code(403);
            $this->respond('Acceso denegado');
            exit;
        }
    }

    private function assertGestorManagerCompany(PDO $pdo): int
    {
        $mcId = $this->currentUserManagerCompanyId($pdo);
        if ($mcId <= 0) {
            $this->flash('Tu usuario no tiene empresa gestora asignada.', 'danger', 'Error');
            header('Location: ' . $this->areaBaseUrl() . '/dashboard');
            exit;
        }
        return $mcId;
    }

    /** @return array<string, mixed>|null */
    private function findTechnicianByTaxId(PDO $pdo, string $taxId): ?array
    {
        if ($taxId === '') {
            return null;
        }
        $stmt = $pdo->prepare("
            SELECT id, entity_type, tax_id, display_name, email, phone, professions,
                   city, province, postal_code, address, is_active
            FROM technicians
            WHERE tax_id = :tax_id
            LIMIT 1
        ");
        $stmt->execute(['tax_id' => $taxId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function linkTechnicianToManagerCompany(PDO $pdo, int $managerCompanyId, int $technicianId): void
    {
        $stmt = $pdo->prepare("
            SELECT id, status FROM manager_company_technician
            WHERE manager_company_id = :mc AND technician_id = :tid
            LIMIT 1
        ");
        $stmt->execute(['mc' => $managerCompanyId, 'tid' => $technicianId]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($link) {
            $pdo->prepare("
                UPDATE manager_company_technician
                SET status = 'active', updated_at = NOW()
                WHERE id = :id
            ")->execute(['id' => (int) $link['id']]);
        } else {
            $pdo->prepare("
                INSERT INTO manager_company_technician
                (manager_company_id, technician_id, status, created_at, updated_at)
                VALUES (:mc, :tid, 'active', NOW(), NOW())
            ")->execute(['mc' => $managerCompanyId, 'tid' => $technicianId]);
        }
    }

    /** @param array<string, mixed> $payload */
    private function notifyAdmins(string $type, string $title, string $message, array $payload): void
    {
        $pdo = Database::connection();
        $adminIds = $pdo
            ->query("SELECT id FROM users WHERE role = 'admin' AND is_active = TRUE")
            ->fetchAll(PDO::FETCH_COLUMN);

        foreach ($adminIds as $adminId) {
            $this->createNotification((int) $adminId, $type, $title, $message, $payload);
        }
    }

    public function gestorLinkForm(): void
    {
        $this->assertAreaAccess();
        $this->requireGestor();

        $this->render('technicians.gestor-link', [
            'title' => 'Vincular técnico',
            'areaBaseUrl' => $this->areaBaseUrl(),
            'taxId' => trim((string) ($_GET['tax_id'] ?? '')),
            'lookup' => null,
            'errors' => [],
        ]);
    }

    public function gestorLinkLookup(): void
    {
        $this->assertAreaAccess();
        $this->requireGestor();

        $pdo = Database::connection();
        $mcId = $this->assertGestorManagerCompany($pdo);
        $taxId = $this->normalizeTaxId(trim((string) ($_POST['tax_id'] ?? '')));
        $errors = [];

        if ($taxId === '') {
            $errors['tax_id'] = 'Introduce un NIF/CIF';
        }

        $lookup = null;
        if ($errors === []) {
            $tech = $this->findTechnicianByTaxId($pdo, $taxId);

            if (!$tech) {
                $lookup = ['state' => 'not_found', 'tax_id' => $taxId];
            } elseif (!$this->boolFromPg($tech['is_active'] ?? false)) {
                $lookup = ['state' => 'inactive_global', 'tech' => $tech];
            } else {
                $stmt = $pdo->prepare("
                    SELECT status FROM manager_company_technician
                    WHERE manager_company_id = :mc AND technician_id = :tid
                    LIMIT 1
                ");
                $stmt->execute(['mc' => $mcId, 'tid' => (int) $tech['id']]);
                $mctStatus = (string) ($stmt->fetchColumn() ?: '');

                if ($mctStatus === 'active') {
                    $lookup = ['state' => 'in_portfolio', 'tech' => $tech];
                } else {
                    $stmt = $pdo->prepare("
                        SELECT id FROM technician_association_requests
                        WHERE technician_id = :tid
                          AND manager_company_id = :mc
                          AND status = 'pending'
                        LIMIT 1
                    ");
                    $stmt->execute(['tid' => (int) $tech['id'], 'mc' => $mcId]);
                    $pendingId = (int) ($stmt->fetchColumn() ?: 0);

                    $lookup = $pendingId > 0
                        ? ['state' => 'pending_request', 'tech' => $tech, 'request_id' => $pendingId]
                        : ['state' => 'can_request', 'tech' => $tech];
                }
            }
        }

        $this->render('technicians.gestor-link', [
            'title' => 'Vincular técnico',
            'areaBaseUrl' => $this->areaBaseUrl(),
            'taxId' => $taxId,
            'lookup' => $lookup,
            'errors' => $errors,
        ]);
    }

    public function gestorCreate(): void
    {
        $this->assertAreaAccess();
        $this->requireGestor();

        $taxId = $this->normalizeTaxId(trim((string) ($_GET['tax_id'] ?? '')));
        $this->render('technicians.form', [
            'title' => 'Nuevo técnico',
            'areaBaseUrl' => $this->areaBaseUrl(),
            'mode' => 'gestor_create',
            'tech' => ['tax_id' => $taxId, 'entity_type' => 'individual'],
            'errors' => [],
            'returnTo' => $this->areaBaseUrl() . '/tecnicos/vincular',
        ]);
    }

    public function gestorStore(): void
    {
        $this->assertAreaAccess();
        $this->requireGestor();

        $pdo = Database::connection();
        $mcId = $this->assertGestorManagerCompany($pdo);
        $userId = (int) ($_SESSION['user']['id'] ?? 0);

        $data = $this->extractTechnicianInput();
        $errors = $this->validateTechnicianInput($data);

        if ($errors !== []) {
            $this->render('technicians.form', [
                'title' => 'Nuevo técnico',
                'areaBaseUrl' => $this->areaBaseUrl(),
                'mode' => 'gestor_create',
                'tech' => $data,
                'errors' => $errors,
                'returnTo' => $this->areaBaseUrl() . '/tecnicos/vincular',
            ]);
            return;
        }

        if ($this->findTechnicianByTaxId($pdo, $data['tax_id']) !== null) {
            $this->flash('Ese identificador fiscal ya existe. Usa «Vincular» para solicitar asociación.', 'warning', 'Aviso');
            header('Location: ' . $this->areaBaseUrl() . '/tecnicos/vincular');
            exit;
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO technicians
                (entity_type, tax_id, display_name, email, phone, professions,
                 city, province, postal_code, address, is_active, created_at, updated_at)
                VALUES
                (:entity_type, :tax_id, :display_name, :email, :phone, :professions,
                 :city, :province, :postal_code, :address, TRUE, NOW(), NOW())
                RETURNING id
            ");
            $stmt->execute($data);
            $newId = (int) $stmt->fetchColumn();

            $this->linkTechnicianToManagerCompany($pdo, $mcId, $newId);
            $pdo->commit();

            $gestorName = trim((string) ($_SESSION['user']['full_name'] ?? 'Un gestor'));
            $this->notifyAdmins(
                'technician_created_by_gestor',
                'Nuevo técnico creado por gestor',
                'El gestor «' . $gestorName . '» ha dado de alta al técnico «' . $data['display_name'] . '» (' . $data['tax_id'] . ').',
                ['technician_id' => $newId, 'manager_company_id' => $mcId, 'created_by_user_id' => $userId]
            );

            $this->flash('Técnico creado y añadido a tu cartera.', 'success', 'Correcto');
            header('Location: ' . $this->areaBaseUrl() . '/tecnicos/' . $newId);
            exit;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->flash('No se pudo crear el técnico.', 'danger', 'Error');
            header('Location: ' . $this->areaBaseUrl() . '/tecnicos/vincular');
            exit;
        }
    }

    public function gestorRequestAssociation(): void
    {
        $this->assertAreaAccess();
        $this->requireGestor();

        $pdo = Database::connection();
        $mcId = $this->assertGestorManagerCompany($pdo);
        $userId = (int) ($_SESSION['user']['id'] ?? 0);

        $technicianId = (int) ($_POST['technician_id'] ?? 0);
        $gestorNotes = trim((string) ($_POST['gestor_notes'] ?? ''));

        if ($technicianId <= 0) {
            $this->flash('Técnico no válido.', 'danger', 'Error');
            header('Location: ' . $this->areaBaseUrl() . '/tecnicos/vincular');
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT id, display_name, tax_id, is_active FROM technicians WHERE id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $technicianId]);
        $tech = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tech || !$this->boolFromPg($tech['is_active'] ?? false)) {
            $this->flash('Técnico no encontrado o inactivo.', 'warning', 'Aviso');
            header('Location: ' . $this->areaBaseUrl() . '/tecnicos/vincular');
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT status FROM manager_company_technician
            WHERE manager_company_id = :mc AND technician_id = :tid LIMIT 1
        ");
        $stmt->execute(['mc' => $mcId, 'tid' => $technicianId]);
        if ((string) ($stmt->fetchColumn() ?: '') === 'active') {
            $this->flash('Este técnico ya está en tu cartera.', 'info', 'Aviso');
            header('Location: ' . $this->areaBaseUrl() . '/tecnicos/' . $technicianId);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO technician_association_requests
                (technician_id, manager_company_id, requested_by_user_id, status, gestor_notes, created_at, updated_at)
                VALUES (:tid, :mc, :uid, 'pending', :notes, NOW(), NOW())
                RETURNING id
            ");
            $stmt->execute([
                'tid' => $technicianId,
                'mc' => $mcId,
                'uid' => $userId,
                'notes' => $gestorNotes !== '' ? $gestorNotes : null,
            ]);
            $requestId = (int) $stmt->fetchColumn();

            $gestorName = trim((string) ($_SESSION['user']['full_name'] ?? 'Un gestor'));
            $techName = trim((string) ($tech['display_name'] ?? 'Técnico'));
            $taxId = trim((string) ($tech['tax_id'] ?? ''));

            $this->notifyAdmins(
                'technician_association_requested',
                'Solicitud de asociación de técnico',
                'El gestor «' . $gestorName . '» solicita vincular al técnico «' . $techName . '» (' . $taxId . ').',
                [
                    'technician_id' => $technicianId,
                    'manager_company_id' => $mcId,
                    'request_id' => $requestId,
                ]
            );

            $this->flash('Solicitud enviada. El administrador debe aprobarla.', 'success', 'Solicitud enviada');
        } catch (\PDOException $e) {
            // unique partial index: pending duplicada
            $this->flash('Ya tienes una solicitud pendiente para este técnico.', 'warning', 'Aviso');
        }

        header('Location: ' . $this->areaBaseUrl() . '/tecnicos/vincular');
        exit;
    }
}