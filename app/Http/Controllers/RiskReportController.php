<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Database;
use PDO;

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

        $this->flash('Estado del informe RL actualizado.', 'success', 'Correcto');
        header('Location: ' . $this->areaBaseUrl() . '/comunidades/' . $communityId . '#c-rl');
        exit;
    }

    /** @param array<string, string> $params */
    public function uploadReport(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireAdmin();

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

        $stmt = $pdo->prepare("SELECT id, notes FROM community_risk_reports WHERE community_id = :cid LIMIT 1");
        $stmt->execute(['cid' => $communityId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $stmt = $pdo->prepare("
                UPDATE community_risk_reports
                SET report_filename = :filename,
                    report_path = :path,
                    status = 'in_progress',
                    completed_at = NULL
                WHERE id = :id
            ");
            $stmt->execute([
                'filename' => $originalName,
                'path' => $relativePath,
                'id' => (int) $existing['id'],
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO community_risk_reports
                (community_id, status, report_filename, report_path, notes, completed_at)
                VALUES
                (:cid, 'in_progress', :filename, :path, :notes, NULL)
            ");
            $stmt->execute([
                'cid' => $communityId,
                'filename' => $originalName,
                'path' => $relativePath,
                'notes' => '',
            ]);
        }

        $this->flash('Informe RL subido correctamente.', 'success', 'Correcto');
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

        $this->flash('Informe RL eliminado correctamente.', 'success', 'Correcto');
        header('Location: ' . $this->areaBaseUrl() . '/comunidades/' . $communityId . '#c-rl');
        exit;
    }
}