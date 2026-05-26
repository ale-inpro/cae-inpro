<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rgpd;

use App\Core\Controller;

final class RgpdDashboardController extends Controller
{
    use RgpdControllerTrait;

    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        $this->assertAreaAccess();
        $pdo = $this->rgpdPdo();
        [$role, $mcId] = $this->rgpdAccessContext();
        $scope = $this->communitiesScopeSql($role, $mcId);

        $stats = $pdo->query("
            SELECT
                (SELECT COUNT(*) FROM rgpd_signature_requests s
                 JOIN communities c ON c.id = s.community_id
                 WHERE s.status = 'pending' AND c.is_active = TRUE {$scope}) AS pending_signatures,
                (SELECT COUNT(*) FROM communities c
                 WHERE c.is_active = TRUE {$scope}
                   AND NOT EXISTS (
                       SELECT 1 FROM community_rgpd_contracts rc
                       WHERE rc.community_id = c.id AND rc.status = 'active'
                         AND (rc.expires_at IS NULL OR rc.expires_at >= CURRENT_DATE)
                   )) AS communities_without_contract,
                (SELECT COUNT(*) FROM rgpd_campaigns cp
                 JOIN communities c ON c.id = cp.community_id
                 WHERE cp.created_at >= NOW() - INTERVAL '30 days' AND c.is_active = TRUE {$scope}) AS campaigns_30d
        ")->fetch(\PDO::FETCH_ASSOC) ?: [];

        $recent = $pdo->query("
            SELECT s.id, s.status, s.created_at, c.name AS community_name,
                TRIM(CONCAT_WS(' ', r.nombre, r.apellidos)) AS resident_name,
                t.name AS template_name
            FROM rgpd_signature_requests s
            JOIN communities c ON c.id = s.community_id
            JOIN community_residents r ON r.id = s.resident_id
            JOIN rgpd_templates t ON t.id = s.template_id
            WHERE c.is_active = TRUE {$scope}
            ORDER BY s.created_at DESC
            LIMIT 8
        ")->fetchAll(\PDO::FETCH_ASSOC);

        $this->render('rgpd.dashboard', [
            'title' => 'RGPD · Resumen',
            'area' => $this->currentArea(),
            'areaBaseUrl' => $this->areaBaseUrl(),
            'baseUrl' => $this->baseUrl(),
            'stats' => $stats,
            'recent' => $recent,
        ]);
    }
}
