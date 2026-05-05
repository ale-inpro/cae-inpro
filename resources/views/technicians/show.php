<?php declare(strict_types=1);
$ab = htmlspecialchars($areaBaseUrl ?? '/cae-inpro/public/gestor');
$t = $tech ?? [];
$name = trim(((string) ($t['first_name'] ?? '')) . ' ' . ((string) ($t['last_name'] ?? '')));
$current = $currentCae ?? null;
$history = $caeHistory ?? [];
$docs = $caeDocuments ?? [];
$isAdmin = (($area ?? 'gestor') === 'admin');
$currentUrl = $ab . '/tecnicos/' . (int) ($t['id'] ?? 0) . '#pane-info';
$editUrl = $ab . '/tecnicos/' . (int) ($t['id'] ?? 0) . '/edit?return_to=' . urlencode($currentUrl);

$statusLabel = static function (string $status): string {
    return match ($status) {
        'approved' => 'Aprobado',
        'in_review' => 'En revisión',
        'pending_docs' => 'Pendiente',
        'rejected' => 'Rechazado',
        'expired' => 'Caducado',
        default => ucfirst($status),
    };
};
$statusBadge = static function (string $status): string {
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
                    <form method="post" enctype="multipart/form-data" action="<?= $ab ?>/cae/<?= (int) ($current['id'] ?? 0) ?>/documentos" class="row g-2">
                        <input type="hidden" name="return_to" value="<?= $ab ?>/tecnicos/<?= (int) ($t['id'] ?? 0) ?>#pane-docs">
                        <input type="hidden" name="upload_mode" value="supporting">
                        <div class="col-md-4">
                            <select name="document_type_id" class="form-select" required>
                                <option value="">Tipo de documento</option>
                                <?php foreach ($types as $dt): ?>
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