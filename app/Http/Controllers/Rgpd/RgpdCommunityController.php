<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rgpd;

use App\Core\Controller;
use App\Services\Rgpd\RgpdAccess;
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
                enviar_email, enviar_whatsapp, enviar_postal, direccion_postal,
                es_representante, is_owner, is_president, is_active
            FROM community_residents
            WHERE community_id = :cid
            ORDER BY is_president DESC, nombre, apellidos
        ");
        $residentsStmt->execute(['cid' => $id]);
        $residentRows = $residentsStmt->fetchAll(PDO::FETCH_ASSOC);

        $residentSignStats = $this->loadResidentSignStats($pdo, $id);
        $documentSummaries = $this->loadDocumentSummaries($pdo, $id);

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

    /** @return array<int, array{signed_n: int, pending_n: int}> */
    private function loadResidentSignStats(PDO $pdo, int $communityId): array
    {
        $stmt = $pdo->prepare("
            SELECT resident_id,
                COUNT(*) FILTER (WHERE status IN ('signed', 'paper')) AS signed_n,
                COUNT(*) FILTER (WHERE status = 'pending') AS pending_n
            FROM rgpd_signature_requests
            WHERE community_id = :cid
            GROUP BY resident_id
        ");
        $stmt->execute(['cid' => $communityId]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int) $row['resident_id']] = [
                'signed_n' => (int) ($row['signed_n'] ?? 0),
                'pending_n' => (int) ($row['pending_n'] ?? 0),
            ];
        }
        return $map;
    }

    /** @return list<array<string, mixed>> */
    private function loadDocumentSummaries(PDO $pdo, int $communityId): array
    {
        $templates = $pdo->query("
            SELECT id, name, kind FROM rgpd_templates
            WHERE is_active = TRUE
            ORDER BY kind DESC, name
        ")->fetchAll(PDO::FETCH_ASSOC);

        $lastCampStmt = $pdo->prepare("
            SELECT cp.id, cp.audience, cp.completed_at
            FROM rgpd_campaigns cp
            INNER JOIN rgpd_campaign_templates rct ON rct.campaign_id = cp.id
            WHERE cp.community_id = :cid
                AND cp.status = 'completed'
                AND rct.template_id = :tid
            ORDER BY cp.completed_at DESC NULLS LAST, cp.id DESC
            LIMIT 1
        ");

        $statsStmt = $pdo->prepare("
            SELECT status, COUNT(*)::int AS cnt
            FROM rgpd_signature_requests
            WHERE community_id = :cid AND template_id = :tid
            GROUP BY status
        ");

        $rows = [];
        foreach ($templates as $tpl) {
            $tid = (int) $tpl['id'];
            $lastCampStmt->execute(['cid' => $communityId, 'tid' => $tid]);
            $camp = $lastCampStmt->fetch(PDO::FETCH_ASSOC) ?: null;

            $statsStmt->execute(['cid' => $communityId, 'tid' => $tid]);
            $byStatus = [];
            foreach ($statsStmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
                $byStatus[(string) $s['status']] = (int) $s['cnt'];
            }

            $audience = $camp ? (string) ($camp['audience'] ?? 'both') : null;
            $eligible = $camp ? $this->countEligibleResidents($pdo, $communityId, $audience) : 0;
            $signed = ($byStatus['signed'] ?? 0) + ($byStatus['paper'] ?? 0);
            $pending = $byStatus['pending'] ?? 0;

            $pendingResidents = [];
            if ($camp !== null) {
                $audWhere = $this->audienceSqlFragment($audience);
                $sql = "
                    SELECT r.id,
                        TRIM(CONCAT_WS(' ', r.nombre, r.apellidos)) AS resident_name,
                        r.email
                    FROM community_residents r
                    WHERE r.community_id = :cid AND r.is_active = TRUE
                    {$audWhere}
                    AND NOT EXISTS (
                        SELECT 1 FROM rgpd_signature_requests s
                        WHERE s.resident_id = r.id AND s.template_id = :tid
                            AND s.status IN ('signed', 'paper')
                    )
                    ORDER BY r.nombre, r.apellidos
                ";
                $pStmt = $pdo->prepare($sql);
                $pStmt->execute(['cid' => $communityId, 'tid' => $tid]);
                $pendingResidents = $pStmt->fetchAll(PDO::FETCH_ASSOC);
            }

            $missing = count($pendingResidents);
            $rows[] = [
                'template_id' => $tid,
                'template_name' => (string) $tpl['name'],
                'kind' => (string) $tpl['kind'],
                'has_campaign' => $camp !== null,
                'last_campaign_at' => $camp['completed_at'] ?? null,
                'audience' => $audience,
                'audience_label' => $this->audienceLabel($audience),
                'eligible' => $eligible,
                'signed' => $signed,
                'pending' => $pending,
                'missing' => $missing,
                'is_complete' => $camp !== null && $eligible > 0 && $missing === 0,
                'pending_residents' => $pendingResidents,
            ];
        }

        return $rows;
    }

    private function audienceSqlFragment(?string $audience): string
    {
        return match ($audience) {
            'owners' => ' AND r.is_owner = TRUE',
            'presidents' => ' AND r.is_president = TRUE',
            default => '',
        };
    }

    private function audienceLabel(?string $audience): string
    {
        return match ($audience) {
            'owners' => 'Propietarios',
            'presidents' => 'Presidentes',
            'both', null => 'Todos los vecinos activos',
            default => 'Todos los vecinos activos',
        };
    }

    private function countEligibleResidents(PDO $pdo, int $communityId, ?string $audience): int
    {
        $audWhere = $this->audienceSqlFragment($audience);
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM community_residents r
            WHERE r.community_id = :cid AND r.is_active = TRUE {$audWhere}
        ");
        $stmt->execute(['cid' => $communityId]);

        return (int) $stmt->fetchColumn();
    }
}
