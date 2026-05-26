<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rgpd;

use App\Core\Controller;
use App\Core\Database;
use PDO;

final class RgpdTemplateController extends Controller
{
    use RgpdControllerTrait;

    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        $this->assertAreaAccess();
        $pdo = $this->rgpdPdo();
        $role = $this->rgpdRole();

        $rows = $pdo->query("
            SELECT id, kind, name, slug, category, description, is_active, updated_at
            FROM rgpd_templates
            ORDER BY kind DESC, name
        ")->fetchAll(PDO::FETCH_ASSOC);

        $this->render('rgpd.templates.index', [
            'title' => 'RGPD · Plantillas',
            'area' => $this->currentArea(),
            'areaBaseUrl' => $this->areaBaseUrl(),
            'baseUrl' => $this->baseUrl(),
            'templates' => $rows,
            'isAdmin' => $role === 'admin',
        ]);
    }

    /** @param array<string, string> $params */
    public function show(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireRgpdAdmin();

        $tpl = $this->findTemplate((int) ($params['id'] ?? 0));
        if ($tpl === null) {
            http_response_code(404);
            $this->respond('Plantilla no encontrada');
            return;
        }

        $this->render('rgpd.templates.show', [
            'title' => 'RGPD · ' . ($tpl['name'] ?? 'Plantilla'),
            'area' => $this->currentArea(),
            'areaBaseUrl' => $this->areaBaseUrl(),
            'baseUrl' => $this->baseUrl(),
            'template' => $tpl,
            'readOnly' => ($tpl['kind'] ?? '') === 'system',
        ]);
    }

    /** @param array<string, string> $params */
    public function create(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireRgpdAdmin();

        $this->render('rgpd.templates.form', [
            'title' => 'RGPD · Nueva plantilla',
            'area' => $this->currentArea(),
            'areaBaseUrl' => $this->areaBaseUrl(),
            'baseUrl' => $this->baseUrl(),
            'mode' => 'create',
            'template' => [],
            'errors' => [],
        ]);
    }

    /** @param array<string, string> $params */
    public function store(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireRgpdAdmin();

        [$data, $errors] = $this->validateTemplateInput($_POST);
        if ($errors !== []) {
            $this->render('rgpd.templates.form', [
                'title' => 'RGPD · Nueva plantilla',
                'area' => $this->currentArea(),
                'areaBaseUrl' => $this->areaBaseUrl(),
                'baseUrl' => $this->baseUrl(),
                'mode' => 'create',
                'template' => $data,
                'errors' => $errors,
            ]);
            return;
        }

        $pdo = $this->rgpdPdo();
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $slug = $this->uniqueSlug($pdo, $data['slug'] !== '' ? $data['slug'] : $data['name']);

        $stmt = $pdo->prepare("
            INSERT INTO rgpd_templates (kind, name, slug, category, description, body_html, is_active, created_by_user_id, created_at, updated_at)
            VALUES ('user', :name, :slug, :category, :description, :body_html, :is_active, :uid, NOW(), NOW())
            RETURNING id
        ");
        $stmt->execute([
            'name' => $data['name'],
            'slug' => $slug,
            'category' => $data['category'],
            'description' => $data['description'] !== '' ? $data['description'] : null,
            'body_html' => $data['body_html'],
            'is_active' => $data['is_active'] ? 'true' : 'false',
            'uid' => $userId > 0 ? $userId : null,
        ]);

        $id = (int) $stmt->fetchColumn();
        $this->flash('Plantilla creada correctamente.', 'success', 'RGPD');
        header('Location: ' . $this->areaBaseUrl() . '/rgpd/plantillas/' . $id);
        exit;
    }

    /** @param array<string, string> $params */
    public function edit(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireRgpdAdmin();

        $tpl = $this->findTemplate((int) ($params['id'] ?? 0));
        if ($tpl === null) {
            http_response_code(404);
            $this->respond('Plantilla no encontrada');
            return;
        }
        if (($tpl['kind'] ?? '') === 'system') {
            $this->flash('Las plantillas de sistema no se pueden editar.', 'warning', 'RGPD');
            header('Location: ' . $this->areaBaseUrl() . '/rgpd/plantillas/' . (int) $tpl['id']);
            exit;
        }

        $this->render('rgpd.templates.form', [
            'title' => 'RGPD · Editar plantilla',
            'area' => $this->currentArea(),
            'areaBaseUrl' => $this->areaBaseUrl(),
            'baseUrl' => $this->baseUrl(),
            'mode' => 'edit',
            'template' => $tpl,
            'errors' => [],
        ]);
    }

    /** @param array<string, string> $params */
    public function update(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireRgpdAdmin();

        $id = (int) ($params['id'] ?? 0);
        $tpl = $this->findTemplate($id);
        if ($tpl === null) {
            http_response_code(404);
            $this->respond('Plantilla no encontrada');
            return;
        }
        if (($tpl['kind'] ?? '') === 'system') {
            http_response_code(403);
            $this->respond('Plantilla de sistema protegida');
            return;
        }

        [$data, $errors] = $this->validateTemplateInput($_POST);
        if ($errors !== []) {
            $data['id'] = $id;
            $this->render('rgpd.templates.form', [
                'title' => 'RGPD · Editar plantilla',
                'area' => $this->currentArea(),
                'areaBaseUrl' => $this->areaBaseUrl(),
                'baseUrl' => $this->baseUrl(),
                'mode' => 'edit',
                'template' => array_merge($tpl, $data),
                'errors' => $errors,
            ]);
            return;
        }

        $pdo = $this->rgpdPdo();
        $pdo->prepare("
            UPDATE rgpd_templates
            SET name = :name, category = :category, description = :description,
                body_html = :body_html, is_active = :is_active, updated_at = NOW()
            WHERE id = :id AND kind = 'user'
        ")->execute([
            'id' => $id,
            'name' => $data['name'],
            'category' => $data['category'],
            'description' => $data['description'] !== '' ? $data['description'] : null,
            'body_html' => $data['body_html'],
            'is_active' => $data['is_active'] ? 'true' : 'false',
        ]);

        $this->flash('Plantilla actualizada.', 'success', 'RGPD');
        header('Location: ' . $this->areaBaseUrl() . '/rgpd/plantillas/' . $id);
        exit;
    }

    /** @param array<string, string> $params */
    public function destroy(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireRgpdAdmin();

        $id = (int) ($params['id'] ?? 0);
        $pdo = $this->rgpdPdo();
        $stmt = $pdo->prepare("DELETE FROM rgpd_templates WHERE id = :id AND kind = 'user'");
        $stmt->execute(['id' => $id]);

        $this->flash($stmt->rowCount() > 0 ? 'Plantilla eliminada.' : 'No se pudo eliminar (sistema o en uso).', $stmt->rowCount() > 0 ? 'success' : 'warning', 'RGPD');
        header('Location: ' . $this->areaBaseUrl() . '/rgpd/plantillas');
        exit;
    }

    /** @return array<string, mixed>|null */
    private function findTemplate(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $stmt = $this->rgpdPdo()->prepare('SELECT * FROM rgpd_templates WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function validateTemplateInput(array $input): array
    {
        $data = [
            'name' => trim((string) ($input['name'] ?? '')),
            'slug' => trim((string) ($input['slug'] ?? '')),
            'category' => trim((string) ($input['category'] ?? 'consentimiento')),
            'description' => trim((string) ($input['description'] ?? '')),
            'body_html' => trim((string) ($input['body_html'] ?? '')),
            'is_active' => !empty($input['is_active']),
        ];
        $errors = [];
        if ($data['name'] === '') {
            $errors['name'] = 'El nombre es obligatorio.';
        }
        if ($data['body_html'] === '') {
            $errors['body_html'] = 'El contenido HTML es obligatorio.';
        }

        return [$data, $errors];
    }

    private function uniqueSlug(PDO $pdo, string $base): string
    {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $base) ?? 'plantilla');
        $slug = trim($slug, '-') ?: 'plantilla';
        $candidate = $slug;
        $n = 0;
        while (true) {
            $stmt = $pdo->prepare('SELECT 1 FROM rgpd_templates WHERE slug = :s LIMIT 1');
            $stmt->execute(['s' => $candidate]);
            if (!$stmt->fetchColumn()) {
                return $candidate;
            }
            $n++;
            $candidate = $slug . '-' . $n;
        }
    }
}
