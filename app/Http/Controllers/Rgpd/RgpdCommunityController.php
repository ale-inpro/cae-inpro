<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rgpd;

use App\Core\Controller;
use App\Services\Rgpd\RgpdAccess;
use App\Services\Rgpd\RgpdTemplateCompliance;
use PDO;
use App\Services\Rgpd\RgpdSignedPdfService;
use DateTimeImmutable;
use App\Services\Rgpd\RgpdPdfMergeService;
use App\Services\Rgpd\RgpdBlankPdfZipService;
use App\Services\Rgpd\RgpdTemplateRenderer;

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

        $templatesForDownload = $pdo->query("
            SELECT id, name
            FROM rgpd_templates
            WHERE is_active = TRUE
            ORDER BY name
        ")->fetchAll(PDO::FETCH_ASSOC);

        $this->render('rgpd.communities.index', [
            'title' => 'RGPD · Comunidades',
            'area' => $this->currentArea(),
            'areaBaseUrl' => $this->areaBaseUrl(),
            'baseUrl' => $this->baseUrl(),
            'communities' => $rows,
            'templatesForDownload' => $templatesForDownload,
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
                s.template_id, s.paper_signed_pdf_path,
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

        $paperEligibleByResident = [];
        foreach ($residentRows as $row) {
            $rid = (int) ($row['id'] ?? 0);
            if ($rid <= 0) {
                continue;
            }
            $paperEligibleByResident[$rid] = RgpdTemplateCompliance::paperUploadableTemplates($pdo, $rid);
        }

        $blankResidentsByTemplate = [];
        foreach ($documentSummaries as $doc) {
            $tid = (int) ($doc['template_id'] ?? 0);
            if ($tid > 0) {
                $blankResidentsByTemplate[$tid] = RgpdTemplateCompliance::blankDownloadableResidents($pdo, $id, $tid);
            }
        }

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
            'paperEligibleByResident' => $paperEligibleByResident,
            'blankResidentsByTemplate' => $blankResidentsByTemplate,
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

    /** @param array<string, string> $params */
    public function downloadResidentSignedDocuments(array $params = []): void
    {
        $this->assertAreaAccess();
        $pdo = $this->rgpdPdo();
        [$role, $mcId] = $this->rgpdAccessContext();

        $communityId = (int) ($params['id'] ?? 0);
        $residentId = (int) ($params['residentId'] ?? 0);

        $community = RgpdAccess::assertCommunity($pdo, $communityId, $role, $mcId);
        if ($community === null) {
            http_response_code(404);
            $this->respond('Comunidad no encontrada');
            return;
        }

        $residentStmt = $pdo->prepare("
            SELECT id, TRIM(CONCAT_WS(' ', nombre, apellidos)) AS resident_name
            FROM community_residents
            WHERE id = :rid AND community_id = :cid AND is_active = TRUE
            LIMIT 1
        ");
        $residentStmt->execute(['rid' => $residentId, 'cid' => $communityId]);
        $resident = $residentStmt->fetch(PDO::FETCH_ASSOC);
        if (!$resident) {
            $this->flash('Vecino no válido para esta comunidad.', 'warning', 'RGPD');
            header('Location: ' . $this->areaBaseUrl() . '/rgpd/comunidades/' . $communityId . '#rgpd-vecinos');
            exit;
        }

        $selectedTemplateIds = array_map('intval', (array) ($_POST['template_ids'] ?? []));
        $selectedTemplateIds = array_values(array_unique(array_filter($selectedTemplateIds, static fn(int $v): bool => $v > 0)));

        $includePaper = !empty($_POST['include_paper']);

        if ($selectedTemplateIds !== []) {
            $in = implode(',', array_fill(0, count($selectedTemplateIds), '?'));
            $sql = "
                SELECT s.id, s.status, s.rendered_html, s.signature_image_path, s.signed_at, s.signer_ip, s.signer_user_agent,
                        t.name AS template_name
                FROM rgpd_signature_requests s
                JOIN rgpd_templates t ON t.id = s.template_id
                WHERE s.community_id = ? AND s.resident_id = ?
                    AND s.status IN ('signed'" . ($includePaper ? ",'paper'" : '') . ")
                    AND s.template_id IN ({$in})
                ORDER BY s.signed_at DESC, s.created_at DESC
            ";
            $stmt = $pdo->prepare($sql);
            $paramsExec = array_merge([$communityId, $residentId], $selectedTemplateIds);
            $stmt->execute($paramsExec);
        } else {
            $stmt = $pdo->prepare("
                SELECT s.id, s.status, s.rendered_html, s.signature_image_path, s.signed_at, s.signer_ip, s.signer_user_agent,
                        t.name AS template_name
                FROM rgpd_signature_requests s
                JOIN rgpd_templates t ON t.id = s.template_id
                WHERE s.community_id = :cid
                    AND s.resident_id = :rid
                    AND s.status IN ('signed'" . ($includePaper ? ",'paper'" : '') . ")
                ORDER BY s.signed_at DESC, s.created_at DESC
            ");
            $stmt->execute([
                'cid' => $communityId,
                'rid' => $residentId,
            ]);
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            $this->flash('No hay documentos firmados con los filtros seleccionados.', 'info', 'RGPD');
            header('Location: ' . $this->areaBaseUrl() . '/rgpd/comunidades/' . $communityId . '#rgpd-vecinos');
            exit;
        }

        $docs = [];
        foreach ($rows as $row) {
            $sigPath = trim((string) ($row['signature_image_path'] ?? ''));
            $sigDataUri = '';
            if ($sigPath !== '') {
                $abs = dirname(__DIR__, 4) . '/public' . $sigPath;
                if (is_file($abs)) {
                    $raw = file_get_contents($abs);
                    if ($raw !== false) {
                        $sigDataUri = 'data:image/png;base64,' . base64_encode($raw);
                    }
                }
            }

            $signedAtRaw = (string) ($row['signed_at'] ?? '');
            $signedAtLabel = '—';
            if ($signedAtRaw !== '') {
                try {
                    $signedAtLabel = (new DateTimeImmutable($signedAtRaw))->format('d/m/Y H:i');
                } catch (\Throwable) {
                    $signedAtLabel = $signedAtRaw;
                }
            }

            $docs[] = [
                'template_name' => (string) ($row['template_name'] ?? 'Documento RGPD'),
                'status' => (string) ($row['status'] ?? ''),
                'rendered_html' => (string) ($row['rendered_html'] ?? ''),
                'signature_data_uri' => $sigDataUri,
                'signed_at_label' => $signedAtLabel,
                'signer_ip' => (string) ($row['signer_ip'] ?? ''),
                'signer_user_agent' => (string) ($row['signer_user_agent'] ?? ''),
            ];
        }

        $communityName = (string) ($community['name'] ?? ('Comunidad ' . $communityId));
        $residentName = (string) ($resident['resident_name'] ?? ('Vecino ' . $residentId));

        $pdfBytes = RgpdSignedPdfService::renderResidentSignedBundle($communityName, $residentName, $docs);

        $safeCommunity = preg_replace('/[^A-Za-z0-9_-]+/', '_', $communityName) ?: 'comunidad';
        $safeResident = preg_replace('/[^A-Za-z0-9_-]+/', '_', $residentName) ?: 'vecino';
        $filename = 'rgpd_firmados_' . $safeCommunity . '_' . $safeResident . '_' . date('Ymd_His') . '.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfBytes));
        echo $pdfBytes;
        exit;
    }

    /** @param array<string, string> $params */
    public function downloadCommunitySignedDocuments(array $params = []): void
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

        $selectedTemplateIds = array_map('intval', (array) ($_POST['template_ids'] ?? []));
        $selectedTemplateIds = array_values(array_unique(array_filter($selectedTemplateIds, static fn(int $v): bool => $v > 0)));
        $includePaper = !empty($_POST['include_paper']);
        $includeContract = !empty($_POST['include_contract']);

        if ($selectedTemplateIds === []) {
            $this->flash('Seleccione al menos un tipo de documento.', 'warning', 'RGPD');
            header('Location: ' . $this->areaBaseUrl() . '/rgpd/comunidades');
            exit;
        }

        $in = implode(',', array_fill(0, count($selectedTemplateIds), '?'));
        $sql = "
            SELECT s.id, s.status, s.rendered_html, s.signature_image_path, s.signed_at, s.signer_ip, s.signer_user_agent,
                t.name AS template_name,
                TRIM(CONCAT_WS(' ', r.nombre, r.apellidos)) AS resident_name
            FROM rgpd_signature_requests s
            JOIN rgpd_templates t ON t.id = s.template_id
            JOIN community_residents r ON r.id = s.resident_id
            WHERE s.community_id = ?
            AND s.status IN ('signed'" . ($includePaper ? ",'paper'" : '') . ")
            AND s.template_id IN ({$in})
            ORDER BY resident_name ASC, s.signed_at DESC, s.created_at DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$communityId], $selectedTemplateIds));

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            $this->flash('No hay documentos firmados con los filtros seleccionados.', 'info', 'RGPD');
            header('Location: ' . $this->areaBaseUrl() . '/rgpd/comunidades');
            exit;
        }

        $docs = [];
        foreach ($rows as $row) {
            $sigPath = trim((string) ($row['signature_image_path'] ?? ''));
            $sigDataUri = '';
            if ($sigPath !== '') {
                $abs = dirname(__DIR__, 4) . '/public' . $sigPath;
                if (is_file($abs)) {
                    $raw = file_get_contents($abs);
                    if ($raw !== false) {
                        $sigDataUri = 'data:image/png;base64,' . base64_encode($raw);
                    }
                }
            }

            $signedAtRaw = (string) ($row['signed_at'] ?? '');
            $signedAtLabel = '—';
            if ($signedAtRaw !== '') {
                try {
                    $signedAtLabel = (new DateTimeImmutable($signedAtRaw))->format('d/m/Y H:i');
                } catch (\Throwable) {
                    $signedAtLabel = $signedAtRaw;
                }
            }

            $docs[] = [
                'template_name' => (string) ($row['template_name'] ?? 'Documento RGPD'),
                'resident_name' => (string) ($row['resident_name'] ?? '—'),
                'status' => (string) ($row['status'] ?? ''),
                'rendered_html' => (string) ($row['rendered_html'] ?? ''),
                'signature_data_uri' => $sigDataUri,
                'signed_at_label' => $signedAtLabel,
                'signer_ip' => (string) ($row['signer_ip'] ?? ''),
                'signer_user_agent' => (string) ($row['signer_user_agent'] ?? ''),
            ];
        }

        $communityName = (string) ($community['name'] ?? ('Comunidad ' . $communityId));
        $signedDocsPdfBytes = RgpdSignedPdfService::renderCommunitySignedBundle($communityName, $docs);

        $tmpFiles = [];
        try {
            // 1) PDF generado al vuelo (firmados)
            $signedTmp = tempnam(sys_get_temp_dir(), 'rgpd_signed_');
            if ($signedTmp === false) {
                throw new \RuntimeException('No se pudo crear archivo temporal.');
            }
            $signedTmpPdf = $signedTmp . '.pdf';
            @rename($signedTmp, $signedTmpPdf);
            file_put_contents($signedTmpPdf, $signedDocsPdfBytes);
            $tmpFiles[] = $signedTmpPdf;

            // 2) Contrato comunidad (si se marca y existe)
            if ($includeContract) {
                $contractStmt = $pdo->prepare("
                    SELECT storage_path
                    FROM community_rgpd_contracts
                    WHERE community_id = :cid
                    AND storage_path IS NOT NULL
                    AND storage_path <> ''
                    ORDER BY created_at DESC
                    LIMIT 1
                ");
                $contractStmt->execute(['cid' => $communityId]);
                $contractPath = trim((string) ($contractStmt->fetchColumn() ?: ''));
                if ($contractPath !== '') {
                    $contractAbs = dirname(__DIR__, 4) . '/public' . $contractPath;
                    if (is_file($contractAbs)) {
                        $tmpFiles[] = $contractAbs;
                    }
                }
            }

            $mergedPdfBytes = RgpdPdfMergeService::mergeFiles($tmpFiles);

            $safeCommunity = preg_replace('/[^A-Za-z0-9_-]+/', '_', $communityName) ?: 'comunidad';
            $filename = 'rgpd_firmados_comunidad_' . $safeCommunity . '_' . date('Ymd_His') . '.pdf';

            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($mergedPdfBytes));
            echo $mergedPdfBytes;
            exit;
        } finally {
            foreach ($tmpFiles as $f) {
                if (str_contains($f, sys_get_temp_dir()) && is_file($f)) {
                    @unlink($f);
                }
            }
        }
    }

    /** @param array<string, string> $params */
    public function downloadBlankTemplatesZip(array $params = []): void
    {
        $this->assertAreaAccess();
        $pdo = $this->rgpdPdo();
        [$role, $mcId] = $this->rgpdAccessContext();

        $communityId = (int) ($params['id'] ?? 0);
        $templateId = (int) ($params['templateId'] ?? 0);

        $community = RgpdAccess::assertCommunity($pdo, $communityId, $role, $mcId);
        if ($community === null) {
            http_response_code(404);
            $this->respond('Comunidad no encontrada');
            return;
        }

        $tplStmt = $pdo->prepare('SELECT id, name, body_html FROM rgpd_templates WHERE id = :id AND is_active = TRUE LIMIT 1');
        $tplStmt->execute(['id' => $templateId]);
        $template = $tplStmt->fetch(PDO::FETCH_ASSOC);
        if (!$template) {
            $this->flash('Plantilla no válida.', 'warning', 'RGPD');
            header('Location: ' . $this->areaBaseUrl() . '/rgpd/comunidades/' . $communityId . '#rgpd-documentos');
            exit;
        }

        $residentIds = array_map('intval', (array) ($_POST['resident_ids'] ?? []));
        $residentIds = array_values(array_unique(array_filter($residentIds, static fn(int $v): bool => $v > 0)));
        if ($residentIds === []) {
            $this->flash('Seleccione al menos un vecino.', 'warning', 'RGPD');
            header('Location: ' . $this->areaBaseUrl() . '/rgpd/comunidades/' . $communityId . '#rgpd-documentos');
            exit;
        }

        $in = implode(',', array_fill(0, count($residentIds), '?'));
        $resStmt = $pdo->prepare("
            SELECT * FROM community_residents
            WHERE community_id = ? AND is_active = TRUE AND id IN ({$in})
            ORDER BY nombre, apellidos
        ");
        $resStmt->execute(array_merge([$communityId], $residentIds));
        $residents = $resStmt->fetchAll(PDO::FETCH_ASSOC);

        $files = [];
        $communityName = (string) ($community['name'] ?? 'Comunidad');
        $tplName = (string) ($template['name'] ?? 'Plantilla');
        $tplSlug = RgpdBlankPdfZipService::slug($tplName);
        $commSlug = RgpdBlankPdfZipService::slug($communityName);

        foreach ($residents as $resident) {
            $rid = (int) ($resident['id'] ?? 0);
            if (RgpdTemplateCompliance::residentTemplateState($pdo, $rid, $templateId) === RgpdTemplateCompliance::STATE_SIGNED) {
                continue;
            }

            $html = RgpdTemplateRenderer::render((string) ($template['body_html'] ?? ''), $community, $resident);
            $pdf = RgpdBlankPdfZipService::renderBlankPdf(
                $communityName,
                app_resident_name($resident),
                $tplName,
                $html
            );

            $resSlug = RgpdBlankPdfZipService::slug(app_resident_name($resident));
            $files[] = [
                'filename' => "RGPD_{$commSlug}_{$tplSlug}_{$resSlug}.pdf",
                'pdf_bytes' => $pdf,
            ];
        }

        if ($files === []) {
            $this->flash('Ningún vecino seleccionado puede recibir esta plantilla (ya firmada).', 'info', 'RGPD');
            header('Location: ' . $this->areaBaseUrl() . '/rgpd/comunidades/' . $communityId . '#rgpd-documentos');
            exit;
        }

        $zipBytes = RgpdBlankPdfZipService::buildZip($files);
        $zipName = 'RGPD_plantillas_' . $commSlug . '_' . $tplSlug . '_' . date('Ymd_His') . '.zip';

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipName . '"');
        header('Content-Length: ' . strlen($zipBytes));
        echo $zipBytes;
        exit;
    }

    /** @param array<string, string> $params */
    public function uploadResidentPaperSignatures(array $params = []): void
    {
        $this->assertAreaAccess();
        $pdo = $this->rgpdPdo();
        [$role, $mcId] = $this->rgpdAccessContext();

        $communityId = (int) ($params['id'] ?? 0);
        $residentId = (int) ($params['residentId'] ?? 0);
        $userId = (int) ($_SESSION['user']['id'] ?? 0);

        $community = RgpdAccess::assertCommunity($pdo, $communityId, $role, $mcId);
        if ($community === null) {
            http_response_code(404);
            $this->respond('Comunidad no encontrada');
            return;
        }

        $resStmt = $pdo->prepare('SELECT * FROM community_residents WHERE id = :rid AND community_id = :cid AND is_active = TRUE LIMIT 1');
        $resStmt->execute(['rid' => $residentId, 'cid' => $communityId]);
        $resident = $resStmt->fetch(PDO::FETCH_ASSOC);
        if (!$resident) {
            $this->flash('Vecino no válido.', 'warning', 'RGPD');
            header('Location: ' . $this->areaBaseUrl() . '/rgpd/comunidades/' . $communityId . '#rgpd-vecinos');
            exit;
        }

        $templateIdsRaw = (array) ($_POST['template_id'] ?? []);
        $files = $_FILES['paper_pdf'] ?? null;
        if (!$files) {
            $this->flash('Añada al menos una plantilla y su PDF.', 'warning', 'RGPD');
            header('Location: ' . $this->areaBaseUrl() . '/rgpd/comunidades/' . $communityId . '#rgpd-vecinos');
            exit;
        }

        $names = (array) ($files['name'] ?? []);
        $tmps = (array) ($files['tmp_name'] ?? []);
        $errors = (array) ($files['error'] ?? []);
        $rowCount = max(count($templateIdsRaw), count($names));

        $pairs = [];
        for ($i = 0; $i < $rowCount; $i++) {
            $tid = (int) ($templateIdsRaw[$i] ?? 0);
            $hasFile = isset($names[$i]) && (string) $names[$i] !== '';
            if ($tid <= 0 && !$hasFile) {
                continue; // fila vacía: ignorar
            }
            if ($tid <= 0 || !$hasFile) {
                $this->flash('Complete plantilla y PDF en cada fila, o elimine las filas vacías.', 'warning', 'RGPD');
                header('Location: ' . $this->areaBaseUrl() . '/rgpd/comunidades/' . $communityId . '#rgpd-vecinos');
                exit;
            }
            $pairs[] = ['template_id' => $tid, 'index' => $i];
        }

        if ($pairs === []) {
            $this->flash('Añada al menos una plantilla y su PDF.', 'warning', 'RGPD');
            header('Location: ' . $this->areaBaseUrl() . '/rgpd/comunidades/' . $communityId . '#rgpd-vecinos');
            exit;
        }

        $templateIds = array_column($pairs, 'template_id');
        if (count($templateIds) !== count(array_unique($templateIds))) {
            $this->flash('No puede repetir la misma plantilla en un mismo envío.', 'warning', 'RGPD');
            header('Location: ' . $this->areaBaseUrl() . '/rgpd/comunidades/' . $communityId . '#rgpd-vecinos');
            exit;
        }

        $uploadDir = dirname(__DIR__, 4) . '/public/uploads/rgpd-paper/' . $communityId;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $pdo->beginTransaction();
        try {
            $saved = 0;
            foreach ($pairs as $pair) {
                $templateId = (int) $pair['template_id'];
                $idx = (int) $pair['index'];

                if (RgpdTemplateCompliance::residentTemplateState($pdo, $residentId, $templateId) === RgpdTemplateCompliance::STATE_SIGNED) {
                    continue;
                }

                if ((int) ($errors[$idx] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    throw new \RuntimeException('Archivo inválido en la fila ' . ($idx + 1));
                }

                $original = (string) ($names[$idx] ?? '');
                if (strtolower(pathinfo($original, PATHINFO_EXTENSION)) !== 'pdf') {
                    throw new \RuntimeException('Solo se admiten PDF.');
                }

                $tplStmt = $pdo->prepare('SELECT id, body_html FROM rgpd_templates WHERE id = :id AND is_active = TRUE LIMIT 1');
                $tplStmt->execute(['id' => $templateId]);
                $tpl = $tplStmt->fetch(PDO::FETCH_ASSOC);
                if (!$tpl) {
                    throw new \RuntimeException('Plantilla no válida.');
                }

                $safeName = date('Ymd_His') . '_' . $residentId . '_' . $templateId . '_' . bin2hex(random_bytes(4)) . '.pdf';
                $dest = $uploadDir . '/' . $safeName;
                if (!move_uploaded_file((string) ($tmps[$idx] ?? ''), $dest)) {
                    throw new \RuntimeException('No se pudo guardar el PDF.');
                }

                $storagePath = '/uploads/rgpd-paper/' . $communityId . '/' . $safeName;
                $renderedHtml = RgpdTemplateRenderer::render((string) ($tpl['body_html'] ?? ''), $community, $resident);

                $pendingStmt = $pdo->prepare("
                    SELECT id FROM rgpd_signature_requests
                    WHERE resident_id = :rid AND template_id = :tid AND status = 'pending'
                    ORDER BY id DESC LIMIT 1
                ");
                $pendingStmt->execute(['rid' => $residentId, 'tid' => $templateId]);
                $pendingId = (int) ($pendingStmt->fetchColumn() ?: 0);

                if ($pendingId > 0) {
                    $pdo->prepare("
                        UPDATE rgpd_signature_requests
                        SET status = 'paper',
                            signed_on_paper = TRUE,
                            paper_signed_pdf_path = :path,
                            rendered_html = :html,
                            paper_recorded_by_user_id = :uid,
                            signed_at = NOW(),
                            updated_at = NOW()
                        WHERE id = :id
                    ")->execute([
                        'id' => $pendingId,
                        'path' => $storagePath,
                        'html' => $renderedHtml,
                        'uid' => $userId > 0 ? $userId : null,
                    ]);
                } else {
                    $token = bin2hex(random_bytes(32));
                    $pdo->prepare("
                        INSERT INTO rgpd_signature_requests
                        (campaign_id, community_id, resident_id, template_id, token, status, rendered_html,
                            signed_on_paper, paper_signed_pdf_path, paper_recorded_by_user_id, signed_at, created_at, updated_at)
                        VALUES (NULL, :cid, :rid, :tid, :token, 'paper', :html,
                                TRUE, :path, :uid, NOW(), NOW(), NOW())
                    ")->execute([
                        'cid' => $communityId,
                        'rid' => $residentId,
                        'tid' => $templateId,
                        'token' => $token,
                        'html' => $renderedHtml,
                        'path' => $storagePath,
                        'uid' => $userId > 0 ? $userId : null,
                    ]);
                }
                $saved++;
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $this->flash($e->getMessage(), 'danger', 'RGPD');
            header('Location: ' . $this->areaBaseUrl() . '/rgpd/comunidades/' . $communityId . '#rgpd-vecinos');
            exit;
        }

        $this->flash($saved > 0 ? 'Firma(s) en papel registrada(s).' : 'No se registró ninguna firma.', $saved > 0 ? 'success' : 'info', 'RGPD');
        header('Location: ' . $this->areaBaseUrl() . '/rgpd/comunidades/' . $communityId . '#rgpd-vecinos');
        exit;
    }
}
