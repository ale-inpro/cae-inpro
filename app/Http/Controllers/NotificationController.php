<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Database;
use PDO;

final class NotificationController extends Controller
{
    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        $this->requireAuth();
        $userId = (int) ($_SESSION['user']['id'] ?? 0);

        $pdo  = Database::connection();
        $stmt = $pdo->prepare("
            SELECT id, type, title, message, payload_json, is_read, created_at
            FROM notifications
            WHERE user_id = :uid
            ORDER BY created_at DESC
            LIMIT 50
        ");
        $stmt->execute(['uid' => $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->render('notifications.index', [
            'title'         => 'Notificaciones',
            'notifications' => $rows,
            'areaBaseUrl'   => $this->areaBaseUrl(),
        ]);
    }

    /** @param array<string, string> $params */
    public function markAllRead(array $params = []): void
    {
        $this->requireAuth();
        $userId = (int) ($_SESSION['user']['id'] ?? 0);

        $pdo  = Database::connection();
        $pdo->prepare("
            UPDATE notifications
            SET is_read = TRUE, read_at = NOW()
            WHERE user_id = :uid AND is_read = FALSE
        ")->execute(['uid' => $userId]);

        $this->flash('Notificaciones marcadas como leídas.', 'success', 'Correcto');
        header('Location: ' . $this->areaBaseUrl() . '/notificaciones');
        exit;
    }

    /** @param array<string, string> $params */
    public function markOneRead(array $params = []): void
    {
        $this->requireAuth();
        $userId  = (int) ($_SESSION['user']['id'] ?? 0);
        $notifId = (int) ($params['notifId'] ?? 0);

        if ($notifId > 0) {
            $pdo = Database::connection();
            $pdo->prepare("
                UPDATE notifications
                SET is_read = TRUE, read_at = NOW()
                WHERE id = :id AND user_id = :uid
            ")->execute(['id' => $notifId, 'uid' => $userId]);
        }

        header('Location: ' . $this->areaBaseUrl() . '/notificaciones');
        exit;
    }

    /**
     * Marca la notificación como leída y redirige a su destino contextual.
     * Marca como leída y redirige según tipo (admin y gestor).
     * @param array<string, string> $params
     */
    public function openNotification(array $params = []): void
    {
        $this->requireAuth();
        $userId  = (int) ($_SESSION['user']['id'] ?? 0);
        $notifId = (int) ($params['notifId'] ?? 0);

        $fallback = $this->areaBaseUrl() . '/notificaciones';

        if ($notifId <= 0) {
            header('Location: ' . $fallback);
            exit;
        }

        $pdo  = Database::connection();
        $stmt = $pdo->prepare("
            SELECT type, payload_json, is_read
            FROM notifications
            WHERE id = :id AND user_id = :uid
            LIMIT 1
        ");
        $stmt->execute(['id' => $notifId, 'uid' => $userId]);
        $notif = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$notif) {
            header('Location: ' . $fallback);
            exit;
        }

        // Marcar siempre como leída (evita problemas con booleans de PostgreSQL)
        $pdo->prepare("
            UPDATE notifications SET is_read = TRUE WHERE id = :id AND user_id = :uid
        ")->execute(['id' => $notifId, 'uid' => $userId]);

        $role    = (string) ($_SESSION['user']['role'] ?? '');
        $payload = json_decode((string) ($notif['payload_json'] ?? '{}'), true) ?? [];
        $communityId = (int) ($payload['community_id'] ?? 0);
        $technicianId = (int) ($payload['technician_id'] ?? 0);
        $type        = (string) ($notif['type'] ?? '');
        $base = $this->baseUrl();

        if ($role === 'admin') {
            if ($type === 'technician_association_requested') {
                header('Location: ' . $base . '/admin/tecnicos/solicitudes');
                exit;
            }
            if ($type === 'technician_created_by_gestor' && $technicianId > 0) {
                header('Location: ' . $base . '/admin/tecnicos/' . $technicianId);
                exit;
            }
            if ($communityId > 0) {
                if (in_array($type, ['rl_request_created', 'rl_report_uploaded_by_gestor'], true)) {
                    header('Location: ' . $base . '/admin/comunidades/' . $communityId . '#c-rl');
                    exit;
                }
                if ($type === 'community_tech_not_preferred') {
                    header('Location: ' . $base . '/admin/comunidades/' . $communityId . '#c-tech');
                    exit;
                }
            }
        }

        if ($role === 'gestor') {
            if ($type === 'technician_association_approved' && $technicianId > 0) {
                header('Location: ' . $base . '/gestor/tecnicos/' . $technicianId);
                exit;
            }
            if ($type === 'technician_association_rejected') {
                header('Location: ' . $base . '/gestor/tecnicos/vincular');
                exit;
            }
        }

        header('Location: ' . $fallback);
        exit;
    }

    /**
     * Endpoint JSON para polling: devuelve contador de no leídas
     * y las 8 notificaciones más recientes para actualizar el dropdown.
     * @param array<string, string> $params
     */
    public function pollData(array $params = []): void
    {
        $this->requireAuth();
        $userId = (int) ($_SESSION['user']['id'] ?? 0);

        $pdo = Database::connection();

        $stmtC = $pdo->prepare("
            SELECT COUNT(*) FROM notifications
            WHERE user_id = :uid AND is_read = FALSE
        ");
        $stmtC->execute(['uid' => $userId]);
        $unread = (int) $stmtC->fetchColumn();

        $stmtN = $pdo->prepare("
            SELECT id, type, title, message, payload_json, is_read, created_at
            FROM notifications
            WHERE user_id = :uid
            ORDER BY created_at DESC
            LIMIT 8
        ");
        $stmtN->execute(['uid' => $userId]);
        $items = $stmtN->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as &$item) {
            $item['is_read']     = $this->boolFromPg($item['is_read']);
            $item['created_fmt'] = app_datetime($item['created_at'] ?? null);
            $item['payload'] = !empty($item['payload_json'])
                ? json_decode((string) $item['payload_json'], true)
                : null;
            unset($item['payload_json']);
        }
        unset($item);

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['unread' => $unread, 'items' => $items]);
        exit;
    }

    /** @param array<string, string> $params */
    public function deleteOne(array $params = []): void
    {
        $this->requireAuth();
        $userId  = (int) ($_SESSION['user']['id'] ?? 0);
        $notifId = (int) ($params['notifId'] ?? 0);

        if ($notifId > 0) {
            $pdo = Database::connection();
            $pdo->prepare("
                DELETE FROM notifications
                WHERE id = :id AND user_id = :uid
            ")->execute(['id' => $notifId, 'uid' => $userId]);
        }

        header('Location: ' . $this->areaBaseUrl() . '/notificaciones');
        exit;
    }

    /** @param array<string, string> $params */
    public function deleteAll(array $params = []): void
    {
        $this->requireAuth();
        $userId = (int) ($_SESSION['user']['id'] ?? 0);

        $pdo = Database::connection();
        $pdo->prepare("
            DELETE FROM notifications WHERE user_id = :uid
        ")->execute(['uid' => $userId]);

        $this->flash('Todas las notificaciones eliminadas.', 'success', 'Correcto');
        header('Location: ' . $this->areaBaseUrl() . '/notificaciones');
        exit;
    }
}