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
        $pdo = Database::connection();
        $mcId = $this->currentUserManagerCompanyId($pdo);

        $baseData = $this->buildDashboardData('gestor');
        $opsData = $mcId > 0 ? $this->buildGestorOpsData($pdo, $mcId) : [
            'opsCards' => [],
            'opsTotals' => ['pending' => 0, 'overdue' => 0],
            'urgentItems' => [],
            'chartActivity' => ['labels' => [], 'series' => []],
        ];

        $this->render('dashboard.index', array_merge($baseData, $opsData, [
            'title' => 'Panel Gestor · CAE',
            'panelHeading' => 'Dashboard CAE',
            'panelSubheading' => 'Estado de certificados, revisiones pendientes y solicitudes de documentación.',
            'baseUrl' => $this->baseUrl(),
            'areaPrefix' => '/gestor',
            'completionRate' => $this->caeCompletionRate($baseData['chartSeries'] ?? []),
        ]));
    }

    public function admin(): void
    {
        $this->assertAreaAccess();

        $baseData = $this->buildDashboardData('admin');
        $opsData = $this->buildAdminOpsData();
        $opsData['chartActivity'] = $this->buildCaeActivityChart(Database::connection(), null);

        $this->render('dashboard.admin', array_merge($baseData, $opsData, [
            'title' => 'Panel Administración · CAE',
            'panelHeading' => 'Dashboard CAE',
            'panelSubheading' => 'Visión global del cumplimiento CAE, revisiones y documentación pendiente.',
            'baseUrl' => $this->baseUrl(),
            'areaPrefix' => '/admin',
            'completionRate' => $this->caeCompletionRate($baseData['chartSeries'] ?? []),
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
            'kpiInReview' => $statusMap['in_review'],
            'kpiPendingDocs' => $statusMap['pending_docs'],
            'kpiCaeOpen' => $statusMap['pending'] + $statusMap['in_review'] + $statusMap['pending_docs'],
            'kpiRejected' => $statusMap['rejected'],
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
                    'key' => 'notif',
                    'title' => 'Notificaciones',
                    'icon' => 'bi-bell',
                    'pending' => $notifUnread,
                    'overdue' => 0,
                    'hint' => 'Avisos CAE sin revisar.',
                    'url' => $this->baseUrl() . '/admin/notificaciones',
                ],
            ],
            'urgentItems' => $urgentItems,
            'opsTotals' => [
                'pending' => $caePending + $docReqOpen + $notifUnread,
                'overdue' => $caeOverdue + $docReqExpired,
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
            'kpiInReview' => 0,
            'kpiPendingDocs' => 0,
            'kpiCaeOpen' => 0,
            'kpiRejected' => 0,
            'chartLabels' => ['Aprobado', 'En revisión', 'Pendiente', 'Pendiente docs.', 'Rechazado'],
            'chartSeries' => [0, 0, 0, 0, 0],
        ];
    }

    /**
     * @param list<int> $chartSeries
     */
    private function caeCompletionRate(array $chartSeries): int
    {
        $approved = (int) ($chartSeries[0] ?? 0);
        $total = array_sum(array_map('intval', $chartSeries));

        return $total > 0 ? (int) round(($approved / $total) * 100) : 0;
    }

    /**
     * @return array{labels: list<string>, series: list<int>}
     */
    private function buildCaeActivityChart(PDO $pdo, ?int $managerCompanyId): array
    {
        $params = [];
        $scopeSql = '';

        if ($managerCompanyId !== null && $managerCompanyId > 0) {
            $scopeSql = "
                AND EXISTS (
                    SELECT 1
                    FROM manager_company_technician mct
                    WHERE mct.technician_id = c.technician_id
                    AND mct.manager_company_id = :mc
                    AND mct.status = 'active'
                )
            ";
            $params['mc'] = $managerCompanyId;
        }

        $stmt = $pdo->prepare("
            SELECT DATE(c.created_at) AS day, COUNT(*)::int AS total
            FROM cae_records c
            WHERE c.is_current = TRUE
            AND c.created_at >= CURRENT_DATE - INTERVAL '13 days'
            {$scopeSql}
            GROUP BY DATE(c.created_at)
            ORDER BY day
        ");
        $stmt->execute($params);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(string) ($row['day'] ?? '')] = (int) ($row['total'] ?? 0);
        }

        $labels = [];
        $series = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = (new \DateTimeImmutable('today'))->modify("-{$i} days")->format('Y-m-d');
            $labels[] = (new \DateTimeImmutable($day))->format('d/m');
            $series[] = $map[$day] ?? 0;
        }

        return ['labels' => $labels, 'series' => $series];
    }

    /**
     * @return array<string,mixed>
     */
    private function buildGestorOpsData(PDO $pdo, int $managerCompanyId): array
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)::int
            FROM cae_records c
            JOIN manager_company_technician mct ON mct.technician_id = c.technician_id
            WHERE mct.manager_company_id = :mc
            AND mct.status = 'active'
            AND c.is_current = TRUE
            AND c.status IN ('pending', 'pending_docs', 'in_review')
        ");
        $stmt->execute(['mc' => $managerCompanyId]);
        $caePending = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT COUNT(*)::int
            FROM cae_records c
            JOIN manager_company_technician mct ON mct.technician_id = c.technician_id
            WHERE mct.manager_company_id = :mc
            AND mct.status = 'active'
            AND c.is_current = TRUE
            AND c.status IN ('pending', 'pending_docs', 'in_review')
            AND COALESCE(c.updated_at, c.created_at) < NOW() - INTERVAL '7 days'
        ");
        $stmt->execute(['mc' => $managerCompanyId]);
        $caeOverdue = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT COUNT(*)::int
            FROM cae_document_requests r
            JOIN manager_company_technician mct ON mct.technician_id = r.technician_id
            WHERE mct.manager_company_id = :mc
              AND mct.status = 'active'
              AND r.status = 'sent'
              AND r.token_used_at IS NULL
        ");
        $stmt->execute(['mc' => $managerCompanyId]);
        $docReqOpen = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT COUNT(*)::int
            FROM cae_document_requests r
            JOIN manager_company_technician mct ON mct.technician_id = r.technician_id
            WHERE mct.manager_company_id = :mc
              AND mct.status = 'active'
              AND r.status = 'sent'
              AND r.token_used_at IS NULL
              AND r.token_expires_at IS NOT NULL
              AND r.token_expires_at < NOW()
        ");
        $stmt->execute(['mc' => $managerCompanyId]);
        $docReqExpired = (int) $stmt->fetchColumn();

        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $stmt = $pdo->prepare("
            SELECT COUNT(*)::int
            FROM notifications
            WHERE user_id = :uid AND is_read = FALSE
        ");
        $stmt->execute(['uid' => $userId]);
        $notifUnread = (int) $stmt->fetchColumn();

        $base = $this->baseUrl();

        return [
            'opsCards' => [
                [
                    'key' => 'cae',
                    'title' => 'Gestión CAE',
                    'icon' => 'bi-shield-check',
                    'pending' => $caePending,
                    'overdue' => $caeOverdue,
                    'hint' => 'CAE de tus técnicos en revisión o pendientes.',
                    'url' => $base . '/gestor/tecnicos?focus=cae_pending',
                ],
                [
                    'key' => 'docreq',
                    'title' => 'Solicitudes de documentos',
                    'icon' => 'bi-envelope-paper',
                    'pending' => $docReqOpen,
                    'overdue' => $docReqExpired,
                    'hint' => 'Peticiones de documentación CAE sin respuesta.',
                    'url' => $base . '/gestor/tecnicos?focus=docreq_open',
                ],
                [
                    'key' => 'notif',
                    'title' => 'Notificaciones',
                    'icon' => 'bi-bell',
                    'pending' => $notifUnread,
                    'overdue' => 0,
                    'hint' => 'Avisos CAE sin leer.',
                    'url' => $base . '/gestor/notificaciones',
                ],
            ],
            'opsTotals' => [
                'pending' => $caePending + $docReqOpen + $notifUnread,
                'overdue' => $caeOverdue + $docReqExpired,
            ],
            'urgentItems' => $this->loadGestorUrgentItems($pdo, $managerCompanyId),
            'chartActivity' => $this->buildCaeActivityChart($pdo, $managerCompanyId),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function loadGestorUrgentItems(PDO $pdo, int $managerCompanyId): array
    {
        $stmt = $pdo->prepare("
            (
                SELECT
                    'CAE'::text AS item_type,
                    COALESCE(t.display_name, 'Técnico')::text AS item_label,
                    ('Estado: ' || c.status::text)::text AS item_detail,
                    GREATEST(0, FLOOR(EXTRACT(EPOCH FROM (NOW() - COALESCE(c.updated_at, c.created_at))) / 86400))::int AS age_days,
                    '/gestor/tecnicos/' || c.technician_id || '/cae' AS item_url,
                    2 AS priority
                FROM cae_records c
                JOIN technicians t ON t.id = c.technician_id
                JOIN manager_company_technician mct ON mct.technician_id = c.technician_id
                WHERE mct.manager_company_id = :mc
                AND mct.status = 'active'
                AND c.is_current = TRUE
                AND c.status IN ('pending', 'pending_docs', 'in_review')
            )
            ORDER BY priority DESC, age_days DESC
            LIMIT 8
        ");
        $stmt->execute(['mc' => $managerCompanyId]);
        $base = $this->baseUrl();

        return array_map(static function (array $row) use ($base): array {
            return [
                'type' => (string) ($row['item_type'] ?? ''),
                'label' => (string) ($row['item_label'] ?? ''),
                'detail' => (string) ($row['item_detail'] ?? ''),
                'age_days' => (int) ($row['age_days'] ?? 0),
                'url' => $base . (string) ($row['item_url'] ?? '/gestor/dashboard'),
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }
}