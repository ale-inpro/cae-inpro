<?php declare(strict_types=1);

use App\Services\DocumentIntakePresentationService;

$ab = htmlspecialchars($areaBaseUrl ?? '/cae-inpro/public/gestor');
$t = $tech ?? [];
$name = trim((string) ($t['display_name'] ?? ''));
$entityType = (string) ($t['entity_type'] ?? 'individual');
$entityLabel = $entityType === 'company' ? 'Empresa' : 'Persona física / autónomo';
$taxLabel = $entityType === 'company' ? 'CIF' : 'NIF / DNI / NIE';
$current = $currentCae ?? null;
$history = $caeHistory ?? [];
$docs = $caeDocuments ?? [];
$pendingIntake = $pendingIntakeDocs ?? [];
$activeSupportingFilenameByDocTypeId = $activeSupportingFilenameByDocTypeId ?? [];
$isAdmin = (($area ?? 'gestor') === 'admin');
$currentUrl = $ab . '/tecnicos/' . (int) ($t['id'] ?? 0) . '#pane-info';
$editUrl = $ab . '/tecnicos/' . (int) ($t['id'] ?? 0) . '/edit?return_to=' . urlencode($currentUrl);

$statusLabel = static function (string $status): string {
    return match ($status) {
        'approved'     => 'Aprobado',
        'in_review'    => 'En revisión',
        'pending'      => 'Pendiente',
        'pending_docs' => 'Pendiente docs.',
        'rejected'     => 'Rechazado',
        'expired'      => 'Caducado',
        default        => ucfirst($status),
    };
};
$statusBadge = static function (string $status): string {
    return match ($status) {
        'approved'     => 'text-bg-success',
        'in_review'    => 'text-bg-warning',
        'pending'      => 'text-bg-secondary',
        'pending_docs' => 'text-bg-warning text-dark',
        'rejected'     => 'text-bg-danger',
        'expired'      => 'text-bg-dark',
        default        => 'text-bg-light text-dark',
    };
};
?>
<div class="panel-identity mb-3">
    <div class="panel-identity-icon"><i class="bi bi-person-badge-fill"></i></div>
    <div>
        <p class="panel-identity-kicker mb-1">Panel operativo</p>
        <h2 class="panel-identity-title mb-0">Panel de Técnico</h2>
    </div>
</div>

<div class="page-header page-header--balanced page-header--premium">
    <div class="page-header-left">
        <h1 class="h3 page-title mb-1"><?= htmlspecialchars($name !== '' ? $name : 'Técnico') ?></h1>
        <p class="page-meta mb-0">
            <span class="badge text-bg-light text-dark border me-1"><?= htmlspecialchars($entityLabel) ?></span>
            <?= htmlspecialchars((string) ($t['professions'] ?? '-')) ?> ·
            <?= htmlspecialchars((string) ($t['city'] ?? '-')) ?> ·
            <?php if ($current): ?>
                <span class="badge <?= $statusBadge((string) $current['status']) ?>"><?= htmlspecialchars($statusLabel((string) $current['status'])) ?></span>
            <?php else: ?>
                <span class="badge text-bg-secondary">Sin CAE actual</span>
            <?php endif; ?>
        </p>
    </div>

    <div class="page-header-center">
        <?php if ($isAdmin): ?>
            <a class="btn btn-success btn-sm px-3" href="<?= $ab ?>/tecnicos/<?= (int) ($t['id'] ?? 0) ?>/cae">Gestionar CAE vigente</a>
        <?php endif; ?>
    </div>

    <div class="page-header-right d-flex align-items-center gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="<?= $ab ?>/tecnicos" title="Volver"><i class="bi bi-arrow-left"></i></a>
        <?php if ($isAdmin): ?>
            <a class="btn btn-success btn-sm" href="<?= htmlspecialchars($editUrl) ?>" title="Editar"><i class="bi bi-pencil-square"></i></a>
            <form method="post" action="<?= $ab ?>/tecnicos/<?= (int) ($t['id'] ?? 0) ?>" data-confirm="¿Desactivar este técnico?" class="m-0">
                <input type="hidden" name="_method" value="DELETE">
                <button class="btn btn-outline-danger btn-sm" type="submit" title="Desactivar"><i class="bi bi-person-x"></i></button>
            </form>
        <?php endif; ?>
    </div>
</div>

<ul class="nav nav-tabs mb-3" id="techTabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#pane-info" type="button">Información</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-hist" type="button">Histórico CAE</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-docs" type="button">Documentos</button></li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="pane-info">
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="subpanel">
                    <div class="subpanel-h">Datos de contacto</div>
                    <div class="subpanel-b">
                        <dl class="row mb-0 small">
                            <dt class="col-sm-4 text-muted">Email</dt><dd class="col-sm-8"><?= htmlspecialchars((string) ($t['email'] ?? '-')) ?></dd>
                            <dt class="col-sm-4 text-muted">Teléfono</dt><dd class="col-sm-8"><?= htmlspecialchars((string) ($t['phone'] ?? '-')) ?></dd>
                            <dt class="col-sm-4 text-muted"><?= htmlspecialchars($taxLabel) ?></dt><dd class="col-sm-8"><code><?= htmlspecialchars((string) ($t['tax_id'] ?? '-')) ?></code></dd>
                            <dt class="col-sm-4 text-muted">Tipo</dt><dd class="col-sm-8"><?= htmlspecialchars($entityLabel) ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="subpanel">
                    <div class="subpanel-h">Ubicación</div>
                    <div class="subpanel-b">
                        <dl class="row mb-0 small">
                            <dt class="col-sm-4 text-muted">Provincia</dt><dd class="col-sm-8"><?= htmlspecialchars((string) ($t['province'] ?? '-')) ?></dd>
                            <dt class="col-sm-4 text-muted">Ciudad</dt><dd class="col-sm-8"><?= htmlspecialchars((string) ($t['city'] ?? '-')) ?></dd>
                            <dt class="col-sm-4 text-muted">Profesión</dt><dd class="col-sm-8"><?= htmlspecialchars((string) ($t['professions'] ?? '-')) ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="pane-hist">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 table-mobile-cards">
                <thead><tr><th>Periodo</th><th>Estado</th><th>Notas</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($history as $row): ?>
                    <tr class="<?= !empty($row['is_current']) ? 'cae-row-current' : '' ?>">
                        <td data-label="Periodo"><?= app_date_range_html($row['valid_from'] ?? null, $row['valid_until'] ?? null) ?></td>
                        <td data-label="Estado">
                            <?php $st = (string) ($row['status'] ?? ''); ?>
                            <span class="badge <?= $statusBadge($st) ?>"><?= htmlspecialchars($statusLabel($st)) ?></span>
                            <?php if (!empty($row['is_current'])): ?>
                                <span class="badge text-bg-success ms-1">Actual</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Notas" class="text-muted"><?= htmlspecialchars((string) ($row['notes'] ?? '—')) ?></td>
                        <td data-label="Acciones" class="text-end">
                            <?php if (!empty($row['latest_doc_id'])): ?>
                                <div class="table-actions">
                                    <?php if (!empty($row['latest_doc_path'])): ?>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-secondary"
                                            data-file-preview
                                            data-file-preview-url="<?= htmlspecialchars((string) (($baseUrl ?? '') . ($row['latest_doc_path'] ?? ''))) ?>"
                                            data-file-preview-name="<?= htmlspecialchars((string) ($row['latest_doc_name'] ?? 'Documento CAE')) ?>"
                                            title="Ver"
                                        ><i class="bi bi-eye"></i></button>
                                    <?php endif; ?>
                                    <a
                                        class="btn btn-sm btn-outline-primary"
                                        href="<?= $ab ?>/cae/documentos/<?= (int) ($row['latest_doc_id'] ?? 0) ?>/download"
                                        title="Descargar revisión"
                                    ><i class="bi bi-download"></i></a>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="tab-pane fade" id="pane-docs">
        <?php $types = $caeDocTypes ?? []; ?>
        <?php if ($isAdmin && !empty($current) && !empty($types)): ?>
            <div class="subpanel mb-3">
                <div class="subpanel-h">Subir documento complementario</div>
                <div class="subpanel-b">
                    <form id="form-tech-upload-supporting"
                        method="post"
                        enctype="multipart/form-data"
                        action="<?= $ab ?>/cae/<?= (int) ($current['id'] ?? 0) ?>/documentos"
                        data-upload-action="<?= $ab ?>/cae/<?= (int) ($current['id'] ?? 0) ?>/documentos"
                        data-csv-action="<?= $ab ?>/cae/<?= (int) ($current['id'] ?? 0) ?>/documentos/hacienda-csv"
                        class="row g-2">
                        <input type="hidden" name="return_to" value="<?= $ab ?>/tecnicos/<?= (int) ($t['id'] ?? 0) ?>#pane-docs">
                        <input type="hidden" name="upload_mode" value="supporting">
                        <input type="hidden" name="submit_mode" id="tech-upload-submit-mode" value="file">

                        <div class="col-md-4">
                            <select name="document_type_id" id="tech-upload-doc-type" class="form-select" required>
                                <option value="" data-active-filename="" data-is-hacienda="0">Tipo de documento</option>
                                <?php foreach ($types as $dt): ?>
                                    <?php
                                        $dtypeId = (int) ($dt['id'] ?? 0);
                                        $prevFn = isset($activeSupportingFilenameByDocTypeId[$dtypeId])
                                            ? trim((string) $activeSupportingFilenameByDocTypeId[$dtypeId])
                                            : '';
                                        $isHaciendaOpt = trim((string) ($dt['name'] ?? '')) === \App\Services\CaeReadinessService::DOCUMENT_TYPE_NAME_HACIENDA;
                                    ?>
                                    <option value="<?= $dtypeId ?>"
                                            data-active-filename="<?= htmlspecialchars($prevFn, ENT_QUOTES, 'UTF-8') ?>"
                                            data-is-hacienda="<?= $isHaciendaOpt ? '1' : '0' ?>">
                                        <?= htmlspecialchars((string) ($dt['name'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <input type="file" name="document_file" id="tech-upload-doc-file" class="form-control" accept=".pdf,application/pdf">
                        </div>

                        <div class="col-md-2">
                            <button class="btn btn-success w-100" type="submit" id="tech-upload-btn-file">
                                <i class="bi bi-upload"></i> Subir
                            </button>
                        </div>

                        <div class="col-12 d-none" id="tech-hacienda-csv-row">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-8">
                                    <label for="tech-hacienda-csv-input" class="form-label small mb-1">CSV del certificado (16 caracteres)</label>
                                    <input type="text"
                                        name="manual_aeat_csv"
                                        id="tech-hacienda-csv-input"
                                        class="form-control text-uppercase"
                                        maxlength="16"
                                        pattern="[A-Za-z0-9]{16}"
                                        placeholder="8KFA439XY6N4SP24"
                                        autocomplete="off">
                                </div>
                                <div class="col-md-4">
                                    <button class="btn btn-primary w-100" type="submit" id="tech-upload-btn-csv">
                                        <i class="bi bi-search"></i> Obtener certificado
                                    </button>
                                </div>
                            </div>
                            <p class="small text-muted mb-0 mt-1">
                                Consulta Hacienda con el CSV sin subir escaneo. Si subes PDF, se usará el flujo habitual (extracción de CSV del archivo).
                            </p>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($isAdmin && !empty($pendingIntake)): ?>
            <div class="subpanel mb-3 border-warning doc-panel doc-panel--pending">
                <div class="subpanel-h d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-hourglass-split me-2"></i>Documentos pendientes de revisión</span>
                    <span class="badge text-bg-warning text-dark"><?= (int) count($pendingIntake) ?></span>
                </div>
                <div class="subpanel-b">
                    <p class="doc-panel-intro mb-3">
                        Estos archivos no se han validado automáticamente.
                        Para <strong>Hacienda</strong>, indica el <strong>CSV</strong> (16 caracteres).
                        Para el resto, indica la <strong>fecha de caducidad</strong> y aprueba para publicarlos.
                        El estado <strong>Válido</strong> / <strong>No válido</strong> aparecerá en la tabla inferior.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 table-mobile-cards doc-table">
                            <thead>
                                <tr>
                                    <th>Documento</th>
                                    <th>Archivo</th>
                                    <th>Motivo</th>
                                    <th>Análisis</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($pendingIntake as $p): ?>
                                <?php
                                    $pres = $p['_present'] ?? DocumentIntakePresentationService::presentPendingIntake($p);
                                    $hasAiExpiry = !empty(trim((string) ($p['ai_expires_at'] ?? '')));
                                    $aiExpiryVal = $hasAiExpiry ? htmlspecialchars((string) $p['ai_expires_at']) : '';
                                    $docNameForExpiry = (string) ($p['document_name'] ?? '');
                                    $needsForcedDate = (!$hasAiExpiry || str_contains($docNameForExpiry, 'Prevención'));
                                ?>
                                <tr>
                                    <td data-label="Documento" class="doc-table-type"><?= htmlspecialchars((string) ($p['document_name'] ?? '-')) ?></td>
                                    <td data-label="Archivo">
                                        <span class="doc-table-filename"><?= htmlspecialchars((string) ($p['original_filename'] ?? '-')) ?></span>
                                    </td>
                                    <td data-label="Motivo" class="doc-table-reason">
                                        <?= htmlspecialchars($pres['reason'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td data-label="Análisis">
                                        <?php
                                            $isHaciendaIntakeRow = trim((string) ($p['document_name'] ?? '')) === \App\Services\CaeReadinessService::DOCUMENT_TYPE_NAME_HACIENDA;
                                        ?>
                                        <?php if ($isHaciendaIntakeRow): ?>
                                            <span class="badge rounded-pill text-bg-light text-dark border">CSV</span>
                                            <div class="doc-table-meta small text-muted mt-1">
                                                <?= htmlspecialchars(trim((string) ($p['ai_notes'] ?? 'Pendiente de CSV manual.')), ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge rounded-pill <?= htmlspecialchars($pres['status_badge'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($pres['status_label'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <div class="doc-table-meta small text-muted mt-1">
                                                Caducidad detectada: <?= htmlspecialchars($pres['expiry_label'], ENT_QUOTES, 'UTF-8') ?>
                                                · Confianza: <?= htmlspecialchars($pres['confidence_label'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Acciones" class="text-end">
                                        <?php $intakeApproveFormId = 'intake-approve-' . (int) ($p['id'] ?? 0); ?>
                                        <div class="table-actions intake-review-actions">
                                            <?php if (!empty($p['storage_path'])): ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary intake-review-actions__btn" data-file-preview
                                                    data-file-preview-url="<?= htmlspecialchars((string) (($baseUrl ?? '') . ($p['storage_path'] ?? ''))) ?>"
                                                    data-file-preview-name="<?= htmlspecialchars((string) ($p['original_filename'] ?? 'Documento')) ?>"
                                                    title="Ver"><i class="bi bi-eye"></i></button>
                                            <?php endif; ?>
                                            <div class="intake-review-actions__controls">
                                                <?php
                                                    $isHaciendaIntake = trim((string) ($p['document_name'] ?? '')) === \App\Services\CaeReadinessService::DOCUMENT_TYPE_NAME_HACIENDA;
                                                    $detectedCsv = isset($p['extracted_aeat_csv']) ? trim((string) $p['extracted_aeat_csv']) : '';
                                                ?>
                                                <form method="post" id="<?= htmlspecialchars($intakeApproveFormId, ENT_QUOTES, 'UTF-8') ?>"
                                                    action="<?= $ab ?>/cae/intake/<?= (int) ($p['id'] ?? 0) ?>/approve"
                                                    class="intake-review-actions__group intake-review-actions__approve mb-0">
                                                    <input type="hidden" name="return_to" value="<?= $ab ?>/tecnicos/<?= (int) ($t['id'] ?? 0) ?>#pane-docs">
                                                    <?php if ($isHaciendaIntake): ?>
                                                        <div class="doc-approve-date">
                                                            <label class="form-label small mb-0">CSV *</label>
                                                            <input type="text" name="manual_aeat_csv" class="form-control form-control-sm text-uppercase"
                                                                maxlength="16" pattern="[A-Za-z0-9]{16}" required autocomplete="off"
                                                                placeholder="16 caracteres"
                                                                value="<?= htmlspecialchars($detectedCsv, ENT_QUOTES, 'UTF-8') ?>">
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="doc-approve-date">
                                                            <label class="form-label small mb-0">Caducidad<?= $needsForcedDate ? ' *' : '' ?></label>
                                                            <input type="date" name="manual_expires_at" class="form-control form-control-sm"
                                                                value="<?= $aiExpiryVal ?>" <?= $needsForcedDate ? 'required' : '' ?>>
                                                        </div>
                                                    <?php endif; ?>
                                                </form>
                                                <div class="intake-review-actions__buttons">
                                                    <button class="btn btn-sm btn-success flex-shrink-0" type="submit"
                                                        form="<?= htmlspecialchars($intakeApproveFormId, ENT_QUOTES, 'UTF-8') ?>"
                                                        title="Aprobar y publicar">
                                                        <i class="bi bi-check2 d-md-none"></i><span class="d-none d-md-inline">Aprobar</span>
                                                    </button>
                                                    <form method="post" action="<?= $ab ?>/cae/intake/<?= (int) ($p['id'] ?? 0) ?>/reject"
                                                        class="intake-review-actions__group intake-review-actions__reject mb-0"
                                                        data-confirm="¿Rechazar este documento?">
                                                        <input type="hidden" name="return_to" value="<?= $ab ?>/tecnicos/<?= (int) ($t['id'] ?? 0) ?>#pane-docs">
                                                        <button class="btn btn-sm btn-outline-danger flex-shrink-0" type="submit" title="Rechazar">
                                                            <i class="bi bi-x-lg d-md-none"></i><span class="d-none d-md-inline">Rechazar</span>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="subpanel">
            <div class="subpanel-h">Documentos complementarios publicados</div>
            <div class="subpanel-b">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 table-mobile-cards doc-table">
                    <thead><tr><th>Documento</th><th>Archivo</th><th>Validez</th><th>Detalles</th><th></th></tr></thead>
                        <tbody>
                <?php foreach ($docs as $d): ?>
                    <?php
                        $v = $d['cae_validity'] ?? null;
                        $ok = is_array($v) && !empty($v['valid_for_cae']);
                        $docTypeName = trim((string) ($d['document_name'] ?? ''));
                        $isPrlOptional = $docTypeName === \App\Services\CaeReadinessService::DOCUMENT_TYPE_NAME_PRL;
                        $label = is_array($v) ? (string) ($v['label'] ?? ($ok ? 'Válido' : 'No válido')) : '—';
                        if ($isPrlOptional) {
                            $badgeClass = $ok ? 'text-bg-success' : 'text-bg-light text-dark border';
                        } else {
                            $badgeClass = $ok ? 'text-bg-success' : 'text-bg-danger';
                        }
                        $expRaw = trim((string) ($d['expires_at'] ?? ''));
                        $reasonPrimary = \App\Services\DocumentIntakePresentationService::validityPrimaryReason(is_array($v) ? $v : null);
                        $reasonSecondary = \App\Services\DocumentIntakePresentationService::validitySecondaryLine(is_array($v) ? $v : null, $expRaw !== '' ? $expRaw : null);
                    ?>
                    <tr>
                        <td data-label="Documento" class="doc-table-type">
                            <?= htmlspecialchars((string) ($d['document_name'] ?? '-')) ?>
                            <?php if ($isPrlOptional): ?>
                                <span class="badge rounded-pill badge-doc-optional ms-1" title="No es obligatorio para generar el CAE con IA">Opcional</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Archivo"><span class="doc-table-filename"><?= htmlspecialchars((string) ($d['original_filename'] ?? '-')) ?></span></td>
                        <td data-label="Validez">
                            <span class="badge <?= htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                        </td>
                        <td data-label="Motivo" class="doc-table-reason">
                            <span class="d-block"><?= htmlspecialchars($reasonPrimary, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if ($reasonSecondary !== ''): ?>
                                <span class="small text-muted d-block mt-1"><?= htmlspecialchars($reasonSecondary, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Acciones" class="text-end">
                            <div class="table-actions">
                                <?php if (!empty($d['storage_path'])): ?>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-secondary"
                                        data-file-preview
                                        data-file-preview-url="<?= htmlspecialchars((string) (($baseUrl ?? '') . ($d['storage_path'] ?? ''))) ?>"
                                        data-file-preview-name="<?= htmlspecialchars((string) ($d['original_filename'] ?? 'Documento')) ?>"
                                        title="Ver"
                                    ><i class="bi bi-eye"></i></button>
                                <?php endif; ?>
                                <a
                                    class="btn btn-sm btn-outline-primary"
                                    href="<?= $ab ?>/cae/documentos/<?= (int) ($d['id'] ?? 0) ?>/download"
                                    title="Descargar"
                                ><i class="bi bi-download"></i></a>
                                <?php if ($isAdmin): ?>
                                    <form method="post" action="<?= $ab ?>/cae/documentos/<?= (int) ($d['id'] ?? 0) ?>" data-confirm="¿Eliminar este documento CAE?">
                                        <input type="hidden" name="_method" value="DELETE">
                                        <input type="hidden" name="return_to" value="<?= $ab ?>/tecnicos/<?= (int) ($t['id'] ?? 0) ?>#pane-docs">
                                        <button class="btn btn-sm btn-outline-danger" type="submit" title="Quitar"><i class="bi bi-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Confirmación: subir y sustituir documento complementario -->
<div class="modal fade" id="modalTechUploadReplace" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-triangle text-warning me-2"></i>Sustituir documento
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2" id="tech-upload-replace-intro"></p>
                <p class="small text-muted mb-0">El archivo anterior dejará de ser el vigente en el CAE actual.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" id="tech-upload-replace-confirm">
                    <i class="bi bi-upload me-1" id="tech-upload-replace-confirm-icon"></i><span id="tech-upload-replace-confirm-label">Subir y sustituir</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('form-tech-upload-supporting');
    const typeSelect = document.getElementById('tech-upload-doc-type');
    const fileInput = document.getElementById('tech-upload-doc-file');
    const csvRow = document.getElementById('tech-hacienda-csv-row');
    const csvInput = document.getElementById('tech-hacienda-csv-input');
    const submitMode = document.getElementById('tech-upload-submit-mode');
    const btnFile = document.getElementById('tech-upload-btn-file');
    const btnCsv = document.getElementById('tech-upload-btn-csv');
    const modalEl = document.getElementById('modalTechUploadReplace');
    if (!form) return;

    const uploadAction = form.dataset.uploadAction || form.action;
    const csvAction = form.dataset.csvAction || '';
    const modal = modalEl ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
    const introEl = document.getElementById('tech-upload-replace-intro');
    const confirmBtn = document.getElementById('tech-upload-replace-confirm');
    const confirmLabel = document.getElementById('tech-upload-replace-confirm-label');
    const confirmIcon = document.getElementById('tech-upload-replace-confirm-icon');

    const setReplaceModalMode = (mode) => {
        if (mode === 'csv') {
            if (confirmLabel) confirmLabel.textContent = 'Obtener y sustituir';
            if (confirmIcon) confirmIcon.className = 'bi bi-search me-1';
        } else {
            if (confirmLabel) confirmLabel.textContent = 'Subir y sustituir';
            if (confirmIcon) confirmIcon.className = 'bi bi-upload me-1';
        }
    };

    csvInput?.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            if (submitMode) submitMode.value = 'csv';
            form.action = csvAction;
        }
    });

    const clearModalBackdrop = () => {
        document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
    };

    const lockAdminUploadForm = () => {
        form.dataset.submitting = '1';
        setTimeout(function () {
            form.querySelectorAll('button[type="submit"], select, input[type="file"], input[type="text"]').forEach(function (el) {
                el.disabled = true;
            });
        }, 0);
    };

    const showAnalyzeOverlay = (title) => {
        requestAnimationFrame(() => {
            clearModalBackdrop();
            window.AppDocAnalyzeOverlay?.show({
                title: title || 'Analizando documento complementario'
            });
            lockAdminUploadForm();
        });
    };

    const syncHaciendaUi = () => {
        if (!typeSelect || !csvRow || !fileInput) return;
        const opt = typeSelect.options[typeSelect.selectedIndex];
        const isHacienda = (opt?.getAttribute('data-is-hacienda') || '0') === '1';
        csvRow.classList.toggle('d-none', !isHacienda);
        if (submitMode?.value !== 'csv') {
            fileInput.required = !isHacienda ? true : false;
        }
    };

    typeSelect?.addEventListener('change', function () {
        if (submitMode) submitMode.value = 'file';
        form.action = uploadAction;
        syncHaciendaUi();
    });

    btnFile?.addEventListener('click', function () {
        if (submitMode) submitMode.value = 'file';
        form.action = uploadAction;
        if (fileInput) fileInput.required = true;
    });

    btnCsv?.addEventListener('click', function () {
        if (submitMode) submitMode.value = 'csv';
        form.action = csvAction;
        if (fileInput) fileInput.required = false;
    });

    syncHaciendaUi();

    form.addEventListener('submit', function (e) {
        if (form.dataset.submitting === '1') {
            e.preventDefault();
            return;
        }

        if (document.activeElement === csvInput && csvRow && !csvRow.classList.contains('d-none')) {
            if (submitMode) submitMode.value = 'csv';
            form.action = csvAction;
        }

        const isCsvMode = submitMode?.value === 'csv';

        if (isCsvMode) {
            const csv = (csvInput?.value || '').trim();
            if (!/^[A-Za-z0-9]{16}$/.test(csv)) {
                e.preventDefault();
                alert('Indica un CSV válido de 16 caracteres alfanuméricos.');
                return;
            }
            if (form.dataset.confirmed === '1') {
                delete form.dataset.confirmed;
                showAnalyzeOverlay('Obteniendo certificado de Hacienda');
                return;
            }
            if (!typeSelect) {
                showAnalyzeOverlay('Obteniendo certificado de Hacienda');
                return;
            }
            const opt = typeSelect.options[typeSelect.selectedIndex];
            const prev = (opt?.getAttribute('data-active-filename') || '').trim();
            if (prev === '') {
                showAnalyzeOverlay('Obteniendo certificado de Hacienda');
                return;
            }
            e.preventDefault();
            const typeName = (opt.textContent || '').trim();
            if (introEl) {
                introEl.textContent =
                    'Vas a obtener un nuevo certificado de «' + typeName +
                    '» por CSV. Sustituirá el vigente «' + prev + '».';
            }
            setReplaceModalMode('csv');
            const onConfirm = () => {
                confirmBtn?.removeEventListener('click', onConfirm);
                const submitAfterHide = () => {
                    modalEl?.removeEventListener('hidden.bs.modal', submitAfterHide);
                    form.dataset.confirmed = '1';
                    form.requestSubmit();
                };
                modalEl?.addEventListener('hidden.bs.modal', submitAfterHide, { once: true });
                modal?.hide();
            };
            confirmBtn?.addEventListener('click', onConfirm, { once: true });
            modal?.show();
            return;
        }

        // --- Flujo subir PDF (existente) ---
        if (form.dataset.confirmed === '1') {
            delete form.dataset.confirmed;
            showAnalyzeOverlay();
            return;
        }

        if (!typeSelect) {
            if (!fileInput?.files?.length) {
                e.preventDefault();
                alert('Selecciona un archivo PDF.');
                return;
            }
            showAnalyzeOverlay();
            return;
        }

        if (!fileInput?.files?.length) {
            e.preventDefault();
            alert('Selecciona un archivo PDF.');
            return;
        }

        const opt = typeSelect.options[typeSelect.selectedIndex];
        const prev = (opt?.getAttribute('data-active-filename') || '').trim();
        if (prev === '') {
            showAnalyzeOverlay();
            return;
        }

        e.preventDefault();
        const typeName = (opt.textContent || '').trim();
        if (introEl) {
            introEl.textContent =
                'Vas a subir un nuevo «' + typeName + '». Sustituirá el archivo vigente «' + prev + '».';
        }
        setReplaceModalMode('file');

        const onConfirm = () => {
            confirmBtn?.removeEventListener('click', onConfirm);
            const submitAfterHide = () => {
                modalEl?.removeEventListener('hidden.bs.modal', submitAfterHide);
                form.dataset.confirmed = '1';
                form.requestSubmit();
            };
            modalEl?.addEventListener('hidden.bs.modal', submitAfterHide, { once: true });
            modal?.hide();
        };

        confirmBtn?.addEventListener('click', onConfirm, { once: true });
        modal?.show();
    });
});
</script>