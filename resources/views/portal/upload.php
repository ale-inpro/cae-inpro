<?php declare(strict_types=1);
$state     = (string) ($state    ?? 'error');
$token     = (string) ($token    ?? '');
$baseUrl   = (string) ($baseUrl  ?? '');
$docs      = (array)  ($docs     ?? []);
$techName  = (string) ($techName ?? 'Técnico/a');
$formError = (string) ($formError ?? '');
$uploaded  = (int)    ($uploaded  ?? 0);
$msg       = (string) ($msg      ?? '');
$uploadUrl = rtrim($baseUrl, '/') . '/portal/' . $token . '/upload';
$docStatuses  = (array)  ($docStatuses  ?? []);
$batchSummary = (array)  ($batchSummary ?? []);

?>
<div class="portal-card">

<?php if ($state === 'error'): ?>
    <div class="card border-0 shadow-sm text-center p-5">
        <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size:3rem"></i>
        <h2 class="h4 mt-3 mb-2">Enlace no disponible</h2>
        <p class="text-muted"><?= htmlspecialchars($msg) ?></p>
    </div>

<?php elseif ($state === 'used'): ?>
    <div class="card border-0 shadow-sm text-center p-5">
        <i class="bi bi-check-circle-fill text-success" style="font-size:3rem"></i>
        <h2 class="h4 mt-3 mb-2">Documentos ya enviados</h2>
        <p class="text-muted"><?= htmlspecialchars($msg) ?></p>
    </div>

<?php elseif ($state === 'success'): ?>
    <div class="card border-0 shadow-sm text-center p-5">
        <i class="bi bi-check-circle-fill text-success" style="font-size:3.5rem"></i>
        <h2 class="h4 mt-3 mb-2">¡Documentos enviados!</h2>
        <p class="text-muted">
            Hemos recibido y validado <strong>todos los documentos solicitados</strong>.
            Ya puedes cerrar esta página.<br>
            Gracias, <?= htmlspecialchars($techName) ?>.
        </p>
    </div>

<?php else: /* state === 'form' */ ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4 p-md-5">

            <h2 class="h5 fw-semibold mb-1">Hola, <?= htmlspecialchars($techName) ?></h2>
            <p class="text-muted small mb-4">
                El administrador necesita que subas la siguiente documentación para gestionar tu CAE.
                Adjunta cada archivo y pulsa <strong>Enviar documentos</strong>.
            </p>

            <?php if ($formError !== ''): ?>
                <div class="alert alert-warning d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <?= htmlspecialchars($formError) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($docStatuses)): ?>
                <?php
                    $hasIssues = false;
                    foreach ($docStatuses as $st) {
                        if (($st['state'] ?? '') !== 'valid') {
                            $hasIssues = true;
                            break;
                        }
                    }
                ?>
                <?php if ($hasIssues): ?>
                    <div class="alert alert-warning border-0 shadow-sm">
                        <div class="fw-semibold mb-2">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            Hay documentos que debes corregir
                        </div>
                        <p class="small mb-2">
                            El enlace sigue activo. Revisa los documentos marcados en rojo o amarillo,
                            corrige el archivo y vuelve a enviarlo.
                        </p>
                        <?php if (!empty($batchSummary)): ?>
                            <div class="small text-muted">
                                Válidos: <?= (int) ($batchSummary['valid'] ?? 0) ?> ·
                                No válidos: <?= (int) ($batchSummary['invalid'] ?? 0) ?> ·
                                Sin validar: <?= (int) ($batchSummary['pending'] ?? 0) ?> ·
                                Pendientes de subir: <?= (int) ($batchSummary['missing'] ?? 0) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php
            $request = $request ?? [];
            $customMsg = trim((string) ($request['custom_message'] ?? ''));
            if ($customMsg !== ''): ?>
                <div class="alert alert-light border small mb-4">
                    <i class="bi bi-chat-left-text me-1 text-success"></i>
                    <strong>Mensaje del administrador:</strong><br>
                    <?= nl2br(htmlspecialchars($customMsg)) ?>
                </div>
            <?php endif; ?>

            <div id="portal-upload-no-files" class="alert alert-warning py-2 small d-none mb-3" role="alert">
                Selecciona al menos un archivo antes de enviar.
            </div>

            <form id="portal-cae-upload-form" method="post" action="<?= htmlspecialchars($uploadUrl) ?>" enctype="multipart/form-data">
                <div class="mb-4">
                <?php foreach ($docs as $doc): ?>
                        <?php
                            $dtId = (int) ($doc['id'] ?? 0);
                            $statusRow = null;
                            foreach ($docStatuses as $candidate) {
                                if ((int) ($candidate['doc_type_id'] ?? 0) === $dtId) {
                                    $statusRow = $candidate;
                                    break;
                                }
                            }
                        ?>
                        <div class="mb-3 portal-doc-upload-row">
                            <label class="form-label fw-semibold small d-flex align-items-center gap-2 flex-wrap">
                                <span>
                                    <i class="bi bi-file-earmark-text me-1 text-success"></i>
                                    <?= htmlspecialchars((string) ($doc['name'] ?? '')) ?>
                                </span>
                                <?php if ($statusRow): ?>
                                    <span class="badge rounded-pill <?= htmlspecialchars((string) ($statusRow['badge'] ?? 'text-bg-secondary'), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string) ($statusRow['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                <?php endif; ?>
                            </label>

                            <?php if ($statusRow && trim((string) ($statusRow['message'] ?? '')) !== ''): ?>
                                <?php
                                    $statusState = (string) ($statusRow['state'] ?? '');
                                    $statusClass = match ($statusState) {
                                        'valid' => 'text-success',
                                        'pending' => 'text-warning',
                                        'missing' => 'text-muted',
                                        default => 'text-danger',
                                    };
                                ?>
                                <div class="portal-doc-status small mb-2 <?= $statusClass ?>">
                                    <?php if (($statusRow['filename'] ?? '') !== ''): ?>
                                        <strong><?= htmlspecialchars((string) $statusRow['filename'], ENT_QUOTES, 'UTF-8') ?>:</strong>
                                    <?php endif; ?>
                                    <?= htmlspecialchars((string) $statusRow['message'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>

                            <input
                                type="file"
                                class="form-control"
                                name="files[<?= $dtId ?>]"
                                accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                            >
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="d-grid">
                    <button type="submit" id="portal-cae-upload-submit-btn" class="btn btn-success btn-lg">
                        <i class="bi bi-upload me-2"></i>Enviar documentos
                    </button>
                </div>
            </form>

            <script>
            document.addEventListener('DOMContentLoaded', function () {
                const form = document.getElementById('portal-cae-upload-form');
                if (!form || !window.AppDocAnalyzeOverlay) return;

                window.AppDocAnalyzeOverlay.bindForm(form, {
                    title: 'Analizando documentos enviados',
                    requireFiles: true,
                    noFilesMessageId: 'portal-upload-no-files',
                    lockSelects: true,
                    submitButtonLoadingHtml:
                        '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Enviando…'
                });
            });
            </script>

            <p class="text-center text-muted small mt-4 mb-0">
                <i class="bi bi-lock me-1"></i>
                Enlace personal · Permanece activo hasta que todos los documentos sean válidos · Caduca en 7 días
            </p>
        </div>
    </div>
<?php endif; ?>

</div>