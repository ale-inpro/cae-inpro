<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Database;
use App\Services\AeatCotejoVerifierService;

$docId = isset($argv[1]) ? (int) $argv[1] : 0;

$pdo = Database::connection();
$cfg = require dirname(__DIR__) . '/config/app.php';

if ($docId <= 0) {
    $docId = (int) ($pdo->query("
        SELECT cd.id
        FROM cae_documents cd
        JOIN document_types dt ON dt.id = cd.document_type_id
        WHERE dt.name = 'Certificado de estar al corriente con Hacienda'
        ORDER BY cd.id DESC
        LIMIT 1
    ")->fetchColumn() ?: 0);
}

echo "Doc ID: {$docId}\n";

$stmt = $pdo->prepare('SELECT id, storage_path, extracted_aeat_csv, aeat_cotejo_checked_at FROM cae_documents WHERE id = :id');
$stmt->execute(['id' => $docId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($row);

$publicRoot = dirname(__DIR__) . '/public';
$abs = $publicRoot . ($row['storage_path'] ?? '');
echo "File exists: " . (is_file($abs) ? 'yes' : 'no') . " -> {$abs}\n";

try {
    $v = new AeatCotejoVerifierService();
    $r = $v->verifyDocumentById($docId, $pdo, $cfg);
    echo json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} catch (Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

$stmt2 = $pdo->prepare('
    SELECT aeat_cotejo_checked_at, aeat_cotejo_codigo, aeat_pdf_validation_ok,
           aeat_pdf_validation_errors, aeat_cotejo_descripcion, aeat_cotejo_curl_error
    FROM cae_documents WHERE id = :id
');
$stmt2->execute(['id' => $docId]);
print_r($stmt2->fetch(PDO::FETCH_ASSOC));
