<?php
declare(strict_types=1);

use App\Core\Database;
use App\Services\Mailer;

require __DIR__ . '/../vendor/autoload.php';

// Autoload App\ igual que bootstrap
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../app/';
    if (!str_starts_with($class, $prefix)) return;
    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (is_file($file)) require $file;
});

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pdo = Database::connection();
$app = require __DIR__ . '/../config/app.php';
$baseUrl = rtrim((string)($app['url'] ?? ''), '/');

$hasExpiresAt = (bool) $pdo->query("
    SELECT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = 'cae_documents'
          AND column_name = 'expires_at'
    )
")->fetchColumn();

if (!$hasExpiresAt) {
    echo "Falta la columna cae_documents.expires_at. Ejecuta la migración 2026_05_11_cae_doc_expiry_and_auto_requests.sql\n";
    exit(1);
}

$daysBefore = 15;   // configurable
$cooldownDays = 7;  // no reenviar en menos de X días

$requiredNames = [
    'Certificado de estar al corriente con Hacienda',
    'Certificado de estar al corriente con Seguridad Social',
    'Póliza de Responsabilidad Civil',
    'Certificado de Prevención de Riesgos Laborales',
];

// IDs de tipos requeridos
$in = implode(',', array_fill(0, count($requiredNames), '?'));
$stmt = $pdo->prepare("
    SELECT id, name
    FROM document_types
    WHERE scope = 'technician_cae'
      AND is_cae_file_type = FALSE
      AND is_active = TRUE
      AND name IN ($in)
");
foreach ($requiredNames as $k => $name) {
    $stmt->bindValue($k + 1, $name);
}
$stmt->execute();
$types = $stmt->fetchAll(PDO::FETCH_ASSOC);

$typeMap = [];
foreach ($types as $t) {
    $typeMap[(int)$t['id']] = (string)$t['name'];
}
if ($typeMap === []) {
    echo "No hay tipos de documento requeridos activos.\n";
    exit(0);
}

// técnicos activos
$techs = $pdo->query("
    SELECT id, first_name, last_name, email
    FROM technicians
    WHERE is_active = TRUE
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($techs as $tech) {
    $tid = (int)$tech['id'];
    $techEmail = trim((string)$tech['email']);
    if ($techEmail === '') continue;

    // tipo -> último documento activo
    $typeIds = array_keys($typeMap);
    $inTypes = implode(',', array_fill(0, count($typeIds), '?'));

    $sqlLatest = "
        SELECT DISTINCT ON (cd.document_type_id)
            cd.document_type_id, cd.expires_at
        FROM cae_documents cd
        JOIN cae_records cr ON cr.id = cd.cae_record_id
        WHERE cr.technician_id = ?
        AND cd.is_active = TRUE
        AND cd.is_cae_file = FALSE
        AND cd.document_type_id IN ($inTypes)
        ORDER BY cd.document_type_id, cd.uploaded_at DESC, cd.id DESC
    ";
    $stmt = $pdo->prepare($sqlLatest);

    $i = 1;
    $stmt->bindValue($i++, $tid, PDO::PARAM_INT);
    foreach ($typeIds as $typeId) {
        $stmt->bindValue($i++, (int)$typeId, PDO::PARAM_INT);
    }
    $stmt->execute();
    
    $latestDocs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $docByType = [];
    foreach ($latestDocs as $d) {
        $docByType[(int)$d['document_type_id']] = $d;
    }

    $toRequest = [];
    $today = new DateTimeImmutable('today');
    $limit = $today->modify("+{$daysBefore} days");

    foreach ($typeMap as $typeId => $typeName) {
        $doc = $docByType[$typeId] ?? null;
        if (!$doc || empty($doc['expires_at'])) {
            $toRequest[] = ['id' => $typeId, 'name' => $typeName];
            continue;
        }

        $exp = DateTimeImmutable::createFromFormat('Y-m-d', (string)$doc['expires_at']);
        if (!$exp || $exp <= $limit) {
            $toRequest[] = ['id' => $typeId, 'name' => $typeName];
        }
    }

    if ($toRequest === []) continue;

    // evitar duplicados: auto request reciente
    $stmt = $pdo->prepare("
        SELECT COUNT(*)::int
        FROM cae_document_requests
        WHERE technician_id = :tid
          AND auto_generated = TRUE
          AND created_at >= NOW() - INTERVAL '{$cooldownDays} days'
          AND token_used_at IS NULL
    ");
    $stmt->execute(['tid' => $tid]);
    if ((int)$stmt->fetchColumn() > 0) continue;

    // cae vigente (si existe)
    $stmt = $pdo->prepare("
        SELECT id
        FROM cae_records
        WHERE technician_id = :tid AND is_current = TRUE
        LIMIT 1
    ");
    $stmt->execute(['tid' => $tid]);
    $currentCaeId = (int)($stmt->fetchColumn() ?: 0);

    $token = bin2hex(random_bytes(32));
    $expires = (new DateTimeImmutable('+7 days'))->format('Y-m-d H:i:s');

    // crear solicitud auto
    $stmt = $pdo->prepare("
        INSERT INTO cae_document_requests
        (technician_id, cae_record_id, requested_by_user_id, documents_requested_json,
         custom_message, status, upload_token, token_expires_at, auto_generated, auto_reason,
         sent_at, created_at, updated_at)
        VALUES
        (:tid, :cae_id, NULL, CAST(:docs AS jsonb),
         :msg, 'sent', :token, :token_exp, TRUE, :reason,
         NOW(), NOW(), NOW())
        RETURNING id
    ");
    $customMsg = 'Solicitud automática: hay documentos caducados o próximos a caducar.';
    $stmt->execute([
        'tid' => $tid,
        'cae_id' => $currentCaeId > 0 ? $currentCaeId : null,
        'docs' => json_encode($toRequest, JSON_UNESCAPED_UNICODE),
        'msg' => $customMsg,
        'token' => $token,
        'token_exp' => $expires,
        'reason' => "caducidad<=${daysBefore}d",
    ]);
    $requestId = (int)$stmt->fetchColumn();

    $portalUrl = $baseUrl . '/portal/' . $token;
    $techName = trim(((string)$tech['first_name']) . ' ' . ((string)$tech['last_name']));

    $listHtml = '';
    foreach ($toRequest as $d) {
        $listHtml .= '<li>' . htmlspecialchars((string)$d['name']) . '</li>';
    }

    $body = "
        <h2>Actualización automática de documentación CAE</h2>
        <p>Hola <strong>" . htmlspecialchars($techName !== '' ? $techName : 'técnico/a') . "</strong>,</p>
        <p>Detectamos documentos caducados o próximos a caducar. Necesitamos actualización de:</p>
        <ul>{$listHtml}</ul>
        <p><a href='" . htmlspecialchars($portalUrl) . "'>Subir documentos en el portal</a></p>
    ";

    Mailer::send($techEmail, 'Actualización de documentación CAE', Mailer::template('Solicitud automática CAE', $body));

    // notificar admins
    $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND is_active = TRUE")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($admins as $adminId) {
        $stmtN = $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, payload_json, is_read, created_at)
            VALUES (:uid, 'cae_doc_auto_request', :title, :msg, CAST(:payload AS jsonb), FALSE, NOW())
        ");
        $stmtN->execute([
            'uid' => (int)$adminId,
            'title' => 'Solicitud automática de documentos CAE',
            'msg' => "Se solicitó actualización documental al técnico {$techName}.",
            'payload' => json_encode(['technician_id' => $tid, 'request_id' => $requestId], JSON_UNESCAPED_UNICODE),
        ]);
    }

    echo "Auto-request enviada a técnico #{$tid}\n";
}

echo "Proceso completado.\n";