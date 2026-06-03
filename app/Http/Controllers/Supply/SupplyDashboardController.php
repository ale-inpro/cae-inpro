<?php

declare(strict_types=1);

namespace App\Http\Controllers\Supply;

use App\Core\Controller;
use App\Core\Database;
use PDO;

final class SupplyDashboardController extends Controller
{
    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        $this->assertAreaAccess();

        $pdo = $this->db();
        [$role, $managerCompanyId] = $this->supplyAccessContext($pdo);

        $scopeSql = '';
        if ($role === 'gestor') {
            $scopeSql = $managerCompanyId > 0
                ? ' AND COALESCE(c_comm.manager_company_id, c_res.manager_company_id) = :mcid '
                : ' AND 1=0 ';
        }

        $sql = "
            SELECT
                COUNT(*) FILTER (WHERE sc.status IN ('active', 'pending_renewal'))::int AS active_contracts,
                COUNT(*) FILTER (
                    WHERE sc.status IN ('active', 'pending_renewal')
                      AND sc.end_date IS NOT NULL
                      AND sc.end_date <= (CURRENT_DATE + INTERVAL '60 day')
                )::int AS expiring_60d,
                COUNT(*) FILTER (
                    WHERE sc.scope = 'community' AND sc.status IN ('active', 'pending_renewal')
                )::int AS community_active,
                COUNT(*) FILTER (
                    WHERE sc.scope = 'resident' AND sc.status IN ('active', 'pending_renewal')
                )::int AS resident_active,
                COALESCE(SUM(sc.admin_fee_eur) FILTER (
                    WHERE sc.status IN ('active', 'pending_renewal')
                ), 0)::numeric(12,2) AS monthly_fee_total
            FROM supply_contracts sc
            LEFT JOIN communities c_comm ON c_comm.id = sc.community_id
            LEFT JOIN community_residents r ON r.id = sc.resident_id
            LEFT JOIN communities c_res ON c_res.id = r.community_id
            WHERE COALESCE(c_comm.id, c_res.id) IS NOT NULL
              {$scopeSql}
        ";

        $stmt = $pdo->prepare($sql);
        if ($role === 'gestor' && $managerCompanyId > 0) {
            $stmt->bindValue(':mcid', $managerCompanyId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $countsSql = "
            SELECT
                COUNT(*) FILTER (WHERE sc.status <> 'draft')::int AS total_contracts,
                COUNT(*) FILTER (WHERE sc.status = 'active')::int AS active_count,
                COUNT(*) FILTER (WHERE sc.status = 'pending_renewal')::int AS upcoming_count,
                COUNT(*) FILTER (WHERE sc.status IN ('expired', 'cancelled'))::int AS inactive_count
            FROM supply_contracts sc
            LEFT JOIN communities c_comm ON c_comm.id = sc.community_id
            LEFT JOIN community_residents r ON r.id = sc.resident_id
            LEFT JOIN communities c_res ON c_res.id = r.community_id
            WHERE COALESCE(c_comm.id, c_res.id) IS NOT NULL
            {$scopeSql}
        ";
        $countsStmt = $pdo->prepare($countsSql);
        if ($role === 'gestor' && $managerCompanyId > 0) {
            $countsStmt->bindValue(':mcid', $managerCompanyId, PDO::PARAM_INT);
        }
        $countsStmt->execute();
        $counts = $countsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $companiesTotal = (int) $pdo->query("SELECT COUNT(*) FROM supply_companies WHERE is_active = TRUE")->fetchColumn();

        $chartStatus = [
            'labels' => ['Activos', 'Próximos', 'Bajas'],
            'series' => [
                (int) ($counts['active_count'] ?? 0),
                (int) ($counts['upcoming_count'] ?? 0),
                (int) ($counts['inactive_count'] ?? 0),
            ],
        ];
        $chartScope = [
            'labels' => ['Comunidad', 'Vecino'],
            'series' => [
                (int) ($stats['community_active'] ?? 0),
                (int) ($stats['resident_active'] ?? 0),
            ],
        ];

        $recentSql = "
            SELECT
                sc.id,
                sc.scope,
                sc.supply_type,
                sc.contract_number,
                sc.status,
                sc.end_date,
                COALESCE(c_comm.name, c_res.name) AS community_name,
                COALESCE(
                    NULLIF(TRIM(CONCAT_WS(' ', res.nombre, res.apellidos)), ''),
                    res.full_name
                ) AS resident_name
            FROM supply_contracts sc
            LEFT JOIN communities c_comm ON c_comm.id = sc.community_id
            LEFT JOIN community_residents res ON res.id = sc.resident_id
            LEFT JOIN communities c_res ON c_res.id = res.community_id
            WHERE COALESCE(c_comm.id, c_res.id) IS NOT NULL
              {$scopeSql}
            ORDER BY sc.created_at DESC
            LIMIT 10
        ";
        $recentStmt = $pdo->prepare($recentSql);
        if ($role === 'gestor' && $managerCompanyId > 0) {
            $recentStmt->bindValue(':mcid', $managerCompanyId, PDO::PARAM_INT);
        }
        $recentStmt->execute();
        $recent = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

        $this->render('supply.dashboard', [
            'title' => 'Suministros · Resumen',
            'area' => $this->currentArea(),
            'areaBaseUrl' => $this->areaBaseUrl(),
            'baseUrl' => $this->baseUrl(),
            'stats' => $stats,
            'recent' => $recent,
            'counts' => $counts,
            'companiesTotal' => $companiesTotal,
            'chartStatus' => $chartStatus,
            'chartScope' => $chartScope,
            'isAdmin' => $role === 'admin',
        ]);
    }

    /**
     * @return array{0:string,1:int}
     */
    private function supplyAccessContext(PDO $pdo): array
    {
        $role = (string) ($_SESSION['user']['role'] ?? '');
        if ($role === 'gestor') {
            return ['gestor', $this->currentUserManagerCompanyId($pdo)];
        }
        return ['admin', 0];
    }

    private function currentUserManagerCompanyId(PDO $pdo): int
    {
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        if ($userId <= 0) {
            return 0;
        }
        $stmt = $pdo->prepare('SELECT manager_company_id FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function db(): PDO
    {
        return Database::connection();
    }
}