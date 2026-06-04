<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rgpd;

use App\Core\Controller;
use App\Services\Rgpd\RgpdAccess;
use App\Services\Rgpd\RgpdMailService;
use App\Services\Rgpd\RgpdTemplateCompliance;
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

        if ($step === 1 && empty($_GET['preserve'])) {
            unset($_SESSION[self::WIZARD_KEY]);
        }

        $wizard = $_SESSION[self::WIZARD_KEY] ?? [];

        if (isset($_GET['community_ids'])) {
            $newCommunityIds = $this->normalizeCommunityIds((array) ($_GET['community_ids'] ?? []));
            $selections = (array) ($wizard['resident_selections'] ?? []);

            foreach (array_keys($selections) as $cid) {
                if (!in_array((int) $cid, $newCommunityIds, true)) {
                    unset($selections[(int) $cid]);
                }
            }

            $wizard['community_ids'] = $newCommunityIds;
            $wizard['resident_selections'] = $selections;
            unset($wizard['community_id'], $wizard['resident_ids']);
            $_SESSION[self::WIZARD_KEY] = $wizard;
        }

        $communities = $pdo->query("
            SELECT c.id, c.name FROM communities c
            WHERE c.is_active = TRUE {$scope}
            ORDER BY c.name
        ")->fetchAll(PDO::FETCH_ASSOC);

        $templates = $pdo->query("
            SELECT id, kind, name, slug, category, description FROM rgpd_templates
            WHERE is_active = TRUE
            ORDER BY kind DESC, name
        ")->fetchAll(PDO::FETCH_ASSOC);

        $systemIds = [];
        foreach ($templates as $t) {
            if (($t['kind'] ?? '') === 'system') {
                $systemIds[] = (int) $t['id'];
            }
        }

        $templateIds = array_map('intval', (array) ($wizard['template_ids'] ?? []));
        $selectedCommunityIds = $this->normalizeCommunityIds((array) ($wizard['community_ids'] ?? []));
        $residentSelections = $this->normalizeResidentSelections((array) ($wizard['resident_selections'] ?? []));

        $wizardResidentsByCommunity = [];
        if ($step === 2 && $selectedCommunityIds !== [] && $templateIds !== []) {
            foreach ($communities as $c) {
                $cid = (int) ($c['id'] ?? 0);
                if (!in_array($cid, $selectedCommunityIds, true)) {
                    continue;
                }
                if (RgpdAccess::assertCommunity($pdo, $cid, $role, $mcId) === null) {
                    continue;
                }
                $wizardResidentsByCommunity[] = [
                    'community_id' => $cid,
                    'community_name' => (string) ($c['name'] ?? ''),
                    'residents' => RgpdTemplateCompliance::loadWizardResidents($pdo, $cid, $templateIds),
                ];
            }
        }

        $preview = null;
        $confirmMeta = null;
        if ($step === 3 && $residentSelections !== [] && $templateIds !== []) {
            $preview = $this->buildLaunchPreview($pdo, $wizard, $role, $mcId);
            $confirmMeta = $this->buildConfirmMeta($pdo, $wizard, $communities, $templates, $role, $mcId);
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
            'selectedCommunityIds' => $selectedCommunityIds,
            'residentSelections' => $residentSelections,
            'wizardResidentsByCommunity' => $wizardResidentsByCommunity,
            'preview' => $preview,
            'confirmMeta' => $confirmMeta,
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
                t.name AS template_name, t.body_html AS template_body,
                c.name AS community_name, c.city, c.cif, c.address,
                c.contact_email
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

        if (!$this->residentHasEmail($row['email'] ?? '')) {
            $this->flash('Este vecino no tiene email registrado.', 'warning', 'RGPD');
            header('Location: ' . $this->refererCommunityUrl((int) $row['community_id']));
            exit;
        }

        $pdo->beginTransaction();
        try {
            RgpdTemplateCompliance::cancelPendingRequests($pdo, (int) $row['resident_id'], (int) $row['template_id']);

            $token = bin2hex(random_bytes(32));
            $community = [
                'name' => (string) ($row['community_name'] ?? ''),
                'city' => (string) ($row['city'] ?? ''),
                'cif' => (string) ($row['cif'] ?? ''),
                'address' => (string) ($row['address'] ?? ''),
                'contact_email' => (string) ($row['contact_email'] ?? ''),
            ];
            $resident = $pdo->prepare('SELECT * FROM community_residents WHERE id = :id LIMIT 1');
            $resident->execute(['id' => (int) $row['resident_id']]);
            $residentRow = $resident->fetch(PDO::FETCH_ASSOC) ?: [];

            $html = RgpdTemplateRenderer::render((string) ($row['template_body'] ?? ''), $community, $residentRow);
            $pdo->prepare("
                INSERT INTO rgpd_signature_requests
                (campaign_id, community_id, resident_id, template_id, token, status, rendered_html, token_expires_at, created_at, updated_at)
                VALUES (:campaign, :cid, :rid, :tid, :token, 'pending', :html, NOW() + INTERVAL '90 days', NOW(), NOW())
            ")->execute([
                'campaign' => $row['campaign_id'] ?? null,
                'cid' => (int) $row['community_id'],
                'rid' => (int) $row['resident_id'],
                'tid' => (int) $row['template_id'],
                'token' => $token,
                'html' => $html,
            ]);

            $signUrl = $this->baseUrl() . '/rgpd/firmar/' . $token;
            $ok = RgpdMailService::sendSignatureReminder(
                (string) $row['email'],
                (string) $row['resident_name'],
                (string) $row['community_name'],
                (string) $row['template_name'],
                $signUrl
            );

            if ($ok) {
                $pdo->prepare("UPDATE rgpd_signature_requests SET email_sent_at = NOW() WHERE token = :token")
                    ->execute(['token' => $token]);
            }

            $pdo->commit();
            $this->flash($ok ? 'Recordatorio enviado con un enlace nuevo.' : 'Enlace renovado, pero no se pudo enviar el correo.', $ok ? 'success' : 'warning', 'RGPD');
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $this->flash('No se pudo reenviar: ' . $e->getMessage(), 'danger', 'RGPD');
        }

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

        $this->flash('Registre la firma en papel desde la pestaña Vecinos subiendo el PDF firmado.', 'info', 'RGPD');
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
            $templateIds = array_map('intval', (array) ($_POST['template_ids'] ?? []));
            $templateIds = array_values(array_unique(array_filter($templateIds, static fn(int $v): bool => $v > 0)));
            if ($templateIds === []) {
                $this->flash('Seleccione al menos una plantilla.', 'warning', 'RGPD');
                header('Location: ' . $ab . '/rgpd/envio-masivo?step=1&preserve=1');
                exit;
            }
            $wizard['template_ids'] = $templateIds;
            unset($wizard['community_id'], $wizard['community_ids'], $wizard['resident_ids'], $wizard['resident_selections']);
            $_SESSION[self::WIZARD_KEY] = $wizard;
            header('Location: ' . $ab . '/rgpd/envio-masivo?step=2');
            exit;
        }

        if ($action === 'step2') {
            $communityIds = $this->normalizeCommunityIds((array) ($_POST['community_ids'] ?? []));
            if ($communityIds === []) {
                $this->flash('Seleccione al menos una comunidad.', 'warning', 'RGPD');
                header('Location: ' . $ab . '/rgpd/envio-masivo?step=2');
                exit;
            }

            foreach ($communityIds as $communityId) {
                if (RgpdAccess::assertCommunity($pdo, $communityId, $role, $mcId) === null) {
                    $this->flash('Una o más comunidades no son válidas.', 'warning', 'RGPD');
                    header('Location: ' . $ab . '/rgpd/envio-masivo?step=2');
                    exit;
                }
            }

            $rawSelections = (array) ($_POST['resident_ids'] ?? []);
            $residentSelections = [];
            $totalResidents = 0;

            foreach ($communityIds as $communityId) {
                $ids = array_map('intval', (array) ($rawSelections[$communityId] ?? $rawSelections[(string) $communityId] ?? []));
                $ids = array_values(array_unique(array_filter($ids, static fn(int $v): bool => $v > 0)));
                if ($ids !== []) {
                    $residentSelections[$communityId] = $ids;
                    $totalResidents += count($ids);
                }
            }

            if ($totalResidents === 0) {
                $this->flash('Seleccione al menos un vecino.', 'warning', 'RGPD');
                $query = http_build_query(['step' => 2, 'community_ids' => $communityIds], '', '&', PHP_QUERY_RFC3986);
                header('Location: ' . $ab . '/rgpd/envio-masivo?' . $query);
                exit;
            }

            $wizard['community_ids'] = $communityIds;
            $wizard['resident_selections'] = $residentSelections;
            unset($wizard['community_id'], $wizard['resident_ids']);
            $_SESSION[self::WIZARD_KEY] = $wizard;
            header('Location: ' . $ab . '/rgpd/envio-masivo?step=3');
            exit;
        }

        if ($action === 'launch') {
            $result = $this->executeAllCampaigns($pdo, $wizard, $role, $mcId);
            unset($_SESSION[self::WIZARD_KEY]);
            $this->flash($result['message'], $result['ok'] ? 'success' : 'warning', 'RGPD');
            header('Location: ' . $ab . '/rgpd/comunidades');
            exit;
        }

        header('Location: ' . $ab . '/rgpd/envio-masivo');
        exit;
    }

     /**
     * @param list<int> $residentIds
     * @param list<int> $templateIds
     * @param array<string, mixed> $wizard
     * @return array{ok: bool, message: string, invites?: int, reminders?: int, skipped?: int, emails_ok?: int}
     */
    private function executeSingleCommunityCampaign(
        PDO $pdo,
        int $communityId,
        array $residentIds,
        array $templateIds,
        array $wizard,
        string $role,
        ?int $mcId
    ): array {
        $community = RgpdAccess::assertCommunity($pdo, $communityId, $role, $mcId);
        if ($community === null || $templateIds === [] || $residentIds === []) {
            return ['ok' => false, 'message' => 'Datos de campaña incompletos.'];
        }

        $in = implode(',', array_fill(0, count($residentIds), '?'));
        $resStmt = $pdo->prepare("
            SELECT r.id, r.email, r.nombre, r.apellidos, r.full_name,
                   r.dni, r.telefono, r.unit_label, r.propiedades, r.direccion_postal
            FROM community_residents r
            WHERE r.community_id = ? AND r.is_active = TRUE AND r.id IN ({$in})
        ");
        $resStmt->execute(array_merge([$communityId], $residentIds));
        $residents = $resStmt->fetchAll(PDO::FETCH_ASSOC);
        if ($residents === []) {
            return ['ok' => false, 'message' => 'No hay vecinos válidos seleccionados.'];
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
                VALUES (:cid, :uid, 'both', 'sending', :notes, NOW())
                RETURNING id
            ");
            $campStmt->execute([
                'cid' => $communityId,
                'uid' => $userId,
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

            $invites = 0;
            $reminders = 0;
            $skipped = 0;
            $emailsOk = 0;

            foreach ($residents as $resident) {
                foreach ($templates as $tpl) {
                    $rid = (int) $resident['id'];
                    $tid = (int) $tpl['id'];
                    $action = RgpdTemplateCompliance::resolveMassSendAction($pdo, $rid, $tid);

                    if ($action === RgpdTemplateCompliance::SEND_SKIP) {
                        $skipped++;
                        continue;
                    }

                    if ($action === RgpdTemplateCompliance::SEND_REMINDER) {
                        RgpdTemplateCompliance::cancelPendingRequests($pdo, $rid, $tid);
                        $reminders++;
                    } else {
                        $invites++;
                    }

                    $token = bin2hex(random_bytes(32));
                    $html = RgpdTemplateRenderer::render((string) $tpl['body_html'], $community, $resident);
                    $insertReq->execute([
                        'campaign' => $campaignId,
                        'cid' => $communityId,
                        'rid' => $rid,
                        'tid' => $tid,
                        'token' => $token,
                        'html' => $html,
                    ]);

                    $signUrl = $this->baseUrl() . '/rgpd/firmar/' . $token;
                    $residentName = app_resident_name($resident);
                    $canEmail = $this->residentHasEmail($resident['email'] ?? '');

                    if ($canEmail) {
                        $sent = $action === RgpdTemplateCompliance::SEND_REMINDER
                            ? RgpdMailService::sendSignatureReminder(
                                (string) $resident['email'],
                                $residentName,
                                (string) $community['name'],
                                (string) $tpl['name'],
                                $signUrl
                            )
                            : RgpdMailService::sendSignatureInvite(
                                (string) $resident['email'],
                                $residentName,
                                (string) $community['name'],
                                (string) $tpl['name'],
                                $signUrl
                            );

                        if ($sent) {
                            $emailsOk++;
                            $pdo->prepare("UPDATE rgpd_signature_requests SET email_sent_at = NOW() WHERE token = :token")
                                ->execute(['token' => $token]);
                        }
                    }
                }
            }

            $pdo->prepare("UPDATE rgpd_campaigns SET status = 'completed', completed_at = NOW() WHERE id = :id")
                ->execute(['id' => $campaignId]);
            $pdo->commit();

            return [
                'ok' => true,
                'message' => '',
                'invites' => $invites,
                'reminders' => $reminders,
                'skipped' => $skipped,
                'emails_ok' => $emailsOk,
            ];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Error al lanzar campaña: ' . $e->getMessage()];
        }
    }

        /**
     * @param array<string, mixed> $wizard
     * @return array{ok: bool, message: string}
     */
    private function executeAllCampaigns(PDO $pdo, array $wizard, string $role, ?int $mcId): array
    {
        $templateIds = array_map('intval', (array) ($wizard['template_ids'] ?? []));
        $selections = $this->normalizeResidentSelections((array) ($wizard['resident_selections'] ?? []));

        if ($templateIds === [] || $selections === []) {
            return ['ok' => false, 'message' => 'Datos de campaña incompletos.'];
        }

        $totalInvites = 0;
        $totalReminders = 0;
        $totalSkipped = 0;
        $totalEmails = 0;
        $campaignsOk = 0;
        $errors = [];

        foreach ($selections as $communityId => $residentIds) {
            $result = $this->executeSingleCommunityCampaign(
                $pdo,
                $communityId,
                $residentIds,
                $templateIds,
                $wizard,
                $role,
                $mcId
            );

            if (!$result['ok']) {
                $errors[] = $result['message'];
                continue;
            }

            $campaignsOk++;
            $totalInvites += (int) ($result['invites'] ?? 0);
            $totalReminders += (int) ($result['reminders'] ?? 0);
            $totalSkipped += (int) ($result['skipped'] ?? 0);
            $totalEmails += (int) ($result['emails_ok'] ?? 0);
        }

        if ($campaignsOk === 0) {
            return ['ok' => false, 'message' => $errors[0] ?? 'No se pudo lanzar ninguna campaña.'];
        }

        $message = "{$campaignsOk} campaña" . ($campaignsOk === 1 ? '' : 's') . " enviada" . ($campaignsOk === 1 ? '' : 's')
            . ": {$totalInvites} invitaciones, {$totalReminders} recordatorios"
            . ($totalSkipped > 0 ? ", {$totalSkipped} omitidas (ya firmadas)" : '')
            . ", {$totalEmails} correos enviados.";

        if ($errors !== []) {
            $message .= ' Algunas comunidades fallaron.';
        }

        return ['ok' => true, 'message' => $message];
    }

    /** @param list<mixed> $ids */
    private function normalizeCommunityIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $v): bool => $v > 0)));
    }

    /** @param array<mixed, mixed> $selections @return array<int, list<int>> */
    private function normalizeResidentSelections(array $selections): array
    {
        $normalized = [];
        foreach ($selections as $communityId => $residentIds) {
            $cid = (int) $communityId;
            if ($cid <= 0) {
                continue;
            }
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) $residentIds), static fn(int $v): bool => $v > 0)));
            if ($ids !== []) {
                $normalized[$cid] = $ids;
            }
        }

        return $normalized;
    }

        /**
     * @param array<string, mixed> $wizard
     * @return array<string, int>|null
     */
    private function buildLaunchPreview(PDO $pdo, array $wizard, string $role, ?int $mcId): ?array
    {
        $templateIds = array_map('intval', (array) ($wizard['template_ids'] ?? []));
        $selections = $this->normalizeResidentSelections((array) ($wizard['resident_selections'] ?? []));
        if ($templateIds === [] || $selections === []) {
            return null;
        }

        $invites = 0;
        $reminders = 0;
        $skipped = 0;
        $emailsPlanned = 0;
        $residentsTotal = 0;

        $resStmt = $pdo->prepare('SELECT id, email FROM community_residents WHERE community_id = :cid AND is_active = TRUE AND id = :id');

        foreach ($selections as $communityId => $residentIds) {
            if (RgpdAccess::assertCommunity($pdo, $communityId, $role, $mcId) === null) {
                return null;
            }

            $residentsTotal += count($residentIds);

            foreach ($residentIds as $rid) {
                $resStmt->execute(['cid' => $communityId, 'id' => $rid]);
                $resident = $resStmt->fetch(PDO::FETCH_ASSOC);
                if (!$resident) {
                    continue;
                }

                foreach ($templateIds as $tid) {
                    $action = RgpdTemplateCompliance::resolveMassSendAction($pdo, $rid, $tid);
                    if ($action === RgpdTemplateCompliance::SEND_SKIP) {
                        $skipped++;
                        continue;
                    }
                    if ($action === RgpdTemplateCompliance::SEND_REMINDER) {
                        $reminders++;
                    } else {
                        $invites++;
                    }
                    if ($this->residentHasEmail($resident['email'] ?? '')) {
                        $emailsPlanned++;
                    }
                }
            }
        }

        return [
            'communities' => count($selections),
            'residents' => $residentsTotal,
            'templates' => count($templateIds),
            'invites' => $invites,
            'reminders' => $reminders,
            'skipped' => $skipped,
            'emails_planned' => $emailsPlanned,
        ];
    }

    private function refererCommunityUrl(int $communityId, string $hash = '#rgpd-solicitudes'): string
    {
        if ($communityId > 0) {
            return $this->areaBaseUrl() . '/rgpd/comunidades/' . $communityId . $hash;
        }

        return $this->areaBaseUrl() . '/rgpd/comunidades';
    }

    private function residentHasEmail(mixed $email): bool
    {
        return trim((string) $email) !== '';
    }

        /**
     * @param list<array<string, mixed>> $communities
     * @param list<array<string, mixed>> $templates
     * @param array<string, mixed> $wizard
     * @return array<string, mixed>|null
     */
    private function buildConfirmMeta(PDO $pdo, array $wizard, array $communities, array $templates, string $role, ?int $mcId): ?array
    {
        $templateIds = array_map('intval', (array) ($wizard['template_ids'] ?? []));
        $selections = $this->normalizeResidentSelections((array) ($wizard['resident_selections'] ?? []));
        if ($templateIds === [] || $selections === []) {
            return null;
        }

        $communityNames = [];
        foreach ($communities as $c) {
            $communityNames[(int) ($c['id'] ?? 0)] = (string) ($c['name'] ?? '');
        }

        $templateNames = [];
        foreach ($templates as $t) {
            if (in_array((int) ($t['id'] ?? 0), $templateIds, true)) {
                $templateNames[] = (string) ($t['name'] ?? '');
            }
        }

        $communityRows = [];
        $residentNamesByCommunity = [];

        foreach ($selections as $communityId => $residentIds) {
            if (RgpdAccess::assertCommunity($pdo, $communityId, $role, $mcId) === null) {
                return null;
            }

            $names = [];
            if ($residentIds !== []) {
                $in = implode(',', array_fill(0, count($residentIds), '?'));
                $stmt = $pdo->prepare("
                    SELECT TRIM(CONCAT_WS(' ', nombre, apellidos)) AS resident_name
                    FROM community_residents
                    WHERE community_id = ? AND id IN ({$in})
                    ORDER BY nombre, apellidos
                ");
                $stmt->execute(array_merge([$communityId], $residentIds));
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $name = trim((string) ($row['resident_name'] ?? ''));
                    if ($name !== '') {
                        $names[] = $name;
                    }
                }
            }

            $communityRows[] = [
                'community_id' => $communityId,
                'community_name' => $communityNames[$communityId] ?? ('Comunidad #' . $communityId),
                'residents' => count($residentIds),
            ];
            $residentNamesByCommunity[] = [
                'community_name' => $communityNames[$communityId] ?? ('Comunidad #' . $communityId),
                'resident_names' => $names,
            ];
        }

        return [
            'community_rows' => $communityRows,
            'template_names' => $templateNames,
            'resident_names_by_community' => $residentNamesByCommunity,
        ];
    }
}