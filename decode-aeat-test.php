<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\Services\AeatCotejoInternetService;

$cfg = require __DIR__ . '/config/app.php';
$csv = '8KFA439XY6N4SP24'; // tu CSV real

$svc = new AeatCotejoInternetService();
$res = $svc->cotejar($csv, false, [
    'endpoint' => (string) ($cfg['aeat_cotejo_endpoint'] ?? ''),
    'client_cert_path' => (string) ($cfg['aeat_cotejo_client_cert_path'] ?? ''),
    'client_cert_password' => (string) ($cfg['aeat_cotejo_client_cert_password'] ?? ''),
    'ca_bundle' => (string) ($cfg['aeat_cotejo_ca_bundle'] ?? ''),
    'use_mock' => false,
]);

if (($res['codigo'] ?? '') !== '1' || empty($res['binario_base64'])) {
    echo "Error o sin binario\n";
    print_r($res);
    exit(1);
}

$out = __DIR__ . '/aeat-oficial.pdf';
file_put_contents($out, base64_decode((string) $res['binario_base64']));

$sha1 = strtoupper(sha1_file($out));
$huella = strtoupper((string) ($res['huella'] ?? ''));

echo "Guardado: $out\n";
echo "SHA-1 del PDF de AEAT: $sha1\n";
echo "Huella devuelta por AEAT: $huella\n";
echo "Coinciden: " . ($sha1 === $huella ? 'SI' : 'NO') . "\n";

$local = __DIR__ . '/PDF-alejandro_20260511_0001-3.pdf';
if (is_file($local)) {
    $localSha1 = strtoupper(sha1_file($local));
    echo "SHA-1 de tu PDF local: $localSha1\n";
    echo "Tu PDF = PDF AEAT: " . ($localSha1 === $sha1 ? 'SI' : 'NO') . "\n";
}