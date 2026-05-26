<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Database;
use PDO;

final class TechnicianAssociationController extends Controller
{
    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireAdmin();

        $pdo = Database::connection();
        $status = trim((string) ($_GET['status'] ?? 'pending'));
        if (!in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
            $status = 'pending';
        }

        $where = $status === 'all' ? '' : ' AND r.status = :st';
        $sql = "
            SELECT
                r.id,
                r.status,
                r.gestor_notes,
                r.admin_notes,
                r.created_at,
                r.reviewed_at,
                t.id AS technician_id,
                t.display_name,
                t.tax_id,
                t.entity_type,
                t.professions,
                u.full_name AS requester_name,
                u.email AS requester_email,
                r.manager_company_id
            FROM technician_association_requests r
            JOIN technicians t ON t.id = r.technician_id
            JOIN users u ON u.id = r.requested_by_user_id
            WHERE 1=1 {$where}
            ORDER BY r.created_at DESC
            LIMIT 100
        ";
        $stmt = $pdo->prepare($sql);
        if ($status !== 'all') {
            $stmt->execute(['st' => $status]);
        } else {
            $stmt->execute();
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $pendingCount = (int) $pdo->query("
            SELECT COUNT(*) FROM technician_association_requests WHERE status = 'pending'
        ")->fetchColumn();

        $listVersion = (string) $pdo->query("
            SELECT COALESCE(MAX(updated_at)::text, '0') FROM technician_association_requests
        ")->fetchColumn();

        $this->render('technician-associations.index', [
            'title' => 'Solicitudes de asociación',
            'areaBaseUrl' => $this->areaBaseUrl(),
            'requests' => $rows,
            'statusFilter' => $status,
            'pendingCount' => $pendingCount,
            'listVersion' => $listVersion,
        ]);
    }

    /** @param array<string, string> $params */
    public function approve(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireAdmin();
        $this->resolveRequest($params, true);
    }

    /** @param array<string, string> $params */
    public function reject(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireAdmin();
        $this->resolveRequest($params, false);
    }

        /**
     * JSON para polling: contador pendientes + versión de lista (cualquier cambio en solicitudes).
     * @param array<string, string> $params
     */
    public function syncPoll(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireAdmin();

        $pdo = Database::connection();

        $pendingCount = (int) $pdo->query("
            SELECT COUNT(*) FROM technician_association_requests WHERE status = 'pending'
        ")->fetchColumn();

        $listVersion = (string) $pdo->query("
            SELECT COALESCE(MAX(updated_at)::text, '0') FROM technician_association_requests
        ")->fetchColumn();

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode([
            'pending_count' => $pendingCount,
            'list_version' => $listVersion,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** @param array<string, string> $params */
    private function resolveRequest(array $params, bool $approve): void
    {
        $requestId = (int) ($params['id'] ?? 0);
        $adminNotes = trim((string) ($_POST['admin_notes'] ?? ''));
        $adminId = (int) ($_SESSION['user']['id'] ?? 0);
        $returnTo = $this->areaBaseUrl() . '/tecnicos/solicitudes';

        if ($requestId <= 0) {
            $this->flash('Solicitud no válida.', 'danger', 'Error');
            header('Location: ' . $returnTo);
            exit;
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                SELECT r.id, r.status, r.technician_id, r.manager_company_id, r.requested_by_user_id,
                       t.display_name, t.is_active
                FROM technician_association_requests r
                JOIN technicians t ON t.id = r.technician_id
                WHERE r.id = :id
                FOR UPDATE
            ");
            $stmt->execute(['id' => $requestId]);
            $req = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$req || ($req['status'] ?? '') !== 'pending') {
                $pdo->rollBack();
                $this->flash('La solicitud no existe o ya fue resuelta.', 'warning', 'Aviso');
                header('Location: ' . $returnTo);
                exit;
            }

            if (!$approve && $adminNotes === '') {
                $pdo->rollBack();
                $this->flash('Indica un motivo de rechazo.', 'warning', 'Aviso');
                header('Location: ' . $returnTo . '?focus=' . $requestId);
                exit;
            }

            if (empty($req['is_active']) || !$this->boolFromPg($req['is_active'])) {
                $pdo->rollBack();
                $this->flash('El técnico está desactivado globalmente.', 'danger', 'Error');
                header('Location: ' . $returnTo);
                exit;
            }

            $techId = (int) $req['technician_id'];
            $mcId = (int) $req['manager_company_id'];
            $gestorId = (int) $req['requested_by_user_id'];
            $techName = trim((string) ($req['display_name'] ?? 'Técnico'));

            if ($approve) {
                $this->upsertManagerCompanyTechnician($pdo, $mcId, $techId);
                $newStatus = 'approved';
                $notifType = 'technician_association_approved';
                $notifTitle = 'Asociación de técnico aprobada';
                $notifMsg = 'El administrador ha aprobado la vinculación del técnico «' . $techName . '» a tu cartera.';
            } else {
                $newStatus = 'rejected';
                $notifType = 'technician_association_rejected';
                $notifTitle = 'Asociación de técnico rechazada';
                $notifMsg = 'El administrador ha rechazado la vinculación del técnico «' . $techName . '».';
                if ($adminNotes !== '') {
                    $notifMsg .= ' Motivo: «' . $adminNotes . '».';
                }
            }

            $pdo->prepare("
                UPDATE technician_association_requests
                SET status = :st,
                    admin_notes = :notes,
                    reviewed_by_user_id = :uid,
                    reviewed_at = NOW(),
                    updated_at = NOW()
                WHERE id = :id
            ")->execute([
                'st' => $newStatus,
                'notes' => $adminNotes !== '' ? $adminNotes : null,
                'uid' => $adminId,
                'id' => $requestId,
            ]);

            $pdo->commit();

            if ($gestorId > 0) {
                $this->createNotification(
                    $gestorId,
                    $notifType,
                    $notifTitle,
                    $notifMsg,
                    ['technician_id' => $techId, 'request_id' => $requestId]
                );
            }

            $this->flash(
                $approve ? 'Solicitud aprobada. El técnico ya está en la cartera del gestor.' : 'Solicitud rechazada.',
                $approve ? 'success' : 'warning',
                $approve ? 'Correcto' : 'Aviso'
            );
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->flash('No se pudo procesar la solicitud.', 'danger', 'Error');
        }

        header('Location: ' . $returnTo);
        exit;
    }

    private function upsertManagerCompanyTechnician(PDO $pdo, int $managerCompanyId, int $technicianId): void
    {
        $stmt = $pdo->prepare("
            SELECT id FROM manager_company_technician
            WHERE manager_company_id = :mc AND technician_id = :tid
            LIMIT 1
        ");
        $stmt->execute(['mc' => $managerCompanyId, 'tid' => $technicianId]);
        $linkId = (int) ($stmt->fetchColumn() ?: 0);

        if ($linkId > 0) {
            $pdo->prepare("
                UPDATE manager_company_technician
                SET status = 'active', updated_at = NOW()
                WHERE id = :id
            ")->execute(['id' => $linkId]);
        } else {
            $pdo->prepare("
                INSERT INTO manager_company_technician
                (manager_company_id, technician_id, status, created_at, updated_at)
                VALUES (:mc, :tid, 'active', NOW(), NOW())
            ")->execute(['mc' => $managerCompanyId, 'tid' => $technicianId]);
        }
    }

    private function requireAdmin(): void
    {
        if (($_SESSION['user']['role'] ?? '') !== 'admin') {
            http_response_code(403);
            $this->respond('Acceso denegado');
            exit;
        }
    }
}