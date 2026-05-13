<?php declare(strict_types=1);

$ab = $areaBaseUrl ?? '/cae-inpro/public/admin';
$t  = $tech ?? [];
$current  = $currentCae ?? null;
$isValid  = (bool) ($isCurrentValid ?? false);
$doc      = $currentCaeDoc ?? null;
$requestableDocTypes = $requestableDocTypes ?? [];
$caeDocRequests      = $caeDocRequests ?? [];
$existingCaeDocs     = $existingCaeDocs ?? [];

$techId   = (int) ($t['id'] ?? 0);
$techName = trim(((string) ($t['first_name'] ?? '')) . ' ' . ((string) ($t['last_name'] ?? '')));
$returnToForm = $ab . '/tecnicos/' . $techId . '/cae';
$returnToTech = $ab . '/tecnicos/' . $techId . '#pane-hist';

$statusLabel = static function (string $status): string {
    return match ($status) {
        'pending'      => 'Pendiente',
        'approved'     => 'Aprobado',
        'in_review'    => 'En revisión',
        'pending_docs' => 'Pendiente docs.',
        'rejected'     => 'Rechazado',
        'expired'      => 'Caducado',
        default        => ucfirst($status),
    };
};
$statusBadge = static function (string $status): string {
    return match ($status) {
        'pending'      => 'text-bg-secondary',
        'approved'     => 'text-bg-success',
        'in_review'    => 'text-bg-warning',
        'pending_docs' => 'text-bg-warning text-dark',
        'rejected'     => 'text-bg-danger',
        'expired'      => 'text-bg-dark',
        default        => 'text-bg-light text-dark',
    };
};
?>

<!-- Loading overlay IA -->
<div id="ai-loading-overlay" style="display:none;">
    <div class="ai-loading-card">
        <div class="ai-loading-icon">
            <i class="bi bi-robot"></i>
        </div>
        <h5 class="ai-loading-title">Generando CAE con IA</h5>
        <div class="ai-loading-steps">
            <div class="ai-step" id="ai-step-1"><span class="ai-step-dot"></span><span>Analizando documentos</span></div>
            <div class="ai-step" id="ai-step-2"><span class="ai-step-dot"></span><span>Procesando con IA</span></div>
            <div class="ai-step" id="ai-step-3"><span class="ai-step-dot"></span><span>Construyendo PDF</span></div>
        </div>
        <p class="ai-loading-hint">Esto puede tardar unos segundos...</p>
    </div>
</div>

<div class="panel-identity mb-3">
    <div class="panel-identity-icon"><i class="bi bi-file-earmark-medical-fill"></i></div>
    <div>
        <p class="panel-identity-kicker mb-1">Gestión CAE · panel operativo</p>
        <h2 class="panel-identity-title mb-0">Revisión de CAE vigente</h2>
    </div>
</div>

<div class="page-header page-header--balanced page-header--premium mb-3">
    <div class="page-header-left">
        <h1 class="h3 page-title mb-1"><?= htmlspecialchars($techName !== '' ? $techName : 'Técnico') ?></h1>
        <p class="page-meta mb-0">
            <?= htmlspecialchars((string) ($t['professions'] ?? '-')) ?> ·
            <?= htmlspecialchars((string) ($t['city'] ?? '-')) ?>
            <?php if ($current): ?>
                · <span class="badge <?= $statusBadge((string) $current['status']) ?>">
                    <?= htmlspecialchars($statusLabel((string) $current['status'])) ?>
                </span>
                <span class="badge <?= $isValid ? 'text-bg-success' : 'text-bg-warning' ?>">
                    <?= $isValid ? 'Vigente' : 'Caducado' ?>
                </span>
            <?php else: ?>
                · <span class="badge text-bg-secondary">Sin CAE actual</span>
            <?php endif; ?>
        </p>
    </div>
    <div class="page-header-center"></div>
    <div class="page-header-right d-flex align-items-center gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($returnToTech) ?>" title="Volver">
            <i class="bi bi-arrow-left"></i>
        </a>
    </div>
</div>

<ul class="nav nav-pills app-subtabs mb-3" id="caeInnerTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-cae-manage" data-bs-toggle="tab" data-bs-target="#pane-cae-manage" type="button" role="tab">
            <i class="bi bi-sliders me-1"></i> Gestión CAE
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-cae-requests" data-bs-toggle="tab" data-bs-target="#pane-cae-requests" type="button" role="tab">
            <i class="bi bi-envelope-paper me-1"></i> Solicitudes documentos
        </button>
    </li>
</ul>

<div class="tab-content">
        <!-- ── Pestaña: Gestión CAE ── -->
        <div class="tab-pane fade show active" id="pane-cae-manage" role="tabpanel">
        <div class="row g-3">

            <!-- Resumen -->
            <div class="col-12">
                <div class="subpanel">
                    <div class="subpanel-h d-flex align-items-center justify-content-between">
                        <span>Resumen del CAE actual</span>
                        <?php if ($current): ?>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#manualCaeModal" title="Ajustar manualmente">
                                <i class="bi bi-gear me-1"></i> Ajustar manualmente
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="subpanel-b">
                        <?php if ($current): ?>
                            <div class="row g-2 small">
                                <div class="col-md-3"><strong>Estado:</strong> <?= htmlspecialchars($statusLabel((string) ($current['status'] ?? ''))) ?></div>
                                <div class="col-md-3"><strong>Válido desde:</strong> <?= htmlspecialchars((string) ($current['valid_from'] ?? '-')) ?></div>
                                <div class="col-md-3"><strong>Válido hasta:</strong> <?= htmlspecialchars((string) ($current['valid_until'] ?? '-')) ?></div>
                                <div class="col-md-3"><strong>Situación:</strong> <?= $isValid ? 'Vigente' : 'Caducado' ?></div>
                            </div>
                            <?php if (!empty($current['notes'])): ?>
                                <p class="text-muted small mb-0 mt-2"><?= htmlspecialchars((string) $current['notes']) ?></p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="text-muted mb-0">No hay revisión CAE actual. Genera una nueva con IA para empezar.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Formulario IA (ancho completo) -->
            <div class="col-12">
                <div class="subpanel">
                    <div class="subpanel-h d-flex align-items-center gap-2">
                        <i class="bi bi-stars text-primary"></i>
                        <span>Generar CAE con IA</span>
                    </div>
                    <div class="subpanel-b">
                        <form id="cae-ai-form" enctype="multipart/form-data">
                            <input type="hidden" name="technician_id" value="<?= $techId ?>">

                            <div class="row g-3 align-items-start">

                                <!-- Columna izquierda: documentos -->
                                <div class="col-md-7">
                                    <label class="form-label fw-semibold mb-2">Documentos existentes</label>
                                    <?php if (empty($existingCaeDocs)): ?>
                                        <p class="small text-muted">No hay documentos subidos todavía.</p>
                                    <?php else: ?>
                                        <div class="doc-chips-wrap mb-3">
                                            <?php foreach ($existingCaeDocs as $d): ?>
                                                <label class="doc-chip">
                                                    <input type="checkbox" name="existing_doc_ids[]" value="<?= (int) $d['id'] ?>">
                                                    <span title="<?= htmlspecialchars((string) $d['original_filename']) ?>">
                                                        <i class="bi bi-file-earmark me-1"></i><?= htmlspecialchars((string) $d['original_filename']) ?>
                                                    </span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <label class="form-label fw-semibold">Adjuntar nuevos documentos <span class="text-muted fw-normal">(opcional)</span></label>
                                    <input type="file" name="new_docs[]" class="form-control" multiple>
                                </div>

                                <!-- Columna derecha: fechas + botón -->
                                <div class="col-md-5">
                                    <label class="form-label fw-semibold">Fechas de validez del CAE</label>
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <label class="form-label small text-muted mb-1">Válido desde</label>
                                            <input type="date" name="valid_from" id="ai-valid-from" class="form-control form-control-sm"
                                                value="<?= htmlspecialchars((string) ($current['valid_from'] ?? date('Y-m-d'))) ?>">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small text-muted mb-1">Válido hasta</label>
                                            <input type="date" name="valid_until" id="ai-valid-until" class="form-control form-control-sm"
                                                value="<?= htmlspecialchars((string) ($current['valid_until'] ?? '')) ?>">
                                        </div>
                                    </div>

                                    <div id="ai-form-error" class="alert alert-warning small py-2 d-none">
                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                        <span id="ai-form-error-msg"></span>
                                    </div>

                                    <button type="submit" class="btn btn-primary w-100" id="ai-generate-btn">
                                        <i class="bi bi-stars me-1"></i> Generar CAE PDF con IA
                                    </button>
                                </div>

                            </div>
                        </form>

                        <?php if ($doc): ?>
                            <div class="d-flex align-items-center justify-content-between mt-3 p-2 bg-light rounded small">
                                <span class="text-muted text-truncate me-2"><i class="bi bi-file-earmark-pdf me-1 text-danger"></i><?= htmlspecialchars((string) ($doc['original_filename'] ?? '-')) ?></span>
                                <div class="d-flex gap-1 flex-shrink-0">
                                    <?php if (!empty($doc['storage_path'])): ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                            data-file-preview
                                            data-file-preview-url="<?= htmlspecialchars((string) (($baseUrl ?? '') . ($doc['storage_path'] ?? ''))) ?>"
                                            data-file-preview-name="<?= htmlspecialchars((string) ($doc['original_filename'] ?? 'CAE')) ?>"
                                            title="Ver"><i class="bi bi-eye"></i></button>
                                    <?php endif; ?>
                                    <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($ab) ?>/cae/documentos/<?= (int) ($doc['id'] ?? 0) ?>/download" title="Descargar"><i class="bi bi-download"></i></a>
                                    <form method="post" action="<?= htmlspecialchars($ab) ?>/cae/documentos/<?= (int) ($doc['id'] ?? 0) ?>" data-confirm="¿Quitar archivo CAE actual?" class="m-0">
                                        <input type="hidden" name="_method" value="DELETE">
                                        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnToForm) ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit" title="Quitar"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- ── Pestaña: Solicitudes ── -->
    <div class="tab-pane fade" id="pane-cae-requests" role="tabpanel">

        <!-- Formulario solicitud inline -->
        <div class="subpanel mb-3">
            <div class="subpanel-h"><i class="bi bi-envelope-paper me-2"></i>Solicitar documentos al técnico</div>
            <div class="subpanel-b">
                <p class="text-muted small mb-3">
                    Se enviará un email a <strong><?= htmlspecialchars((string) ($t['email'] ?? 'sin email')) ?></strong> con la solicitud de los documentos seleccionados.
                </p>
                <form method="post" action="<?= htmlspecialchars($ab) ?>/tecnicos/<?= $techId ?>/cae/request-docs">
                    <label class="form-label fw-semibold">Documentos solicitados <span class="text-danger">*</span></label>
                    <?php if (empty($requestableDocTypes)): ?>
                        <div class="alert alert-warning py-2 mb-3">No hay tipos de documento configurados para <code>technician_cae</code>.</div>
                    <?php else: ?>
                        <div class="doc-chips-wrap mb-3">
                            <?php foreach ($requestableDocTypes as $dt): ?>
                                <label class="doc-chip">
                                    <input type="checkbox" name="document_type_ids[]" value="<?= (int) $dt['id'] ?>">
                                    <span title="<?= htmlspecialchars((string) $dt['name']) ?>">
                                        <i class="bi bi-file-earmark me-1"></i><?= htmlspecialchars((string) $dt['name']) ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <label class="form-label fw-semibold" for="custom_message">Mensaje adicional (opcional)</label>
                    <textarea id="custom_message" name="custom_message" class="form-control mb-3" rows="3"
                        placeholder="Ej: Necesitamos esta documentación antes del viernes."></textarea>
                    <button type="submit" class="btn btn-primary" <?= empty($requestableDocTypes) ? 'disabled' : '' ?>>
                        <i class="bi bi-send me-1"></i> Enviar solicitud
                    </button>
                </form>
            </div>
        </div>

        <!-- Historial -->
        <div class="subpanel">
            <div class="subpanel-h d-flex justify-content-between align-items-center">
                <span>Historial de solicitudes</span>
                <span class="badge text-bg-light text-dark"><?= count($caeDocRequests) ?></span>
            </div>
            <div class="subpanel-b p-0">
                <?php if (empty($caeDocRequests)): ?>
                    <p class="text-muted small mb-0 p-3">Aún no se han enviado solicitudes.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Fecha</th><th>Documentos solicitados</th><th>Estado</th><th>Solicitado por</th></tr></thead>
                            <tbody>
                            <?php foreach ($caeDocRequests as $r): ?>
                                <?php
                                    $docs   = json_decode((string) ($r['documents_requested_json'] ?? '[]'), true) ?: [];
                                    $status = (string) ($r['status'] ?? '');
                                    $badge  = match ($status) { 'sent' => 'text-bg-success', 'failed' => 'text-bg-warning', 'completed' => 'text-bg-primary', 'cancelled' => 'text-bg-secondary', default => 'text-bg-light text-dark' };
                                    $slabel = match ($status) { 'sent' => 'Enviada', 'failed' => 'Error envío', 'completed' => 'Completada', 'cancelled' => 'Cancelada', default => ucfirst($status) };
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) ($r['sent_at'] ?? '-')) ?></td>
                                    <td><?php foreach ($docs as $d): ?><span class="badge text-bg-light border me-1 mb-1"><?= htmlspecialchars((string) ($d['name'] ?? '-')) ?></span><?php endforeach; ?></td>
                                    <td><span class="badge <?= $badge ?>"><?= $slabel ?></span></td>
                                    <td><?= htmlspecialchars((string) ($r['requested_by_name'] ?? '-')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Ajustes manuales del CAE -->
<div class="modal fade" id="manualCaeModal" tabindex="-1" aria-labelledby="manualCaeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="manualCaeModalLabel">
                    <i class="bi bi-gear me-2"></i>Ajustar CAE vigente manualmente
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="cae-operation-form" method="post" action="<?= htmlspecialchars($ab) ?>/tecnicos/<?= $techId ?>/cae">
                <div class="modal-body">
                    <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnToForm) ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Estado</label>
                            <select name="status" class="form-select" required>
                                <option value="pending"      <?= (($current['status'] ?? '') === 'pending')      ? 'selected' : '' ?>>Pendiente</option>
                                <option value="pending_docs" <?= (($current['status'] ?? '') === 'pending_docs') ? 'selected' : '' ?>>Pendiente docs.</option>
                                <option value="in_review"    <?= (($current['status'] ?? '') === 'in_review')    ? 'selected' : '' ?>>En revisión</option>
                                <option value="approved"     <?= (($current['status'] ?? '') === 'approved')     ? 'selected' : '' ?>>Aprobado</option>
                                <option value="rejected"     <?= (($current['status'] ?? '') === 'rejected')     ? 'selected' : '' ?>>Rechazado</option>
                                <option value="expired"      <?= (($current['status'] ?? '') === 'expired')      ? 'selected' : '' ?>>Caducado</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Válido desde</label>
                            <input type="date" name="valid_from" id="valid_from" class="form-control"
                                value="<?= htmlspecialchars((string) ($current['valid_from'] ?? date('Y-m-d'))) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Válido hasta</label>
                            <input type="date" name="valid_until" id="valid_until" class="form-control"
                                value="<?= htmlspecialchars((string) ($current['valid_until'] ?? '')) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notas</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Observaciones"><?= htmlspecialchars((string) ($current['notes'] ?? '')) ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" id="save-cae-btn" class="btn btn-success">
                        <i class="bi bi-check2-circle me-1"></i> Guardar cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Preview PDF generado por IA -->
<div class="modal fade" id="aiPreviewModal" tabindex="-1" aria-labelledby="aiPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="aiPreviewModalLabel"><i class="bi bi-file-earmark-pdf me-2"></i>Vista previa — CAE generado</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="ai-pdf-frame" src="" style="width:100%;height:72vh;border:none;"></iframe>
            </div>
            <div class="modal-footer">
                <div class="me-auto d-flex align-items-center gap-2">
                    <span class="badge" id="ai-status-badge">–</span>
                    <small class="text-muted" id="ai-status-hint"></small>
                </div>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Descartar</button>
                <button type="button" class="btn btn-success" id="ai-save-btn">
                    <i class="bi bi-cloud-arrow-up me-1"></i> Guardar como CAE vigente
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Conflicto CAE vigente -->
<div class="modal fade" id="aiConflictModal" tabindex="-1" aria-labelledby="aiConflictLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="aiConflictLabel"><i class="bi bi-exclamation-triangle me-2 text-warning"></i>Ya existe un CAE vigente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Este técnico ya tiene un CAE vigente activo. ¿Cómo deseas proceder?</p>
                <div class="form-check border rounded p-3 mb-2">
                    <input class="form-check-input" type="radio" name="conflict_action" id="conflict-new" value="new_revision" checked>
                    <label class="form-check-label" for="conflict-new">
                        <strong>Nueva revisión <span class="badge text-bg-success ms-1">Recomendado</span></strong>
                        <div class="text-muted small mt-1">Archiva el CAE actual como histórico y crea uno nuevo con el PDF generado y las fechas de la IA.</div>
                    </label>
                </div>
                <div class="form-check border rounded p-3">
                    <input class="form-check-input" type="radio" name="conflict_action" id="conflict-replace" value="replace_pdf">
                    <label class="form-check-label" for="conflict-replace">
                        <strong>Reemplazar documento</strong>
                        <div class="text-muted small mt-1">Sustituye únicamente el PDF del CAE actual, actualizando también el estado y fechas. No crea historial.</div>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="ai-conflict-confirm">
                    <i class="bi bi-check2 me-1"></i> Confirmar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    /* ── 1. Formulario manual (ahora en modal) ── */
    // El formulario está en el modal; no necesita dirty-check activo.
    // Si no hay CAE vigente, pre-rellenar fechas por defecto.
    const hasCurrent    = <?= $current ? 'true' : 'false' ?>;
    const isCurrentValid = <?= $isValid ? 'true' : 'false' ?>;
    if (!hasCurrent || !isCurrentValid) {
        const today = new Date().toISOString().slice(0, 10);
        const plus3 = new Date(); plus3.setMonth(plus3.getMonth() + 3);
        const plus3Str = plus3.toISOString().slice(0, 10);
        const fromEl  = document.getElementById('valid_from');
        const untilEl = document.getElementById('valid_until');
        if (fromEl  && !fromEl.value)  fromEl.value  = today;
        if (untilEl && !untilEl.value) untilEl.value = plus3Str;
    }

    /* ── 2. Generación IA ── */
    const aiForm      = document.getElementById('cae-ai-form');
    const overlay     = document.getElementById('ai-loading-overlay');
    const pdfFrame    = document.getElementById('ai-pdf-frame');
    const statusBadge = document.getElementById('ai-status-badge');
    const statusHint  = document.getElementById('ai-status-hint');
    const aiSaveBtn   = document.getElementById('ai-save-btn');
    const conflictConfirmBtn = document.getElementById('ai-conflict-confirm');
    const baseUrl     = document.querySelector('meta[name="app-base-url"]')?.content || '';
    const techId      = <?= $techId ?>;

    let currentGenId     = null;
    let currentPdfUrl    = null;
    let currentCaeStatus = null;

    const previewModal  = bootstrap.Modal.getOrCreateInstance(document.getElementById('aiPreviewModal'));
    const conflictModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('aiConflictModal'));

    // Pasos animados
    const steps = [document.getElementById('ai-step-1'), document.getElementById('ai-step-2'), document.getElementById('ai-step-3')];
    let stepInterval = null;
    const startStepAnimation = () => {
        let i = 0;
        steps.forEach(s => s.classList.remove('active', 'done'));
        stepInterval = setInterval(() => {
            if (i > 0) steps[i - 1]?.classList.replace('active', 'done');
            if (i < steps.length) { steps[i].classList.add('active'); i++; }
            else clearInterval(stepInterval);
        }, 1200);
    };
    const stopStepAnimation = () => {
        clearInterval(stepInterval);
        steps.forEach(s => { s.classList.remove('active'); s.classList.add('done'); });
    };

    const statusMeta = {
        approved:     { cls: 'text-bg-success',   label: 'Aprobado',         hint: 'La IA determinó que la documentación está completa y válida.' },
        in_review:    { cls: 'text-bg-warning',   label: 'En revisión',      hint: 'La IA recomienda revisión manual antes de aprobar.' },
        pending_docs: { cls: 'text-bg-warning text-dark', label: 'Pendiente docs.',  hint: 'Faltan documentos obligatorios: Póliza RC y/o Recibo RC.' },
        rejected:     { cls: 'text-bg-danger',    label: 'Rechazado',        hint: 'Documentos inválidos, caducados o ilegibles según la IA.' },
    };

    const aiFormError    = document.getElementById('ai-form-error');
    const aiFormErrorMsg = document.getElementById('ai-form-error-msg');

    function showAiError(msg) {
        if (aiFormError && aiFormErrorMsg) {
            aiFormErrorMsg.textContent = msg;
            aiFormError.classList.remove('d-none');
            aiFormError.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }
    function hideAiError() {
        if (aiFormError) aiFormError.classList.add('d-none');
    }

    if (aiForm) {
        aiForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            hideAiError();
            overlay.style.display = 'flex';
            startStepAnimation();

            const fd = new FormData(aiForm);
            const aiFrom  = document.getElementById('ai-valid-from');
            const aiUntil = document.getElementById('ai-valid-until');
            if (aiFrom?.value)  fd.set('valid_from',  aiFrom.value);
            if (aiUntil?.value) fd.set('valid_until', aiUntil.value);

            try {
                const res = await fetch(`${baseUrl}/admin/tecnicos/${techId}/cae/ia/generate`, {
                    method: 'POST',
                    body: fd,
                });
                const data = await res.json();
                stopStepAnimation();
                overlay.style.display = 'none';

                if (!data.ok) {
                    showAiError(data.error || 'Error desconocido al generar el CAE.');
                    return;
                }

                currentGenId     = data.generation_id;
                currentPdfUrl    = data.pdf_url;
                currentCaeStatus = data.cae_status;

                // Mostrar preview
                pdfFrame.src = currentPdfUrl;
                const meta = statusMeta[currentCaeStatus] || statusMeta['in_review'];
                statusBadge.className = 'badge ' + meta.cls;
                statusBadge.textContent = meta.label;
                statusHint.textContent  = meta.hint;

                previewModal.show();
            } catch (err) {
                stopStepAnimation();
                overlay.style.display = 'none';
                showAiError('Error de comunicación con el servidor. Inténtalo de nuevo.');
                console.error('[CAE AI] generate error:', err.message);
            }
        });
    }

    // Click "Guardar como CAE vigente"
    if (aiSaveBtn) {
        aiSaveBtn.addEventListener('click', () => {
            if (hasCurrent) {
                previewModal.hide();
                document.getElementById('aiPreviewModal').addEventListener('hidden.bs.modal', () => {
                    conflictModal.show();
                }, { once: true });
            } else {
                doSave('new_revision');
            }
        });
    }

    // Click "Confirmar" en modal de conflicto
    if (conflictConfirmBtn) {
        conflictConfirmBtn.addEventListener('click', () => {
            const chosen = document.querySelector('input[name="conflict_action"]:checked')?.value || 'new_revision';
            conflictModal.hide();
            doSave(chosen);
        });
    }

    async function doSave(conflictAction) {
        overlay.style.display = 'flex';
        startStepAnimation();
        try {
            const fd = new FormData();
            fd.append('generation_id', String(currentGenId));
            fd.append('conflict_action', conflictAction);
            const res  = await fetch(`${baseUrl}/admin/tecnicos/${techId}/cae/ia/save`, { method: 'POST', body: fd });
            const data = await res.json();
            stopStepAnimation();
            overlay.style.display = 'none';
            if (data.ok) {
                window.location.href = data.redirect_url;
            } else {
                showAiError('Error al guardar: ' + (data.error || 'Error desconocido.'));
            }
        } catch (err) {
            stopStepAnimation();
            overlay.style.display = 'none';
            showAiError('Error de comunicación al guardar. Inténtalo de nuevo.');
            console.error('[CAE AI] save error:', err.message);
        }
    }

    /* ── 3. Persistencia de pestaña por hash ── */
    const hashMap = { '#cae-manage': 'tab-cae-manage', '#cae-requests': 'tab-cae-requests' };
    const activateTabFromHash = () => {
        const id = hashMap[window.location.hash];
        if (id) bootstrap.Tab.getOrCreateInstance(document.getElementById(id))?.show();
    };
    activateTabFromHash();
    document.querySelectorAll('#caeInnerTabs [data-bs-toggle="tab"]').forEach(btn => {
        btn.addEventListener('shown.bs.tab', (e) => {
            const id = e.target.id;
            if (id === 'tab-cae-manage') window.history.replaceState(null, '', '#cae-manage');
            if (id === 'tab-cae-requests') window.history.replaceState(null, '', '#cae-requests');
        });
    });
});
</script>