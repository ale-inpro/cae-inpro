<?php declare(strict_types=1);

$state     = (string) ($state    ?? 'error');

$token     = (string) ($token    ?? '');

$baseUrl   = (string) ($baseUrl  ?? '');

$docs      = (array)  ($docs     ?? []);

$techName  = (string) ($techName ?? 'Técnico/a');

$formError = (string) ($formError ?? '');

$uploaded  = (int)    ($uploaded  ?? 0);

$msg       = (string) ($msg      ?? '');

$haciendaCsvUrl = rtrim($baseUrl, '/') . '/portal/' . $token . '/hacienda-csv';

$haciendaTypeName = \App\Services\CaeReadinessService::DOCUMENT_TYPE_NAME_HACIENDA;

$hasHaciendaRequested = false;

foreach ($docs as $d) {

    if (trim((string) ($d['name'] ?? '')) === $haciendaTypeName) {

        $hasHaciendaRequested = true;

        break;

    }

}

$docStatuses  = (array)  ($docStatuses  ?? []);

$batchSummary = (array)  ($batchSummary ?? []);

$showUploadFeedback = !empty($showUploadFeedback);

$haciendaConfirmUrl = rtrim($baseUrl, '/') . '/portal/' . $token . '/hacienda-confirm-csv';

$portalProgressValid = 0;

$portalProgressTotal = count($docs);

$haciendaStatus = '';

foreach ($docStatuses as $st) {

    if (($st['state'] ?? '') === 'valid') {

        $portalProgressValid++;

    }

    if (trim((string) ($st['doc_type_name'] ?? '')) === $haciendaTypeName) {

        $haciendaStatus = (string) ($st['state'] ?? '');

    }

}

$portalProgressPct = $portalProgressTotal > 0

    ? (int) round(($portalProgressValid / $portalProgressTotal) * 100)

    : 0;

$showHaciendaMode = $hasHaciendaRequested

    && !in_array($haciendaStatus, ['valid', 'confirm_csv'], true);



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

                Sube <strong>cada documento por separado</strong>. Los que ya estén validados quedan bloqueados.

            </p>



            <?php if ($portalProgressTotal > 0): ?>

                <div class="mb-4">

                    <div class="d-flex justify-content-between small mb-1">

                        <span class="text-muted">Progreso</span>

                        <span class="fw-semibold"><?= $portalProgressValid ?> de <?= $portalProgressTotal ?> completados</span>

                    </div>

                    <div class="progress" style="height:8px">

                        <div class="progress-bar bg-success" style="width: <?= $portalProgressPct ?>%"></div>

                    </div>

                </div>

            <?php endif; ?>



            <?php if ($formError !== ''): ?>

                <div class="alert alert-warning d-flex align-items-center gap-2">

                    <i class="bi bi-exclamation-triangle-fill"></i>

                    <?= htmlspecialchars($formError) ?>

                </div>

            <?php endif; ?>



            <?php if ($showUploadFeedback && !empty($docStatuses)): ?>

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

                            Revisa los documentos marcados en rojo o amarillo y vuelve a enviarlos uno a uno.

                        </p>

                        <?php if (!empty($batchSummary)): ?>

                            <div class="small text-muted">

                                Válidos: <?= (int) ($batchSummary['valid'] ?? 0) ?> ·

                                No válidos: <?= (int) ($batchSummary['invalid'] ?? 0) ?> ·

                                Confirma CSV: <?= (int) ($batchSummary['confirm_csv'] ?? 0) ?> ·

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



            <?php if ($showHaciendaMode): ?>

                <div class="alert alert-light border small mb-3" id="portal-hacienda-mode-box">

                    <div class="fw-semibold mb-2">

                        Certificado de Hacienda: elige cómo enviarlo

                    </div>

                    <div class="form-check form-check-inline">

                        <input class="form-check-input" type="radio" name="portal_hacienda_mode" id="portal-hacienda-mode-file" value="file" checked>

                        <label class="form-check-label" for="portal-hacienda-mode-file">Subir documento PDF</label>

                    </div>

                    <div class="form-check form-check-inline">

                        <input class="form-check-input" type="radio" name="portal_hacienda_mode" id="portal-hacienda-mode-csv" value="csv">

                        <label class="form-check-label" for="portal-hacienda-mode-csv">Enviar por CSV (16 caracteres)</label>

                    </div>

                </div>

            <?php endif; ?>



            <div class="mb-4 portal-doc-checklist">

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

                        $isHaciendaRow = trim((string) ($doc['name'] ?? '')) === $haciendaTypeName;

                        $statusState = (string) ($statusRow['state'] ?? 'missing');

                        $isDocComplete = ($statusState === 'valid');

                        $needsCsvConfirm = $isHaciendaRow && $statusState === 'confirm_csv';

                        $suggestedCsv = $needsCsvConfirm ? trim((string) ($statusRow['suggested_csv'] ?? '')) : '';

                        $pendingIntakeId = $needsCsvConfirm ? (int) ($statusRow['intake_id'] ?? 0) : 0;

                        $docUploadUrl = rtrim($baseUrl, '/') . '/portal/' . rawurlencode($token) . '/document/' . $dtId . '/upload';

                        $cardClass = $isDocComplete ? 'border-success bg-light' : 'border-light';

                        $showStatusUi = $statusRow && in_array($statusState, ['valid', 'invalid', 'error', 'confirm_csv', 'pending'], true);

                    ?>

                    <div class="card mb-3 portal-doc-card <?= $cardClass ?><?= $isHaciendaRow ? ' portal-hacienda-row' : '' ?>"<?= $isHaciendaRow ? ' id="portal-hacienda-row"' : '' ?>>

                        <div class="card-body">

                            <div class="d-flex align-items-center gap-2 flex-wrap mb-2">

                                <span class="fw-semibold small">

                                    <i class="bi bi-file-earmark-text me-1 text-success"></i>

                                    <?= htmlspecialchars((string) ($doc['name'] ?? '')) ?>

                                </span>

                                <?php if ($showStatusUi): ?>

                                    <span class="badge rounded-pill <?= htmlspecialchars((string) ($statusRow['badge'] ?? 'text-bg-secondary'), ENT_QUOTES, 'UTF-8') ?>">

                                        <?= htmlspecialchars((string) ($statusRow['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>

                                    </span>

                                <?php endif; ?>

                            </div>



                            <?php if ($showStatusUi && trim((string) ($statusRow['message'] ?? '')) !== ''): ?>

                                <?php

                                    $statusClass = match ($statusState) {

                                        'valid' => 'text-success',

                                        'pending', 'confirm_csv' => 'text-warning',

                                        default => 'text-danger',

                                    };

                                ?>

                                <div class="portal-doc-status small mb-3 <?= $statusClass ?>">

                                    <?php if (($statusRow['filename'] ?? '') !== ''): ?>

                                        <strong><?= htmlspecialchars((string) $statusRow['filename'], ENT_QUOTES, 'UTF-8') ?>:</strong>

                                    <?php endif; ?>

                                    <?= htmlspecialchars((string) $statusRow['message'], ENT_QUOTES, 'UTF-8') ?>

                                </div>

                            <?php endif; ?>



                            <?php if ($isDocComplete): ?>

                                <div class="alert alert-success py-2 small mb-0">

                                    <i class="bi bi-check-circle-fill me-1"></i>

                                    Documento validado. No necesitas volver a enviarlo.

                                </div>

                            <?php elseif ($isHaciendaRow): ?>

                                <div id="portal-hacienda-file-panel" class="<?= $needsCsvConfirm ? 'd-none' : '' ?>">

                                    <form method="post" action="<?= htmlspecialchars($docUploadUrl, ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data" class="portal-doc-upload-form">

                                        <input type="file" name="file" class="form-control portal-hacienda-file-input mb-2" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>

                                        <button type="submit" class="btn btn-success btn-sm">

                                            <i class="bi bi-upload me-1"></i>Enviar este documento

                                        </button>

                                    </form>

                                </div>



                                <div id="portal-hacienda-csv-direct-panel" class="d-none">

                                    <div class="row g-2 align-items-end portal-hacienda-csv-controls">

                                        <input type="hidden" form="portal-hacienda-csv-form" name="document_type_id" value="<?= $dtId ?>">

                                        <div class="col-md-8">

                                            <label class="form-label small mb-0" for="portal-hacienda-csv-input">CSV del certificado (16 caracteres)</label>

                                            <input type="text"

                                                id="portal-hacienda-csv-input"

                                                form="portal-hacienda-csv-form"

                                                name="manual_aeat_csv"

                                                class="form-control text-uppercase"

                                                maxlength="16"

                                                pattern="[A-Za-z0-9]{16}"

                                                placeholder="16 caracteres"

                                                autocomplete="off"

                                                required>

                                        </div>

                                        <div class="col-md-4">

                                            <button type="submit" form="portal-hacienda-csv-form" class="btn btn-outline-primary w-100">

                                                <i class="bi bi-search me-1"></i>Obtener certificado

                                            </button>

                                        </div>

                                    </div>

                                </div>



                                <?php if ($needsCsvConfirm): ?>

                                    <div id="portal-hacienda-confirm-panel" class="mt-2">

                                        <p class="small text-muted mb-2">

                                            Confirma el CSV del pie del PDF subido. Si no coincide, corrígelo antes de enviar.

                                        </p>

                                        <div class="row g-2 align-items-end portal-hacienda-confirm-controls">

                                            <input type="hidden" form="portal-hacienda-confirm-form" name="intake_id" value="<?= $pendingIntakeId ?>">

                                            <div class="col-md-8">

                                                <label class="form-label small mb-0" for="portal-hacienda-confirm-input">CSV *</label>

                                                <input type="text"

                                                    id="portal-hacienda-confirm-input"

                                                    form="portal-hacienda-confirm-form"

                                                    name="manual_aeat_csv"

                                                    class="form-control text-uppercase"

                                                    maxlength="16"

                                                    pattern="[A-Za-z0-9]{16}"

                                                    placeholder="16 caracteres"

                                                    autocomplete="off"

                                                    required

                                                    value="<?= htmlspecialchars($suggestedCsv, ENT_QUOTES, 'UTF-8') ?>">

                                            </div>

                                            <div class="col-md-4">

                                                <button type="submit" form="portal-hacienda-confirm-form" class="btn btn-primary w-100">

                                                    <i class="bi bi-check2 me-1"></i>Confirmar CSV y enviar

                                                </button>

                                            </div>

                                        </div>

                                    </div>

                                <?php endif; ?>

                            <?php else: ?>

                                <form method="post" action="<?= htmlspecialchars($docUploadUrl, ENT_QUOTES, 'UTF-8') ?>" enctype="multipart/form-data" class="portal-doc-upload-form">

                                    <input type="file" name="file" class="form-control mb-2" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>

                                    <button type="submit" class="btn btn-success btn-sm">

                                        <i class="bi bi-upload me-1"></i>Enviar este documento

                                    </button>

                                </form>

                            <?php endif; ?>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>



            <?php if ($hasHaciendaRequested): ?>

                <form id="portal-hacienda-csv-form"

                    method="post"

                    action="<?= htmlspecialchars($haciendaCsvUrl, ENT_QUOTES, 'UTF-8') ?>"

                    class="d-none"></form>

                <form id="portal-hacienda-confirm-form"

                    method="post"

                    action="<?= htmlspecialchars($haciendaConfirmUrl, ENT_QUOTES, 'UTF-8') ?>"

                    class="d-none"></form>

            <?php endif; ?>



            <script>

            document.addEventListener('DOMContentLoaded', function () {

                document.querySelectorAll('.portal-doc-upload-form').forEach(function (docForm) {

                    docForm.addEventListener('submit', function () {

                        window.AppDocAnalyzeOverlay?.show({ title: 'Analizando documento enviado' });

                    });

                });



                [

                    { id: 'portal-hacienda-csv-form', title: 'Consultando certificado en Hacienda' },

                    { id: 'portal-hacienda-confirm-form', title: 'Confirmando CSV y consultando Hacienda' },

                ].forEach(function (cfg) {

                    const csvForm = document.getElementById(cfg.id);

                    if (!csvForm) return;

                    csvForm.addEventListener('submit', function () {

                        window.AppDocAnalyzeOverlay?.show({ title: cfg.title });

                    });

                });



                const modeFile = document.getElementById('portal-hacienda-mode-file');

                const modeCsv = document.getElementById('portal-hacienda-mode-csv');

                const modeBox = document.getElementById('portal-hacienda-mode-box');

                const haciendaRow = document.getElementById('portal-hacienda-row');

                const filePanel = document.getElementById('portal-hacienda-file-panel');

                const csvDirectPanel = document.getElementById('portal-hacienda-csv-direct-panel');

                const confirmPanel = document.getElementById('portal-hacienda-confirm-panel');



                const syncHaciendaMode = () => {

                    if (!haciendaRow) return;



                    const hasConfirm = !!confirmPanel;

                    const useCsv = !!modeCsv?.checked && !hasConfirm;



                    if (hasConfirm) {

                        modeFile && (modeFile.checked = true);

                        if (modeCsv) modeCsv.disabled = true;

                        if (modeBox) modeBox.classList.add('d-none');

                    } else {

                        if (modeCsv) modeCsv.disabled = false;

                        if (modeBox) modeBox.classList.remove('d-none');

                    }



                    if (filePanel) {

                        filePanel.classList.toggle('d-none', useCsv || hasConfirm);

                        filePanel.querySelectorAll('input[type="file"], button[type="submit"]').forEach((el) => {

                            el.disabled = useCsv || hasConfirm;

                        });

                    }



                    if (csvDirectPanel) {

                        csvDirectPanel.classList.toggle('d-none', !useCsv);

                        csvDirectPanel.querySelectorAll('input, button').forEach((el) => {

                            el.disabled = !useCsv;

                        });

                    }



                    if (confirmPanel && hasConfirm) {

                        confirmPanel.classList.remove('d-none');

                    }

                };



                modeFile?.addEventListener('change', syncHaciendaMode);

                modeCsv?.addEventListener('change', syncHaciendaMode);

                syncHaciendaMode();

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


