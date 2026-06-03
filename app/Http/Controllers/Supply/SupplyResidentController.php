<?php

declare(strict_types=1);

namespace App\Http\Controllers\Supply;

use App\Core\Controller;
use App\Core\Database;
use PDO;
use App\Services\Supply\SupplyContractStatus;

final class SupplyResidentController extends Controller
{
    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        $this->assertAreaAccess();

        $pdo = $this->db();
        [$role, $managerCompanyId] = $this->supplyAccessContext($pdo);
        $filterCommunityId = (int) ($_GET['community_id'] ?? 0);

        $scopeSql = '';
        if ($role === 'gestor') {
            $scopeSql = $managerCompanyId > 0
                ? ' AND c.manager_company_id = :mcid '
                : ' AND 1=0 ';
        }

        $filterSql = $filterCommunityId > 0 ? ' AND r.community_id = :filter_cid ' : '';

        $sql = "
            SELECT
                r.id,
                r.community_id,
                COALESCE(NULLIF(TRIM(CONCAT_WS(' ', r.nombre, r.apellidos)), ''), r.full_name) AS display_name,
                r.email,
                r.telefono,
                r.dni,
                r.unit_label,
                c.name AS community_name,
                (
                    SELECT COUNT(*)::int
                    FROM supply_contracts sc
                    WHERE sc.scope = 'resident'
                      AND sc.resident_id = r.id
                      AND sc.status IN ('active', 'pending_renewal')
                ) AS active_contracts_count, 
                 (SELECT COUNT(*)::int FROM supply_contracts sc WHERE sc.scope='resident' AND sc.resident_id=r.id AND sc.status<>'draft') AS total_contracts_count,
                 (SELECT COUNT(*)::int FROM supply_contracts sc WHERE sc.scope='resident' AND sc.resident_id=r.id AND sc.status='active') AS active_count,
                 (SELECT COUNT(*)::int FROM supply_contracts sc WHERE sc.scope='resident' AND sc.resident_id=r.id AND sc.status='pending_renewal') AS upcoming_count,
                 (SELECT COUNT(*)::int FROM supply_contracts sc WHERE sc.scope='resident' AND sc.resident_id=r.id AND sc.status IN ('expired','cancelled')) AS inactive_count
            FROM community_residents r
            INNER JOIN communities c ON c.id = r.community_id
            WHERE r.is_active = TRUE
              AND c.is_active = TRUE
              {$scopeSql}
              {$filterSql}
            ORDER BY c.name, display_name
        ";

        $stmt = $pdo->prepare($sql);
        if ($role === 'gestor' && $managerCompanyId > 0) {
            $stmt->bindValue(':mcid', $managerCompanyId, PDO::PARAM_INT);
        }
        if ($filterCommunityId > 0) {
            $stmt->bindValue(':filter_cid', $filterCommunityId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $residents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $communities = $this->fetchCommunitiesForFilter($pdo, $role, $managerCompanyId);

        $this->render('supply.residents.index', [
            'title' => 'Suministros · Vecinos',
            'area' => $this->currentArea(),
            'areaBaseUrl' => $this->areaBaseUrl(),
            'baseUrl' => $this->baseUrl(),
            'residents' => $residents,
            'communities' => $communities,
            'filterCommunityId' => $filterCommunityId,
        ]);
    }

    /** @param array<string, string> $params */
    public function show(array $params = []): void
    {
        $this->assertAreaAccess();

        $residentId = (int) ($params['id'] ?? 0);
        if ($residentId <= 0) {
            http_response_code(404);
            $this->respond('Vecino no encontrado');
            return;
        }

        $pdo = $this->db();
        [$role, $managerCompanyId] = $this->supplyAccessContext($pdo);

        $resident = $this->assertResidentAccess($pdo, $residentId, $role, $managerCompanyId);
        if (!$resident) {
            http_response_code(404);
            $this->respond('Vecino no encontrado');
            return;
        }

        $contracts = $this->fetchResidentContracts($pdo, $residentId);
        $companies = $this->fetchCompanies($pdo);

        $this->render('supply.residents.show', [
            'title' => 'Suministros · ' . (string) ($resident['display_name'] ?? 'Vecino'),
            'area' => $this->currentArea(),
            'areaBaseUrl' => $this->areaBaseUrl(),
            'baseUrl' => $this->baseUrl(),
            'resident' => $resident,
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

        $residentId = (int) ($params['id'] ?? 0);
        $pdo = $this->db();
        [$role, $managerCompanyId] = $this->supplyAccessContext($pdo);

        $resident = $this->assertResidentAccess($pdo, $residentId, $role, $managerCompanyId);
        if (!$resident) {
            http_response_code(404);
            $this->respond('Vecino no encontrado');
            return;
        }

        $old = $this->collectContractOldFromPost();
        $errors = $this->validateContractOld($old, 'resident', null, $residentId, $pdo);

        if ($errors !== []) {
            $this->renderResidentShowWithErrors($resident, $this->fetchResidentContracts($pdo, $residentId), $this->fetchCompanies($pdo), $errors, $old, 'nuevo');
            return;
        }

        $userId = (int) ($_SESSION['user']['id'] ?? 0);
        $adminFee = (float) str_replace(',', '.', $old['admin_fee_eur']);
        $marketerId = $old['marketer_company_id'] !== '' ? (int) $old['marketer_company_id'] : null;
        $distributorId = $old['distributor_company_id'] !== '' ? (int) $old['distributor_company_id'] : null;

        $resolvedStatus = SupplyContractStatus::resolveFromDates(
            $old['start_date'],
            $old['end_date'] !== '' ? $old['end_date'] : null
        );

        $pdo->beginTransaction();
        try {
            $insertStmt = $pdo->prepare("
                INSERT INTO supply_contracts (
                    scope, community_id, resident_id, supply_type,
                    marketer_company_id, distributor_company_id,
                    contract_number, cups, start_date, end_date, status,
                    auto_renew, admin_fee_eur, supply_address, notes,
                    created_by_user_id, updated_by_user_id, created_at, updated_at
                ) VALUES (
                    'resident', NULL, :resident_id, :supply_type,
                    :marketer_company_id, :distributor_company_id,
                    :contract_number, :cups, :start_date, :end_date, :status,
                    :auto_renew, :admin_fee_eur, :supply_address, :notes,
                    :user_id, :user_id, NOW(), NOW()
                )
                RETURNING id
            ");
            $insertStmt->execute([
                'resident_id' => $residentId,
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
            $this->storeContractPdfIfAny($pdo, $contractId, $residentId, 'resident', $userId);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $e->getMessage();
            $this->renderResidentShowWithErrors($resident, $this->fetchResidentContracts($pdo, $residentId), $this->fetchCompanies($pdo), $errors, $old, 'nuevo');
            return;
        }

        $this->flash('Suministro del vecino registrado correctamente.', 'success', 'Suministros');
        header('Location: ' . $this->areaBaseUrl() . '/suministros/vecinos/' . $residentId . '#tab-suministros');
        exit;
    }

    /** @param array<string, string> $params */
    public function update(array $params = []): void
    {
        $this->assertAreaAccess();

        $residentId = (int) ($params['id'] ?? 0);
        $contractId = (int) ($params['contractId'] ?? 0);

        $pdo = $this->db();
        [$role, $mcid] = $this->supplyAccessContext($pdo);
        $resident = $this->assertResidentAccess($pdo, $residentId, $role, $mcid);
        if (!$resident) {
            http_response_code(404);
            $this->respond('Vecino no encontrado');
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
            header('Location: ' . $this->areaBaseUrl() . '/suministros/vecinos/' . $residentId);
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
            WHERE id = :id AND scope = 'resident' AND resident_id = :rid
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
            'rid' => $residentId,
        ]);

        $file = $_FILES['contract_pdf'] ?? null;
        if ($file && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $userId = $uid;
            $dir = dirname(__DIR__, 4) . '/public/uploads/supply-contracts/residents/' . $residentId;
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
                        'path' => '/uploads/supply-contracts/residents/' . $residentId . '/' . $safeName,
                        'orig' => $original,
                        'mime' => (string) ($file['type'] ?? 'application/pdf'),
                        'size' => (int) ($file['size'] ?? 0),
                        'uid' => $userId > 0 ? $userId : null,
                    ]);
                }
            }
        }

        $this->flash('Contrato actualizado.', 'success', 'Suministros');
        header('Location: ' . $this->areaBaseUrl() . '/suministros/vecinos/' . $residentId);
        exit;
    }

    /** @param array<string, string> $params */
    public function delete(array $params = []): void
    {
        $this->assertAreaAccess();

        $residentId = (int) ($params['id'] ?? 0);
        $contractId = (int) ($params['contractId'] ?? 0);

        $pdo = $this->db();
        [$role, $mcid] = $this->supplyAccessContext($pdo);
        $resident = $this->assertResidentAccess($pdo, $residentId, $role, $mcid);
        if (!$resident) {
            http_response_code(404);
            $this->respond('Vecino no encontrado');
            return;
        }

        $docStmt = $pdo->prepare('SELECT storage_path FROM supply_contract_documents WHERE contract_id = :cid');
        $docStmt->execute(['cid' => $contractId]);
        foreach ($docStmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
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
            WHERE id = :id AND scope = 'resident' AND resident_id = :rid
        ");
        $del->execute(['id' => $contractId, 'rid' => $residentId]);

        $this->flash('Contrato eliminado.', 'success', 'Suministros');
        header('Location: ' . $this->areaBaseUrl() . '/suministros/vecinos/' . $residentId);
        exit;
    }

    /** @return array<string, string> */
    private function collectContractOldFromPost(): array
    {
        return [
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
    }

    /**
     * @param array<string, string> $old
     * @return list<string>
     */
    private function validateContractOld(array $old, string $scope, ?int $communityId, ?int $residentId, PDO $pdo): array
    {
        $errors = [];
        $allowedTypes = ['electricity', 'gas', 'water', 'telecom', 'other'];

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

        if ($old['cups'] !== '') {
            if ($scope === 'community') {
                $dupStmt = $pdo->prepare("
                    SELECT 1 FROM supply_contracts
                    WHERE scope = 'community' AND community_id = :cid
                      AND supply_type = :stype
                      AND UPPER(COALESCE(cups, '')) = UPPER(:cups)
                      AND status IN ('active', 'pending_renewal')
                    LIMIT 1
                ");
                $dupStmt->execute(['cid' => $communityId, 'stype' => $old['supply_type'], 'cups' => $old['cups']]);
            } else {
                $dupStmt = $pdo->prepare("
                    SELECT 1 FROM supply_contracts
                    WHERE scope = 'resident' AND resident_id = :rid
                      AND supply_type = :stype
                      AND UPPER(COALESCE(cups, '')) = UPPER(:cups)
                      AND status IN ('active', 'pending_renewal')
                    LIMIT 1
                ");
                $dupStmt->execute(['rid' => $residentId, 'stype' => $old['supply_type'], 'cups' => $old['cups']]);
            }
            if ($dupStmt->fetchColumn()) {
                $errors[] = 'Ya existe un contrato activo para ese tipo y CUPS.';
            }
        }

        return $errors;
    }

    private function storeContractPdfIfAny(PDO $pdo, int $contractId, int $ownerId, string $scope, int $userId): void
    {
        $file = $_FILES['contract_pdf'] ?? null;
        if (!$file || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return;
        }

        $original = (string) ($file['name'] ?? 'contrato.pdf');
        if (strtolower(pathinfo($original, PATHINFO_EXTENSION)) !== 'pdf') {
            throw new \RuntimeException('Solo se admite PDF.');
        }

        $subdir = $scope === 'resident'
            ? '/uploads/supply-contracts/residents/' . $ownerId
            : '/uploads/supply-contracts/' . $ownerId;

        $dir = dirname(__DIR__, 4) . '/public' . $subdir;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('No se pudo crear el directorio de subida.');
        }

        $safeName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.pdf';
        $dest = $dir . '/' . $safeName;
        if (!move_uploaded_file((string) $file['tmp_name'], $dest)) {
            throw new \RuntimeException('No se pudo guardar el PDF.');
        }

        $pdo->prepare("
            INSERT INTO supply_contract_documents
            (contract_id, storage_path, original_filename, mime_type, file_size_bytes, uploaded_by_user_id, created_at)
            VALUES (:cid, :path, :orig, :mime, :size, :uid, NOW())
        ")->execute([
            'cid' => $contractId,
            'path' => $subdir . '/' . $safeName,
            'orig' => $original,
            'mime' => (string) ($file['type'] ?? 'application/pdf'),
            'size' => (int) ($file['size'] ?? 0),
            'uid' => $userId > 0 ? $userId : null,
        ]);
    }

    /**
     * @param list<string> $errors
     * @param array<string, string> $old
     */
    private function renderResidentShowWithErrors(array $resident, array $contracts, array $companies, array $errors, array $old, string $activeTab): void
    {
        $this->render('supply.residents.show', [
            'title' => 'Suministros · ' . (string) ($resident['display_name'] ?? 'Vecino'),
            'area' => $this->currentArea(),
            'areaBaseUrl' => $this->areaBaseUrl(),
            'baseUrl' => $this->baseUrl(),
            'resident' => $resident,
            'contracts' => $contracts,
            'companies' => $companies,
            'formErrors' => $errors,
            'old' => $old,
            'activeTab' => $activeTab,
        ]);
    }

    private function fetchResidentContracts(PDO $pdo, int $residentId): array
    {
        $stmt = $pdo->prepare("
            SELECT sc.id, sc.supply_type, sc.contract_number, sc.cups, sc.status,
                   sc.start_date, sc.end_date, sc.admin_fee_eur, sc.supply_address,
                   sc.notes, sc.marketer_company_id, sc.distributor_company_id, sc.auto_renew,
                   mk.name AS marketer_name, mk.phone AS marketer_phone,
                   ds.name AS distributor_name, ds.phone AS distributor_phone,
                   (SELECT COUNT(*)::int FROM supply_contract_documents d WHERE d.contract_id = sc.id) AS documents_count
            FROM supply_contracts sc
            LEFT JOIN supply_companies mk ON mk.id = sc.marketer_company_id
            LEFT JOIN supply_companies ds ON ds.id = sc.distributor_company_id
            WHERE sc.scope = 'resident' AND sc.resident_id = :rid
            ORDER BY sc.created_at DESC
        ");
        $stmt->execute(['rid' => $residentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchCompanies(PDO $pdo): array
    {
        return $pdo->query("
            SELECT id, name, company_role, phone
            FROM supply_companies WHERE is_active = TRUE ORDER BY name
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchCommunitiesForFilter(PDO $pdo, string $role, int $managerCompanyId): array
    {
        $scopeSql = $role === 'gestor'
            ? ($managerCompanyId > 0 ? ' AND manager_company_id = :mcid ' : ' AND 1=0 ')
            : '';
        $stmt = $pdo->prepare("
            SELECT id, name FROM communities
            WHERE is_active = TRUE {$scopeSql}
            ORDER BY name
        ");
        if ($role === 'gestor' && $managerCompanyId > 0) {
            $stmt->bindValue(':mcid', $managerCompanyId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function assertResidentAccess(PDO $pdo, int $residentId, string $role, int $managerCompanyId): ?array
    {
        $scopeSql = '';
        if ($role === 'gestor') {
            if ($managerCompanyId <= 0) {
                return null;
            }
            $scopeSql = ' AND c.manager_company_id = :mcid ';
        }

        $stmt = $pdo->prepare("
            SELECT
                r.id,
                r.community_id,
                COALESCE(NULLIF(TRIM(CONCAT_WS(' ', r.nombre, r.apellidos)), ''), r.full_name) AS display_name,
                r.email,
                r.telefono,
                r.dni,
                r.unit_label,
                r.direccion_postal,
                c.name AS community_name,
                c.address AS community_address,
                c.city AS community_city
            FROM community_residents r
            INNER JOIN communities c ON c.id = r.community_id
            WHERE r.id = :rid AND r.is_active = TRUE AND c.is_active = TRUE
            {$scopeSql}
            LIMIT 1
        ");
        $stmt->bindValue(':rid', $residentId, PDO::PARAM_INT);
        if ($role === 'gestor') {
            $stmt->bindValue(':mcid', $managerCompanyId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

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