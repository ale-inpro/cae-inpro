<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Database;
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

        if ($role === 'admin') {
            $sql = "
                SELECT
                    t.id,
                    t.first_name,
                    t.last_name,
                    t.professions,
                    t.city,
                    t.email,
                    COALESCE(c.status::text, 'pending_docs') AS cae_status
                FROM technicians t
                LEFT JOIN cae_records c
                    ON c.technician_id = t.id
                   AND c.is_current = TRUE
                WHERE t.is_active = TRUE
                ORDER BY t.last_name, t.first_name
            ";
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $sql = "
                SELECT
                    t.id,
                    t.first_name,
                    t.last_name,
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
                ORDER BY t.last_name, t.first_name
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['mc' => $managerCompanyId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $this->render('technicians.index', [
            'title' => 'Técnicos',
            'baseUrl' => $this->baseUrl(),
            'area' => $this->currentArea(),
            'areaBaseUrl' => $this->areaBaseUrl(),
            'technicians' => $rows,
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
                SELECT id, first_name, last_name, dni_nie, email, phone, professions, city, province, postal_code, address
                FROM technicians
                WHERE id = :id AND is_active = TRUE
                LIMIT 1
            ");
            $stmt->execute(['id' => $id]);
        } else {
            $stmt = $pdo->prepare("
                SELECT t.id, t.first_name, t.last_name, t.dni_nie, t.email, t.phone, t.professions, t.city, t.province, t.postal_code, t.address
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

        $stmt = $pdo->prepare("
            SELECT
                cd.id,
                dt.name AS document_name,
                cd.original_filename,
                cd.storage_path,
                cd.uploaded_at
            FROM cae_documents cd
            JOIN document_types dt ON dt.id = cd.document_type_id
            JOIN cae_records cr ON cr.id = cd.cae_record_id
            WHERE cr.technician_id = :tid
            AND cd.is_active = TRUE
            AND cd.is_cae_file = FALSE
            ORDER BY cd.uploaded_at DESC
        ");
        $stmt->execute(['tid' => $id]);
        $caeDocuments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $caeDocTypes = [];
        if ($this->currentArea() === 'admin') {
            $stmt = $pdo->query("
                SELECT id, name
                FROM document_types
                WHERE scope = 'technician_cae'
                    AND is_active = TRUE
                    AND is_cae_file_type = FALSE
                ORDER BY name
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
            'caeDocTypes' => $caeDocTypes,
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
            (first_name, last_name, dni_nie, email, phone, professions, city, province, postal_code, address, is_active, created_at, updated_at)
            VALUES
            (:first_name, :last_name, :dni_nie, :email, :phone, :professions, :city, :province, :postal_code, :address, TRUE, NOW(), NOW())
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
            SELECT id, first_name, last_name, dni_nie, email, phone, professions, city, province, postal_code, address
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
            SET first_name = :first_name,
                last_name = :last_name,
                dni_nie = :dni_nie,
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
        return [
            'first_name' => trim((string) ($_POST['first_name'] ?? '')),
            'last_name' => trim((string) ($_POST['last_name'] ?? '')),
            'dni_nie' => trim((string) ($_POST['dni_nie'] ?? '')),
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
        if ($data['first_name'] === '') $errors['first_name'] = 'Nombre obligatorio';
        if ($data['last_name'] === '') $errors['last_name'] = 'Apellidos obligatorios';
        if ($data['dni_nie'] === '') $errors['dni_nie'] = 'DNI/NIE obligatorio';
        if ($data['professions'] === '') $errors['professions'] = 'Profesión obligatoria';

        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email no válido';
        }

        // Unicidad dni_nie
        if ($data['dni_nie'] !== '') {
            $pdo = Database::connection();
            $stmt = $pdo->prepare("
                SELECT id FROM technicians WHERE dni_nie = :dni LIMIT 1
            ");
            $stmt->execute(['dni' => $data['dni_nie']]);
            $existingId = (int) ($stmt->fetchColumn() ?: 0);

            if ($existingId > 0 && $existingId !== $editingId) {
                $errors['dni_nie'] = 'Ya existe un técnico con ese DNI/NIE';
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