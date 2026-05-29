<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Database;
use App\Services\AeatCotejoVerifierService;

$pdo = Database::connection();
$cfg = require dirname(__DIR__) . '/config/app.php';

$ids = $pdo->query("
    SELECT cd.id
    FROM cae_documents cd
    JOIN document_types dt ON dt.id = cd.document_type_id
    WHERE dt.name = 'Certificado de estar al corriente con Hacienda'
      AND cd.is_active = TRUE
      AND cd.extracted_aeat_csv IS NOT NULL
      AND cd.aeat_cotejo_checked_at IS NULL
    ORDER BY cd.id
")->fetchAll(PDO::FETCH_COLUMN);

$verifier = new AeatCotejoVerifierService();

foreach ($ids as $id) {
    $id = (int) $id;
    echo "Verifying doc {$id}...\n";
    $result = $verifier->verifyDocumentById($id, $pdo, $cfg);
    $err = $result['error'] ?? ($result['pdf_validation_errors'][0] ?? '');
    echo '  ok=' . (!empty($result['ok']) ? 'yes' : 'no') . ($err !== '' ? " ({$err})" : '') . "\n";
}

echo 'Backfill complete. Processed: ' . count($ids) . "\n";
