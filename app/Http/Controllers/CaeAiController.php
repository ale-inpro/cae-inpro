<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\CaeAiService;
use App\Services\CaePdfService;
use Smalot\PdfParser\Parser;
use PDO;

final class CaeAiController extends Controller
{
    /** @param array<string,string> $params */
    public function builder(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireAdmin();

        $tid = (int) ($params['id'] ?? 0);
        $pdo = Database::connection();

        $tech = $this->loadTech($pdo, $tid);
        if (!$tech) {
            http_response_code(404);
            $this->respond('Técnico no encontrado');
            return;
        }

        $stmt = $pdo->prepare("
            SELECT cd.id, cd.original_filename, cd.mime_type, cd.storage_path, cd.uploaded_at
            FROM cae_documents cd
            JOIN cae_records cr ON cr.id = cd.cae_record_id
            WHERE cr.technician_id = :tid
              AND cd.is_active = TRUE
            ORDER BY cd.uploaded_at DESC
            LIMIT 100
        ");
        $stmt->execute(['tid' => $tid]);
        $existingDocs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT id, status, generated_at, pdf_storage_path
            FROM cae_ai_generations
            WHERE technician_id = :tid
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $stmt->execute(['tid' => $tid]);
        $generations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->render('cae.ai_builder', [
            'title' => 'Generación CAE con IA',
            'areaBaseUrl' => $this->areaBaseUrl(),
            'baseUrl' => $this->baseUrl(),
            'tech' => $tech,
            'existingDocs' => $existingDocs,
            'generations' => $generations,
        ]);
    }

    /** @param array<string,string> $params */
    public function generate(array $params = []): void
    {
        ob_start(); // captura cualquier warning PHP para que no corrompa el JSON

        $this->assertAreaAccess();
        $this->requireAdmin();

        $tid = (int) ($params['id'] ?? 0);
        $pdo = Database::connection();

        $tech = $this->loadTech($pdo, $tid);
        if (!$tech) {
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Técnico no encontrado.']);
            exit;
        }

        $selected   = array_map('intval', (array) ($_POST['existing_doc_ids'] ?? []));
        $extraNotes = trim((string) ($_POST['extra_notes'] ?? ''));

        if ($selected === [] && empty($_FILES['new_docs']['name'][0])) {
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Selecciona al menos un documento existente o adjunta uno nuevo.']);
            exit;
        }

        // Cargar fechas del CAE vigente para pasarlas a la IA como datos fijos
        $stmt = $pdo->prepare("
            SELECT valid_from::text AS valid_from, valid_until::text AS valid_until
            FROM cae_records
            WHERE technician_id = :tid AND is_current = TRUE
            LIMIT 1
        ");
        $stmt->execute(['tid' => $tid]);
        $currentCaeRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $tech['valid_from']  = (string) ($currentCaeRow['valid_from']  ?? '');
        $tech['valid_until'] = (string) ($currentCaeRow['valid_until'] ?? '');

        $sources = [];

        // Fuentes existentes
        if ($selected !== []) {
            $in = implode(',', array_fill(0, count($selected), '?'));
            $stmt = $pdo->prepare("
                SELECT cd.id, cd.original_filename, cd.storage_path, cd.mime_type
                FROM cae_documents cd
                JOIN cae_records cr ON cr.id = cd.cae_record_id
                WHERE cr.technician_id = ?
                  AND cd.is_active = TRUE
                  AND cd.id IN ($in)
            ");
            $i = 1;
            $stmt->bindValue($i++, $tid, PDO::PARAM_INT);
            foreach ($selected as $id) $stmt->bindValue($i++, $id, PDO::PARAM_INT);
            $stmt->execute();

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $abs = dirname(__DIR__, 3) . '/public' . (string) $row['storage_path'];
                $row['source_type'] = 'existing';
                $row['extracted_text'] = $this->extractText($abs, (string) $row['mime_type']);
                $sources[] = $row;
            }
        }

        // Nuevos uploads
        if (!empty($_FILES['new_docs']['name'][0])) {
            $dir = dirname(__DIR__, 3) . '/public/uploads/cae-ai-temp/' . $tid;
            if (!is_dir($dir)) mkdir($dir, 0775, true);

            $names = (array) $_FILES['new_docs']['name'];
            $tmp   = (array) $_FILES['new_docs']['tmp_name'];
            $errs  = (array) $_FILES['new_docs']['error'];
            $types = (array) $_FILES['new_docs']['type'];

            foreach ($names as $k => $name) {
                if (($errs[$k] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
                $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $name) ?: 'doc.bin';
                $final = uniqid('ai_', true) . '_' . $safe;
                $abs = $dir . '/' . $final;
                move_uploaded_file((string) $tmp[$k], $abs);

                $sources[] = [
                    'id' => null,
                    'original_filename' => (string) $name,
                    'storage_path' => '/uploads/cae-ai-temp/' . $tid . '/' . $final,
                    'mime_type' => (string) ($types[$k] ?? 'application/octet-stream'),
                    'source_type' => 'upload',
                    'extracted_text' => $this->extractText($abs, (string) ($types[$k] ?? '')),
                ];
            }
        }

        // 1. PHP determina el estado — lógica determinista, sin IA
        $statusResult = CaeAiService::determineStatus($sources);

        // 2. IA solo redacta el texto narrativo del PDF
        $draft = CaeAiService::generateNarrative($tech, $sources, $statusResult, $extraNotes);

        // 3. Fechas del formulario tienen prioridad
        $overrideFrom  = trim((string) ($_POST['valid_from']  ?? ''));
        $overrideUntil = trim((string) ($_POST['valid_until'] ?? ''));
        if ($overrideFrom !== '')  $draft['campos']['valido_desde'] = $overrideFrom;
        if ($overrideUntil !== '') $draft['campos']['valido_hasta'] = $overrideUntil;

        // El estado ya viene determinado por PHP, no por la IA
        $caeStatus = (string) ($draft['conclusion_estado'] ?? 'in_review');

        // ── Renderizar PDF con el draft ya corregido ──
        $pdfDir = dirname(__DIR__, 3) . '/public/uploads/cae-generated/' . $tid;
        if (!is_dir($pdfDir)) mkdir($pdfDir, 0775, true);
        $pdfName = 'cae_ai_' . date('Ymd_His') . '_' . uniqid() . '.pdf';
        $pdfAbs  = $pdfDir . '/' . $pdfName;
        $pdfRel  = '/uploads/cae-generated/' . $tid . '/' . $pdfName;

        CaePdfService::render($pdfAbs, $tech, $draft);

        // ── Persistencia ──
        $stmt = $pdo->prepare("
            INSERT INTO cae_ai_generations
            (technician_id, cae_record_id, requested_by_user_id, model_name, status, input_json, output_json, pdf_storage_path, generated_at, created_at, updated_at)
            VALUES
            (:tid, NULL, :uid, 'gpt-4o-mini', 'generated', CAST(:in AS jsonb), CAST(:out AS jsonb), :pdf, NOW(), NOW(), NOW())
            RETURNING id
        ");
        $stmt->execute([
            'tid' => $tid,
            'uid' => (int) ($_SESSION['user']['id'] ?? 0),
            'in'  => json_encode(['extra_notes' => $extraNotes], JSON_UNESCAPED_UNICODE),
            'out' => json_encode($draft, JSON_UNESCAPED_UNICODE),
            'pdf' => $pdfRel,
        ]);
        $genId = (int) $stmt->fetchColumn();

        $stmtS = $pdo->prepare("
            INSERT INTO cae_ai_generation_sources
            (generation_id, source_type, cae_document_id, original_filename, storage_path, mime_type, extracted_text, created_at)
            VALUES
            (:gid, :type, :docid, :name, :path, :mime, :txt, NOW())
        ");
        foreach ($sources as $s) {
            $stmtS->execute([
                'gid'   => $genId,
                'type'  => (string) ($s['source_type'] ?? 'upload'),
                'docid' => isset($s['id']) ? (int) $s['id'] : null,
                'name'  => (string) ($s['original_filename'] ?? 'documento'),
                'path'  => (string) ($s['storage_path'] ?? ''),
                'mime'  => (string) ($s['mime_type'] ?? ''),
                'txt'   => (string) ($s['extracted_text'] ?? ''),
            ]);
        }

        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'ok'            => true,
            'pdf_url'       => $this->baseUrl() . $pdfRel,
            'generation_id' => $genId,
            'cae_status'    => $caeStatus,
            'ai_estado'     => $caeStatus,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** @param array<string,string> $params */
    public function save(array $params = []): void
    {
        ob_start(); // captura cualquier warning PHP para que no corrompa el JSON

        $this->assertAreaAccess();
        $this->requireAdmin();

        $tid            = (int) ($params['id'] ?? 0);
        $genId          = (int) ($_POST['generation_id'] ?? 0);
        $conflictAction = trim((string) ($_POST['conflict_action'] ?? 'new_revision'));

        ob_end_clean();
        header('Content-Type: application/json');

        if ($tid <= 0 || $genId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Parámetros inválidos']);
            exit;
        }

        $pdo = Database::connection();

        // Cargar generación
        $stmt = $pdo->prepare("
            SELECT id, technician_id, output_json, pdf_storage_path
            FROM cae_ai_generations
            WHERE id = :id AND technician_id = :tid
            LIMIT 1
        ");
        $stmt->execute(['id' => $genId, 'tid' => $tid]);
        $gen = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$gen) {
            echo json_encode(['ok' => false, 'error' => 'Generación no encontrada']);
            exit;
        }

        $draft    = json_decode((string) ($gen['output_json'] ?? '{}'), true) ?: [];
        $pdfPath  = (string) ($gen['pdf_storage_path'] ?? '');
        $campos   = (array) ($draft['campos'] ?? []);

        // Mapear estado
        $aiEstado  = (string) ($draft['conclusion_estado'] ?? 'in_review');
        $caeStatus = match ($aiEstado) {
            'approved'     => 'approved',
            'in_review'    => 'in_review',
            'pending_docs' => 'pending_docs',
            'rejected'     => 'rejected',
            default        => 'in_review',
        };

        $validFrom  = (string) ($campos['valido_desde'] ?? date('Y-m-d'));
        $validUntil = (string) ($campos['valido_hasta'] ?? date('Y-m-d', strtotime('+3 months')));
        $notes      = (string) ($draft['resumen'] ?? '');
        $userId     = (int) ($_SESSION['user']['id'] ?? 0);

        // Tipo de documento principal CAE
        $dtStmt   = $pdo->query("SELECT id FROM document_types WHERE scope = 'technician_cae' AND is_cae_file_type = TRUE AND is_active = TRUE LIMIT 1");
        $docTypeId = (int) ($dtStmt->fetchColumn() ?: 0);

        // CAE vigente actual
        $stmt = $pdo->prepare("SELECT id FROM cae_records WHERE technician_id = :tid AND is_current = TRUE LIMIT 1");
        $stmt->execute(['tid' => $tid]);
        $existingCaeId = (int) ($stmt->fetchColumn() ?: 0);

        $pdo->beginTransaction();
        try {
            $caeRecordId = 0;

            if ($existingCaeId > 0 && $conflictAction === 'replace_pdf') {
                // Reemplazar documento: actualiza el mismo registro
                $pdo->prepare("
                    UPDATE cae_records
                    SET status = :status, valid_from = :from, valid_until = :until, notes = :notes, updated_at = NOW()
                    WHERE id = :id
                ")->execute(['status' => $caeStatus, 'from' => $validFrom, 'until' => $validUntil, 'notes' => $notes, 'id' => $existingCaeId]);
                $caeRecordId = $existingCaeId;
            } else {
                // Nueva revisión (o no había CAE): archiva el anterior y crea uno nuevo
                if ($existingCaeId > 0) {
                    $pdo->prepare("UPDATE cae_records SET is_current = FALSE, updated_at = NOW() WHERE id = :id")
                        ->execute(['id' => $existingCaeId]);
                }
                $ins = $pdo->prepare("
                    INSERT INTO cae_records
                    (technician_id, status, issue_date, valid_from, valid_until, notes, is_current, created_at, updated_at)
                    VALUES
                    (:tid, :status, CURRENT_DATE, :from, :until, :notes, TRUE, NOW(), NOW())
                    RETURNING id
                ");
                $ins->execute(['tid' => $tid, 'status' => $caeStatus, 'from' => $validFrom, 'until' => $validUntil, 'notes' => $notes]);
                $caeRecordId = (int) $ins->fetchColumn();
            }

            // Desactivar archivos CAE anteriores del registro
            $pdo->prepare("UPDATE cae_documents SET is_active = FALSE, updated_at = NOW() WHERE cae_record_id = :cid AND is_cae_file = TRUE")
                ->execute(['cid' => $caeRecordId]);

            // Insertar nuevo documento apuntando al PDF generado por IA
            $pdo->prepare("
                INSERT INTO cae_documents
                (cae_record_id, document_type_id, original_filename, storage_path, mime_type, file_size, uploaded_by_user_id, uploaded_at, is_active, is_cae_file, created_at, updated_at)
                VALUES
                (:cid, :dtype, :orig, :path, 'application/pdf', 0, :uid, NOW(), TRUE, TRUE, NOW(), NOW())
            ")->execute([
                'cid'   => $caeRecordId,
                'dtype' => $docTypeId ?: null,
                'orig'  => 'CAE_IA_' . date('Y-m-d') . '.pdf',
                'path'  => $pdfPath,
                'uid'   => $userId,
            ]);

            // Vincular generación al registro CAE
            $pdo->prepare("UPDATE cae_ai_generations SET cae_record_id = :cid, updated_at = NOW() WHERE id = :id")
                ->execute(['cid' => $caeRecordId, 'id' => $genId]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[CaeAiController::save] ' . $e->getMessage());
            echo json_encode(['ok' => false, 'error' => 'Error al guardar: ' . $e->getMessage()]);
            exit;
        }

        // Flash según estado
        [$flashMsg, $flashType] = match ($caeStatus) {
            'approved'     => ['CAE generado con IA y aprobado correctamente.', 'success'],
            'in_review'    => ['CAE guardado. Estado: En revisión — requiere validación.', 'warning'],
            'pending_docs' => ['CAE guardado. Faltan documentos obligatorios (Póliza RC / Recibo RC).', 'warning'],
            default        => ['CAE guardado como Rechazado según el análisis de la IA.', 'danger'],
        };
        $this->flash($flashMsg, $flashType, $caeStatus === 'approved' ? 'Correcto' : 'Atención');

        echo json_encode([
            'ok'           => true,
            'redirect_url' => $this->areaBaseUrl() . '/tecnicos/' . $tid . '/cae',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    

    /** @param array<string,string> $params */
    public function download(array $params = []): void
    {
        $this->assertAreaAccess();
        $this->requireAdmin();

        $gid = (int) ($params['generationId'] ?? 0);
        $pdo = Database::connection();

        $stmt = $pdo->prepare("SELECT pdf_storage_path FROM cae_ai_generations WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $gid]);
        $path = (string) ($stmt->fetchColumn() ?: '');

        if ($path === '') {
            http_response_code(404);
            $this->respond('PDF no encontrado');
            return;
        }

        $abs = dirname(__DIR__, 3) . '/public' . $path;
        if (!is_file($abs)) {
            http_response_code(404);
            $this->respond('Archivo no disponible');
            return;
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($abs) . '"');
        header('Content-Length: ' . (string) filesize($abs));
        readfile($abs);
        exit;
    }

    /** @return array<string,mixed>|null */
    private function loadTech(PDO $pdo, int $tid): ?array
    {
        if ($tid <= 0) return null;

        $stmt = $pdo->prepare("
            SELECT id, first_name, last_name, email, professions
            FROM technicians
            WHERE id = :id AND is_active = TRUE
            LIMIT 1
        ");
        $stmt->execute(['id' => $tid]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$r) return null;

        $r['full_name'] = trim((string) $r['first_name'] . ' ' . (string) $r['last_name']);
        return $r;
    }

    private function extractText(string $absolutePath, string $mimeType): string
    {
        if (!is_file($absolutePath)) return '';

        $mime = strtolower($mimeType);

        if (str_contains($mime, 'text/')) {
            return trim((string) file_get_contents($absolutePath));
        }

        if (str_contains($mime, 'pdf')) {
            try {
                $parser = new Parser();
                $pdf = $parser->parseFile($absolutePath);
                return trim($pdf->getText());
            } catch (\Throwable) {
                return '';
            }
        }

        // Imágenes/escaneados: OCR pendiente (siguiente fase)
        return '';
    }

    private function requireAdmin(): void
    {
        if (($_SESSION['user']['role'] ?? '') !== 'admin') {
            http_response_code(403);
            $this->respond('Acceso denegado');
            exit;
        }
    }
}