<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Database;
use PDO;
use App\Services\Mailer;

final class RiskReportController extends Controller
{
    /** @param array<string, string> $params */
    public function show(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->render('risk-reports.show', [
            'title' => 'Informe RL',
            'id' => $params['id'] ?? null,
        ]);
    }

    /** @param array<string, string> $params */
    public function updateStatus(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireAdmin();

        $communityId = (int) ($params['id'] ?? 0);
        if ($communityId <= 0) {
            $this->flash('Comunidad no válida.', 'danger', 'Error');
            header('Location: ' . $this->areaBaseUrl() . '/comunidades');
            exit;
        }

        $status = trim((string) ($_POST['status'] ?? 'pending'));
        $notes = trim((string) ($_POST['notes'] ?? ''));

        $allowed = ['pending', 'in_progress', 'completed', 'rejected'];
        if (!in_array($status, $allowed, true)) {
            $this->flash('Estado RL no válido.', 'warning', 'Aviso');
            header('Location: ' . $this->areaBaseUrl() . '/comunidades/' . $communityId . '#c-rl');
            exit;
        }

        $pdo = Database::connection();
        $this->assertCommunityAccess($pdo, $communityId);

        $stmt = $pdo->prepare("SELECT id FROM community_risk_reports WHERE community_id = :cid LIMIT 1");
        $stmt->execute(['cid' => $communityId]);
        $riskId = (int) ($stmt->fetchColumn() ?: 0);

        $completedAt = ($status === 'completed') ? date('Y-m-d H:i:s') : null;

        if ($riskId > 0) {
            $stmt = $pdo->prepare("
                UPDATE community_risk_reports
                SET status = :status,
                    notes = :notes,
                    completed_at = :completed_at
                WHERE id = :id
            ");
            $stmt->execute([
                'status' => $status,
                'notes' => $notes,
                'completed_at' => $completedAt,
                'id' => $riskId,
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO community_risk_reports
                (community_id, status, notes, completed_at)
                VALUES
                (:cid, :status, :notes, :completed_at)
            ");
            $stmt->execute([
                'cid' => $communityId,
                'status' => $status,
                'notes' => $notes,
                'completed_at' => $completedAt,
            ]);
        }

        // Si el informe pasa a completado, notificar al gestor y cerrar solicitudes en proceso
        if ($status === 'completed') {
            $this->notifyGestorsOfCommunity($pdo, $communityId,
                'rl_report_completed',
                'Informe RL completado',
                'El informe de Riesgos Laborales de tu comunidad ha sido marcado como completado y está listo para descargar.',
                ['community_id' => $communityId]
            );
            $this->sendEmailToGestorsOfCommunity(
                $pdo, $communityId,
                'Informe RL completado · listo para descargar',
                'Informe RL completado',
                'El informe de Riesgos Laborales de tu comunidad ha sido marcado como <strong>completado</strong> y está listo para descargar.'
            );
            $pdo->prepare("
                UPDATE rl_requests
                SET status = 'sent', resolved_at = NOW(), updated_at = NOW()
                WHERE community_id = :cid AND status IN ('requested', 'in_progress')
            ")->execute(['cid' => $communityId]);

        } elseif ($status === 'rejected') {
            $this->notifyGestorsOfCommunity($pdo, $communityId,
                'rl_report_rejected',
                'Informe RL rechazado',
                'El administrador ha cambiado el estado del informe de Riesgos Laborales de tu comunidad a rechazado.',
                ['community_id' => $communityId]
            );
            $this->sendEmailToGestorsOfCommunity(
                $pdo, $communityId,
                'Informe RL marcado como rechazado',
                'Informe RL rechazado',
                'El administrador ha marcado el informe de Riesgos Laborales de tu comunidad como <strong>rechazado</strong>. Contacta con el administrador para más detalles.'
            );
        }

        $this->flash('Estado del informe RL actualizado.', 'success', 'Correcto');
        header('Location: ' . $this->areaBaseUrl() . '/comunidades/' . $communityId . '#c-rl');
        exit;
    }

    /** @param array<string, string> $params */
    public function uploadReport(array $params = []): void
    {
        $this->assertAreaAccess();
        $role = (string) ($_SESSION['user']['role'] ?? '');
        if (!in_array($role, ['admin', 'gestor'], true)) {
            http_response_code(403);
            $this->respond('Acceso denegado');
            exit;
        }

        $communityId = (int) ($params['id'] ?? 0);
        if ($communityId <= 0 || empty($_FILES['report_file'])) {
            $this->flash('Datos incompletos para subir el informe RL.', 'danger', 'Error');
            header('Location: ' . $this->areaBaseUrl() . '/comunidades');
            exit;
        }

        $file = $_FILES['report_file'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->flash('Error al subir el informe RL.', 'danger', 'Error');
            header('Location: ' . $this->areaBaseUrl() . '/comunidades/' . $communityId . '#c-rl');
            exit;
        }

        $size = (int) ($file['size'] ?? 0);
        $maxBytes = 15 * 1024 * 1024;
        if ($size <= 0 || $size > $maxBytes) {
            $this->flash('El informe RL debe ser > 0 y <= 15MB.', 'warning', 'Aviso');
            header('Location: ' . $this->areaBaseUrl() . '/comunidades/' . $communityId . '#c-rl');
            exit;
        }

        $pdo = Database::connection();
        $this->assertCommunityAccess($pdo, $communityId);

        $originalName = (string) ($file['name'] ?? 'informe_rl.pdf');
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName) ?: 'informe_rl.pdf';
        $finalName = uniqid('rl_', true) . '_' . $safeName;

        $targetDir = dirname(__DIR__, 3) . '/public/uploads/risk-reports/' . $communityId;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $fullPath = $targetDir . '/' . $finalName;
        if (!move_uploaded_file((string) $file['tmp_name'], $fullPath)) {
            $this->flash('No se pudo guardar el archivo en el servidor.', 'danger', 'Error');
            header('Location: ' . $this->areaBaseUrl() . '/comunidades/' . $communityId . '#c-rl');
            exit;
        }

        $relativePath = '/uploads/risk-reports/' . $communityId . '/' . $finalName;

        $uploadStatus = ($role === 'gestor') ? 'completed' : 'in_progress';
        $uploadCompletedAt = ($role === 'gestor') ? date('Y-m-d H:i:s') : null;

        $stmt = $pdo->prepare("SELECT id, notes, report_path FROM community_risk_reports WHERE community_id = :cid LIMIT 1");
        $stmt->execute(['cid' => $communityId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $oldPath = (string) ($existing['report_path'] ?? '');
            if ($oldPath !== '') {
                $absoluteOld = dirname(__DIR__, 3) . '/public' . $oldPath;
                if (is_file($absoluteOld)) {
                    @unlink($absoluteOld);
                }
            }
            $stmt = $pdo->prepare("
                UPDATE community_risk_reports
                SET report_filename = :filename,
                    report_path = :path,
                    status = :status,
                    completed_at = :completed_at
                WHERE id = :id
            ");
            $stmt->execute([
                'filename' => $originalName,
                'path' => $relativePath,
                'status' => $uploadStatus,
                'completed_at' => $uploadCompletedAt,
                'id' => (int) $existing['id'],
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO community_risk_reports
                (community_id, status, report_filename, report_path, notes, completed_at)
                VALUES
                (:cid, :status, :filename, :path, :notes, :completed_at)
            ");
            $stmt->execute([
                'cid' => $communityId,
                'status' => $uploadStatus,
                'filename' => $originalName,
                'path' => $relativePath,
                'notes' => '',
                'completed_at' => $uploadCompletedAt,
            ]);
        }

        if ($role === 'admin') {
            $this->notifyGestorsOfCommunity($pdo, $communityId,
                'rl_report_uploaded',
                'Informe RL disponible',
                'El administrador ha subido el informe de Riesgos Laborales de tu comunidad.',
                ['community_id' => $communityId]
            );

            $this->sendEmailToGestorsOfCommunity(
                $pdo,
                $communityId,
                'Informe RL disponible · accede al panel',
                'Informe RL disponible',
                'El administrador ha subido el informe de Riesgos Laborales de tu comunidad. Ya puedes verlo y descargarlo desde el panel.'
            );
        } else {
            $commData = $pdo->prepare("SELECT name FROM communities WHERE id = :id LIMIT 1");
            $commData->execute(['id' => $communityId]);
            $communityName = (string) ($commData->fetchColumn() ?: 'N/D');
            $gestorName = htmlspecialchars((string) ($_SESSION['user']['full_name'] ?? 'Un gestor'));

            $adminIds = $pdo
                ->query("SELECT id FROM users WHERE role = 'admin' AND is_active = TRUE")
                ->fetchAll(PDO::FETCH_COLUMN);

            foreach ($adminIds as $adminId) {
                $this->createNotification(
                    (int) $adminId,
                    'rl_report_uploaded_by_gestor',
                    'Informe RL subido por gestor',
                    'Un gestor ha subido el informe de Riesgos Laborales de la comunidad «' . $communityName . '».',
                    ['community_id' => $communityId]
                );

                $adminData = $pdo->prepare("SELECT email, full_name FROM users WHERE id = :id LIMIT 1");
                $adminData->execute(['id' => (int) $adminId]);
                $adminUser = $adminData->fetch(PDO::FETCH_ASSOC);

                if (!empty($adminUser['email'])) {
                    $body = "
                        <h2>Informe RL subido por gestor</h2>
                        <p>El gestor <strong>{$gestorName}</strong> ha subido el informe de Riesgos Laborales
                        de la comunidad <strong>" . htmlspecialchars($communityName) . "</strong>.</p>
                        <hr class='divider'>
                        <p>Puedes revisarlo en el panel de administración.</p>
                    ";
                    Mailer::send(
                        (string) $adminUser['email'],
                        'Informe RL subido por gestor · ' . $communityName,
                        Mailer::template('Informe RL subido', $body)
                    );
                }
            }
        }

        // Al subir el informe, cerrar TODAS las solicitudes pendientes de esta comunidad
        $pdo->prepare("
            UPDATE rl_requests
            SET status = 'sent', resolved_at = NOW(), updated_at = NOW()
            WHERE community_id = :cid AND status IN ('requested', 'in_progress')
        ")->execute(['cid' => $communityId]);

        $flashMsg = ($role === 'gestor')
            ? 'Informe RL subido correctamente. Ya está disponible para tu comunidad.'
            : 'Informe RL subido correctamente.';
        $this->flash($flashMsg, 'success', 'Correcto');
        header('Location: ' . $this->areaBaseUrl() . '/comunidades/' . $communityId . '#c-rl');
        exit;
    }

    /** @param array<string, string> $params */
    public function downloadReport(array $params = []): void
    {
        $this->assertAreaAccess();

        $communityId = (int) ($params['id'] ?? 0);
        if ($communityId <= 0) {
            http_response_code(404);
            $this->respond('Comunidad no encontrada');
            return;
        }

        $pdo = Database::connection();
        $this->assertCommunityAccess($pdo, $communityId);

        $stmt = $pdo->prepare("
            SELECT report_filename, report_path
            FROM community_risk_reports
            WHERE community_id = :cid
            LIMIT 1
        ");
        $stmt->execute(['cid' => $communityId]);
        $risk = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$risk || empty($risk['report_path'])) {
            http_response_code(404);
            $this->respond('Informe RL no disponible');
            return;
        }

        $storagePath = (string) $risk['report_path'];
        $absolutePath = dirname(__DIR__, 3) . '/public' . $storagePath;

        if (!is_file($absolutePath)) {
            http_response_code(404);
            $this->respond('Archivo no disponible');
            return;
        }

        $filename = (string) ($risk['report_filename'] ?? 'informe_rl.pdf');

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . (string) filesize($absolutePath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: public');

        readfile($absolutePath);
        exit;
    }

    private function requireAdmin(): void
    {
        if (($_SESSION['user']['role'] ?? '') !== 'admin') {
            http_response_code(403);
            $this->respond('Acceso denegado');
            exit;
        }
    }

    private function assertCommunityAccess(PDO $pdo, int $communityId): void
    {
        $role = (string) ($_SESSION['user']['role'] ?? '');
        if ($role === 'admin') {
            $stmt = $pdo->prepare("SELECT id FROM communities WHERE id = :id AND is_active = TRUE LIMIT 1");
            $stmt->execute(['id' => $communityId]);

            if (!(int) $stmt->fetchColumn()) {
                http_response_code(404);
                $this->respond('Comunidad no encontrada');
                exit;
            }
            return;
        }

        $managerCompanyId = $this->currentUserManagerCompanyId($pdo);
        $stmt = $pdo->prepare("
            SELECT id
            FROM communities
            WHERE id = :id
              AND is_active = TRUE
              AND manager_company_id = :mc
            LIMIT 1
        ");
        $stmt->execute([
            'id' => $communityId,
            'mc' => $managerCompanyId,
        ]);

        if (!(int) $stmt->fetchColumn()) {
            http_response_code(403);
            $this->respond('Sin permisos sobre esta comunidad');
            exit;
        }
    }

    private function currentUserManagerCompanyId(PDO $pdo): int
    {
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        if ($userId <= 0) {
            return 0;
        }

        $stmt = $pdo->prepare("SELECT manager_company_id FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $userId]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /** @param array<string, string> $params */
    public function deleteReport(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireAdmin();

        $communityId = (int) ($params['id'] ?? 0);
        if ($communityId <= 0) {
            $this->flash('Comunidad no válida.', 'danger', 'Error');
            header('Location: ' . $this->areaBaseUrl() . '/comunidades');
            exit;
        }

        $pdo = Database::connection();
        $this->assertCommunityAccess($pdo, $communityId);

        // Busca informe actual para poder eliminar archivo físico si existe
        $stmt = $pdo->prepare("
            SELECT id, report_path
            FROM community_risk_reports
            WHERE community_id = :cid
            LIMIT 1
        ");
        $stmt->execute(['cid' => $communityId]);
        $risk = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$risk) {
            $this->flash('No existe informe RL para quitar.', 'warning', 'Aviso');
            header('Location: ' . $this->areaBaseUrl() . '/comunidades/' . $communityId . '#c-rl');
            exit;
        }

        $reportPath = (string) ($risk['report_path'] ?? '');
        if ($reportPath !== '') {
            $absolutePath = dirname(__DIR__, 3) . '/public' . $reportPath;
            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }
        }

        // Mantiene el registro, pero limpia archivo y reinicia estado
        $stmt = $pdo->prepare("
            UPDATE community_risk_reports
            SET report_filename = NULL,
                report_path = NULL,
                status = 'pending',
                completed_at = NULL
            WHERE id = :id
        ");
        $stmt->execute(['id' => (int) $risk['id']]);

        // Notificar al gestor que el informe ha sido eliminado
        $this->notifyGestorsOfCommunity($pdo, $communityId,
            'rl_report_deleted',
            'Informe RL eliminado',
            'El administrador ha eliminado el informe de Riesgos Laborales de tu comunidad. El estado ha vuelto a pendiente.',
            ['community_id' => $communityId]
        );
        $this->sendEmailToGestorsOfCommunity(
            $pdo, $communityId,
            'Informe RL eliminado',
            'Informe RL eliminado',
            'El administrador ha eliminado el archivo del informe de Riesgos Laborales de tu comunidad. El estado ha vuelto a <strong>pendiente</strong>. Si necesitas el informe, puedes solicitarlo de nuevo.'
        );

        $this->flash('Informe RL eliminado correctamente.', 'success', 'Correcto');
        header('Location: ' . $this->areaBaseUrl() . '/comunidades/' . $communityId . '#c-rl');
        exit;
    }

    /** @param array<string, string> $params */
    public function requestFromGestor(array $params = []): void
    {
        $this->assertAreaAccess();

        if (($_SESSION['user']['role'] ?? '') !== 'gestor') {
            http_response_code(403);
            $this->respond('Acceso denegado');
            return;
        }

        $communityId = (int) ($params['id'] ?? 0);
        $notes = trim((string) ($_POST['request_notes'] ?? ''));
        $userId = (int) ($_SESSION['user']['id'] ?? 0);

        if ($communityId <= 0) {
            $this->flash('Comunidad no válida.', 'danger', 'Error');
            header('Location: ' . $this->areaBaseUrl() . '/comunidades');
            exit;
        }

        $pdo = Database::connection();
        $this->assertCommunityAccess($pdo, $communityId);

        // Guardar la solicitud en rl_requests
        $stmt = $pdo->prepare("
            INSERT INTO rl_requests
                (community_id, requested_by_user_id, status, request_notes, requested_at, created_at, updated_at)
            VALUES
                (:cid, :uid, 'requested', :notes, NOW(), NOW(), NOW())
            RETURNING id
        ");
        $stmt->execute([
            'cid'   => $communityId,
            'uid'   => $userId,
            'notes' => $notes !== '' ? $notes : null,
        ]);
        $requestId = (int) $stmt->fetchColumn();

        // Notificar a todos los admins activos
        $adminIds = $pdo
            ->query("SELECT id FROM users WHERE role = 'admin' AND is_active = TRUE")
            ->fetchAll(PDO::FETCH_COLUMN);

        foreach ($adminIds as $adminId) {
            $this->createNotification(
                (int) $adminId,
                'rl_request_created',
                'Nueva solicitud de informe RL',
                'Un gestor ha solicitado el informe de Riesgos Laborales de una comunidad.',
                ['community_id' => $communityId, 'request_id' => $requestId]
            );

            // Email al admin
            $adminData = $pdo->prepare("SELECT email, full_name FROM users WHERE id = :id LIMIT 1");
            $adminData->execute(['id' => (int) $adminId]);
            $adminUser = $adminData->fetch(PDO::FETCH_ASSOC);

            $commData = $pdo->prepare("SELECT name FROM communities WHERE id = :id LIMIT 1");
            $commData->execute(['id' => $communityId]);
            $communityName = (string) ($commData->fetchColumn() ?: 'N/D');

            $gestorName = htmlspecialchars((string) ($_SESSION['user']['full_name'] ?? 'Un gestor'));

            if (!empty($adminUser['email'])) {
                $body = "
                    <h2>Nueva solicitud de informe RL</h2>
                    <p>El gestor <strong>{$gestorName}</strong> ha solicitado el informe de Riesgos Laborales
                    de la comunidad <strong>" . htmlspecialchars($communityName) . "</strong>.</p>
                    " . ($notes !== '' ? "<p><em>Notas: " . htmlspecialchars($notes) . "</em></p>" : '') . "
                    <hr class='divider'>
                    <p>Accede al panel de administración para gestionar esta solicitud.</p>
                ";
                Mailer::send(
                    (string) $adminUser['email'],
                    'Nueva solicitud de informe RL · ' . $communityName,
                    Mailer::template('Nueva solicitud de informe RL', $body)
                );
            }
        }

        $this->flash('Solicitud enviada al administrador correctamente.', 'success', 'Solicitud enviada');
        header('Location: ' . $this->areaBaseUrl() . '/comunidades/' . $communityId . '#c-rl');
        exit;
    }

        /**
     * Rechaza una solicitud RL individual (solo admin).
     * @param array<string, string> $params
     */
    public function rejectRequest(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireAdmin();

        $requestId   = (int) ($params['requestId'] ?? 0);
        $communityId = (int) ($params['id'] ?? 0);

        if ($requestId <= 0 || $communityId <= 0) {
            $this->flash('Solicitud no válida.', 'danger', 'Error');
            header('Location: ' . $this->areaBaseUrl() . '/comunidades/' . $communityId . '#c-rl');
            exit;
        }

        $pdo = Database::connection();

        // Verificar que la solicitud pertenece a esta comunidad
        $stmt = $pdo->prepare("
            SELECT id, requested_by_user_id
            FROM rl_requests
            WHERE id = :rid AND community_id = :cid
            LIMIT 1
        ");
        $stmt->execute(['rid' => $requestId, 'cid' => $communityId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            $this->flash('Solicitud no encontrada.', 'warning', 'Aviso');
            header('Location: ' . $this->areaBaseUrl() . '/comunidades/' . $communityId . '#c-rl');
            exit;
        }

        // Marcar como rechazada
        $pdo->prepare("
            UPDATE rl_requests
            SET status = 'rejected', resolved_at = NOW(), updated_at = NOW()
            WHERE id = :rid
        ")->execute(['rid' => $requestId]);

        // Notificar al gestor que la solicitó
        $gestorId = (int) ($request['requested_by_user_id'] ?? 0);
        if ($gestorId > 0) {
            $this->createNotification(
                $gestorId,
                'rl_request_rejected',
                'Solicitud de informe RL rechazada',
                'El administrador ha rechazado tu solicitud de informe de Riesgos Laborales.',
                ['community_id' => $communityId, 'request_id' => $requestId]
            );

            // Email al gestor que hizo la solicitud
            $gestorData = $pdo->prepare("SELECT email, full_name FROM users WHERE id = :id LIMIT 1");
            $gestorData->execute(['id' => $gestorId]);
            $gestorUser = $gestorData->fetch(PDO::FETCH_ASSOC);

            $commData2 = $pdo->prepare("SELECT name FROM communities WHERE id = :id LIMIT 1");
            $commData2->execute(['id' => $communityId]);
            $commName = (string) ($commData2->fetchColumn() ?: 'N/D');

            if (!empty($gestorUser['email'])) {
                $nombre = htmlspecialchars((string) ($gestorUser['full_name'] ?? 'Gestor'));
                $body = "
                    <h2>Solicitud de informe RL rechazada</h2>
                    <p>Hola, <strong>{$nombre}</strong>.</p>
                    <p>Tu solicitud del informe de Riesgos Laborales para la comunidad
                    <strong>" . htmlspecialchars($commName) . "</strong> ha sido <strong>rechazada</strong>
                    por el administrador.</p>
                    <p>Si tienes dudas, contacta directamente con el administrador.</p>
                ";
                Mailer::send(
                    (string) $gestorUser['email'],
                    'Solicitud de informe RL rechazada · ' . $commName,
                    Mailer::template('Solicitud rechazada', $body)
                );
            }
        }

        $this->flash('Solicitud rechazada y gestor notificado.', 'success', 'Hecho');
        header('Location: ' . $this->areaBaseUrl() . '/comunidades/' . $communityId . '#c-rl');
        exit;
    }

    /**
     * Busca todos los usuarios gestores de la empresa que gestiona esta comunidad
     * y les envía una notificación.
     * @param array<string, mixed>|null $payload
     */
    private function notifyGestorsOfCommunity(
        \PDO $pdo,
        int $communityId,
        string $type,
        string $title,
        string $message,
        ?array $payload = null
    ): void {
        // Obtener el manager_company_id de la comunidad
        $stmt = $pdo->prepare("SELECT manager_company_id FROM communities WHERE id = :cid LIMIT 1");
        $stmt->execute(['cid' => $communityId]);
        $managerCompanyId = (int) ($stmt->fetchColumn() ?: 0);

        if ($managerCompanyId <= 0) {
            return;
        }

        // Buscar todos los gestores activos de esa empresa
        $stmt = $pdo->prepare("
            SELECT id FROM users
            WHERE manager_company_id = :mc
              AND role = 'gestor'
              AND is_active = TRUE
        ");
        $stmt->execute(['mc' => $managerCompanyId]);
        $gestorIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($gestorIds as $gestorId) {
            $this->createNotification((int) $gestorId, $type, $title, $message, $payload);
        }
    }

        /**
     * Envía un email a todos los gestores activos de la empresa
     * que gestiona la comunidad indicada.
     */
    private function sendEmailToGestorsOfCommunity(
        \PDO $pdo,
        int $communityId,
        string $subject,
        string $emailTitle,
        string $bodyText
    ): void {
        $stmt = $pdo->prepare("SELECT manager_company_id FROM communities WHERE id = :cid LIMIT 1");
        $stmt->execute(['cid' => $communityId]);
        $mcId = (int) ($stmt->fetchColumn() ?: 0);

        if ($mcId <= 0) {
            return;
        }

        $stmt = $pdo->prepare("
            SELECT email, full_name
            FROM users
            WHERE manager_company_id = :mc
              AND role = 'gestor'
              AND is_active = TRUE
              AND email IS NOT NULL
        ");
        $stmt->execute(['mc' => $mcId]);
        $gestors = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($gestors as $g) {
            if (empty($g['email'])) {
                continue;
            }
            $nombre   = htmlspecialchars((string) ($g['full_name'] ?? 'Gestor'));
            $fullBody = "<h2>{$emailTitle}</h2><p>Hola, <strong>{$nombre}</strong>.</p><p>{$bodyText}</p>";

            Mailer::send(
                (string) $g['email'],
                $subject,
                Mailer::template($emailTitle, $fullBody)
            );
        }
    }
}