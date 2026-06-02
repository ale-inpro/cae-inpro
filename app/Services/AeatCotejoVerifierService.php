<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class AeatCotejoVerifierService
{
    public function __construct(
        private readonly AeatCsvExtractionService $csvExtractor = new AeatCsvExtractionService(),
        private readonly AeatCotejoInternetService $cotejo = new AeatCotejoInternetService(),
        private readonly AeatHaciendaOfficialPdfValidator $pdfValidator = new AeatHaciendaOfficialPdfValidator(),
    ) {
    }

    /**
     * @param array<string, mixed> $appConfig config/app.php completo
     * @return array<string, mixed>
     */
    public function verifyDocumentById(int $documentId, PDO $pdo, array $appConfig): array
    {
        $stmt = $pdo->prepare('
            SELECT cd.id,
                cd.storage_path,
                cd.mime_type,
                cd.original_filename,
                cd.cae_record_id,
                cd.file_size,
                cd.extracted_aeat_csv,
                t.tax_id
            FROM cae_documents cd
            JOIN cae_records cr ON cr.id = cd.cae_record_id
            JOIN technicians t ON t.id = cr.technician_id
            WHERE cd.id = :id
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
            $msg = 'Fichero en disco no encontrado: ' . $rel;
            $this->persist($pdo, $documentId, [
                'aeat_cotejo_used_mock' => !empty($appConfig['aeat_cotejo_use_mock']),
                'aeat_cotejo_checked_at' => date('c'),
                'aeat_cotejo_codigo' => null,
                'aeat_cotejo_descripcion' => $msg,
                'aeat_cotejo_huella_ok' => null,
                'aeat_pdf_validation_ok' => false,
                'aeat_pdf_validation_errors' => json_encode([$msg], JSON_UNESCAPED_UNICODE),
            ]);

            return ['ok' => false, 'error' => $msg];
        }

        $useMock = !empty($appConfig['aeat_cotejo_use_mock']);
        $basePersist = [
            'aeat_cotejo_used_mock' => $useMock,
            'aeat_cotejo_checked_at' => date('c'),
            'aeat_cotejo_http_code' => null,
            'aeat_cotejo_csv_sustituto' => null,
            'aeat_cotejo_curl_error' => null,
            'aeat_pdf_validation_ok' => null,
            'aeat_pdf_validation_errors' => null,
            'aeat_replaced_upload' => false,
            'aeat_upload_backup_path' => null,
            'aeat_official_sha1' => null,
            'aeat_parsed_tax_id' => null,
            'aeat_parsed_expires_at' => null,
        ];

        $storedCsv = (new AeatCsvExtractionService())->normalizeCsv(
            trim((string) ($row['extracted_aeat_csv'] ?? ''))
        );

        if ($storedCsv !== null) {
            $csv = $storedCsv;
            $ex = ['candidates' => [$csv], 'csv' => $csv, 'error' => null];
        } else {
            $ex = $this->csvExtractor->extractFromPdfPath($abs);
            if (($ex['error'] ?? null) !== null && $ex['error'] !== '') {
                $this->persist($pdo, $documentId, array_merge($basePersist, [
                    'extracted_aeat_csv' => null,
                    'aeat_cotejo_codigo' => null,
                    'aeat_cotejo_descripcion' => 'Sin CSV: ' . $ex['error'],
                    'aeat_cotejo_huella_ok' => null,
                ]));
                return ['ok' => false, 'error' => $ex['error'], 'candidates' => $ex['candidates']];
            }

            $csv = $ex['csv'];
            if ($csv === null) {
                $msg = count($ex['candidates']) === 0
                    ? 'No se encontró ningún candidato CSV'
                    : 'Varios candidatos CSV; indicar CSV manualmente';
                $this->persist($pdo, $documentId, array_merge($basePersist, [
                    'extracted_aeat_csv' => null,
                    'aeat_cotejo_codigo' => null,
                    'aeat_cotejo_descripcion' => $msg,
                    'aeat_cotejo_huella_ok' => null,
                ]));
                return ['ok' => false, 'error' => $msg, 'candidates' => $ex['candidates']];
            }
        }

        $uploadSha1 = strtoupper((string) sha1_file($abs));

        $cotejoCfg = [
            'endpoint' => (string) ($appConfig['aeat_cotejo_endpoint'] ?? ''),
            'client_cert_path' => (string) ($appConfig['aeat_cotejo_client_cert_path'] ?? ''),
            'client_cert_password' => (string) ($appConfig['aeat_cotejo_client_cert_password'] ?? ''),
            'ca_bundle' => (string) ($appConfig['aeat_cotejo_ca_bundle'] ?? ''),
            'use_mock' => $useMock,
            'mock_scenario' => (string) ($appConfig['aeat_cotejo_mock_scenario'] ?? 'success'),
            'mock_sha1_for_file' => $uploadSha1,
        ];

        $res = $this->cotejarOnce($csv, $cotejoCfg);
        $csv = (string) ($res['resolved_csv'] ?? $csv);

        $codigo = (string) ($res['codigo'] ?? '');
        $huellaAeat = strtoupper(trim((string) ($res['huella'] ?? '')));
        $huellaOk = null;
        if ($codigo === '1' && $huellaAeat !== '') {
            $huellaOk = ($huellaAeat === $uploadSha1);
        }

        $descripcion = (string) ($res['descripcion'] ?? '');

        $persist = array_merge($basePersist, [
            'extracted_aeat_csv' => $csv,
            'aeat_cotejo_codigo' => $codigo !== '' ? $codigo : null,
            'aeat_cotejo_descripcion' => $descripcion,
            'aeat_cotejo_huella_ok' => $huellaOk,
            'aeat_cotejo_http_code' => (int) ($res['http_code'] ?? 0),
            'aeat_cotejo_csv_sustituto' => isset($res['csv_sustituto']) ? (string) $res['csv_sustituto'] : null,
            'aeat_cotejo_curl_error' => isset($res['curl_error']) ? (string) $res['curl_error'] : null,
        ]);

        if ($codigo !== '1') {
            $persist['aeat_pdf_validation_ok'] = false;
            $persist['aeat_pdf_validation_errors'] = json_encode([
                'No se ha podido obtener el certificado de Hacienda.',
                trim((string) ($res['descripcion'] ?? '')),
            ], JSON_UNESCAPED_UNICODE);
            $this->persist($pdo, $documentId, $persist);

            $out = $res;
            unset($out['raw_response'], $out['binario_base64']);
            $out['extracted_csv'] = $csv;
            $out['huella_match'] = $huellaOk;
            $out['huella_info_only'] = true;
            $out['candidates'] = $ex['candidates'];
            $out['ok'] = false;
            return $out;
        }

        $binB64 = trim((string) ($res['binario_base64'] ?? ''));
        if ($binB64 === '' && $useMock) {
            $persist['aeat_pdf_validation_ok'] = true;
            $persist['aeat_pdf_validation_errors'] = null;
            $persist['aeat_cotejo_descripcion'] = trim($persist['aeat_cotejo_descripcion'] . ' [Mock: sin PDF oficial; validación de contenido omitida.]');
            $this->persist($pdo, $documentId, $persist);

            $out = $res;
            unset($out['raw_response'], $out['binario_base64']);
            $out['extracted_csv'] = $csv;
            $out['huella_match'] = $huellaOk;
            $out['huella_info_only'] = true;
            $out['pdf_validation_ok'] = true;
            $out['mock_pdf_skipped'] = true;
            $out['candidates'] = $ex['candidates'];
            $out['ok'] = true;
            return $out;
        }

        if ($binB64 === '') {
            $err = ['No se recibió el certificado oficial en la respuesta (binario vacío).'];
            $persist['aeat_pdf_validation_ok'] = false;
            $persist['aeat_pdf_validation_errors'] = json_encode($err, JSON_UNESCAPED_UNICODE);
            $persist['aeat_cotejo_descripcion'] = $err[0];
            $this->persist($pdo, $documentId, $persist);

            $out = $res;
            unset($out['raw_response'], $out['binario_base64']);
            $out['extracted_csv'] = $csv;
            $out['huella_match'] = $huellaOk;
            $out['huella_info_only'] = true;
            $out['pdf_validation_ok'] = false;
            $out['pdf_validation_errors'] = $err;
            $out['candidates'] = $ex['candidates'];
            $out['ok'] = false;
            return $out;
        }

        $pdfBytes = base64_decode($binB64, true);
        if ($pdfBytes === false || $pdfBytes === '') {
            $err = ['No se pudo decodificar el certificado oficial (Base64 inválido).'];
            $persist['aeat_pdf_validation_ok'] = false;
            $persist['aeat_pdf_validation_errors'] = json_encode($err, JSON_UNESCAPED_UNICODE);
            $this->persist($pdo, $documentId, $persist);

            $out = $res;
            unset($out['raw_response'], $out['binario_base64']);
            $out['extracted_csv'] = $csv;
            $out['huella_match'] = $huellaOk;
            $out['huella_info_only'] = true;
            $out['pdf_validation_ok'] = false;
            $out['pdf_validation_errors'] = $err;
            $out['candidates'] = $ex['candidates'];
            $out['ok'] = false;
            return $out;
        }

        $officialSha1 = strtoupper(sha1($pdfBytes));
        $validation = $this->pdfValidator->validatePdfBytes($pdfBytes, (string) ($row['tax_id'] ?? ''));

        $persist['aeat_official_sha1'] = $officialSha1;
        $persist['aeat_parsed_tax_id'] = $validation['tax_id'];
        $persist['aeat_parsed_expires_at'] = $validation['expires_at'];
        $persist['aeat_pdf_validation_ok'] = $validation['ok'];
        $persist['aeat_pdf_validation_errors'] = $validation['ok']
            ? null
            : json_encode($validation['errors'], JSON_UNESCAPED_UNICODE);

        $caeRecordId = (int) ($row['cae_record_id'] ?? 0);
        $replace = $this->replaceWithOfficialPdf(
            $publicRoot,
            $abs,
            $rel,
            $caeRecordId,
            $pdfBytes,
            (string) ($row['original_filename'] ?? 'certificado_hacienda.pdf')
        );

        if (($replace['error'] ?? null) !== null) {
            $err = [(string) $replace['error']];
            $persist['aeat_pdf_validation_ok'] = false;
            $persist['aeat_pdf_validation_errors'] = json_encode($err, JSON_UNESCAPED_UNICODE);
            $this->persist($pdo, $documentId, $persist);

            $out = $res;
            unset($out['raw_response'], $out['binario_base64']);
            $out['extracted_csv'] = $csv;
            $out['huella_match'] = $huellaOk;
            $out['huella_info_only'] = true;
            $out['pdf_validation_ok'] = false;
            $out['pdf_validation_errors'] = $err;
            $out['candidates'] = $ex['candidates'];
            $out['ok'] = false;
            return $out;
        }

        $persist['storage_path'] = $replace['storage_path'];
        $persist['file_size'] = $replace['file_size'];
        $persist['mime_type'] = 'application/pdf';
        $persist['aeat_replaced_upload'] = true;
        $persist['aeat_upload_backup_path'] = $replace['backup_path'];
        $persist['original_filename'] = $this->officialHaciendaDisplayFilename(
            $validation['expires_at'] !== null ? (string) $validation['expires_at'] : null
        );

        if ($validation['expires_at'] !== null) {
            $persist['expires_at'] = $validation['expires_at'];
        }

        if (!$validation['ok']) {
            $firstError = $validation['errors'][0] ?? 'El certificado no cumple los requisitos.';
            $persist['aeat_cotejo_descripcion'] = mb_substr((string) $firstError, 0, 2000);
        } else {
            $persist['aeat_cotejo_descripcion'] = trim((string) ($res['descripcion'] ?? 'Correcto'));
        }

        $this->persist($pdo, $documentId, $persist);

        $out = $res;
        unset($out['raw_response'], $out['binario_base64']);
        $out['extracted_csv'] = $csv;
        $out['huella_match'] = $huellaOk;
        $out['huella_info_only'] = true;
        $out['official_sha1'] = $officialSha1;
        $out['pdf_validation_ok'] = $validation['ok'];
        $out['pdf_validation_errors'] = $validation['errors'];
        $out['replaced_upload'] = true;
        $out['new_storage_path'] = $replace['storage_path'];
        $out['backup_path'] = $replace['backup_path'];
        $out['parsed_expires_at'] = $validation['expires_at'];
        $out['candidates'] = $ex['candidates'];
        $out['ok'] = $validation['ok'];
        return $out;
    }

        /**
     * CSV manual → cotejo → PDF oficial → publica cae_documents (sin escaneo).
     *
     * @param array<string, mixed> $appConfig
     * @return array{ok: bool, document_id?: int, error?: string, ...}
     */
    public function publishHaciendaFromCsv(
        int $caeRecordId,
        int $documentTypeId,
        string $csvRaw,
        PDO $pdo,
        array $appConfig,
        int $uploadedByUserId
    ): array {
        $haciendaId = CaeReadinessService::resolveHaciendaDocumentTypeId($pdo);
        if ($haciendaId === null || $documentTypeId !== $haciendaId) {
            return ['ok' => false, 'error' => 'Tipo de documento no válido para consulta por CSV.'];
        }

        $csv = $this->csvExtractor->normalizeCsv($csvRaw);
        if ($csv === null) {
            return ['ok' => false, 'error' => 'CSV no válido (16 caracteres alfanuméricos).'];
        }

        $uploadedByUserIdForDb = $uploadedByUserId > 0 ? $uploadedByUserId : null;

        $stmt = $pdo->prepare('
            SELECT cr.id, t.tax_id
            FROM cae_records cr
            JOIN technicians t ON t.id = cr.technician_id
            WHERE cr.id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $caeRecordId]);
        $rec = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$rec) {
            return ['ok' => false, 'error' => 'CAE no encontrado.'];
        }

        $taxId = trim((string) ($rec['tax_id'] ?? ''));
        if ($taxId === '') {
            return ['ok' => false, 'error' => 'El técnico no tiene NIF/CIF registrado.'];
        }

        $useMock = !empty($appConfig['aeat_cotejo_use_mock']);
        $cotejoCfg = [
            'endpoint' => (string) ($appConfig['aeat_cotejo_endpoint'] ?? ''),
            'client_cert_path' => (string) ($appConfig['aeat_cotejo_client_cert_path'] ?? ''),
            'client_cert_password' => (string) ($appConfig['aeat_cotejo_client_cert_password'] ?? ''),
            'ca_bundle' => (string) ($appConfig['aeat_cotejo_ca_bundle'] ?? ''),
            'use_mock' => $useMock,
            'mock_scenario' => (string) ($appConfig['aeat_cotejo_mock_scenario'] ?? 'success'),
            'mock_sha1_for_file' => '',
        ];

        $res = $this->cotejarOnce($csv, $cotejoCfg);
        $csv = (string) ($res['resolved_csv'] ?? $csv);
        $codigo = (string) ($res['codigo'] ?? '');

        if ($codigo !== '1') {
            return [
                'ok' => false,
                'error' => trim((string) ($res['descripcion'] ?? '')) !== ''
                    ? (string) $res['descripcion']
                    : 'No se ha podido obtener el certificado de Hacienda.',
                'codigo' => $codigo,
            ];
        }

        $binB64 = trim((string) ($res['binario_base64'] ?? ''));
        if ($binB64 === '' && !$useMock) {
            return ['ok' => false, 'error' => 'No se recibió el certificado oficial en la respuesta.'];
        }

        $publicRoot = dirname(__DIR__, 2) . '/public';
        $checkedAt = date('c');

        // Mock sin PDF: publicar fila mínima (mismo criterio que verifyDocumentById)
        if ($binB64 === '' && $useMock) {
            $docId = (int) CaeDocumentSlotService::replaceActiveSupportingSlot(
                $pdo,
                $caeRecordId,
                $documentTypeId,
                function () use ($pdo, $caeRecordId, $documentTypeId, $csv, $uploadedByUserIdForDb, $useMock, $checkedAt, $res, $codigo): int {
                    $stmt = $pdo->prepare("
                        INSERT INTO cae_documents
                        (cae_record_id, document_type_id, original_filename, storage_path, mime_type, file_size,
                         uploaded_by_user_id, uploaded_at, is_active, is_cae_file, expires_at, extracted_aeat_csv,
                         aeat_cotejo_used_mock, aeat_cotejo_checked_at, aeat_cotejo_codigo, aeat_cotejo_descripcion,
                         aeat_cotejo_huella_ok, aeat_pdf_validation_ok, aeat_replaced_upload, created_at, updated_at)
                        VALUES
                        (:cae_id, :doc_type, :orig, :path, 'application/pdf', 0,
                         :user_id, NOW(), TRUE, FALSE, NULL, :csv,
                         :mock, :checked, :codigo, :desc,
                         NULL, TRUE, FALSE, NOW(), NOW())
                    ");
                    $stmt->bindValue(':cae_id', $caeRecordId, PDO::PARAM_INT);
                    $stmt->bindValue(':doc_type', $documentTypeId, PDO::PARAM_INT);
                    $stmt->bindValue(':orig', 'Certificado_oficial_Hacienda.pdf');
                    $stmt->bindValue(':path', '/uploads/cae/' . $caeRecordId . '/mock_sin_pdf.pdf');
                    if ($uploadedByUserIdForDb !== null) {
                        $stmt->bindValue(':user_id', $uploadedByUserIdForDb, PDO::PARAM_INT);
                    } else {
                        $stmt->bindValue(':user_id', null, PDO::PARAM_NULL);
                    }
                    $stmt->bindValue(':csv', $csv);
                    $stmt->bindValue(':mock', $useMock, PDO::PARAM_BOOL);
                    $stmt->bindValue(':checked', $checkedAt);
                    $stmt->bindValue(':codigo', $codigo);
                    $stmt->bindValue(':desc', trim((string) ($res['descripcion'] ?? 'Correcto')) . ' [Mock]');
                    $stmt->execute();
                    return (int) $pdo->lastInsertId();
                }
            );
            return ['ok' => true, 'document_id' => $docId, 'mock_pdf_skipped' => true];
        }

        $pdfBytes = base64_decode($binB64, true);
        if ($pdfBytes === false || $pdfBytes === '') {
            return ['ok' => false, 'error' => 'No se pudo decodificar el certificado oficial.'];
        }

        $validation = $this->pdfValidator->validatePdfBytes($pdfBytes, $taxId);
        $saved = $this->saveOfficialPdfOnly($publicRoot, $caeRecordId, $pdfBytes);
        if (isset($saved['error'])) {
            return ['ok' => false, 'error' => (string) $saved['error']];
        }

        $displayName = $this->officialHaciendaDisplayFilename($validation['expires_at']);
        $desc = $validation['ok']
            ? trim((string) ($res['descripcion'] ?? 'Correcto'))
            : mb_substr((string) ($validation['errors'][0] ?? 'El certificado no cumple los requisitos.'), 0, 2000);

        $docId = (int) CaeDocumentSlotService::replaceActiveSupportingSlot(
            $pdo,
            $caeRecordId,
            $documentTypeId,
            function () use (
                $pdo, $caeRecordId, $documentTypeId, $csv, $uploadedByUserIdForDb, $useMock, $checkedAt,
                $codigo, $desc, $res, $validation, $saved, $displayName, $pdfBytes
            ): int {
                $pdfOk = (bool) $validation['ok'];
                $pdfErr = $pdfOk ? null : json_encode($validation['errors'], JSON_UNESCAPED_UNICODE);
                $stmt = $pdo->prepare("
                    INSERT INTO cae_documents
                    (cae_record_id, document_type_id, original_filename, storage_path, mime_type, file_size,
                     uploaded_by_user_id, uploaded_at, is_active, is_cae_file, expires_at, extracted_aeat_csv,
                     aeat_cotejo_used_mock, aeat_cotejo_checked_at, aeat_cotejo_codigo, aeat_cotejo_descripcion,
                     aeat_cotejo_huella_ok, aeat_cotejo_http_code, aeat_cotejo_curl_error,
                     aeat_pdf_validation_ok, aeat_pdf_validation_errors, aeat_replaced_upload, aeat_upload_backup_path,
                     aeat_official_sha1, aeat_parsed_tax_id, aeat_parsed_expires_at, created_at, updated_at)
                    VALUES
                    (:cae_id, :doc_type, :orig, :path, 'application/pdf', :size,
                     :user_id, NOW(), TRUE, FALSE, :expires, :csv,
                     :mock, :checked, :codigo, :desc,
                     NULL, :http, :curl,
                     :pdf_ok, :pdf_err, TRUE, NULL,
                     :sha1, :parsed_tax, :parsed_exp, NOW(), NOW())
                ");
                $stmt->bindValue(':cae_id', $caeRecordId, PDO::PARAM_INT);
                $stmt->bindValue(':doc_type', $documentTypeId, PDO::PARAM_INT);
                $stmt->bindValue(':orig', $displayName);
                $stmt->bindValue(':path', $saved['storage_path']);
                $stmt->bindValue(':size', $saved['file_size'], PDO::PARAM_INT);
                if ($uploadedByUserIdForDb !== null) {
                    $stmt->bindValue(':user_id', $uploadedByUserIdForDb, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue(':user_id', null, PDO::PARAM_NULL);
                }
                $stmt->bindValue(':expires', $validation['expires_at']);
                $stmt->bindValue(':csv', $csv);
                $stmt->bindValue(':mock', $useMock, PDO::PARAM_BOOL);
                $stmt->bindValue(':checked', $checkedAt);
                $stmt->bindValue(':codigo', $codigo);
                $stmt->bindValue(':desc', $desc);
                $stmt->bindValue(':http', (int) ($res['http_code'] ?? 0), PDO::PARAM_INT);
                $curlErr = isset($res['curl_error']) ? (string) $res['curl_error'] : null;
                if ($curlErr === null || $curlErr === '') {
                    $stmt->bindValue(':curl', null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':curl', $curlErr);
                }
                $stmt->bindValue(':pdf_ok', $pdfOk, PDO::PARAM_BOOL);
                if ($pdfErr === null) {
                    $stmt->bindValue(':pdf_err', null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':pdf_err', $pdfErr);
                }
                $stmt->bindValue(':sha1', strtoupper(sha1($pdfBytes)));
                $stmt->bindValue(':parsed_tax', $validation['tax_id']);
                $stmt->bindValue(':parsed_exp', $validation['expires_at']);
                $stmt->execute();
                return (int) $pdo->lastInsertId();
            }
        );

        return [
            'ok' => $validation['ok'],
            'document_id' => $docId,
            'pdf_validation_ok' => $validation['ok'],
            'pdf_validation_errors' => $validation['errors'],
            'parsed_expires_at' => $validation['expires_at'],
        ];
    }

    private function officialHaciendaDisplayFilename(?string $expiresAt): string
    {
        if ($expiresAt !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiresAt)) {
            $d = \DateTimeImmutable::createFromFormat('Y-m-d', $expiresAt);
            if ($d !== false) {
                return 'Certificado_oficial_Hacienda_vigente_hasta_'
                    . $d->format('d-m-Y')
                    . '.pdf';
            }
        }

        return 'Certificado_oficial_Hacienda.pdf';
    }

        /**
     * Guarda PDF oficial cuando no hay escaneo subido (flujo CSV directo).
     *
     * @return array{storage_path: string, file_size: int, error?: string}
     */
    private function saveOfficialPdfOnly(string $publicRoot, int $caeRecordId, string $pdfBytes): array
    {
        if ($caeRecordId <= 0) {
            return ['error' => 'cae_record_id no válido.'];
        }

        $targetDir = $publicRoot . '/uploads/cae/' . $caeRecordId;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return ['error' => 'No se pudo crear directorio de subida.'];
        }

        $finalName = 'aeat_oficial_' . uniqid('', true) . '.pdf';
        $finalAbs = $targetDir . '/' . $finalName;
        if (file_put_contents($finalAbs, $pdfBytes) === false) {
            return ['error' => 'No se pudo escribir el certificado oficial en disco.'];
        }

        return [
            'storage_path' => '/uploads/cae/' . $caeRecordId . '/' . $finalName,
            'file_size' => strlen($pdfBytes),
        ];
    }

    /**
     * @return array{storage_path?: string, backup_path?: string|null, file_size?: int, error?: string}
     */
    private function replaceWithOfficialPdf(
        string $publicRoot,
        string $currentAbs,
        string $currentRel,
        int $caeRecordId,
        string $pdfBytes,
        string $originalFilename
    ): array {
        if ($caeRecordId <= 0) {
            return ['error' => 'cae_record_id no válido para guardar PDF oficial.'];
        }

        $targetDir = $publicRoot . '/uploads/cae/' . $caeRecordId;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return ['error' => 'No se pudo crear directorio de subida: ' . $targetDir];
        }

        $backupRel = null;
        if (is_file($currentAbs)) {
            $safeOrig = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalFilename) ?: 'scan.pdf';
            $backupName = 'scan_backup_' . date('Ymd_His') . '_' . $safeOrig;
            $backupAbs = $targetDir . '/' . $backupName;
            if (!@copy($currentAbs, $backupAbs)) {
                return ['error' => 'No se pudo respaldar el escaneo original antes de sustituir.'];
            }
            $backupRel = '/uploads/cae/' . $caeRecordId . '/' . $backupName;
        }

        $finalName = 'aeat_oficial_' . uniqid('', true) . '.pdf';
        $finalAbs = $targetDir . '/' . $finalName;
        if (file_put_contents($finalAbs, $pdfBytes) === false) {
            return ['error' => 'No se pudo escribir el certificado oficial en disco.'];
        }

        return [
            'storage_path' => '/uploads/cae/' . $caeRecordId . '/' . $finalName,
            'backup_path' => $backupRel,
            'file_size' => strlen($pdfBytes),
        ];
    }

    /**
     * PostgreSQL rechaza "" en columnas BOOLEAN; PDO puede enviar false como cadena vacía.
     *
     * @param array<string, mixed> $fields
     */
    private function persist(PDO $pdo, int $documentId, array $fields): void
    {
        if ($fields === []) {
            return;
        }

        /** @var array<string, bool> col => nullable */
        static $boolCols = [
            'aeat_cotejo_used_mock' => false,
            'aeat_cotejo_huella_ok' => true,
            'aeat_pdf_validation_ok' => true,
            'aeat_replaced_upload' => false,
        ];

        $sets = [];
        foreach (array_keys($fields) as $col) {
            $sets[] = $col . ' = :' . $col;
        }

        $sql = 'UPDATE cae_documents SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = :id';
        $stmt = $pdo->prepare($sql);

        foreach ($fields as $col => $val) {
            if (array_key_exists($col, $boolCols)) {
                $nullable = $boolCols[$col];
                if ($val === null || $val === '') {
                    if ($nullable) {
                        $stmt->bindValue(':' . $col, null, PDO::PARAM_NULL);
                    } else {
                        $stmt->bindValue(':' . $col, false, PDO::PARAM_BOOL);
                    }
                } else {
                    $stmt->bindValue(':' . $col, filter_var($val, FILTER_VALIDATE_BOOLEAN), PDO::PARAM_BOOL);
                }
                continue;
            }

            if ($val === null) {
                $stmt->bindValue(':' . $col, null, PDO::PARAM_NULL);
            } elseif (is_int($val)) {
                $stmt->bindValue(':' . $col, $val, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':' . $col, (string) $val);
            }
        }

        $stmt->bindValue(':id', $documentId, PDO::PARAM_INT);
        $stmt->execute();
    }

        /**
     * Una sola consulta AEAT por CSV (sin reintentos por confusiones OCR).
     *
     * @param array<string, mixed> $cotejoCfg
     * @return array<string, mixed>
     */
    private function cotejarOnce(string $csv, array $cotejoCfg): array
    {
        $res = $this->cotejo->cotejar($csv, false, $cotejoCfg);
        $res['resolved_csv'] = $csv;

        return $res;
    }
}