<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Database;
use PDO;

final class DashboardController extends Controller
{
    /** /dashboard -> redirige al panel que corresponde al rol */
    public function index(): void
    {
        $this->requireAuth();
        $role = (string) ($_SESSION['user']['role'] ?? '');

        if ($role === 'admin') {
            header('Location: ' . $this->baseUrl() . '/admin/dashboard');
            exit;
        }
        if ($role === 'gestor') {
            header('Location: ' . $this->baseUrl() . '/gestor/dashboard');
            exit;
        }

        header('Location: ' . $this->baseUrl() . '/login');
        exit;
    }

    public function gestor(): void
    {
        $this->assertAreaAccess();
        $data = $this->buildDashboardData('gestor');

        $this->render('dashboard.index', array_merge($data, [
            'title' => 'Panel Gestor',
            'panelHeading' => 'Dashboard Gestor',
            'panelSubheading' => 'Visión de tus comunidades y técnicos asociados.',
        ]));
    }

    public function admin(): void
    {
        $this->assertAreaAccess();
        $data = $this->buildDashboardData('admin');

        $this->render('dashboard.index', array_merge($data, [
            'title' => 'Panel Administración',
            'panelHeading' => 'Dashboard Admin',
            'panelSubheading' => 'Control global y supervisión operativa.',
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDashboardData(string $scope): array
    {
        $pdo = Database::connection();

        $kpiCommunities = 0;
        $kpiTechnicians = 0;
        $kpiApproved = 0;
        $statusMap = [
            'approved' => 0,
            'in_review' => 0,
            'pending_docs' => 0,
            'rejected' => 0,
        ];

        if ($scope === 'admin') {
            $kpiCommunities = (int) $pdo->query(
                "SELECT COUNT(*) FROM communities WHERE is_active = TRUE"
            )->fetchColumn();

            $kpiTechnicians = (int) $pdo->query(
                "SELECT COUNT(*) FROM technicians WHERE is_active = TRUE"
            )->fetchColumn();

            $kpiApproved = (int) $pdo->query(
                "SELECT COUNT(*) FROM cae_records WHERE is_current = TRUE AND status = 'approved'"
            )->fetchColumn();

            $rows = $pdo->query(
                "SELECT status::text AS status, COUNT(*)::int AS total
                 FROM cae_records
                 WHERE is_current = TRUE
                 GROUP BY status"
            )->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $managerCompanyId = $this->currentUserManagerCompanyId($pdo);

            if ($managerCompanyId <= 0) {
                return $this->emptyDashboardData();
            }

            $stmt = $pdo->prepare(
                "SELECT COUNT(*)::int
                 FROM communities
                 WHERE is_active = TRUE AND manager_company_id = :mc"
            );
            $stmt->execute(['mc' => $managerCompanyId]);
            $kpiCommunities = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare(
                "SELECT COUNT(DISTINCT mct.technician_id)::int
                 FROM manager_company_technician mct
                 JOIN technicians t ON t.id = mct.technician_id
                 WHERE mct.manager_company_id = :mc
                   AND mct.status = 'active'
                   AND t.is_active = TRUE"
            );
            $stmt->execute(['mc' => $managerCompanyId]);
            $kpiTechnicians = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare(
                "SELECT COUNT(*)::int
                 FROM cae_records c
                 JOIN manager_company_technician mct ON mct.technician_id = c.technician_id
                 WHERE mct.manager_company_id = :mc
                   AND mct.status = 'active'
                   AND c.is_current = TRUE
                   AND c.status = 'approved'"
            );
            $stmt->execute(['mc' => $managerCompanyId]);
            $kpiApproved = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare(
                "SELECT c.status::text AS status, COUNT(*)::int AS total
                 FROM cae_records c
                 JOIN manager_company_technician mct ON mct.technician_id = c.technician_id
                 WHERE mct.manager_company_id = :mc
                   AND mct.status = 'active'
                   AND c.is_current = TRUE
                 GROUP BY c.status"
            );
            $stmt->execute(['mc' => $managerCompanyId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (array_key_exists($status, $statusMap)) {
                $statusMap[$status] = (int) $row['total'];
            }
        }

        return [
            'kpiCommunities' => $kpiCommunities,
            'kpiTechnicians' => $kpiTechnicians,
            'kpiApproved' => $kpiApproved,
            'chartLabels' => ['Aprobado', 'En revisión', 'Pendiente', 'Rechazado'],
            'chartSeries' => [
                $statusMap['approved'],
                $statusMap['in_review'],
                $statusMap['pending_docs'],
                $statusMap['rejected'],
            ],
        ];
    }

    private function currentUserManagerCompanyId(PDO $pdo): int
    {
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        if ($userId <= 0) {
            return 0;
        }

        $stmt = $pdo->prepare(
            "SELECT manager_company_id
             FROM users
             WHERE id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $userId]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDashboardData(): array
    {
        return [
            'kpiCommunities' => 0,
            'kpiTechnicians' => 0,
            'kpiApproved' => 0,
            'chartLabels' => ['Aprobado', 'En revisión', 'Pendiente', 'Rechazado'],
            'chartSeries' => [0, 0, 0, 0],
        ];
    }
}