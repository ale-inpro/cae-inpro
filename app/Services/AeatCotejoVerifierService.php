<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class AeatCotejoVerifierService
{
    public function __construct(
        private readonly AeatCsvExtractionService $csvExtractor = new AeatCsvExtractionService(),
        private readonly AeatCotejoInternetService $cotejo = new AeatCotejoInternetService(),
    ) {
    }

    /**
     * @param array<string, mixed> $appConfig config/app.php completo
     * @return array<string, mixed>
     */
    public function verifyDocumentById(int $documentId, PDO $pdo, array $appConfig): array
    {
        $stmt = $pdo->prepare('
            SELECT id, storage_path, mime_type
            FROM cae_documents
            WHERE id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $documentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['ok' => false, 'error' => 'Documento no encontrado'];
        }

        $publicRoot = dirname(__DIR__, 2) . '/public';
        $rel = (string) ($row['storage_path'] ?? '');
        $abs = $publicRoot . $rel;
        if (!is_file($abs)) {
            return ['ok' => false, 'error' => 'Fichero en disco no encontrado: ' . $rel];
        }

        $ex = $this->csvExtractor->extractFromPdfPath($abs);
        if (($ex['error'] ?? null) !== null && $ex['error'] !== '') {
            $this->persist($pdo, $documentId, [
                'extracted_aeat_csv' => null,
                'aeat_cotejo_codigo' => null,
                'aeat_cotejo_descripcion' => 'Sin CSV: ' . $ex['error'],
                'aeat_cotejo_checked_at' => date('c'),
                'aeat_cotejo_huella_ok' => null,
                'aeat_cotejo_http_code' => null,
                'aeat_cotejo_used_mock' => !empty($appConfig['aeat_cotejo_use_mock']),
                'aeat_cotejo_csv_sustituto' => null,
                'aeat_cotejo_curl_error' => null,
            ]);
            return ['ok' => false, 'error' => $ex['error'], 'candidates' => $ex['candidates']];
        }

        $csv = $ex['csv'];
        if ($csv === null) {
            $msg = count($ex['candidates']) === 0
                ? 'No se encontró ningún candidato CSV'
                : 'Varios candidatos CSV; indicar CSV manualmente';
            $this->persist($pdo, $documentId, [
                'extracted_aeat_csv' => null,
                'aeat_cotejo_codigo' => null,
                'aeat_cotejo_descripcion' => $msg,
                'aeat_cotejo_checked_at' => date('c'),
                'aeat_cotejo_huella_ok' => null,
                'aeat_cotejo_http_code' => null,
                'aeat_cotejo_used_mock' => !empty($appConfig['aeat_cotejo_use_mock']),
                'aeat_cotejo_csv_sustituto' => null,
                'aeat_cotejo_curl_error' => null,
            ]);
            return ['ok' => false, 'error' => $msg, 'candidates' => $ex['candidates']];
        }

        $useMock = !empty($appConfig['aeat_cotejo_use_mock']);
        $mockSha1 = strtoupper((string) sha1_file($abs));

        $cotejoCfg = [
            'endpoint' => (string) ($appConfig['aeat_cotejo_endpoint'] ?? ''),
            'client_cert_path' => (string) ($appConfig['aeat_cotejo_client_cert_path'] ?? ''),
            'client_cert_password' => (string) ($appConfig['aeat_cotejo_client_cert_password'] ?? ''),
            'ca_bundle' => (string) ($appConfig['aeat_cotejo_ca_bundle'] ?? ''),
            'use_mock' => $useMock,
            'mock_scenario' => (string) ($appConfig['aeat_cotejo_mock_scenario'] ?? 'success'),
            'mock_sha1_for_file' => $mockSha1,
        ];

        $res = $this->cotejo->cotejar($csv, false, $cotejoCfg);

        $codigo = (string) ($res['codigo'] ?? '');
        $huellaOk = null;
        if ($codigo === '1' && !empty($res['huella'])) {
            $huellaOk = strtoupper((string) $res['huella']) === $mockSha1;
        }

        $this->persist($pdo, $documentId, [
            'extracted_aeat_csv' => $csv,
            'aeat_cotejo_codigo' => $codigo !== '' ? $codigo : null,
            'aeat_cotejo_descripcion' => (string) ($res['descripcion'] ?? ''),
            'aeat_cotejo_checked_at' => date('c'),
            'aeat_cotejo_huella_ok' => $huellaOk,
            'aeat_cotejo_http_code' => (int) ($res['http_code'] ?? 0),
            'aeat_cotejo_used_mock' => $useMock,
            'aeat_cotejo_csv_sustituto' => isset($res['csv_sustituto']) ? (string) $res['csv_sustituto'] : null,
            'aeat_cotejo_curl_error' => isset($res['curl_error']) ? (string) $res['curl_error'] : null,
        ]);

        $out = $res;
        unset($out['raw_response'], $out['binario_base64']);
        $out['extracted_csv'] = $csv;
        $out['huella_match'] = $huellaOk;
        $out['candidates'] = $ex['candidates'];
        return $out;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function persist(PDO $pdo, int $documentId, array $fields): void
    {
        $sets = [];
        $params = ['id' => $documentId];
        foreach ($fields as $col => $val) {
            $sets[] = $col . ' = :' . $col;
            $params[$col] = $val;
        }
        if ($sets === []) {
            return;
        }
        $sql = 'UPDATE cae_documents SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = :id';
        $pdo->prepare($sql)->execute($params);
    }
}