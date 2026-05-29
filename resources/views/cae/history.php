<?php declare(strict_types=1);

$ab = $areaBaseUrl ?? '/cae-inpro/public/admin';
$t  = $tech ?? [];
$current  = $currentCae ?? null;
$isValid  = (bool) ($isCurrentValid ?? false);
$doc      = $currentCaeDoc ?? null;
$requestableDocTypes = $requestableDocTypes ?? [];
$existingCaeDocs     = $existingCaeDocs ?? [];
$activeSupportingFilenameByDocTypeId = $activeSupportingFilenameByDocTypeId ?? [];
$aeatCotejoUseMock = !empty($aeatCotejoUseMock);

$techId   = (int) ($t['id'] ?? 0);
$techName = trim((string) ($t['display_name'] ?? ''));
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
                                <div class="col-md-3"><strong>Válido desde:</strong> <span class="date-display"><?= app_date($current['valid_from'] ?? null) ?></span></div>
                                <div class="col-md-3"><strong>Válido hasta:</strong> <span class="date-display"><?= app_date($current['valid_until'] ?? null) ?></span></div>
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
                        <form id="cae-ai-form" method="post" action="<?= htmlspecialchars($ab) ?>/tecnicos/<?= $techId ?>/cae/ia/generate">
                            <input type="hidden" name="technician_id" value="<?= $techId ?>">

                            <div class="row g-3 align-items-start">

                                <div class="col-md-7">
                                    <label class="form-label fw-semibold mb-2">Documentación que usará la IA</label>
                                    <p class="small text-muted mb-3">
                                    Se cargan automáticamente los complementarios activos del CAE vigente.
                                    Para generar con IA son obligatorios <strong>Hacienda, Seguridad Social y Póliza de Responsabilidad Civil</strong> (vigentes; Hacienda se comprueba automáticamente al publicar).
                                    </p>
                                    <?php if (empty($existingCaeDocs)): ?>
                                        <p class="small text-muted">No hay complementarios en este CAE vigente.</p>
                                    <?php else: ?>
                                        <div id="cae-ai-doc-list" class="doc-chips-wrap mb-3 flex-column align-items-stretch gap-2">
                                            <?php foreach ($existingCaeDocs as $d): ?>
                                                <?php
                                                    $aeAt = isset($d['aeat_cotejo_checked_at']) ? trim((string) $d['aeat_cotejo_checked_at']) : '';
                                                    $aeMock = !empty($d['aeat_cotejo_used_mock']);
                                                    $docId = (int) ($d['id'] ?? 0);
                                                    $isHaciendaDoc = trim((string) ($d['document_name'] ?? '')) === \App\Services\CaeReadinessService::DOCUMENT_TYPE_NAME_HACIENDA;
                                                ?>
                                                <div class="border rounded px-3 py-2 small d-flex justify-content-between align-items-start gap-2 flex-wrap">
                                                    <div class="min-w-0 flex-grow-1">
                                                        <div class="fw-semibold text-truncate" title="<?= htmlspecialchars((string) ($d['original_filename'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                            <i class="bi bi-file-earmark me-1"></i><?= htmlspecialchars((string) ($d['original_filename'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                        </div>
                                                        <div class="doc-chip-meta d-flex flex-wrap align-items-center gap-1 mt-1">
                                                            <?php if ($aeatCotejoUseMock && $aeMock && $aeAt !== ''): ?>
                                                                <span class="badge rounded-pill text-bg-light text-dark border" title="Entorno de prueba">Prueba</span>
                                                            <?php endif; ?>
                                                            <?php
                                                                $cv = $d['cae_validity'] ?? null;
                                                                $docTypeName = trim((string) ($d['document_name'] ?? ''));
                                                                $isPrlOptional = $docTypeName === \App\Services\CaeReadinessService::DOCUMENT_TYPE_NAME_PRL;
                                                                if ($isPrlOptional):
                                                            ?>
                                                                <span class="badge rounded-pill badge-doc-optional" title="No es obligatorio para generar el CAE con IA">Opcional para generar</span>
                                                            <?php endif; ?>
                                                            <?php
                                                                if (is_array($cv) && isset($cv['label'])):
                                                                    $cvOk = !empty($cv['valid_for_cae']);
                                                                    if ($isPrlOptional) {
                                                                        $cvCls = $cvOk ? 'text-bg-success' : 'text-bg-light text-dark border';
                                                                    } else {
                                                                        $cvCls = $cvOk ? 'text-bg-success' : 'text-bg-danger';
                                                                    }
                                                            ?>
                                                                <span class="badge rounded-pill <?= $cvCls ?>" title="<?= htmlspecialchars((string) ($cv['summary'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $cv['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                                            <?php endif; ?>
                                                            <?php
                                                                if (is_array($cv) && empty($cv['valid_for_cae'])):
                                                                    $chipReason = \App\Services\DocumentIntakePresentationService::validityPrimaryReason($cv);
                                                            ?>
                                                                <span class="doc-chip-reason small text-muted w-100 mt-1"><?= htmlspecialchars($chipReason, ENT_QUOTES, 'UTF-8') ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <?php if ($isHaciendaDoc && $docId > 0): ?>
                                                        <form method="post"
                                                            action="<?= htmlspecialchars($ab . '/cae/documentos/' . $docId . '/verify-aeat', ENT_QUOTES, 'UTF-8') ?>"
                                                            class="flex-shrink-0">
                                                            <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnToForm, ENT_QUOTES, 'UTF-8') ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-primary" title="Comprobar certificado de Hacienda con el CSV">
                                                                <i class="bi bi-shield-check me-1"></i><?= $aeAt === '' ? 'Comprobar certificado' : 'Volver a comprobar' ?>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

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
                                        <span id="ai-form-error-msg" style="white-space: pre-line; word-break: break-word;"></span>
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
                <form id="form-cae-request-docs" method="post" action="<?= htmlspecialchars($ab) ?>/tecnicos/<?= $techId ?>/cae/request-docs">
                    <label class="form-label fw-semibold">Documentos solicitados <span class="text-danger">*</span></label>
                    <?php if (empty($requestableDocTypes)): ?>
                        <div class="alert alert-warning py-2 mb-3">No hay tipos de documento configurados para <code>technician_cae</code>.</div>
                    <?php else: ?>
                        <div id="cae-request-doc-types" class="cae-request-doc-grid mb-3">
                            <?php foreach ($requestableDocTypes as $dt): ?>
                                <?php
                                    $dtypeId = (int) ($dt['id'] ?? 0);
                                    $prevFilename = isset($activeSupportingFilenameByDocTypeId[$dtypeId])
                                        ? trim((string) $activeSupportingFilenameByDocTypeId[$dtypeId])
                                        : '';
                                    $reqDocInputId = 'cae-req-doc-' . $dtypeId;
                                ?>
                                <div class="cae-request-doc-item">
                                    <div class="form-check mb-0">
                                        <input type="checkbox"
                                               class="form-check-input"
                                               name="document_type_ids[]"
                                               value="<?= $dtypeId ?>"
                                               id="<?= htmlspecialchars($reqDocInputId, ENT_QUOTES, 'UTF-8') ?>"
                                               data-replaces-filename="<?= htmlspecialchars($prevFilename, ENT_QUOTES, 'UTF-8') ?>">
                                        <label class="form-check-label w-100" for="<?= htmlspecialchars($reqDocInputId, ENT_QUOTES, 'UTF-8') ?>">
                                            <span class="cae-request-doc-name"><?= htmlspecialchars((string) $dt['name']) ?></span>
                                            <?php if ($prevFilename !== ''): ?>
                                                <span class="cae-request-doc-hint">Ya hay archivo en CAE</span>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                </div>
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
    </div>
</div>

<!-- Modal: Ajustes manuales del CAE -->
<div class="modal fade" id="manualCaeModal" tabindex="-1" aria-labelledby="manualCaeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
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
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
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
    <div class="modal-dialog modal-dialog-centered">
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

<!-- Confirmación solicitud documentos -->
<div class="modal fade" id="modalCaeRequestDocsConfirm" tabindex="-1" aria-labelledby="modalCaeRequestDocsConfirmLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCaeRequestDocsConfirmLabel">
                    <i class="bi bi-exclamation-triangle text-warning me-2"></i>Confirmar solicitud
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Se enviará un email al técnico con los documentos que confirmes abajo.</p>
                <div id="cae-request-docs-confirm-conflicts"></div>
                <div id="cae-request-docs-confirm-plain" class="d-none"></div>
                <div id="cae-request-docs-confirm-error" class="alert alert-danger py-2 small d-none mb-0 mt-2" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="cae-request-docs-confirm-btn">
                    <i class="bi bi-send me-1"></i>Enviar solicitud
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
        approved:     { cls: 'text-bg-success',   label: 'Aprobado',         hint: 'Documentación conforme a las reglas del sistema (listo para generar según expediente actual).' },
        in_review:    { cls: 'text-bg-warning',   label: 'En revisión',      hint: 'La IA recomienda revisión manual antes de aprobar.' },
        pending_docs: { cls: 'text-bg-warning text-dark', label: 'Pendiente docs.',  hint: 'Faltan o no cumplen Hacienda, Seguridad Social o Póliza de Responsabilidad Civil (obligatorios para generar).' },
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

            const fd = new FormData();
            const aiFrom  = document.getElementById('ai-valid-from');
            const aiUntil = document.getElementById('ai-valid-until');
            if (aiFrom?.value)  fd.set('valid_from',  aiFrom.value);
            if (aiUntil?.value) fd.set('valid_until', aiUntil.value);
            const extras = aiForm.querySelector('[name="extra_notes"]');
            if (extras) fd.set('extra_notes', extras.value || '');

            try {
                const res = await fetch(`${baseUrl}/admin/tecnicos/${techId}/cae/ia/generate`, {
                    method: 'POST',
                    body: fd,
                });
                let data;
                try {
                    data = await res.json();
                } catch (parseErr) {
                    stopStepAnimation();
                    overlay.style.display = 'none';
                    showAiError('La respuesta del servidor no es válida (¿error 500 o sesión caducada?). Revisa la consola del navegador.');
                    console.error('[CAE AI] generate JSON:', parseErr);
                    return;
                }
                stopStepAnimation();
                overlay.style.display = 'none';

                if (!data.ok) {
                    let msg = data.error || 'Error desconocido al generar el CAE.';
                    if (Array.isArray(data.reasons) && data.reasons.length) {
                        msg = data.reasons.join('\n');
                    }
                    showAiError(msg);
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

    const requestDocsForm = document.getElementById('form-cae-request-docs');
    const requestDocsModalEl = document.getElementById('modalCaeRequestDocsConfirm');
    const requestDocsModal = requestDocsModalEl ? bootstrap.Modal.getOrCreateInstance(requestDocsModalEl) : null;
    const requestConflictsEl = document.getElementById('cae-request-docs-confirm-conflicts');
    const requestPlainEl = document.getElementById('cae-request-docs-confirm-plain');
    const requestErrorEl = document.getElementById('cae-request-docs-confirm-error');
    const requestDocsConfirmBtn = document.getElementById('cae-request-docs-confirm-btn');

    const escapeHtml = (s) => String(s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

    const chipLabel = (cb) => {
        const nameEl = cb.closest('.cae-request-doc-item')?.querySelector('.cae-request-doc-name');
        if (nameEl) {
            return (nameEl.textContent || '').replace(/\s+/g, ' ').trim();
        }
        const titleEl = cb.closest('.doc-chip')?.querySelector('.doc-chip__title');
        if (titleEl) {
            return (titleEl.textContent || '').replace(/\s+/g, ' ').trim();
        }
        const span = cb.closest('.doc-chip')?.querySelector('span');
        if (!span) return 'Tipo #' + cb.value;
        const clone = span.cloneNode(true);
        clone.querySelectorAll('.doc-chip__hint, .text-warning').forEach((n) => n.remove());
        return (clone.textContent || '').replace(/\s+/g, ' ').trim() || ('Tipo #' + cb.value);
    };

    requestDocsForm?.addEventListener('submit', function (e) {
        if (this.dataset.confirmed === '1') {
            delete this.dataset.confirmed;
            return;
        }

        const boxes = [...this.querySelectorAll('input[name="document_type_ids[]"]:checked')];
        if (boxes.length === 0) return;
        e.preventDefault();

        requestErrorEl?.classList.add('d-none');
        if (requestErrorEl) requestErrorEl.textContent = '';

        const conflicts = [];
        const plain = [];

        boxes.forEach((cb) => {
            const prev = (cb.getAttribute('data-replaces-filename') || '').trim();
            const label = chipLabel(cb);
            if (prev !== '') {
                conflicts.push({ cb, label, prev });
            } else {
                plain.push(label);
            }
        });

        if (requestConflictsEl) {
            requestConflictsEl.innerHTML = '';
            if (conflicts.length > 0) {
                const intro = document.createElement('p');
                intro.className = 'alert alert-warning py-2 small mb-3';
                intro.innerHTML = '<strong>Ya hay archivo vigente</strong> en el CAE actual. Si el técnico sube otro desde el portal, se sustituirá. Indica si cada tipo entra en <em>esta</em> solicitud:';
                requestConflictsEl.appendChild(intro);

                conflicts.forEach((c, idx) => {
                    const block = document.createElement('div');
                    block.className = 'cae-request-conflict-block';
                    block.dataset.reqIdx = String(idx);
                    block.innerHTML =
                        '<div class="fw-semibold mb-1">' + escapeHtml(c.label) + '</div>' +
                        '<div class="small text-muted mb-2">Archivo actual: «' + escapeHtml(c.prev) + '»</div>' +
                        '<div class="form-check">' +
                        '<input class="form-check-input" type="radio" name="req_conflict_' + idx + '" id="req_inc_' + idx + '" value="include" checked>' +
                        '<label class="form-check-label" for="req_inc_' + idx + '">Incluir en esta solicitud</label>' +
                        '</div>' +
                        '<div class="form-check mb-0">' +
                        '<input class="form-check-input" type="radio" name="req_conflict_' + idx + '" id="req_exc_' + idx + '" value="exclude">' +
                        '<label class="form-check-label" for="req_exc_' + idx + '">No incluir en esta solicitud</label>' +
                        '</div>';
                    requestConflictsEl.appendChild(block);
                });
            }
        }

        if (requestPlainEl) {
            if (plain.length > 0) {
                requestPlainEl.classList.remove('d-none');
                requestPlainEl.innerHTML =
                    '<p class="small text-muted mb-0"><strong>Sin archivo previo:</strong> ' +
                    plain.map((l) => escapeHtml(l)).join(' · ') + '</p>';
            } else {
                requestPlainEl.classList.add('d-none');
                requestPlainEl.innerHTML = '';
            }
        }

        const onConfirm = () => {
            requestDocsConfirmBtn?.removeEventListener('click', onConfirm);

            conflicts.forEach((c, idx) => {
                if (document.getElementById('req_exc_' + idx)?.checked) {
                    c.cb.checked = false;
                }
            });

            const stillChecked = requestDocsForm.querySelectorAll('input[name="document_type_ids[]"]:checked');
            if (stillChecked.length === 0) {
                if (requestErrorEl) {
                    requestErrorEl.textContent = 'Debes incluir al menos un documento en la solicitud (o cancela y cambia la selección).';
                    requestErrorEl.classList.remove('d-none');
                }
                return;
            }

            requestDocsModal?.hide();
            requestDocsForm.dataset.confirmed = '1';
            requestDocsForm.requestSubmit();
        };

        requestDocsConfirmBtn?.addEventListener('click', onConfirm, { once: true });
        requestDocsModal?.show();
    });
});
</script>