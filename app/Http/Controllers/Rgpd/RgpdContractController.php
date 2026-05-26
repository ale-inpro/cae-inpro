<?php

declare(strict_types=1);

namespace App\Http\Controllers\Rgpd;

use App\Core\Controller;
use App\Services\Rgpd\RgpdAccess;
use PDO;

final class RgpdContractController extends Controller
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
                c.city,
                rc.id AS contract_id,
                rc.status,
                rc.signed_at,
                rc.expires_at,
                rc.storage_path,
                rc.signed_on_paper,
                rc.original_filename
            FROM communities c
            LEFT JOIN LATERAL (
                SELECT * FROM community_rgpd_contracts x
                WHERE x.community_id = c.id
                ORDER BY x.created_at DESC
                LIMIT 1
            ) rc ON TRUE
            WHERE c.is_active = TRUE {$scope}
            ORDER BY c.name
        ";
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        $this->render('rgpd.contracts.index', [
            'title' => 'RGPD · Contratos',
            'area' => $this->currentArea(),
            'areaBaseUrl' => $this->areaBaseUrl(),
            'baseUrl' => $this->baseUrl(),
            'communities' => $rows,
        ]);
    }

    /** @param array<string, string> $params */
    public function uploadPdf(array $params = []): void
    {
        $this->assertAreaAccess();
        $pdo = $this->rgpdPdo();
        [$role, $mcId] = $this->rgpdAccessContext();
        $communityId = (int) ($params['communityId'] ?? 0);

        $community = RgpdAccess::assertCommunity($pdo, $communityId, $role, $mcId);
        if ($community === null) {
            http_response_code(404);
            $this->respond('Comunidad no encontrada');
            return;
        }

        $returnTo = $this->sanitizeContractReturnUrl(
            trim((string) ($_POST['return_to'] ?? '')),
            $communityId
        );

        $file = $_FILES['contract_pdf'] ?? null;
        if (!$file || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->flash('Seleccione un PDF válido.', 'warning', 'RGPD');
            header('Location: ' . $returnTo);
            exit;
        }

        $original = (string) ($file['name'] ?? 'contrato.pdf');
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            $this->flash('Solo se admiten archivos PDF.', 'warning', 'RGPD');
            header('Location: ' . $returnTo);
            exit;
        }

        $dir = dirname(__DIR__, 4) . '/public/uploads/rgpd-contracts/' . $communityId;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $safeName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.pdf';
        $dest = $dir . '/' . $safeName;
        if (!move_uploaded_file((string) $file['tmp_name'], $dest)) {
            $this->flash('No se pudo guardar el archivo.', 'danger', 'RGPD');
            header('Location: ' . $returnTo);
            exit;
        }

        $signedAt = trim((string) ($_POST['signed_at'] ?? ''));
        $expiresAt = trim((string) ($_POST['expires_at'] ?? ''));
        if ($signedAt === '') {
            $signedAt = date('Y-m-d');
        }
        if ($expiresAt === '') {
            $expiresAt = date('Y-m-d', strtotime($signedAt . ' +1 year'));
        }

        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $pdo->prepare("
            INSERT INTO community_rgpd_contracts
            (community_id, status, signed_at, expires_at, storage_path, original_filename, signed_on_paper, uploaded_by_user_id, created_at, updated_at)
            VALUES (:cid, 'active', :signed_at, :expires_at, :path, :orig, FALSE, :uid, NOW(), NOW())
        ")->execute([
            'cid' => $communityId,
            'signed_at' => $signedAt,
            'expires_at' => $expiresAt,
            'path' => '/uploads/rgpd-contracts/' . $communityId . '/' . $safeName,
            'orig' => $original,
            'uid' => $userId > 0 ? $userId : null,
        ]);

        $this->flash('Contrato RGPD registrado.', 'success', 'RGPD');
        header('Location: ' . $returnTo);
        exit;
    }

    /** @param array<string, string> $params */
    public function registerPaper(array $params = []): void
    {
        $this->assertAreaAccess();
        $pdo = $this->rgpdPdo();
        [$role, $mcId] = $this->rgpdAccessContext();
        $communityId = (int) ($params['communityId'] ?? 0);

        $community = RgpdAccess::assertCommunity($pdo, $communityId, $role, $mcId);
        if ($community === null) {
            http_response_code(404);
            $this->respond('Comunidad no encontrada');
            return;
        }

        $returnTo = $this->sanitizeContractReturnUrl(
            trim((string) ($_POST['return_to'] ?? '')),
            $communityId
        );

        $signedAt = trim((string) ($_POST['signed_at'] ?? date('Y-m-d')));
        $expiresAt = trim((string) ($_POST['expires_at'] ?? date('Y-m-d', strtotime('+1 year'))));
        $notes = trim((string) ($_POST['paper_notes'] ?? ''));
        $userId = (int) ($_SESSION['user']['id'] ?? 0);

        $pdo->prepare("
            INSERT INTO community_rgpd_contracts
            (community_id, status, signed_at, expires_at, signed_on_paper, paper_notes, uploaded_by_user_id, created_at, updated_at)
            VALUES (:cid, 'active', :signed_at, :expires_at, TRUE, :notes, :uid, NOW(), NOW())
        ")->execute([
            'cid' => $communityId,
            'signed_at' => $signedAt,
            'expires_at' => $expiresAt,
            'notes' => $notes !== '' ? $notes : null,
            'uid' => $userId > 0 ? $userId : null,
        ]);

        $this->flash('Contrato en papel registrado.', 'success', 'RGPD');
        header('Location: ' . $returnTo);
        exit;
    }

    private function sanitizeContractReturnUrl(string $returnTo, int $communityId): string
    {
        $ab = $this->areaBaseUrl();
        $defaultCommunity = $ab . '/rgpd/comunidades/' . $communityId . '#rgpd-documentos';

        if ($returnTo === '') {
            return $defaultCommunity;
        }

        if (!str_starts_with($returnTo, $ab . '/rgpd/')) {
            return $defaultCommunity;
        }

        return $returnTo;
    }
}
