<?php

declare(strict_types=1);

namespace App\Http\Controllers\Supply;

use App\Core\Controller;
use App\Core\Database;
use PDO;
use App\Services\Supply\SupplyContractStatus;

final class SupplyCommunityController extends Controller
{
    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        $this->assertAreaAccess();

        $pdo = $this->db();
        [$role, $managerCompanyId] = $this->supplyAccessContext($pdo);

        $scopeSql = '';
        if ($role === 'gestor') {
            $scopeSql = $managerCompanyId > 0
                ? ' AND c.manager_company_id = :mcid '
                : ' AND 1=0 ';
        }

        $sql = "
            SELECT
                v.community_id AS id,
                v.community_name AS name,
                v.community_city AS city,
                v.community_address AS address,
                v.total_contracts_count,
                v.active_count,
                v.upcoming_count,
                v.inactive_count,
                v.monthly_admin_fee_total_eur
            FROM v_supply_community_summary v
            INNER JOIN communities c ON c.id = v.community_id
            WHERE c.is_active = TRUE {$scopeSql}
            ORDER BY v.community_name
        ";

        $stmt = $pdo->prepare($sql);
        if ($role === 'gestor' && $managerCompanyId > 0) {
            $stmt->bindValue(':mcid', $managerCompanyId, PDO::PARAM_INT);
        }
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->render('supply.communities.index', [
            'title' => 'Suministros · Comunidades',
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

        $communityId = (int) ($params['id'] ?? 0);
        if ($communityId <= 0) {
            http_response_code(404);
            $this->respond('Comunidad no encontrada');
            return;
        }

        $pdo = $this->db();
        [$role, $managerCompanyId] = $this->supplyAccessContext($pdo);

        $community = $this->assertCommunityAccess($pdo, $communityId, $role, $managerCompanyId);
        if (!$community) {
            http_response_code(404);
            $this->respond('Comunidad no encontrada');
            return;
        }

        $contracts = $this->fetchCommunityContracts($pdo, $communityId);
        $companies = $this->fetchCompanies($pdo);

        $this->render('supply.communities.show', [
            'title' => 'Suministros · ' . (string) ($community['name'] ?? 'Comunidad'),
            'area' => $this->currentArea(),
            'areaBaseUrl' => $this->areaBaseUrl(),
            'baseUrl' => $this->baseUrl(),
            'community' => $community,
            'contracts' => $contracts,
            'companies' => $companies,
            'formErrors' => [],
            'old' => [],
            'activeTab' => 'suministros',
        ]);
    }

    /** @param array<string, string> $params */
    public function store(array $params = []): void
    {
        $this->assertAreaAccess();

        $communityId = (int) ($params['id'] ?? 0);
        if ($communityId <= 0) {
            http_response_code(404);
            $this->respond('Comunidad no encontrada');
            return;
        }

        $pdo = $this->db();
        [$role, $managerCompanyId] = $this->supplyAccessContext($pdo);

        $community = $this->assertCommunityAccess($pdo, $communityId, $role, $managerCompanyId);
        if (!$community) {
            http_response_code(404);
            $this->respond('Comunidad no encontrada');
            return;
        }

        $old = [
            'supply_type' => trim((string) ($_POST['supply_type'] ?? '')),
            'marketer_company_id' => trim((string) ($_POST['marketer_company_id'] ?? '')),
            'distributor_company_id' => trim((string) ($_POST['distributor_company_id'] ?? '')),
            'contract_number' => trim((string) ($_POST['contract_number'] ?? '')),
            'cups' => strtoupper(trim((string) ($_POST['cups'] ?? ''))),
            'start_date' => trim((string) ($_POST['start_date'] ?? '')),
            'end_date' => trim((string) ($_POST['end_date'] ?? '')),
            'auto_renew' => isset($_POST['auto_renew']) ? '1' : '0',
            'admin_fee_eur' => trim((string) ($_POST['admin_fee_eur'] ?? '0')),
            'supply_address' => trim((string) ($_POST['supply_address'] ?? '')),
            'notes' => trim((string) ($_POST['notes'] ?? '')),
        ];

        $allowedTypes = ['electricity', 'gas', 'water', 'telecom', 'other'];

        $errors = [];
        if (!in_array($old['supply_type'], $allowedTypes, true)) {
            $errors[] = 'Tipo de suministro inválido.';
        }
        if ($old['contract_number'] === '') {
            $errors[] = 'El número de contrato es obligatorio.';
        }
        if ($old['start_date'] === '') {
            $errors[] = 'La fecha de inicio es obligatoria.';
        }
        if ($old['supply_address'] === '') {
            $errors[] = 'La dirección del punto de suministro es obligatoria.';
        }
        if ($old['end_date'] !== '' && $old['start_date'] !== '' && $old['end_date'] < $old['start_date']) {
            $errors[] = 'La fecha de fin no puede ser anterior a la fecha de inicio.';
        }
        if ($old['admin_fee_eur'] !== '' && !is_numeric(str_replace(',', '.', $old['admin_fee_eur']))) {
            $errors[] = 'La comisión administrativa debe ser numérica.';
        }

        $marketerId = $old['marketer_company_id'] !== '' ? (int) $old['marketer_company_id'] : null;
        $distributorId = $old['distributor_company_id'] !== '' ? (int) $old['distributor_company_id'] : null;

        // Duplicado activo por comunidad + tipo + CUPS (si se informó CUPS)
        if ($old['cups'] !== '') {
            $dupStmt = $pdo->prepare("
                SELECT 1
                FROM supply_contracts
                WHERE scope = 'community'
                  AND community_id = :cid
                  AND supply_type = :stype
                  AND UPPER(COALESCE(cups, '')) = UPPER(:cups)
                  AND status IN ('active', 'pending_renewal')
                LIMIT 1
            ");
            $dupStmt->execute([
                'cid' => $communityId,
                'stype' => $old['supply_type'],
                'cups' => $old['cups'],
            ]);
            if ($dupStmt->fetchColumn()) {
                $errors[] = 'Ya existe un contrato activo para ese tipo y CUPS en esta comunidad.';
            }
        }

        if ($errors !== []) {
            $contracts = $this->fetchCommunityContracts($pdo, $communityId);
            $companies = $this->fetchCompanies($pdo);

            $this->render('supply.communities.show', [
                'title' => 'Suministros · ' . (string) ($community['name'] ?? 'Comunidad'),
                'area' => $this->currentArea(),
                'areaBaseUrl' => $this->areaBaseUrl(),
                'baseUrl' => $this->baseUrl(),
                'community' => $community,
                'contracts' => $contracts,
                'companies' => $companies,
                'formErrors' => $errors,
                'old' => $old,
                'activeTab' => 'nuevo',
            ]);
            return;
        }

        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $adminFee = (float) str_replace(',', '.', $old['admin_fee_eur']);

        $resolvedStatus = SupplyContractStatus::resolveFromDates(
            $old['start_date'],
            $old['end_date'] !== '' ? $old['end_date'] : null
        );

        $pdo->beginTransaction();
        try {
            $insertStmt = $pdo->prepare("
                INSERT INTO supply_contracts (
                    scope,
                    community_id,
                    resident_id,
                    supply_type,
                    marketer_company_id,
                    distributor_company_id,
                    contract_number,
                    cups,
                    start_date,
                    end_date,
                    status,
                    auto_renew,
                    admin_fee_eur,
                    supply_address,
                    notes,
                    created_by_user_id,
                    updated_by_user_id,
                    created_at,
                    updated_at
                ) VALUES (
                    'community',
                    :community_id,
                    NULL,
                    :supply_type,
                    :marketer_company_id,
                    :distributor_company_id,
                    :contract_number,
                    :cups,
                    :start_date,
                    :end_date,
                    :status,
                    :auto_renew,
                    :admin_fee_eur,
                    :supply_address,
                    :notes,
                    :user_id,
                    :user_id,
                    NOW(),
                    NOW()
                )
                RETURNING id
            ");

            $insertStmt->execute([
                'community_id' => $communityId,
                'supply_type' => $old['supply_type'],
                'marketer_company_id' => $marketerId,
                'distributor_company_id' => $distributorId,
                'contract_number' => $old['contract_number'],
                'cups' => $old['cups'] !== '' ? $old['cups'] : null,
                'start_date' => $old['start_date'],
                'end_date' => $old['end_date'] !== '' ? $old['end_date'] : null,
                'status' => $resolvedStatus,
                'auto_renew' => ($old['auto_renew'] === '1') ? 'true' : 'false',
                'admin_fee_eur' => $adminFee,
                'supply_address' => $old['supply_address'],
                'notes' => $old['notes'] !== '' ? $old['notes'] : null,
                'user_id' => $userId > 0 ? $userId : null,
            ]);

            $contractId = (int) $insertStmt->fetchColumn();

            // PDF opcional
            $file = $_FILES['contract_pdf'] ?? null;
            if ($file && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $original = (string) ($file['name'] ?? 'contrato.pdf');
                $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
                if ($ext !== 'pdf') {
                    throw new \RuntimeException('Solo se admite PDF en documento de contrato.');
                }

                $dir = dirname(__DIR__, 4) . '/public/uploads/supply-contracts/' . $communityId;
                if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                    throw new \RuntimeException('No se pudo crear el directorio de subida.');
                }

                $safeName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.pdf';
                $dest = $dir . '/' . $safeName;
                if (!move_uploaded_file((string) $file['tmp_name'], $dest)) {
                    throw new \RuntimeException('No se pudo guardar el PDF del contrato.');
                }

                $docStmt = $pdo->prepare("
                    INSERT INTO supply_contract_documents (
                        contract_id,
                        storage_path,
                        original_filename,
                        mime_type,
                        file_size_bytes,
                        uploaded_by_user_id,
                        created_at
                    ) VALUES (
                        :contract_id,
                        :storage_path,
                        :original_filename,
                        :mime_type,
                        :file_size_bytes,
                        :uploaded_by_user_id,
                        NOW()
                    )
                ");
                $docStmt->execute([
                    'contract_id' => $contractId,
                    'storage_path' => '/uploads/supply-contracts/' . $communityId . '/' . $safeName,
                    'original_filename' => $original,
                    'mime_type' => (string) ($file['type'] ?? 'application/pdf'),
                    'file_size_bytes' => (int) ($file['size'] ?? 0),
                    'uploaded_by_user_id' => $userId > 0 ? $userId : null,
                ]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $contracts = $this->fetchCommunityContracts($pdo, $communityId);
            $companies = $this->fetchCompanies($pdo);

            $errors[] = $e->getMessage();
            $this->render('supply.communities.show', [
                'title' => 'Suministros · ' . (string) ($community['name'] ?? 'Comunidad'),
                'area' => $this->currentArea(),
                'areaBaseUrl' => $this->areaBaseUrl(),
                'baseUrl' => $this->baseUrl(),
                'community' => $community,
                'contracts' => $contracts,
                'companies' => $companies,
                'formErrors' => $errors,
                'old' => $old,
                'activeTab' => 'nuevo',
            ]);
            return;
        }

        $this->flash('Suministro registrado correctamente.', 'success', 'Suministros');
        header('Location: ' . $this->areaBaseUrl() . '/suministros/comunidades/' . $communityId . '#tab-suministros');
        exit;
    }

    /** @param array<string, string> $params */
    public function update(array $params = []): void
    {
        $this->assertAreaAccess();

        $communityId = (int) ($params['id'] ?? 0);
        $contractId = (int) ($params['contractId'] ?? 0);

        $pdo = $this->db();
        [$role, $mcid] = $this->supplyAccessContext($pdo);
        $community = $this->assertCommunityAccess($pdo, $communityId, $role, $mcid);
        if (!$community) {
            http_response_code(404);
            $this->respond('Comunidad no encontrada');
            return;
        }

        $supplyType = trim((string) ($_POST['supply_type'] ?? ''));
        $contractNumber = trim((string) ($_POST['contract_number'] ?? ''));
        $cups = strtoupper(trim((string) ($_POST['cups'] ?? '')));
        $startDate = trim((string) ($_POST['start_date'] ?? ''));
        $endDate = trim((string) ($_POST['end_date'] ?? ''));
        $adminFeeRaw = trim((string) ($_POST['admin_fee_eur'] ?? '0'));
        $supplyAddress = trim((string) ($_POST['supply_address'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $marketerId = trim((string) ($_POST['marketer_company_id'] ?? '')) !== '' ? (int) $_POST['marketer_company_id'] : null;
        $distributorId = trim((string) ($_POST['distributor_company_id'] ?? '')) !== '' ? (int) $_POST['distributor_company_id'] : null;
        $autoRenew = isset($_POST['auto_renew']) ? '1' : '0';

        $allowedTypes = ['electricity', 'gas', 'water', 'telecom', 'other'];
        if (!in_array($supplyType, $allowedTypes, true) || $contractNumber === '' || $startDate === '' || $supplyAddress === '') {
            $this->flash('Revisa los campos obligatorios del contrato.', 'warning', 'Suministros');
            header('Location: ' . $this->areaBaseUrl() . '/suministros/comunidades/' . $communityId);
            exit;
        }

        $status = SupplyContractStatus::resolveFromDates($startDate, $endDate !== '' ? $endDate : null);
        $adminFee = (float) str_replace(',', '.', $adminFeeRaw);
        $uid = (int) ($_SESSION['user']['id'] ?? 0);

        $pdo->prepare("
            UPDATE supply_contracts SET
                supply_type = :supply_type,
                marketer_company_id = :marketer_company_id,
                distributor_company_id = :distributor_company_id,
                contract_number = :contract_number,
                cups = :cups,
                start_date = :start_date,
                end_date = :end_date,
                status = :status,
                auto_renew = :auto_renew,
                admin_fee_eur = :admin_fee_eur,
                supply_address = :supply_address,
                notes = :notes,
                updated_by_user_id = :uid,
                updated_at = NOW()
            WHERE id = :id AND scope = 'community' AND community_id = :cid
        ")->execute([
            'supply_type' => $supplyType,
            'marketer_company_id' => $marketerId,
            'distributor_company_id' => $distributorId,
            'contract_number' => $contractNumber,
            'cups' => $cups !== '' ? $cups : null,
            'start_date' => $startDate,
            'end_date' => $endDate !== '' ? $endDate : null,
            'status' => $status,
            'auto_renew' => $autoRenew === '1' ? 'true' : 'false',
            'admin_fee_eur' => $adminFee,
            'supply_address' => $supplyAddress,
            'notes' => $notes !== '' ? $notes : null,
            'uid' => $uid > 0 ? $uid : null,
            'id' => $contractId,
            'cid' => $communityId,
        ]);

        // PDF opcional en edición
        $file = $_FILES['contract_pdf'] ?? null;
        if ($file && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $userId = $uid;
            $dir = dirname(__DIR__, 4) . '/public/uploads/supply-contracts/' . $communityId;
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $original = (string) ($file['name'] ?? 'contrato.pdf');
            if (strtolower(pathinfo($original, PATHINFO_EXTENSION)) === 'pdf') {
                $safeName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.pdf';
                $dest = $dir . '/' . $safeName;
                if (move_uploaded_file((string) $file['tmp_name'], $dest)) {
                    $pdo->prepare("
                        INSERT INTO supply_contract_documents (contract_id, storage_path, original_filename, mime_type, file_size_bytes, uploaded_by_user_id, created_at)
                        VALUES (:cid, :path, :orig, :mime, :size, :uid, NOW())
                    ")->execute([
                        'cid' => $contractId,
                        'path' => '/uploads/supply-contracts/' . $communityId . '/' . $safeName,
                        'orig' => $original,
                        'mime' => (string) ($file['type'] ?? 'application/pdf'),
                        'size' => (int) ($file['size'] ?? 0),
                        'uid' => $userId > 0 ? $userId : null,
                    ]);
                }
            }
        }

        $this->flash('Contrato actualizado.', 'success', 'Suministros');
        header('Location: ' . $this->areaBaseUrl() . '/suministros/comunidades/' . $communityId);
        exit;
    }

    /** @param array<string, string> $params */
    public function delete(array $params = []): void
    {
        $this->assertAreaAccess();

        $communityId = (int) ($params['id'] ?? 0);
        $contractId = (int) ($params['contractId'] ?? 0);

        $pdo = $this->db();
        [$role, $mcid] = $this->supplyAccessContext($pdo);
        $community = $this->assertCommunityAccess($pdo, $communityId, $role, $mcid);
        if (!$community) {
            http_response_code(404);
            $this->respond('Comunidad no encontrada');
            return;
        }

        // Borra primero ficheros físicos asociados (si existen)
        $docStmt = $pdo->prepare("
            SELECT storage_path
            FROM supply_contract_documents
            WHERE contract_id = :cid
        ");
        $docStmt->execute(['cid' => $contractId]);
        $docs = $docStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($docs as $d) {
            $rel = (string) ($d['storage_path'] ?? '');
            if ($rel !== '') {
                $abs = dirname(__DIR__, 4) . '/public' . $rel;
                if (is_file($abs)) {
                    @unlink($abs);
                }
            }
        }

        $del = $pdo->prepare("
            DELETE FROM supply_contracts
            WHERE id = :id
            AND scope = 'community'
            AND community_id = :community_id
        ");
        $del->execute([
            'id' => $contractId,
            'community_id' => $communityId,
        ]);

        $this->flash('Contrato eliminado.', 'success', 'Suministros');
        header('Location: ' . $this->areaBaseUrl() . '/suministros/comunidades/' . $communityId);
        exit;
    }

    /** @param array<string, string> $params */
    public function previewDocument(array $params = []): void
    {
        $this->assertAreaAccess();
        $contractId = (int) ($params['contractId'] ?? 0);
        if ($contractId <= 0) {
            http_response_code(404);
            $this->respond('Documento no encontrado');
            return;
        }

        $pdo = $this->db();
        [$role, $mcid] = $this->supplyAccessContext($pdo);

        $sql = "
            SELECT d.storage_path, d.original_filename,
                COALESCE(c_comm.manager_company_id, c_res.manager_company_id) AS manager_company_id
            FROM supply_contract_documents d
            INNER JOIN supply_contracts sc ON sc.id = d.contract_id
            LEFT JOIN communities c_comm ON c_comm.id = sc.community_id
            LEFT JOIN community_residents res ON res.id = sc.resident_id
            LEFT JOIN communities c_res ON c_res.id = res.community_id
            WHERE d.contract_id = :contract_id
            ORDER BY d.created_at DESC
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['contract_id' => $contractId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            http_response_code(404);
            $this->respond('Documento no encontrado');
            return;
        }
        if ($role === 'gestor' && (int) ($row['manager_company_id'] ?? 0) !== $mcid) {
            http_response_code(403);
            $this->respond('No autorizado');
            return;
        }

        $abs = dirname(__DIR__, 4) . '/public' . (string) $row['storage_path'];
        if (!is_file($abs)) {
            http_response_code(404);
            $this->respond('Archivo no disponible');
            return;
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename((string) ($row['original_filename'] ?? 'contrato.pdf')) . '"');
        header('Content-Length: ' . (string) filesize($abs));
        readfile($abs);
        exit;
    }

    /** @param array<string, string> $params */
    public function downloadDocument(array $params = []): void
    {
        $this->assertAreaAccess();

        $contractId = (int) ($params['contractId'] ?? 0);
        if ($contractId <= 0) {
            http_response_code(404);
            $this->respond('Documento no encontrado');
            return;
        }

        $pdo = $this->db();
        [$role, $mcid] = $this->supplyAccessContext($pdo);

        $sql = "
            SELECT
                d.storage_path,
                d.original_filename,
                COALESCE(c_comm.manager_company_id, c_res.manager_company_id) AS manager_company_id
            FROM supply_contract_documents d
            INNER JOIN supply_contracts sc ON sc.id = d.contract_id
            LEFT JOIN communities c_comm ON c_comm.id = sc.community_id
            LEFT JOIN community_residents res ON res.id = sc.resident_id
            LEFT JOIN communities c_res ON c_res.id = res.community_id
            WHERE d.contract_id = :contract_id
            ORDER BY d.created_at DESC
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['contract_id' => $contractId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            http_response_code(404);
            $this->respond('Documento no encontrado');
            return;
        }

        if ($role === 'gestor' && (int) ($row['manager_company_id'] ?? 0) !== $mcid) {
            http_response_code(403);
            $this->respond('No autorizado');
            return;
        }

        $rel = (string) ($row['storage_path'] ?? '');
        $abs = dirname(__DIR__, 4) . '/public' . $rel;
        if (!is_file($abs)) {
            http_response_code(404);
            $this->respond('Archivo no disponible');
            return;
        }

        $name = trim((string) ($row['original_filename'] ?? 'contrato.pdf'));
        if ($name === '') {
            $name = 'contrato.pdf';
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($name) . '"');
        header('Content-Length: ' . (string) filesize($abs));
        readfile($abs);
        exit;
    }

    private function fetchCommunityContracts(PDO $pdo, int $communityId): array
    {
        $contractsSql = "
            SELECT
                sc.id,
                sc.supply_type,
                sc.contract_number,
                sc.cups,
                sc.status,
                sc.start_date,
                sc.end_date,
                sc.admin_fee_eur,
                sc.auto_renew,
                sc.supply_address,
                sc.notes,
                sc.marketer_company_id,
                sc.distributor_company_id,
                mk.name AS marketer_name,
                mk.phone AS marketer_phone,
                ds.name AS distributor_name,
                ds.phone AS distributor_phone,
                (
                    SELECT COUNT(*)::int
                    FROM supply_contract_documents d
                    WHERE d.contract_id = sc.id
                ) AS documents_count
            FROM supply_contracts sc
            LEFT JOIN supply_companies mk ON mk.id = sc.marketer_company_id
            LEFT JOIN supply_companies ds ON ds.id = sc.distributor_company_id
            WHERE sc.scope = 'community'
              AND sc.community_id = :cid
            ORDER BY sc.created_at DESC
        ";

        $contractsStmt = $pdo->prepare($contractsSql);
        $contractsStmt->bindValue(':cid', $communityId, PDO::PARAM_INT);
        $contractsStmt->execute();
        return $contractsStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchCompanies(PDO $pdo): array
    {
        $sql = "
            SELECT id, name, company_role, phone
            FROM supply_companies
            WHERE is_active = TRUE
            ORDER BY name
        ";
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function assertCommunityAccess(PDO $pdo, int $communityId, string $role, int $managerCompanyId): ?array
    {
        $scopeSql = '';
        if ($role === 'gestor') {
            if ($managerCompanyId <= 0) {
                return null;
            }
            $scopeSql = ' AND c.manager_company_id = :mcid ';
        }

        $communitySql = "
            SELECT c.id, c.name, c.address, c.city
            FROM communities c
            WHERE c.id = :cid
            AND c.is_active = TRUE
            {$scopeSql}
            LIMIT 1
        ";

        $communityStmt = $pdo->prepare($communitySql);
        $communityStmt->bindValue(':cid', $communityId, PDO::PARAM_INT);
        if ($role === 'gestor') {
            $communityStmt->bindValue(':mcid', $managerCompanyId, PDO::PARAM_INT);
        }
        $communityStmt->execute();

        $row = $communityStmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @return array{0:string,1:int}
     */
    private function supplyAccessContext(PDO $pdo): array
    {
        $role = (string) ($_SESSION['user']['role'] ?? '');
        if ($role === 'gestor') {
            return ['gestor', $this->currentUserManagerCompanyId($pdo)];
        }

        return ['admin', 0];
    }

    private function currentUserManagerCompanyId(PDO $pdo): int
    {
        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        if ($userId <= 0) {
            return 0;
        }

        $stmt = $pdo->prepare('SELECT manager_company_id FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function db(): PDO
    {
        return Database::connection();
    }
}