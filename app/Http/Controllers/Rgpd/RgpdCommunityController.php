<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rgpd;

use App\Core\Controller;
use App\Services\Rgpd\RgpdAccess;
use App\Services\Rgpd\RgpdTemplateCompliance;
use PDO;

final class RgpdCommunityController extends Controller
{
    use RgpdControllerTrait;

    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        $this->assertAreaAccess();
        $pdo = $this->rgpdPdo();
        [$role, $mcId] = $this->rgpdAccessContext();
        $scope = $this->communitiesScopeSql($role, $mcId);

        $sql = "
            SELECT
                c.id,
                c.name,
                c.address,
                c.city,
                (SELECT COUNT(*) FROM community_residents r WHERE r.community_id = c.id AND r.is_active = TRUE) AS residents_count,
                (SELECT COUNT(*) FROM rgpd_signature_requests s WHERE s.community_id = c.id AND s.status = 'pending') AS pending_signatures,
                (SELECT status FROM community_rgpd_contracts rc
                 WHERE rc.community_id = c.id
                 ORDER BY rc.created_at DESC LIMIT 1) AS contract_status
            FROM communities c
            WHERE c.is_active = TRUE {$scope}
            ORDER BY c.name
        ";
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        $this->render('rgpd.communities.index', [
            'title' => 'RGPD · Comunidades',
            'area' => $this->currentArea(),
            'areaBaseUrl' => $this->areaBaseUrl(),
            'baseUrl' => $this->baseUrl(),
            'communities' => $rows,
        ]);
    }

    /** @param array<string, string> $params */
    public function show(array $params = []): void
    {
        $this->assertAreaAccess();
        $pdo = $this->rgpdPdo();
        [$role, $mcId] = $this->rgpdAccessContext();
        $id = (int) ($params['id'] ?? 0);

        $community = RgpdAccess::assertCommunity($pdo, $id, $role, $mcId);
        if ($community === null) {
            http_response_code(404);
            $this->respond('Comunidad no encontrada');
            return;
        }

        $residentsStmt = $pdo->prepare("
            SELECT id, nombre, apellidos, full_name, email, telefono, dni,
                unit_label, propiedades,
                direccion_postal,
                es_representante, is_owner, is_president, is_active
            FROM community_residents
            WHERE community_id = :cid
            ORDER BY is_president DESC, nombre, apellidos
        ");
        $residentsStmt->execute(['cid' => $id]);
        $residentRows = $residentsStmt->fetchAll(PDO::FETCH_ASSOC);

        $residentSignStats = RgpdTemplateCompliance::loadResidentStats($pdo, $id);
        $documentSummaries = RgpdTemplateCompliance::loadDocumentSummaries($pdo, $id);

        $sigStatus = (string) ($_GET['sig_status'] ?? 'pending');
        if (!in_array($sigStatus, ['pending', 'signed', 'paper', 'cancelled', 'all'], true)) {
            $sigStatus = 'pending';
        }
        $sigTemplateId = (int) ($_GET['sig_template'] ?? 0);
        $sigFrom = trim((string) ($_GET['sig_from'] ?? ''));
        $sigTo = trim((string) ($_GET['sig_to'] ?? ''));

        $sigWhere = ['s.community_id = :cid'];
        $sigParams = ['cid' => $id];
        if ($sigStatus !== 'all') {
            $sigWhere[] = 's.status = :status';
            $sigParams['status'] = $sigStatus;
        }
        if ($sigTemplateId > 0) {
            $sigWhere[] = 's.template_id = :tid';
            $sigParams['tid'] = $sigTemplateId;
        }
        if ($sigFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $sigFrom)) {
            $sigWhere[] = 's.created_at::date >= :from';
            $sigParams['from'] = $sigFrom;
        }
        if ($sigTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $sigTo)) {
            $sigWhere[] = 's.created_at::date <= :to';
            $sigParams['to'] = $sigTo;
        }
        $sigWhereSql = implode(' AND ', $sigWhere);

        $signatures = $pdo->prepare("
            SELECT s.id, s.status, s.created_at, s.signed_at, s.email_sent_at, s.resent_count,
                s.template_id,
                TRIM(CONCAT_WS(' ', r.nombre, r.apellidos)) AS resident_name,
                t.name AS template_name
            FROM rgpd_signature_requests s
            JOIN community_residents r ON r.id = s.resident_id
            JOIN rgpd_templates t ON t.id = s.template_id
            WHERE {$sigWhereSql}
            ORDER BY s.created_at DESC
            LIMIT 200
        ");
        $signatures->execute($sigParams);
        $signatureRows = $signatures->fetchAll(PDO::FETCH_ASSOC);

        $pendingStmt = $pdo->prepare("
            SELECT COUNT(*) FROM rgpd_signature_requests
            WHERE community_id = :cid AND status = 'pending'
        ");
        $pendingStmt->execute(['cid' => $id]);
        $pendingCount = (int) $pendingStmt->fetchColumn();

        $contractStmt = $pdo->prepare("
            SELECT * FROM community_rgpd_contracts
            WHERE community_id = :cid
            ORDER BY created_at DESC LIMIT 1
        ");
        $contractStmt->execute(['cid' => $id]);
        $contract = $contractStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $templatesForFilter = $pdo->query("
            SELECT id, name FROM rgpd_templates WHERE is_active = TRUE ORDER BY name
        ")->fetchAll(PDO::FETCH_ASSOC);

        $this->render('rgpd.communities.show', [
            'title' => 'RGPD · ' . ($community['name'] ?? 'Comunidad'),
            'area' => $this->currentArea(),
            'areaBaseUrl' => $this->areaBaseUrl(),
            'baseUrl' => $this->baseUrl(),
            'community' => $community,
            'residents' => $residentRows,
            'residentSignStats' => $residentSignStats,
            'documentSummaries' => $documentSummaries,
            'signatures' => $signatureRows,
            'contract' => $contract,
            'pendingCount' => $pendingCount,
            'sigFilters' => [
                'status' => $sigStatus,
                'template_id' => $sigTemplateId,
                'from' => $sigFrom,
                'to' => $sigTo,
            ],
            'templatesForFilter' => $templatesForFilter,
        ]);
    }

    /** @param array<string, string> $params */
    public function assignPresident(array $params = []): void
    {
        $this->assertAreaAccess();
        $pdo = $this->rgpdPdo();
        [$role, $mcId] = $this->rgpdAccessContext();
        $communityId = (int) ($params['id'] ?? 0);
        $residentId = (int) ($_POST['resident_id'] ?? 0);

        $community = RgpdAccess::assertCommunity($pdo, $communityId, $role, $mcId);
        if ($community === null) {
            http_response_code(404);
            $this->respond('Comunidad no encontrada');
            return;
        }

        $check = $pdo->prepare('SELECT id FROM community_residents WHERE id = :rid AND community_id = :cid AND is_active = TRUE');
        $check->execute(['rid' => $residentId, 'cid' => $communityId]);
        if (!$check->fetchColumn()) {
            $this->flash('Vecino no válido para esta comunidad.', 'warning', 'RGPD');
            header('Location: ' . $this->areaBaseUrl() . '/rgpd/comunidades/' . $communityId . '#rgpd-vecinos');
            exit;
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE community_residents SET is_president = FALSE, updated_at = NOW() WHERE community_id = :cid")
                ->execute(['cid' => $communityId]);
            $pdo->prepare("UPDATE community_residents SET is_president = TRUE, updated_at = NOW() WHERE id = :rid")
                ->execute(['rid' => $residentId]);
            $pdo->commit();
            $this->flash('Presidente asignado.', 'success', 'RGPD');
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $this->flash('No se pudo asignar presidente (¿ya existe otro activo?).', 'danger', 'RGPD');
        }

        header('Location: ' . $this->areaBaseUrl() . '/rgpd/comunidades/' . $communityId . '#rgpd-vecinos');
        exit;
    }

    /** @param array<string, string> $params */
    public function unassignPresident(array $params = []): void
    {
        $this->assertAreaAccess();
        $pdo = $this->rgpdPdo();
        [$role, $mcId] = $this->rgpdAccessContext();
        $communityId = (int) ($params['id'] ?? 0);

        $community = RgpdAccess::assertCommunity($pdo, $communityId, $role, $mcId);
        if ($community === null) {
            http_response_code(404);
            $this->respond('Comunidad no encontrada');
            return;
        }

        $pdo->prepare("UPDATE community_residents SET is_president = FALSE, updated_at = NOW() WHERE community_id = :cid")
            ->execute(['cid' => $communityId]);

        $this->flash('Presidente desasignado.', 'info', 'RGPD');
        header('Location: ' . $this->areaBaseUrl() . '/rgpd/comunidades/' . $communityId . '#rgpd-vecinos');
        exit;
    }
}
