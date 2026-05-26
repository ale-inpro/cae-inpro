<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rgpd;

use App\Core\Controller;
use App\Services\Rgpd\RgpdAccess;
use App\Services\Rgpd\RgpdMailService;
use App\Services\Rgpd\RgpdTemplateRenderer;
use PDO;

final class RgpdMassSendController extends Controller
{
    use RgpdControllerTrait;

    private const WIZARD_KEY = 'rgpd_mass_wizard';

    /** @param array<string, string> $params */
    public function wizard(array $params = []): void
    {
        $this->assertAreaAccess();
        $pdo = $this->rgpdPdo();
        [$role, $mcId] = $this->rgpdAccessContext();
        $scope = $this->communitiesScopeSql($role, $mcId);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleWizardPost($pdo, $role, $mcId);
            return;
        }

        $step = max(1, min(3, (int) ($_GET['step'] ?? 1)));
        $wizard = $_SESSION[self::WIZARD_KEY] ?? [];
        if (!empty($_GET['community_id'])) {
            $wizard['community_id'] = (int) $_GET['community_id'];
            $_SESSION[self::WIZARD_KEY] = $wizard;
        }

        $communities = $pdo->query("
            SELECT c.id, c.name FROM communities c
            WHERE c.is_active = TRUE {$scope}
            ORDER BY c.name
        ")->fetchAll(PDO::FETCH_ASSOC);

        $templates = $pdo->query("
            SELECT id, kind, name, slug FROM rgpd_templates
            WHERE is_active = TRUE
            ORDER BY kind DESC, name
        ")->fetchAll(PDO::FETCH_ASSOC);

        $systemIds = [];
        foreach ($templates as $t) {
            if (($t['kind'] ?? '') === 'system') {
                $systemIds[] = (int) $t['id'];
            }
        }

        $preview = null;
        if ($step === 3 && !empty($wizard['community_id']) && !empty($wizard['template_ids'])) {
            $preview = $this->buildLaunchPreview($pdo, $wizard, $role, $mcId);
        }

        $this->render('rgpd.mass-send.wizard', [
            'title' => 'RGPD · Envío masivo',
            'area' => $this->currentArea(),
            'areaBaseUrl' => $this->areaBaseUrl(),
            'baseUrl' => $this->baseUrl(),
            'step' => $step,
            'wizard' => $wizard,
            'communities' => $communities,
            'templates' => $templates,
            'systemTemplateIds' => $systemIds,
            'preview' => $preview,
        ]);
    }

    /** @param array<string, string> $params */
    public function launch(array $params = []): void
    {
        $this->wizard($params);
    }

    /** @param array<string, string> $params */
    public function resend(array $params = []): void
    {
        $this->assertAreaAccess();
        $pdo = $this->rgpdPdo();
        [$role, $mcId] = $this->rgpdAccessContext();
        $id = (int) ($params['id'] ?? 0);

        $stmt = $pdo->prepare("
            SELECT s.*, r.email,
                TRIM(CONCAT_WS(' ', r.nombre, r.apellidos)) AS resident_name,
                t.name AS template_name, c.name AS community_name
            FROM rgpd_signature_requests s
            JOIN community_residents r ON r.id = s.resident_id
            JOIN rgpd_templates t ON t.id = s.template_id
            JOIN communities c ON c.id = s.community_id
            WHERE s.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || ($row['status'] ?? '') !== 'pending') {
            $this->flash('Solo se reenvía solicitudes pendientes.', 'warning', 'RGPD');
            header('Location: ' . $this->refererCommunityUrl((int) ($row['community_id'] ?? 0)));
            exit;
        }

        if (RgpdAccess::assertCommunity($pdo, (int) $row['community_id'], $role, $mcId) === null) {
            http_response_code(403);
            $this->respond('Sin acceso');
            return;
        }

        if (empty($row['email']) || !$this->residentWantsEmail($pdo, (int) $row['resident_id'])) {
            $this->flash('Este vecino no tiene email activo para envío electrónico.', 'warning', 'RGPD');
            header('Location: ' . $this->refererCommunityUrl((int) $row['community_id']));
            exit;
        }

        $signUrl = $this->baseUrl() . '/rgpd/firmar/' . urlencode((string) $row['token']);
        $ok = RgpdMailService::sendSignatureInvite(
            (string) $row['email'],
            (string) $row['resident_name'],
            (string) $row['community_name'],
            (string) $row['template_name'],
            $signUrl
        );

        $pdo->prepare("
            UPDATE rgpd_signature_requests
            SET email_sent_at = NOW(), resent_count = resent_count + 1, updated_at = NOW()
            WHERE id = :id
        ")->execute(['id' => $id]);

        $this->flash($ok ? 'Correo reenviado.' : 'No se pudo enviar el correo (revisa configuración).', $ok ? 'success' : 'warning', 'RGPD');
        header('Location: ' . $this->refererCommunityUrl((int) $row['community_id']));
        exit;
    }

    /** @param array<string, string> $params */
    public function markPaper(array $params = []): void
    {
        $this->assertAreaAccess();
        $pdo = $this->rgpdPdo();
        [$role, $mcId] = $this->rgpdAccessContext();
        $id = (int) ($params['id'] ?? 0);
        $notes = trim((string) ($_POST['paper_notes'] ?? ''));
        $userId = (int) ($_SESSION['user']['id'] ?? 0);

        $stmt = $pdo->prepare('SELECT * FROM rgpd_signature_requests WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || ($row['status'] ?? '') !== 'pending') {
            $this->flash('Solo firmas pendientes.', 'warning', 'RGPD');
            header('Location: ' . $this->areaBaseUrl() . '/rgpd/comunidades');
            exit;
        }

        if (RgpdAccess::assertCommunity($pdo, (int) $row['community_id'], $role, $mcId) === null) {
            http_response_code(403);
            $this->respond('Sin acceso');
            return;
        }

        $pdo->prepare("
            UPDATE rgpd_signature_requests
            SET status = 'paper', signed_on_paper = TRUE, paper_notes = :notes,
                paper_recorded_by_user_id = :uid, signed_at = NOW(), updated_at = NOW()
            WHERE id = :id
        ")->execute([
            'id' => $id,
            'notes' => $notes !== '' ? $notes : null,
            'uid' => $userId > 0 ? $userId : null,
        ]);

        $this->flash('Firma registrada en papel.', 'success', 'RGPD');
        header('Location: ' . $this->refererCommunityUrl((int) $row['community_id']));
        exit;
    }

    private function handleWizardPost(PDO $pdo, string $role, ?int $mcId): void
    {
        $action = (string) ($_POST['wizard_action'] ?? '');
        $ab = $this->areaBaseUrl();

        if ($action === 'reset') {
            unset($_SESSION[self::WIZARD_KEY]);
            header('Location: ' . $ab . '/rgpd/envio-masivo?step=1');
            exit;
        }

        $wizard = $_SESSION[self::WIZARD_KEY] ?? [];

        if ($action === 'step1') {
            $wizard['community_id'] = (int) ($_POST['community_id'] ?? 0);
            if (RgpdAccess::assertCommunity($pdo, $wizard['community_id'], $role, $mcId) === null) {
                $this->flash('Comunidad no válida.', 'warning', 'RGPD');
                header('Location: ' . $ab . '/rgpd/envio-masivo?step=1');
                exit;
            }
            $_SESSION[self::WIZARD_KEY] = $wizard;
            header('Location: ' . $ab . '/rgpd/envio-masivo?step=2');
            exit;
        }

        if ($action === 'step2') {
            $templateIds = array_map('intval', (array) ($_POST['template_ids'] ?? []));
            $templateIds = array_values(array_unique(array_filter($templateIds, static fn(int $v): bool => $v > 0)));
            if (count($templateIds) < 1) {
                $this->flash('Seleccione al menos una plantilla.', 'warning', 'RGPD');
                header('Location: ' . $ab . '/rgpd/envio-masivo?step=2');
                exit;
            }
            $audience = (string) ($_POST['audience'] ?? 'both');
            if (!in_array($audience, ['owners', 'presidents', 'both'], true)) {
                $audience = 'both';
            }
            $wizard['template_ids'] = $templateIds;
            $wizard['audience'] = $audience;
            $wizard['notes'] = trim((string) ($_POST['notes'] ?? ''));
            $_SESSION[self::WIZARD_KEY] = $wizard;
            header('Location: ' . $ab . '/rgpd/envio-masivo?step=3');
            exit;
        }

        if ($action === 'launch') {
            $result = $this->executeCampaign($pdo, $wizard, $role, $mcId);
            unset($_SESSION[self::WIZARD_KEY]);
            $this->flash($result['message'], $result['ok'] ? 'success' : 'warning', 'RGPD');
            header('Location: ' . $ab . '/rgpd/comunidades/' . (int) ($wizard['community_id'] ?? 0) . '#rgpd-documentos');
            exit;
        }

        header('Location: ' . $ab . '/rgpd/envio-masivo');
        exit;
    }

    /**
     * @param array<string, mixed> $wizard
     * @return array{ok: bool, message: string}
     */
    private function executeCampaign(PDO $pdo, array $wizard, string $role, ?int $mcId): array
    {
        $communityId = (int) ($wizard['community_id'] ?? 0);
        $templateIds = array_map('intval', (array) ($wizard['template_ids'] ?? []));
        $audience = (string) ($wizard['audience'] ?? 'both');

        $community = RgpdAccess::assertCommunity($pdo, $communityId, $role, $mcId);
        if ($community === null || $templateIds === []) {
            return ['ok' => false, 'message' => 'Datos de campaña incompletos.'];
        }

        $residentWhere = match ($audience) {
            'owners' => ' AND r.is_owner = TRUE',
            'presidents' => ' AND r.is_president = TRUE',
            default => '',
        };

        $resStmt = $pdo->prepare("
            SELECT r.id, r.email, r.nombre, r.apellidos, r.full_name, r.enviar_email
            FROM community_residents r
            WHERE r.community_id = :cid AND r.is_active = TRUE {$residentWhere}
        ");
        $resStmt->execute(['cid' => $communityId]);
        $residents = $resStmt->fetchAll(PDO::FETCH_ASSOC);
        if ($residents === []) {
            return ['ok' => false, 'message' => 'No hay vecinos activos para la audiencia seleccionada.'];
        }

        $tplStmt = $pdo->prepare('SELECT id, name, body_html FROM rgpd_templates WHERE id = :id AND is_active = TRUE');
        $templates = [];
        foreach ($templateIds as $tid) {
            $tplStmt->execute(['id' => $tid]);
            $t = $tplStmt->fetch(PDO::FETCH_ASSOC);
            if ($t) {
                $templates[] = $t;
            }
        }
        if ($templates === []) {
            return ['ok' => false, 'message' => 'Plantillas no válidas.'];
        }

        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $pdo->beginTransaction();
        try {
            $campStmt = $pdo->prepare("
                INSERT INTO rgpd_campaigns (community_id, created_by_user_id, audience, status, notes, created_at)
                VALUES (:cid, :uid, :audience, 'sending', :notes, NOW())
                RETURNING id
            ");
            $campStmt->execute([
                'cid' => $communityId,
                'uid' => $userId,
                'audience' => $audience,
                'notes' => ($wizard['notes'] ?? '') !== '' ? $wizard['notes'] : null,
            ]);
            $campaignId = (int) $campStmt->fetchColumn();

            $linkTpl = $pdo->prepare('INSERT INTO rgpd_campaign_templates (campaign_id, template_id) VALUES (:c, :t)');
            foreach ($templates as $t) {
                $linkTpl->execute(['c' => $campaignId, 't' => (int) $t['id']]);
            }

            $insertReq = $pdo->prepare("
                INSERT INTO rgpd_signature_requests
                (campaign_id, community_id, resident_id, template_id, token, status, rendered_html, token_expires_at, created_at, updated_at)
                VALUES (:campaign, :cid, :rid, :tid, :token, 'pending', :html, NOW() + INTERVAL '90 days', NOW(), NOW())
            ");

            $sent = 0;
            $skipped = 0;
            $emailsOk = 0;

            foreach ($residents as $resident) {
                foreach ($templates as $tpl) {
                    $exists = $pdo->prepare("
                        SELECT 1 FROM rgpd_signature_requests
                        WHERE resident_id = :rid AND template_id = :tid AND status = 'pending'
                        LIMIT 1
                    ");
                    $exists->execute(['rid' => (int) $resident['id'], 'tid' => (int) $tpl['id']]);
                    if ($exists->fetchColumn()) {
                        $skipped++;
                        continue;
                    }

                    $token = bin2hex(random_bytes(32));
                    $html = RgpdTemplateRenderer::render((string) $tpl['body_html'], $community);
                    $insertReq->execute([
                        'campaign' => $campaignId,
                        'cid' => $communityId,
                        'rid' => (int) $resident['id'],
                        'tid' => (int) $tpl['id'],
                        'token' => $token,
                        'html' => $html,
                    ]);
                    $sent++;

                    $signUrl = $this->baseUrl() . '/rgpd/firmar/' . $token;
                    $residentName = app_resident_name($resident);
                    $canEmail = trim((string) ($resident['email'] ?? '')) !== ''
                        && $this->boolFromPg($resident['enviar_email'] ?? true);

                    if ($canEmail && RgpdMailService::sendSignatureInvite(
                        (string) $resident['email'],
                        $residentName,
                        (string) $community['name'],
                        (string) $tpl['name'],
                        $signUrl
                    )) {
                        $emailsOk++;
                        $pdo->prepare("UPDATE rgpd_signature_requests SET email_sent_at = NOW() WHERE token = :token")
                            ->execute(['token' => $token]);
                    }
                }
            }

            $pdo->prepare("UPDATE rgpd_campaigns SET status = 'completed', completed_at = NOW() WHERE id = :id")
                ->execute(['id' => $campaignId]);
            $pdo->commit();

            return [
                'ok' => true,
                'message' => "Campaña enviada: {$sent} solicitudes creadas"
                    . ($skipped > 0 ? ", {$skipped} omitidas (ya pendientes)" : '')
                    . ", {$emailsOk} correos enviados.",
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Error al lanzar campaña: ' . $e->getMessage()];
        }
    }

    /**
     * @param array<string, mixed> $wizard
     * @return array{residents: int, templates: int, labels: list<string>}|null
     */
    private function buildLaunchPreview(PDO $pdo, array $wizard, string $role, ?int $mcId): ?array
    {
        $communityId = (int) ($wizard['community_id'] ?? 0);
        if (RgpdAccess::assertCommunity($pdo, $communityId, $role, $mcId) === null) {
            return null;
        }
        $audience = (string) ($wizard['audience'] ?? 'both');
        $residentWhere = match ($audience) {
            'owners' => ' AND r.is_owner = TRUE',
            'presidents' => ' AND r.is_president = TRUE',
            default => '',
        };
        $countStmt = $pdo->prepare("
            SELECT COUNT(*) FROM community_residents r
            WHERE r.community_id = :cid AND r.is_active = TRUE {$residentWhere}
        ");
        $emailCountStmt = $pdo->prepare("
            SELECT COUNT(*) FROM community_residents r
            WHERE r.community_id = :cid AND r.is_active = TRUE {$residentWhere}
            AND r.email IS NOT NULL AND TRIM(r.email) <> ''
            AND r.enviar_email = TRUE
        ");
        $countStmt->execute(['cid' => $communityId]);
        $residents = (int) $countStmt->fetchColumn();
        $emailCountStmt->execute(['cid' => $communityId]);
        $residentsWithEmail = (int) $emailCountStmt->fetchColumn();
        $templateIds = array_map('intval', (array) ($wizard['template_ids'] ?? []));

        return [
            'residents' => $residents,
            'residents_with_email' => $residentsWithEmail,
            'templates' => count($templateIds),
            'requests' => $residents * count($templateIds),
            'emails_planned' => $residentsWithEmail * count($templateIds),
            'audience' => $audience,
        ];
    }

    private function refererCommunityUrl(int $communityId, string $hash = '#rgpd-solicitudes'): string
    {
        if ($communityId > 0) {
            return $this->areaBaseUrl() . '/rgpd/comunidades/' . $communityId . $hash;
        }

        return $this->areaBaseUrl() . '/rgpd/comunidades';
    }

    private function residentWantsEmail(PDO $pdo, int $residentId): bool
    {
        $stmt = $pdo->prepare('SELECT enviar_email FROM community_residents WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $residentId]);
        $val = $stmt->fetchColumn();

        return $val === false ? false : $this->boolFromPg($val);
    }
}
