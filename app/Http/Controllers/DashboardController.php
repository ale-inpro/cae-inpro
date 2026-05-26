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
            'baseUrl' => $this->baseUrl(),
        ]));
    }

    public function admin(): void
    {
        $this->assertAreaAccess();

        $baseData = $this->buildDashboardData('admin');
        $opsData  = $this->buildAdminOpsData();

        $this->render('dashboard.admin', array_merge($baseData, $opsData, [
            'title' => 'Panel Administración',
            'panelHeading' => 'Centro Operativo Admin',
            'panelSubheading' => 'Prioriza backlog, detecta bloqueos y entra en acción en un clic.',
            'baseUrl' => $this->baseUrl(),
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
            'approved'     => 0,
            'in_review'    => 0,
            'pending'      => 0,
            'pending_docs' => 0,
            'rejected'     => 0,
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
            'chartLabels' => ['Aprobado', 'En revisión', 'Pendiente', 'Pendiente docs.', 'Rechazado'],
            'chartSeries' => [
                $statusMap['approved'],
                $statusMap['in_review'],
                $statusMap['pending'],
                $statusMap['pending_docs'],
                $statusMap['rejected'],
            ],
        ];
    }

        /**
     * @return array<string,mixed>
     */
    private function buildAdminOpsData(): array
    {
        $pdo = Database::connection();
        $adminId = (int) ($_SESSION['user']['id'] ?? 0);

        // CAE backlog
        $caePending = (int) $pdo->query("
            SELECT COUNT(*)::int
            FROM cae_records
            WHERE is_current = TRUE
              AND status IN ('pending', 'pending_docs', 'in_review')
        ")->fetchColumn();

        $caeOverdue = (int) $pdo->query("
            SELECT COUNT(*)::int
            FROM cae_records
            WHERE is_current = TRUE
              AND status IN ('pending', 'pending_docs', 'in_review')
              AND COALESCE(updated_at, created_at) < NOW() - INTERVAL '7 days'
        ")->fetchColumn();

        // Solicitudes documentos CAE
        $docReqOpen = (int) $pdo->query("
            SELECT COUNT(*)::int
            FROM cae_document_requests
            WHERE status = 'sent'
              AND token_used_at IS NULL
        ")->fetchColumn();

        $docReqExpired = (int) $pdo->query("
            SELECT COUNT(*)::int
            FROM cae_document_requests
            WHERE status = 'sent'
              AND token_used_at IS NULL
              AND token_expires_at IS NOT NULL
              AND token_expires_at < NOW()
        ")->fetchColumn();

        // RL backlog
        $rlPending = (int) $pdo->query("
            SELECT COUNT(*)::int
            FROM rl_requests
            WHERE status = 'pending'
        ")->fetchColumn();

        $rlOverdue = (int) $pdo->query("
            SELECT COUNT(*)::int
            FROM rl_requests
            WHERE status = 'pending'
              AND created_at < NOW() - INTERVAL '5 days'
        ")->fetchColumn();

        // Notificaciones sin leer del admin
        $stmt = $pdo->prepare("
            SELECT COUNT(*)::int
            FROM notifications
            WHERE user_id = :uid
              AND is_read = FALSE
        ");
        $stmt->execute(['uid' => $adminId]);
        $notifUnread = (int) $stmt->fetchColumn();

        $urgentItems = $this->loadAdminUrgentItems($pdo);

        return [
            'opsCards' => [
                [
                    'key' => 'cae',
                    'title' => 'Gestión CAE',
                    'icon' => 'bi-shield-check',
                    'pending' => $caePending,
                    'overdue' => $caeOverdue,
                    'hint' => 'Revisiones pendientes o con documentos incompletos.',
                    'url' => $this->baseUrl() . '/admin/tecnicos?focus=cae_pending',
                ],
                [
                    'key' => 'docreq',
                    'title' => 'Solicitudes de documentos',
                    'icon' => 'bi-envelope-paper',
                    'pending' => $docReqOpen,
                    'overdue' => $docReqExpired,
                    'hint' => 'Solicitudes enviadas sin respuesta del técnico.',
                    'url' => $this->baseUrl() . '/admin/tecnicos?focus=docreq_open',
                ],
                [
                    'key' => 'rl',
                    'title' => 'Informes RL',
                    'icon' => 'bi-file-earmark-medical',
                    'pending' => $rlPending,
                    'overdue' => $rlOverdue,
                    'hint' => 'Solicitudes RL pendientes de atención.',
                    'url' => $this->baseUrl() . '/admin/comunidades?focus=rl_pending',
                ],
                [
                    'key' => 'notif',
                    'title' => 'Notificaciones',
                    'icon' => 'bi-bell',
                    'pending' => $notifUnread,
                    'overdue' => 0,
                    'hint' => 'Eventos operativos sin revisar.',
                    'url' => $this->baseUrl() . '/admin/notificaciones',
                ],
            ],
            'urgentItems' => $urgentItems,
            'opsTotals' => [
                'pending' => $caePending + $docReqOpen + $rlPending + $notifUnread,
                'overdue' => $caeOverdue + $docReqExpired + $rlOverdue,
            ],
        ];
    }

        /**
     * @return array<int,array<string,mixed>>
     */
    private function loadAdminUrgentItems(PDO $pdo): array
    {
        $sql = "
            (
                SELECT
                    'CAE'::text AS item_type,
                    COALESCE(t.display_name, 'Técnico')::text AS item_label,
                    ('Técnico #' || c.technician_id || ' · Estado: ' || c.status::text)::text AS item_detail,
                    GREATEST(0, FLOOR(EXTRACT(EPOCH FROM (NOW() - COALESCE(c.updated_at, c.created_at))) / 86400))::int AS age_days,
                    '/admin/tecnicos/' || c.technician_id || '/cae' AS item_url,
                    CASE
                        WHEN c.status = 'pending_docs' THEN 3
                        WHEN c.status = 'in_review' THEN 2
                        ELSE 1
                    END AS priority
                FROM cae_records c
                JOIN technicians t ON t.id = c.technician_id
                WHERE c.is_current = TRUE
                  AND c.status IN ('pending', 'pending_docs', 'in_review')
                  AND t.is_active = TRUE
            )
            UNION ALL
            (
                SELECT
                    'Solicitud Docs'::text AS item_type,
                    COALESCE(t.display_name, 'Técnico')::text AS item_label,
                    ('Técnico #' || r.technician_id || ' · Solicitud enviada sin respuesta')::text AS item_detail,
                    GREATEST(0, FLOOR(EXTRACT(EPOCH FROM (NOW() - r.created_at)) / 86400))::int AS age_days,
                    '/admin/tecnicos/' || r.technician_id || '/cae' AS item_url,
                    CASE
                        WHEN r.token_expires_at IS NOT NULL AND r.token_expires_at < NOW() THEN 3
                        ELSE 2
                    END AS priority
                FROM cae_document_requests r
                JOIN technicians t ON t.id = r.technician_id
                WHERE r.status = 'sent'
                  AND r.token_used_at IS NULL
                  AND t.is_active = TRUE
            )
            UNION ALL
            (
                SELECT
                    'RL'::text AS item_type,
                    c.name::text AS item_label,
                    ('Comunidad #' || rr.community_id || ' · Solicitud RL pendiente')::text AS item_detail,
                    GREATEST(0, FLOOR(EXTRACT(EPOCH FROM (NOW() - rr.created_at)) / 86400))::int AS age_days,
                    '/admin/comunidades/' || rr.community_id || '#c-rl' AS item_url,
                    2 AS priority
                FROM rl_requests rr
                JOIN communities c ON c.id = rr.community_id
                WHERE rr.status = 'pending'
                  AND c.is_active = TRUE
            )
            ORDER BY priority DESC, age_days DESC
            LIMIT 12
        ";

        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $base = $this->baseUrl();

        return array_map(static function (array $r) use ($base): array {
            return [
                'type' => (string) ($r['item_type'] ?? ''),
                'label' => (string) ($r['item_label'] ?? ''),
                'detail' => (string) ($r['item_detail'] ?? ''),
                'age_days' => (int) ($r['age_days'] ?? 0),
                'priority' => (int) ($r['priority'] ?? 1),
                'url' => $base . (string) ($r['item_url'] ?? '/admin/dashboard'),
            ];
        }, $rows);
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
            'chartLabels' => ['Aprobado', 'En revisión', 'Pendiente', 'Pendiente docs.', 'Rechazado'],
            'chartSeries' => [0, 0, 0, 0, 0],
        ];
    }
}