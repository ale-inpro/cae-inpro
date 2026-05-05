<?php declare(strict_types=1);
$ab = htmlspecialchars($areaBaseUrl ?? '/cae-inpro/public/gestor');
$c = $community ?? [];
$risk = $riskReport ?? null;
$techs = $communityTechnicians ?? [];
$docs = $communityDocuments ?? [];
$isAdmin = (($area ?? 'gestor') === 'admin');
$available = $availableTechnicians ?? [];
$currentUrl = $ab . '/comunidades/' . (int) ($c['id'] ?? 0) . '#c-info';
$editUrl = $ab . '/comunidades/' . (int) ($c['id'] ?? 0) . '/edit?return_to=' . urlencode($currentUrl);

$riskLabel = static function (string $status): string {
    return match ($status) {
        'completed' => 'Completado',
        'in_progress' => 'En proceso',
        'rejected' => 'Rechazado',
        'pending' => 'Pendiente',
        default => ucfirst($status),
    };
};
$riskBadge = static function (string $status): string {
    return match ($status) {
        'completed' => 'text-bg-success',
        'in_progress' => 'text-bg-warning',
        'rejected' => 'text-bg-danger',
        'pending' => 'text-bg-secondary',
        default => 'text-bg-light text-dark',
    };
};

$caeLabel = static function (string $status): string {
    return match ($status) {
        'approved' => 'Aprobado',
        'in_review' => 'En revisión',
        'pending_docs' => 'Pendiente',
        'rejected' => 'Rechazado',
        'expired' => 'Caducado',
        default => ucfirst($status),
    };
};
$caeBadge = static function (string $status): string {
    return match ($status) {
        'approved' => 'text-bg-success',
        'in_review' => 'text-bg-warning',
        'pending_docs' => 'text-bg-secondary',
        'rejected' => 'text-bg-danger',
        'expired' => 'text-bg-dark',
        default => 'text-bg-light text-dark',
    };
};
?>
<div class="panel-identity mb-3">
    <div class="panel-identity-icon"><i class="bi bi-buildings-fill"></i></div>
    <div>
        <p class="panel-identity-kicker mb-1">Panel operativo</p>
        <h2 class="panel-identity-title mb-0">Panel de Comunidad</h2>
    </div>
</div>

<div class="page-header page-header--balanced page-header--premium">
    <div class="page-header-left">
        <h1 class="h3 page-title mb-1"><?= htmlspecialchars((string) ($c['name'] ?? 'Comunidad')) ?></h1>
        <p class="page-meta mb-0">
            <?= htmlspecialchars((string) ($c['address'] ?? '-')) ?> ·
            <?= htmlspecialchars((string) ($c['city'] ?? '-')) ?> ·
            <?php if ($risk): ?>
                <span class="badge <?= $riskBadge((string) $risk['status']) ?>"><?= htmlspecialchars($riskLabel((string) $risk['status'])) ?></span>
            <?php else: ?>
                <span class="badge text-bg-secondary">Sin informe RL</span>
            <?php endif; ?>
        </p>
    </div>

    <div class="page-header-center"></div>

    <div class="page-header-right d-flex align-items-center gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="<?= $ab ?>/comunidades" title="Volver"><i class="bi bi-arrow-left"></i></a>
        <?php if ($isAdmin): ?>
            <a class="btn btn-success btn-sm" href="<?= htmlspecialchars($editUrl) ?>" title="Editar"><i class="bi bi-pencil-square"></i></a>
            <form method="post" action="<?= $ab ?>/comunidades/<?= (int) ($c['id'] ?? 0) ?>" data-confirm="¿Desactivar esta comunidad?" class="m-0">
                <input type="hidden" name="_method" value="DELETE">
                <button class="btn btn-outline-danger btn-sm" type="submit" title="Desactivar"><i class="bi bi-building-x"></i></button>
            </form>
        <?php endif; ?>
    </div>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#c-info" type="button">Datos</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#c-tech" type="button">Técnicos</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#c-docs" type="button">Documentos</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#c-rl" type="button">Informe RL</button></li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="c-info">
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="subpanel">
                    <div class="subpanel-h">Identificación</div>
                    <div class="subpanel-b">
                        <dl class="row mb-0 small">
                            <dt class="col-sm-4 text-muted">CIF</dt><dd class="col-sm-8"><?= htmlspecialchars((string) ($c['cif'] ?? '-')) ?></dd>
                            <dt class="col-sm-4 text-muted">Ciudad</dt><dd class="col-sm-8"><?= htmlspecialchars((string) ($c['city'] ?? '-')) ?></dd>
                            <dt class="col-sm-4 text-muted">Provincia</dt><dd class="col-sm-8"><?= htmlspecialchars((string) ($c['province'] ?? '-')) ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="subpanel">
                    <div class="subpanel-h">Contacto</div>
                    <div class="subpanel-b">
                        <dl class="row mb-0 small">
                            <dt class="col-sm-4 text-muted">Persona</dt><dd class="col-sm-8"><?= htmlspecialchars((string) ($c['contact_name'] ?? '-')) ?></dd>
                            <dt class="col-sm-4 text-muted">Teléfono</dt><dd class="col-sm-8"><?= htmlspecialchars((string) ($c['contact_phone'] ?? '-')) ?></dd>
                            <dt class="col-sm-4 text-muted">Email</dt><dd class="col-sm-8"><?= htmlspecialchars((string) ($c['contact_email'] ?? '-')) ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="c-tech">
        <div class="subpanel mb-3">
            <div class="subpanel-h d-flex justify-content-between align-items-center">
                <span>Técnicos asignados</span>
                <span class="badge text-bg-success"><?= count($techs) ?></span>
            </div>
            <div class="subpanel-b">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 table-mobile-cards">
                        <thead><tr><th>Técnico</th><th>Profesión</th><th>Estado CAE</th><?php if ($isAdmin): ?><th></th><?php endif; ?></tr></thead>
                        <tbody>
                        <?php foreach ($techs as $t): ?>
                            <?php
                            $status = (string) ($t['cae_status'] ?? 'pending_docs');
                            $name = trim(((string) ($t['first_name'] ?? '')) . ' ' . ((string) ($t['last_name'] ?? '')));
                            $tid = (int) ($t['id'] ?? 0);
                            ?>
                            <tr class="community-tech-assigned">
                                <td data-label="Técnico"><?= htmlspecialchars($name) ?></td>
                                <td data-label="Profesión"><?= htmlspecialchars((string) ($t['professions'] ?? '-')) ?></td>
                                <td data-label="Estado CAE"><span class="badge <?= $caeBadge($status) ?>"><?= htmlspecialchars($caeLabel($status)) ?></span></td>
                                <?php if ($isAdmin): ?>
                                    <td data-label="Acciones" class="text-end">
                                        <form method="post" action="<?= $ab ?>/comunidades/<?= (int) ($c['id'] ?? 0) ?>/tecnicos/<?= $tid ?>" data-confirm="¿Desasignar este técnico de la comunidad?">
                                            <input type="hidden" name="_method" value="DELETE">
                                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Quitar"><i class="bi bi-person-dash"></i></button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if ($isAdmin && !empty($available)): ?>
            <div class="subpanel mb-3">
                <div class="subpanel-h d-flex justify-content-between align-items-center">
                    <span>Técnicos disponibles</span>
                    <span class="badge text-bg-secondary"><?= count($available) ?></span>
                </div>
                <div class="subpanel-b">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 table-mobile-cards">
                            <thead><tr><th>Técnico</th><th>Profesión</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($available as $a): ?>
                                <?php $tid = (int) ($a['id'] ?? 0); ?>
                                <tr class="community-tech-available">
                                    <td data-label="Técnico"><?= htmlspecialchars(trim(((string) ($a['first_name'] ?? '')) . ' ' . ((string) ($a['last_name'] ?? '')))) ?></td>
                                    <td data-label="Profesión"><?= htmlspecialchars((string) ($a['professions'] ?? '-')) ?></td>
                                    <td data-label="Acciones" class="text-end">
                                        <form method="post" action="<?= $ab ?>/comunidades/<?= (int) ($c['id'] ?? 0) ?>/tecnicos/<?= $tid ?>">
                                            <button class="btn btn-sm btn-outline-success" type="submit" title="Asignar"><i class="bi bi-person-plus"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($techs)): ?>
            <div class="community-empty-note">
                <i class="bi bi-info-circle me-1"></i> Esta comunidad aún no tiene técnicos asignados.
            </div>
        <?php endif; ?>
    </div>

    <div class="tab-pane fade" id="c-docs">
        <?php $docTypes = $communityDocTypes ?? []; ?>

        <?php if ($isAdmin && !empty($docTypes)): ?>
            <div class="subpanel mb-3">
                <div class="subpanel-h">Subir documento de comunidad</div>
                <div class="subpanel-b">
                    <form method="post" enctype="multipart/form-data" action="<?= $ab ?>/comunidades/<?= (int) ($c['id'] ?? 0) ?>/documentos" class="row g-2">
                        <input type="hidden" name="return_to" value="<?= $ab ?>/comunidades/<?= (int) ($c['id'] ?? 0) ?>#c-docs">
                        <div class="col-md-4">
                            <select name="document_type_id" class="form-select" required>
                                <option value="">Tipo de documento</option>
                                <?php foreach ($docTypes as $dt): ?>
                                    <option value="<?= (int) ($dt['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($dt['name'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <input type="file" name="document_file" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-success w-100" type="submit"><i class="bi bi-upload"></i></button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 table-mobile-cards">
                <thead><tr><th>Documento</th><th>Archivo</th><th>Subida</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($docs as $d): ?>
                    <tr>
                        <td data-label="Documento"><?= htmlspecialchars((string) ($d['document_name'] ?? '-')) ?></td>
                        <td data-label="Archivo"><?= htmlspecialchars((string) ($d['original_filename'] ?? '-')) ?></td>
                        <td data-label="Subida"><?= htmlspecialchars((string) ($d['uploaded_at'] ?? '-')) ?></td>
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
                                    href="<?= $ab ?>/comunidades/documentos/<?= (int) ($d['id'] ?? 0) ?>/download"
                                    title="Descargar"
                                ><i class="bi bi-download"></i></a>
                                <?php if ($isAdmin): ?>
                                    <form method="post" action="<?= $ab ?>/comunidades/documentos/<?= (int) ($d['id'] ?? 0) ?>" data-confirm="¿Eliminar este documento?">
                                        <input type="hidden" name="_method" value="DELETE">
                                        <input type="hidden" name="return_to" value="<?= $ab ?>/comunidades/<?= (int) ($c['id'] ?? 0) ?>#c-docs">
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

    <div class="tab-pane fade" id="c-rl">
        <div class="subpanel rl-panel">
            <div class="subpanel-h d-flex justify-content-between align-items-center">
                <span>Informe de riesgos laborales</span>
                <?php if ($risk): ?>
                    <span class="badge <?= $riskBadge((string) $risk['status']) ?>">
                        <?= htmlspecialchars($riskLabel((string) $risk['status'])) ?>
                    </span>
                <?php else: ?>
                    <span class="badge text-bg-secondary">Sin informe RL</span>
                <?php endif; ?>
            </div>

            <div class="subpanel-b small">
                <?php if ($isAdmin): ?>
                    <div class="rl-admin-grid mb-3">
                        <section class="rl-card">
                            <h4 class="rl-card-title">Subir o reemplazar informe</h4>
                            <p class="rl-card-sub mb-3">Carga la version mas reciente del informe RL de la comunidad.</p>
                            <form method="post" enctype="multipart/form-data" action="<?= $ab ?>/comunidades/<?= (int) ($c['id'] ?? 0) ?>/riesgos/upload" class="row g-2">
                                <div class="col-xl-8">
                                    <input type="file" name="report_file" class="form-control" required>
                                </div>
                                <div class="col-xl-4">
                                <button class="btn btn-success w-100" type="submit"><i class="bi bi-upload"></i></button>
                                </div>
                            </form>
                        </section>

                        <section class="rl-card">
                            <h4 class="rl-card-title">Estado y observaciones</h4>
                            <p class="rl-card-sub mb-3">Actualiza el estado operativo y las notas para el gestor.</p>
                            <form method="post" action="<?= $ab ?>/comunidades/<?= (int) ($c['id'] ?? 0) ?>/riesgos" class="row g-2">
                                <input type="hidden" name="_method" value="PUT">
                                <div class="col-12">
                                    <?php $currentStatus = (string) ($risk['status'] ?? 'pending'); ?>
                                    <select name="status" class="form-select" required>
                                        <option value="pending" <?= $currentStatus === 'pending' ? 'selected' : '' ?>>Pendiente</option>
                                        <option value="in_progress" <?= $currentStatus === 'in_progress' ? 'selected' : '' ?>>En proceso</option>
                                        <option value="completed" <?= $currentStatus === 'completed' ? 'selected' : '' ?>>Completado</option>
                                        <option value="rejected" <?= $currentStatus === 'rejected' ? 'selected' : '' ?>>Rechazado</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <textarea name="notes" class="form-control" rows="3" placeholder="Notas del informe RL"><?= htmlspecialchars((string) ($risk['notes'] ?? '')) ?></textarea>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-outline-primary w-100" type="submit"><i class="bi bi-check2-circle"></i></button>
                                </div>
                            </form>
                        </section>
                    </div>
                <?php endif; ?>

                <?php if ($risk): ?>
                    <section class="rl-file-card">
                        <div class="rl-file-head">
                            <div>
                                <p class="rl-file-label mb-1">Archivo actual</p>
                                <p class="rl-file-name mb-0"><?= htmlspecialchars((string) ($risk['report_filename'] ?? 'No disponible')) ?></p>
                            </div>
                            <?php if (!empty($risk['completed_at'])): ?>
                                <span class="rl-date-chip">Completado: <?= htmlspecialchars((string) $risk['completed_at']) ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($risk['report_path'])): ?>
                            <?php $previewRlUrl = htmlspecialchars((string) (($baseUrl ?? '') . ($risk['report_path'] ?? ''))); ?>
                            <div class="rl-actions mt-3">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary"
                                    data-file-preview
                                    data-file-preview-url="<?= $previewRlUrl ?>"
                                    data-file-preview-name="<?= htmlspecialchars((string) ($risk['report_filename'] ?? 'Informe RL')) ?>"
                                    title="Ver archivo"
                                ><i class="bi bi-eye"></i></button>
                                <a class="btn btn-sm btn-outline-primary" href="<?= $ab ?>/comunidades/<?= (int) ($c['id'] ?? 0) ?>/riesgos/download" title="Descargar"><i class="bi bi-download"></i></a>
                                <?php if ($isAdmin): ?>
                                    <form method="post" action="<?= $ab ?>/comunidades/<?= (int) ($c['id'] ?? 0) ?>/riesgos" data-confirm="¿Quitar informe RL de esta comunidad?">
                                        <input type="hidden" name="_method" value="DELETE">
                                        <button class="btn btn-sm btn-outline-danger" type="submit" title="Quitar"><i class="bi bi-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($risk['notes'])): ?>
                            <div class="rl-notes mt-3">
                                <p class="rl-file-label mb-1">Notas</p>
                                <p class="mb-0 text-muted"><?= htmlspecialchars((string) ($risk['notes'] ?? '')) ?></p>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php else: ?>
                    <section class="rl-empty-state">
                        <p class="mb-1 fw-semibold">No hay informe RL cargado</p>
                        <p class="mb-0 text-muted">Cuando el administrador suba un archivo, aparecera aqui con acciones de visualizacion y descarga.</p>
                    </section>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>