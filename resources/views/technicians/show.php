<?php declare(strict_types=1);

use App\Services\DocumentIntakePresentationService;

$ab = htmlspecialchars($areaBaseUrl ?? '/cae-inpro/public/gestor');
$t = $tech ?? [];
$name = trim(((string) ($t['first_name'] ?? '')) . ' ' . ((string) ($t['last_name'] ?? '')));
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
                            <dt class="col-sm-4 text-muted">DNI/NIE</dt><dd class="col-sm-8"><?= htmlspecialchars((string) ($t['dni_nie'] ?? '-')) ?></dd>
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
                        <td data-label="Periodo"><?= htmlspecialchars((string) ($row['valid_from'] ?? '-')) ?> — <?= htmlspecialchars((string) ($row['valid_until'] ?? '-')) ?></td>
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
                    <form id="form-tech-upload-supporting" method="post" enctype="multipart/form-data" action="<?= $ab ?>/cae/<?= (int) ($current['id'] ?? 0) ?>/documentos" class="row g-2">
                        <input type="hidden" name="return_to" value="<?= $ab ?>/tecnicos/<?= (int) ($t['id'] ?? 0) ?>#pane-docs">
                        <input type="hidden" name="upload_mode" value="supporting">
                        <div class="col-md-4">
                            <select name="document_type_id" id="tech-upload-doc-type" class="form-select" required>
                                <option value="" data-active-filename="">Tipo de documento</option>
                                <?php foreach ($types as $dt): ?>
                                    <?php
                                        $dtypeId = (int) ($dt['id'] ?? 0);
                                        $prevFn = isset($activeSupportingFilenameByDocTypeId[$dtypeId])
                                            ? trim((string) $activeSupportingFilenameByDocTypeId[$dtypeId])
                                            : '';
                                    ?>
                                    <option value="<?= $dtypeId ?>"
                                            data-active-filename="<?= htmlspecialchars($prevFn, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string) ($dt['name'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <input type="file" name="document_file" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-success w-100" type="submit"><i class="bi bi-upload"></i></button>
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
                        Estos archivos no se han validado automáticamente. Indica la <strong>fecha de caducidad</strong> y aprueba para publicarlos.
                        El estado <strong>Válido para CAE</strong> aparecerá en la tabla inferior.
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
                                        <span class="badge rounded-pill <?= htmlspecialchars($pres['status_badge'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($pres['status_label'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <div class="doc-table-meta small text-muted mt-1">
                                            Caducidad detectada: <?= htmlspecialchars($pres['expiry_label'], ENT_QUOTES, 'UTF-8') ?>
                                            · Confianza: <?= htmlspecialchars($pres['confidence_label'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </td>
                                    <td data-label="Acciones" class="text-end">
                                        <div class="table-actions intake-review-actions flex-wrap justify-content-end">
                                            <?php if (!empty($p['storage_path'])): ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary intake-review-actions__btn" data-file-preview
                                                    data-file-preview-url="<?= htmlspecialchars((string) (($baseUrl ?? '') . ($p['storage_path'] ?? ''))) ?>"
                                                    data-file-preview-name="<?= htmlspecialchars((string) ($p['original_filename'] ?? 'Documento')) ?>"
                                                    title="Ver"><i class="bi bi-eye"></i></button>
                                            <?php endif; ?>
                                            <form method="post" action="<?= $ab ?>/cae/intake/<?= (int) ($p['id'] ?? 0) ?>/approve" class="intake-review-actions__group d-flex align-items-end gap-2 flex-wrap">
                                                <input type="hidden" name="return_to" value="<?= $ab ?>/tecnicos/<?= (int) ($t['id'] ?? 0) ?>#pane-docs">
                                                <div class="doc-approve-date">
                                                    <label class="form-label small mb-0">Caducidad<?= $needsForcedDate ? ' *' : '' ?></label>
                                                    <input type="date" name="manual_expires_at" class="form-control form-control-sm"
                                                        value="<?= $aiExpiryVal ?>" <?= $needsForcedDate ? 'required' : '' ?>>
                                                </div>
                                                <button class="btn btn-sm btn-success flex-shrink-0" type="submit" title="Aprobar y publicar">
                                                    <i class="bi bi-check2 d-md-none"></i><span class="d-none d-md-inline">Aprobar</span>
                                                </button>
                                            </form>
                                            <form method="post" action="<?= $ab ?>/cae/intake/<?= (int) ($p['id'] ?? 0) ?>/reject" class="intake-review-actions__group d-flex align-items-end" data-confirm="¿Rechazar este documento?">
                                                <input type="hidden" name="return_to" value="<?= $ab ?>/tecnicos/<?= (int) ($t['id'] ?? 0) ?>#pane-docs">
                                                <button class="btn btn-sm btn-outline-danger flex-shrink-0" type="submit" title="Rechazar">
                                                    <i class="bi bi-x-lg d-md-none"></i><span class="d-none d-md-inline">Rechazar</span>
                                                </button>
                                            </form>
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
                    <thead><tr><th>Documento</th><th>Archivo</th><th>Validez CAE</th><th>Detalles</th><th></th></tr></thead>
                        <tbody>
                <?php foreach ($docs as $d): ?>
                    <?php
                        $v = $d['cae_validity'] ?? null;
                        $ok = is_array($v) && !empty($v['valid_for_cae']);
                        $label = is_array($v) ? (string) ($v['label'] ?? ($ok ? 'Válido para CAE' : 'No válido para CAE')) : '—';
                        $badgeClass = $ok ? 'text-bg-success' : 'text-bg-danger';
                        $expRaw = trim((string) ($d['expires_at'] ?? ''));
                        $reasonPrimary = \App\Services\DocumentIntakePresentationService::validityPrimaryReason(is_array($v) ? $v : null);
                        $reasonSecondary = \App\Services\DocumentIntakePresentationService::validitySecondaryLine(is_array($v) ? $v : null, $expRaw !== '' ? $expRaw : null);
                    ?>
                    <tr>
                        <td data-label="Documento" class="doc-table-type"><?= htmlspecialchars((string) ($d['document_name'] ?? '-')) ?></td>
                        <td data-label="Archivo"><span class="doc-table-filename"><?= htmlspecialchars((string) ($d['original_filename'] ?? '-')) ?></span></td>
                        <td data-label="Validez CAE">
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
                    <i class="bi bi-upload me-1"></i>Subir y sustituir
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('form-tech-upload-supporting');
    const typeSelect = document.getElementById('tech-upload-doc-type');
    const modalEl = document.getElementById('modalTechUploadReplace');
    if (!form) return;

    const modal = modalEl ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
    const introEl = document.getElementById('tech-upload-replace-intro');
    const confirmBtn = document.getElementById('tech-upload-replace-confirm');

    const clearModalBackdrop = () => {
        document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
    };

    const lockAdminUploadForm = () => {
        form.dataset.submitting = '1';
        setTimeout(function () {
            form.querySelectorAll('button[type="submit"], select, input[type="file"]').forEach(function (el) {
                el.disabled = true;
            });
        }, 0);
    };

    const showAnalyzeOverlay = () => {
        requestAnimationFrame(() => {
            clearModalBackdrop();
            window.AppDocAnalyzeOverlay?.show({
                title: 'Analizando documento complementario'
            });
            lockAdminUploadForm();
        });
    };

    form.addEventListener('submit', function (e) {

        if (form.dataset.submitting === '1') {
            e.preventDefault();
            return;
        }

        if (form.dataset.confirmed === '1') {
            delete form.dataset.confirmed;
            showAnalyzeOverlay();
            return;
        }

        if (!typeSelect) {
            showAnalyzeOverlay();
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