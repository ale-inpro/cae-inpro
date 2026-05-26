<?php declare(strict_types=1);
$ab = htmlspecialchars($areaBaseUrl ?? '');
$bu = htmlspecialchars($baseUrl ?? '');
$c = $community ?? [];
$residents = $residents ?? [];
$signatures = $signatures ?? [];
$contract = $contract ?? null;
$documentSummaries = $documentSummaries ?? [];
$residentSignStats = $residentSignStats ?? [];
$pendingCount = (int) ($pendingCount ?? 0);
$sigFilters = $sigFilters ?? ['status' => 'pending', 'template_id' => 0, 'from' => '', 'to' => ''];
$templatesForFilter = $templatesForFilter ?? [];
$cid = (int) ($c['id'] ?? 0);
$returnToDocs = ($areaBaseUrl ?? '/admin') . '/rgpd/comunidades/' . $cid . '#rgpd-documentos';

$presidentId = 0;
foreach ($residents as $r) {
    if (in_array($r['is_president'] ?? false, [true, 't', '1', 1], true)) {
        $presidentId = (int) ($r['id'] ?? 0);
        break;
    }
}

$contractBadge = static function (?array $contract): array {
    if (!$contract) {
        return ['text-bg-secondary', 'No subido'];
    }
    $st = (string) ($contract['status'] ?? 'pending');
    $badge = match ($st) {
        'active' => 'text-bg-success',
        'expired' => 'text-bg-danger',
        default => 'text-bg-warning',
    };
    $label = match ($st) {
        'active' => 'Activo',
        'expired' => 'Vencido',
        'pending' => 'Pendiente',
        default => ucfirst($st),
    };
    return [$badge, $label];
};
[$cBadgeClass, $cBadgeLabel] = $contractBadge($contract);

$sigStatusLabel = static function (string $st): string {
    return match ($st) {
        'signed' => 'Firmado',
        'pending' => 'Pendiente',
        'paper' => 'En papel',
        'cancelled' => 'Cancelado',
        default => $st,
    };
};
$sigStatusBadge = static function (string $st): string {
    return match ($st) {
        'signed' => 'text-bg-success',
        'pending' => 'text-bg-warning',
        'paper' => 'text-bg-info',
        default => 'text-bg-secondary',
    };
};
?>
<div class="panel-identity mb-3">
    <div class="panel-identity-icon"><i class="bi bi-shield-lock"></i></div>
    <div>
        <p class="panel-identity-kicker mb-1">RGPD · Comunidad</p>
        <h2 class="panel-identity-title mb-0">Panel RGPD</h2>
    </div>
</div>

<div class="page-header page-header--balanced page-header--premium mb-3">
    <div class="page-header-left">
        <h1 class="h3 page-title mb-1"><?= htmlspecialchars((string) ($c['name'] ?? 'Comunidad')) ?></h1>
        <p class="page-meta mb-0">
            <?= htmlspecialchars((string) ($c['address'] ?? '—')) ?> ·
            <?= htmlspecialchars((string) ($c['city'] ?? '—')) ?>
            <span class="badge <?= $cBadgeClass ?> ms-1"><?= htmlspecialchars($cBadgeLabel) ?></span>
            <?php if ($pendingCount > 0): ?>
                <span class="badge text-bg-warning ms-1"><?= $pendingCount ?> firma(s) pendiente(s)</span>
            <?php endif; ?>
        </p>
    </div>
    <div class="page-header-center"></div>
    <div class="page-header-right d-flex align-items-center gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="<?= $ab ?>/rgpd/comunidades" title="Volver"><i class="bi bi-arrow-left"></i></a>
        <a class="btn btn-success btn-sm" href="<?= $ab ?>/rgpd/envio-masivo?step=1&amp;community_id=<?= $cid ?>" title="Envío masivo">
            <i class="bi bi-send"></i>
        </a>
    </div>
</div>

<ul class="nav nav-tabs nav-tabs--modern mb-3" id="rgpdCommunityTabs" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" id="rgpd-tab-vecinos" data-bs-toggle="tab" data-bs-target="#rgpd-vecinos" type="button">Vecinos</button>
    </li>
    <li class="nav-item">
        <button class="nav-link" id="rgpd-tab-documentos" data-bs-toggle="tab" data-bs-target="#rgpd-documentos" type="button">Documentos</button>
    </li>
    <li class="nav-item">
        <button class="nav-link" id="rgpd-tab-solicitudes" data-bs-toggle="tab" data-bs-target="#rgpd-solicitudes" type="button">
            Solicitudes<?php if ($pendingCount > 0): ?> (<?= $pendingCount ?>)<?php endif; ?>
        </button>
    </li>
</ul>

<div class="tab-content rgpd-community-panel">
    <div class="tab-pane fade show active" id="rgpd-vecinos" role="tabpanel">
        <div class="subpanel mb-3">
            <div class="subpanel-h">Presidente de la comunidad</div>
            <div class="subpanel-b">
                <div class="d-flex flex-wrap align-items-end gap-2">
                    <form method="post" action="<?= $ab ?>/rgpd/comunidades/<?= $cid ?>/presidente" class="d-flex flex-wrap align-items-end gap-2 flex-grow-1">
                        <div class="flex-grow-1" style="min-width:220px;">
                            <label class="form-label small mb-1">Vecino presidente</label>
                            <select name="resident_id" class="form-select form-select-sm" required>
                                <option value="">— Seleccionar —</option>
                                <?php foreach ($residents as $r): ?>
                                    <?php if (in_array($r['is_active'] ?? true, [true, 't', '1', 1], true)): ?>
                                        <option value="<?= (int) ($r['id'] ?? 0) ?>" <?= (int)($r['id'] ?? 0) === $presidentId ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(app_resident_name($r)) ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
                    </form>
                    <?php if ($presidentId > 0): ?>
                    <form method="post"
                          action="<?= $ab ?>/rgpd/comunidades/<?= $cid ?>/presidente"
                          data-confirm="¿Quitar al presidente de esta comunidad?">
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="btn btn-outline-secondary btn-sm">Quitar</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="subpanel">
            <div class="subpanel-h d-flex justify-content-between align-items-center">
                <span>Vecinos</span>
                <span class="badge text-bg-light text-dark"><?= count($residents) ?></span>
            </div>
            <div class="subpanel-b p-0">
                <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>DNI</th>
                        <th>Contacto</th>
                        <th>Vivienda</th>
                        <th>Firmas</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($residents === []): ?>
                        <tr><td colspan="5" class="text-muted text-center py-4">Sin vecinos</td></tr>
                    <?php else: ?>
                        <?php foreach ($residents as $r): ?>
                            <?php
                            $rid = (int) ($r['id'] ?? 0);
                            $stats = $residentSignStats[$rid] ?? ['signed_n' => 0, 'pending_n' => 0];
                            $vivienda = (string) ($r['unit_label'] ?? '');
                            if ($vivienda === '' && !empty($r['propiedades'])) {
                                $props = is_string($r['propiedades']) ? json_decode($r['propiedades'], true) : $r['propiedades'];
                                $vivienda = is_array($props) ? (string) ($props['vivienda'] ?? '') : '';
                            }
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex flex-wrap align-items-center gap-1">
                                        <strong class="text-nowrap"><?= htmlspecialchars(app_resident_name($r)) ?></strong>
                                        <?php if (in_array($r['is_president'] ?? false, [true, 't', '1', 1], true)): ?>
                                            <span class="badge text-bg-primary">Presidente</span>
                                        <?php endif; ?>
                                        <?php if (in_array($r['es_representante'] ?? false, [true, 't', '1', 1], true)): ?>
                                            <span class="badge text-bg-warning text-dark">Representante</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="text-nowrap small text-muted">
                                    <?= !empty($r['dni']) ? htmlspecialchars((string) $r['dni']) : '—' ?>
                                </td>
                                <td>
                                    <?php if (!empty($r['email'])): ?><div><?= htmlspecialchars((string) $r['email']) ?></div><?php endif; ?>
                                    <?php if (!empty($r['telefono'])): ?><div class="small text-muted"><?= htmlspecialchars((string) $r['telefono']) ?></div><?php endif; ?>
                                    <?php if (empty($r['email']) && empty($r['telefono'])): ?><span class="text-muted small">—</span><?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($vivienda !== '' ? $vivienda : '—') ?></td>
                                <td class="text-nowrap">
                                    <?php if ($stats['signed_n'] + $stats['pending_n'] === 0): ?>
                                        <span class="text-muted small">—</span>
                                    <?php else: ?>
                                        <span class="small"><?= (int) $stats['signed_n'] ?> firmado(s)</span>
                                        <?php if ($stats['pending_n'] > 0): ?>
                                            <span class="badge text-bg-warning"><?= (int) $stats['pending_n'] ?> pend.</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="rgpd-documentos" role="tabpanel">
        <div class="subpanel mb-3">
            <div class="subpanel-h d-flex justify-content-between align-items-center">
                <span>Contrato RGPD (marco)</span>
                <div class="table-actions">
                    <?php if ($contract && !empty($contract['storage_path'])): ?>
                        <a class="btn btn-sm btn-outline-secondary"
                           href="<?= $bu . htmlspecialchars((string) $contract['storage_path']) ?>"
                           target="_blank"
                           rel="noopener"
                           title="Ver contrato PDF">
                            <i class="bi bi-eye"></i>
                        </a>
                    <?php endif; ?>
                    <button type="button"
                            class="btn btn-sm btn-outline-primary"
                            data-bs-toggle="modal"
                            data-bs-target="#rgpdContractUploadModal"
                            title="Subir PDF">
                        <i class="bi bi-upload"></i>
                    </button>
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary"
                            data-bs-toggle="modal"
                            data-bs-target="#rgpdContractPaperModal"
                            title="Registrar en papel">
                        <i class="bi bi-journal-text"></i>
                    </button>
                </div>
            </div>
            <div class="subpanel-b">
                <?php if ($contract): ?>
                    <div class="row g-3 small mb-0">
                        <div class="col-md-4">
                            <div class="text-muted">Estado</div>
                            <div class="fw-semibold"><?= htmlspecialchars($cBadgeLabel) ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted">Firma</div>
                            <div><?= app_date((string) ($contract['signed_at'] ?? '')) ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted">Vence</div>
                            <div><?= app_date((string) ($contract['expires_at'] ?? '')) ?></div>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0">Sin contrato registrado para esta comunidad.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="subpanel">
            <div class="subpanel-h">Consentimientos por documento</div>
            <div class="subpanel-b p-0">
                <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 rgpd-doc-table">
                    <thead>
                    <tr>
                        <th>Documento</th>
                        <th>Último envío</th>
                        <th>Audiencia</th>
                        <th>Progreso</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($documentSummaries === []): ?>
                        <tr><td colspan="4" class="text-muted text-center py-3">Sin plantillas activas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($documentSummaries as $doc): ?>
                            <?php
                            $collapseId = 'rgpd-pending-' . (int) $doc['template_id'];
                            $hasCamp = !empty($doc['has_campaign']);
                            ?>
                            <tr class="<?= $hasCamp && (int) ($doc['missing'] ?? 0) > 0 ? 'rgpd-doc-row-expandable' : '' ?>">
                                <td>
                                    <strong><?= htmlspecialchars((string) $doc['template_name']) ?></strong>
                                    <?php if (($doc['kind'] ?? '') === 'system'): ?>
                                        <span class="badge text-bg-primary ms-1">Sistema</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-nowrap">
                                    <?php if ($hasCamp): ?>
                                        <?= app_datetime((string) ($doc['last_campaign_at'] ?? '')) ?>
                                    <?php else: ?>
                                        <span class="text-muted">Sin envío</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small"><?= $hasCamp ? htmlspecialchars((string) $doc['audience_label']) : '—' ?></td>
                                <td>
                                    <?php if (!$hasCamp): ?>
                                        <a class="small" href="<?= $ab ?>/rgpd/envio-masivo?step=1&amp;community_id=<?= $cid ?>">Lanzar envío</a>
                                    <?php elseif (!empty($doc['is_complete'])): ?>
                                        <span class="badge text-bg-success">Completo</span>
                                    <?php else: ?>
                                        <?php
                                        $missing = (int) ($doc['missing'] ?? 0);
                                        $eligible = max(0, (int) ($doc['eligible'] ?? 0));
                                        $completed = max(0, $eligible - $missing);
                                        $pct = $eligible > 0 ? min(100, (int) round(($completed / $eligible) * 100)) : 0;
                                        $missingLabel = $missing === 1 ? '1 sin firmar' : $missing . ' sin firmar';
                                        ?>
                                        <div class="rgpd-doc-meter">
                                            <button type="button"
                                                    class="rgpd-doc-meter-toggle rgpd-pending-toggle"
                                                    data-bs-toggle="collapse"
                                                    data-bs-target="#<?= $collapseId ?>"
                                                    aria-expanded="false"
                                                    aria-label="Ver pendientes de firma"
                                                    title="Ver quién falta por firmar">
                                                <span class="rgpd-doc-meter-chip">
                                                    <i class="bi bi-pen rgpd-doc-meter-chip-icon" aria-hidden="true"></i>
                                                    <?= htmlspecialchars($missingLabel) ?>
                                                    <i class="bi bi-chevron-down rgpd-pending-chevron" aria-hidden="true"></i>
                                                </span>
                                                <span class="rgpd-doc-meter-track" role="progressbar"
                                                      aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"
                                                      aria-label="<?= $completed ?> de <?= $eligible ?> firmados">
                                                    <span class="rgpd-doc-meter-fill" style="width:<?= $pct ?>%"></span>
                                                </span>
                                                <span class="rgpd-doc-meter-meta">
                                                    <span><?= $completed ?> de <?= $eligible ?> firmados</span>
                                                    <span class="rgpd-doc-meter-pct"><?= $pct ?>%</span>
                                                </span>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($hasCamp && (int) ($doc['missing'] ?? 0) > 0): ?>
                            <tr class="rgpd-doc-detail-row">
                                <td colspan="4" class="p-0 border-0">
                                    <div class="collapse" id="<?= $collapseId ?>">
                                        <div class="rgpd-pending-panel">
                                            <div class="rgpd-pending-panel-title small text-muted mb-2">
                                                <i class="bi bi-person-x me-1"></i> Pendientes de firma
                                            </div>
                                            <ul class="list-unstyled mb-0 small">
                                                <?php foreach ($doc['pending_residents'] as $pr): ?>
                                                    <li class="rgpd-pending-item py-2 px-2">
                                                        <strong><?= htmlspecialchars((string) ($pr['resident_name'] ?? '')) ?></strong>
                                                        <?php if (!empty($pr['email'])): ?>
                                                            <span class="text-muted"> · <?= htmlspecialchars((string) $pr['email']) ?></span>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="rgpd-solicitudes" role="tabpanel">
        <?php
        $sigQuery = array_filter([
            'sig_status' => ($sigFilters['status'] ?? '') !== 'pending' ? ($sigFilters['status'] ?? '') : null,
            'sig_template' => (int) ($sigFilters['template_id'] ?? 0) > 0 ? (int) $sigFilters['template_id'] : null,
            'sig_from' => ($sigFilters['from'] ?? '') !== '' ? ($sigFilters['from'] ?? '') : null,
            'sig_to' => ($sigFilters['to'] ?? '') !== '' ? ($sigFilters['to'] ?? '') : null,
        ], static fn($v) => $v !== null && $v !== '');
        $sigFilterQs = $sigQuery !== [] ? '?' . http_build_query($sigQuery) : '';
        ?>

        <div class="subpanel mb-3">
            <div class="subpanel-h">Filtros</div>
            <div class="subpanel-b">
                <form method="get" action="<?= $ab ?>/rgpd/comunidades/<?= $cid ?>#rgpd-solicitudes" class="row g-2 align-items-end mb-0">
                    <div class="col-md-2">
                        <label class="form-label small">Estado</label>
                        <select name="sig_status" class="form-select form-select-sm">
                            <?php foreach (['pending' => 'Pendiente', 'signed' => 'Firmado', 'paper' => 'En papel', 'cancelled' => 'Cancelado', 'all' => 'Todos'] as $k => $lbl): ?>
                                <option value="<?= $k ?>" <?= ($sigFilters['status'] ?? '') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Plantilla</label>
                        <select name="sig_template" class="form-select form-select-sm">
                            <option value="0">Todas</option>
                            <?php foreach ($templatesForFilter as $tf): ?>
                                <option value="<?= (int) $tf['id'] ?>" <?= (int)($sigFilters['template_id'] ?? 0) === (int)$tf['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) $tf['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Desde</label>
                        <input type="date" name="sig_from" class="form-control form-control-sm" value="<?= htmlspecialchars((string) ($sigFilters['from'] ?? '')) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Hasta</label>
                        <input type="date" name="sig_to" class="form-control form-control-sm" value="<?= htmlspecialchars((string) ($sigFilters['to'] ?? '')) ?>">
                    </div>
                    <div class="col-md-3 d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= $ab ?>/rgpd/comunidades/<?= $cid ?>#rgpd-solicitudes">Limpiar</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="subpanel">
            <div class="subpanel-h">Solicitudes de firma</div>
            <div class="subpanel-b p-0">
                <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" data-datatable data-page-length="15" data-empty="No hay solicitudes con estos filtros.">
                    <thead>
                    <tr>
                        <th>Vecino</th>
                        <th>Documento</th>
                        <th>Estado</th>
                        <th>Creada</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($signatures === []): ?>
                        <tr class="rgpd-sig-empty">
                            <td colspan="5" class="text-muted text-center py-4">No hay solicitudes con estos filtros.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($signatures as $s): ?>
                            <?php $st = (string) ($s['status'] ?? ''); ?>
                            <tr>
                                <td class="text-nowrap"><?= htmlspecialchars((string) ($s['resident_name'] ?? '')) ?></td>
                                <td><?= htmlspecialchars((string) ($s['template_name'] ?? '')) ?></td>
                                <td><span class="badge <?= $sigStatusBadge($st) ?>"><?= htmlspecialchars($sigStatusLabel($st)) ?></span></td>
                                <td class="text-nowrap"><?= app_datetime((string) ($s['created_at'] ?? '')) ?></td>
                                <td class="text-end text-nowrap">
                                    <?php if ($st === 'pending'): ?>
                                        <div class="table-actions justify-content-end">
                                            <form method="post"
                                                  action="<?= $ab ?>/rgpd/firmas/<?= (int)($s['id']??0) ?>/reenviar"
                                                  class="d-inline"
                                                  data-confirm="¿Reenviar el correo de firma a este vecino?">
                                                <button type="submit" class="btn btn-sm btn-outline-primary" title="Reenviar correo">
                                                    <i class="bi bi-envelope-arrow-up"></i>
                                                </button>
                                            </form>
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-secondary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#paperModal<?= (int)($s['id']??0) ?>"
                                                    title="Registrar en papel">
                                                <i class="bi bi-journal-text"></i>
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modales contrato -->
<div class="modal fade" id="rgpdContractUploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="<?= $ab ?>/rgpd/contratos/<?= $cid ?>/upload" enctype="multipart/form-data" class="modal-content border-0 shadow-lg">
            <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnToDocs, ENT_QUOTES, 'UTF-8') ?>">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title h6 mb-0">Subir contrato RGPD</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body pt-3">
                <input type="file" name="contract_pdf" class="form-control mb-3" accept="application/pdf" required>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small">Firma</label>
                        <input type="date" name="signed_at" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Vence</label>
                        <input type="date" name="expires_at" class="form-control form-control-sm" value="<?= date('Y-m-d', strtotime('+1 year')) ?>">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top-0 gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success btn-sm">Guardar</button>
            </div>
        </form>
    </div>
</div>
<div class="modal fade" id="rgpdContractPaperModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="<?= $ab ?>/rgpd/contratos/<?= $cid ?>/papel" class="modal-content border-0 shadow-lg">
            <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnToDocs, ENT_QUOTES, 'UTF-8') ?>">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title h6 mb-0">Contrato en papel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body pt-3">
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small">Firma</label>
                        <input type="date" name="signed_at" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Vence</label>
                        <input type="date" name="expires_at" class="form-control form-control-sm" value="<?= date('Y-m-d', strtotime('+1 year')) ?>">
                    </div>
                </div>
                <label class="form-label small">Notas</label>
                <textarea name="paper_notes" class="form-control form-control-sm" rows="2" placeholder="Opcional"></textarea>
            </div>
            <div class="modal-footer border-top-0 gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm">Registrar</button>
            </div>
        </form>
    </div>
</div>

<?php foreach ($signatures as $s): ?>
    <?php if ((string)($s['status'] ?? '') === 'pending'): ?>
    <div class="modal fade" id="paperModal<?= (int)($s['id']??0) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <form method="post" action="<?= $ab ?>/rgpd/firmas/<?= (int)($s['id']??0) ?>/papel" class="modal-content border-0 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title h6 mb-0">Firma en papel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body pt-3">
                    <textarea name="paper_notes" class="form-control form-control-sm" rows="3" placeholder="Notas opcionales"></textarea>
                </div>
                <div class="modal-footer border-top-0 gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm">Registrar</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
<?php endforeach; ?>

<script>
(function () {
    const params = new URLSearchParams(window.location.search);
    const hasSigFilters = ['sig_status', 'sig_template', 'sig_from', 'sig_to'].some(function (k) {
        return params.has(k) && params.get(k) !== '';
    });
    let hash = window.location.hash;
    if (!hash && hasSigFilters) {
        hash = '#rgpd-solicitudes';
    }
    if (hash) {
        const btn = document.querySelector('[data-bs-target="' + hash + '"]');
        if (btn) bootstrap.Tab.getOrCreateInstance(btn).show();
    }

    function initOrAdjustRgpdSolicitudesTable() {
        const table = document.querySelector('#rgpd-solicitudes table[data-datatable]');
        if (!table || typeof DataTable === 'undefined') return;
        if (table.querySelector('tbody tr.rgpd-sig-empty')) return;

        const emptyText = table.dataset.empty || 'No hay solicitudes con estos filtros.';
        const dtOptions = {
            pageLength: Number(table.dataset.pageLength || 15),
            language: {
                search: 'Buscar:',
                lengthMenu: 'Mostrar _MENU_',
                info: 'Mostrando _START_ a _END_ de _TOTAL_',
                infoEmpty: 'Sin registros',
                zeroRecords: emptyText,
                paginate: {
                    first: 'Primero',
                    last: 'Último',
                    next: 'Siguiente',
                    previous: 'Anterior',
                },
            },
        };

        let api = (typeof DataTable.table === 'function' ? DataTable.table(table) : null) || table._rgpdDt || null;
        if (!api) {
            api = new DataTable(table, dtOptions);
            table._rgpdDt = api;
        }
        if (api.columns) {
            api.columns.adjust();
        }
        if (api.draw) {
            api.draw(false);
        }
    }

    if (hash === '#rgpd-solicitudes') {
        setTimeout(initOrAdjustRgpdSolicitudesTable, 100);
    }

    document.querySelectorAll('#rgpdCommunityTabs [data-bs-toggle="tab"]').forEach(function (btn) {
        btn.addEventListener('shown.bs.tab', function (e) {
            const trigger = e.target.closest('[data-bs-toggle="tab"]') || btn;
            const target = trigger.getAttribute('data-bs-target');
            if (!target) return;
            const qs = window.location.search || '';
            history.replaceState(null, '', target + qs);
            if (target === '#rgpd-solicitudes') {
                initOrAdjustRgpdSolicitudesTable();
            }
        });
    });

    document.querySelectorAll('.rgpd-pending-toggle').forEach(function (toggleBtn) {
        const collapseTarget = toggleBtn.getAttribute('data-bs-target');
        if (!collapseTarget) return;
        const collapseEl = document.querySelector(collapseTarget);
        if (!collapseEl) return;
        collapseEl.addEventListener('show.bs.collapse', function () {
            toggleBtn.setAttribute('aria-expanded', 'true');
        });
        collapseEl.addEventListener('hide.bs.collapse', function () {
            toggleBtn.setAttribute('aria-expanded', 'false');
        });
    });
})();
</script>