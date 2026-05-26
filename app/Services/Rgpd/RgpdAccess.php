<?php
declare(strict_types=1);
namespace App\Services\Rgpd;

use PDO;

final class RgpdAccess
{
    public static function assertCommunity(PDO $pdo, int $communityId, string $role, ?int $managerCompanyId): ?array
    {
        $stmt = $pdo->prepare('SELECT * FROM communities WHERE id = :id AND is_active = TRUE LIMIT 1');
        $stmt->execute(['id' => $communityId]);
        $c = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$c) return null;
        if ($role === 'gestor' && $managerCompanyId !== null
            && (int)$c['manager_company_id'] !== $managerCompanyId) {
            return null;
        }
        return $c;
    }
}