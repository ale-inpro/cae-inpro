<?php

declare(strict_types=1);

namespace App\Http\Controllers\Supply;

use App\Core\Controller;
use App\Core\Database;
use PDO;

final class SupplyCompanyController extends Controller
{
    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireAdmin();

        $pdo = $this->db();
        $rows = $pdo->query("
            SELECT id, name, company_role, phone, email, website, is_active, updated_at
            FROM supply_companies
            ORDER BY is_active DESC, name
        ")->fetchAll(PDO::FETCH_ASSOC);

        $this->render('supply.companies.index', [
            'title' => 'Suministros · Empresas',
            'area' => $this->currentArea(),
            'areaBaseUrl' => $this->areaBaseUrl(),
            'baseUrl' => $this->baseUrl(),
            'companies' => $rows,
        ]);
    }

    /** @param array<string, string> $params */
    public function create(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireAdmin();
        $this->renderForm('create', [], []);
    }

    /** @param array<string, string> $params */
    public function store(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireAdmin();

        [$data, $errors] = $this->validateCompanyInput($_POST);
        if ($errors !== []) {
            $this->renderForm('create', $data, $errors);
            return;
        }

        $pdo = $this->db();
        $pdo->prepare("
            INSERT INTO supply_companies (name, company_role, phone, email, website, is_active, created_at, updated_at)
            VALUES (:name, :role, :phone, :email, :website, TRUE, NOW(), NOW())
        ")->execute([
            'name' => $data['name'],
            'role' => $data['company_role'],
            'phone' => $data['phone'] !== '' ? $data['phone'] : null,
            'email' => $data['email'] !== '' ? $data['email'] : null,
            'website' => $data['website'] !== '' ? $data['website'] : null,
        ]);

        $this->flash('Empresa creada correctamente.', 'success', 'Suministros');
        header('Location: ' . $this->areaBaseUrl() . '/suministros/empresas');
        exit;
    }

    /** @param array<string, string> $params */
    public function edit(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireAdmin();

        $company = $this->findCompany((int) ($params['id'] ?? 0));
        if ($company === null) {
            http_response_code(404);
            $this->respond('Empresa no encontrada');
            return;
        }

        $this->renderForm('edit', $company, []);
    }

    /** @param array<string, string> $params */
    public function update(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireAdmin();

        $id = (int) ($params['id'] ?? 0);
        $company = $this->findCompany($id);
        if ($company === null) {
            http_response_code(404);
            $this->respond('Empresa no encontrada');
            return;
        }

        [$data, $errors] = $this->validateCompanyInput($_POST);
        if ($errors !== []) {
            $this->renderForm('edit', array_merge($company, $data), $errors);
            return;
        }

        $pdo = $this->db();
        $pdo->prepare("
            UPDATE supply_companies SET
                name = :name,
                company_role = :role,
                phone = :phone,
                email = :email,
                website = :website,
                is_active = :is_active,
                updated_at = NOW()
            WHERE id = :id
        ")->execute([
            'name' => $data['name'],
            'role' => $data['company_role'],
            'phone' => $data['phone'] !== '' ? $data['phone'] : null,
            'email' => $data['email'] !== '' ? $data['email'] : null,
            'website' => $data['website'] !== '' ? $data['website'] : null,
            'is_active' => $data['is_active'] ? 'true' : 'false',
            'id' => $id,
        ]);

        $this->flash('Empresa actualizada.', 'success', 'Suministros');
        header('Location: ' . $this->areaBaseUrl() . '/suministros/empresas');
        exit;
    }

    /** @param array<string, string> $params */
    public function delete(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireAdmin();

        $id = (int) ($params['id'] ?? 0);
        $pdo = $this->db();
        $pdo->prepare('UPDATE supply_companies SET is_active = FALSE, updated_at = NOW() WHERE id = :id')->execute(['id' => $id]);

        $this->flash('Empresa desactivada.', 'success', 'Suministros');
        header('Location: ' . $this->areaBaseUrl() . '/suministros/empresas');
        exit;
    }

    private function requireAdmin(): void
    {
        if (($_SESSION['user']['role'] ?? '') !== 'admin') {
            http_response_code(403);
            $this->respond('Solo administradores.');
            exit;
        }
    }

    /** @param array<string, mixed> $input */
    /** @return array{0: array<string, string>, 1: list<string>} */
    private function validateCompanyInput(array $input): array
    {
        $data = [
            'name' => trim((string) ($input['name'] ?? '')),
            'company_role' => trim((string) ($input['company_role'] ?? 'mixed')),
            'phone' => trim((string) ($input['phone'] ?? '')),
            'email' => trim((string) ($input['email'] ?? '')),
            'website' => trim((string) ($input['website'] ?? '')),
            'is_active' => isset($input['is_active']),
        ];
        $errors = [];
        if ($data['name'] === '') {
            $errors[] = 'El nombre es obligatorio.';
        }
        if (!in_array($data['company_role'], ['marketer', 'distributor', 'mixed'], true)) {
            $errors[] = 'Tipo de empresa inválido.';
        }
        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email no válido.';
        }
        return [$data, $errors];
    }

    /** @param array<string, mixed> $data */
    /** @param list<string> $errors */
    private function renderForm(string $mode, array $data, array $errors): void
    {
        $this->render('supply.companies.form', [
            'title' => $mode === 'create' ? 'Suministros · Nueva empresa' : 'Suministros · Editar empresa',
            'area' => $this->currentArea(),
            'areaBaseUrl' => $this->areaBaseUrl(),
            'baseUrl' => $this->baseUrl(),
            'mode' => $mode,
            'company' => $data,
            'errors' => $errors,
        ]);
    }

    /** @return array<string, mixed>|null */
    private function findCompany(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $stmt = $this->db()->prepare('SELECT * FROM supply_companies WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function db(): PDO
    {
        return Database::connection();
    }
}