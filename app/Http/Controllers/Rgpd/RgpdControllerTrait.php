<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rgpd;

use App\Core\Database;
use PDO;

trait RgpdControllerTrait
{
    protected function rgpdRole(): string
    {
        return (string) ($_SESSION['user']['role'] ?? '');
    }

    protected function rgpdPdo(): PDO
    {
        return Database::connection();
    }

    protected function rgpdManagerCompanyId(PDO $pdo): ?int
    {
        if ($this->rgpdRole() !== 'gestor') {
            return null;
        }

        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        if ($userId <= 0) {
            return 0;
        }

        $stmt = $pdo->prepare('SELECT manager_company_id FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    protected function requireRgpdAdmin(): void
    {
        if ($this->rgpdRole() !== 'admin') {
            http_response_code(403);
            $this->respond('Solo administración puede gestionar plantillas personalizadas.');
            exit;
        }
    }

    /** @return array{0: string, 1: ?int} */
    protected function rgpdAccessContext(): array
    {
        $pdo = $this->rgpdPdo();

        return [$this->rgpdRole(), $this->rgpdManagerCompanyId($pdo)];
    }

    protected function communitiesScopeSql(string $role, ?int $managerCompanyId): string
    {
        if ($role === 'gestor' && $managerCompanyId) {
            return ' AND c.manager_company_id = ' . (int) $managerCompanyId;
        }

        return '';
    }
}
