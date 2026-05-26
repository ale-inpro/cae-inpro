<?php declare(strict_types=1);
$ab = htmlspecialchars(rtrim($areaBaseUrl ?? '/cae-inpro/public/admin', '/'));
$requests = $requests ?? [];
$statusFilter = (string) ($statusFilter ?? 'pending');
$pendingCount = (int) ($pendingCount ?? 0);
$focusId = (int) ($_GET['focus'] ?? 0);

$statusLabel = static function (string $st): string {
    return match ($st) {
        'pending' => 'Pendiente',
        'approved' => 'Aprobada',
        'rejected' => 'Rechazada',
        default => ucfirst($st),
    };
};
$statusBadge = static function (string $st): string {
    return match ($st) {
        'pending' => 'text-bg-warning text-dark',
        'approved' => 'text-bg-success',
        'rejected' => 'text-bg-danger',
        default => 'text-bg-secondary',
    };
};
$entityBadge = static function (string $et): string {
    return $et === 'company'
        ? '<span class="badge text-bg-primary">Empresa</span>'
        : '<span class="badge text-bg-light text-dark border">Persona</span>';
};
?>
<div class="page-header mb-4">
    <div>
        <h1 class="h3 page-title mb-1">Solicitudes de asociación</h1>
        <p class="page-meta mb-0">Gestores que solicitan vincular técnicos ya existentes a su cartera.</p>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="<?= $ab ?>/tecnicos">
        <i class="bi bi-arrow-left me-1"></i>Técnicos
    </a>
</div>

<div
    id="admin-tecnicos-solicitudes"
    data-admin-tecnicos-page="solicitudes"
    data-assoc-list-version="<?= htmlspecialchars((string) ($listVersion ?? '0'), ENT_QUOTES, 'UTF-8') ?>"
    data-assoc-pending-count="<?= (int) $pendingCount ?>"
    data-assoc-status-filter="<?= htmlspecialchars($statusFilter, ENT_QUOTES, 'UTF-8') ?>"
></div>

<div class="filter-chips mb-3">
    <a href="<?= $ab ?>/tecnicos/solicitudes?status=pending" class="filter-chip <?= $statusFilter === 'pending' ? 'active' : '' ?>">
        Pendientes
        <span
            class="badge text-bg-danger ms-1 js-assoc-pending-badge <?= $pendingCount > 0 ? '' : 'is-hidden' ?>"
            data-assoc-pending-badge
        ><?= $pendingCount > 0 ? (int) $pendingCount : '' ?></span>
    </a>
    <a href="<?= $ab ?>/tecnicos/solicitudes?status=approved" class="filter-chip <?= $statusFilter === 'approved' ? 'active' : '' ?>">Aprobadas</a>
    <a href="<?= $ab ?>/tecnicos/solicitudes?status=rejected" class="filter-chip <?= $statusFilter === 'rejected' ? 'active' : '' ?>">Rechazadas</a>
    <a href="<?= $ab ?>/tecnicos/solicitudes?status=all" class="filter-chip <?= $statusFilter === 'all' ? 'active' : '' ?>">Todas</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <?php if ($requests === []): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                No hay solicitudes en este filtro.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 table-mobile-cards">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Técnico</th>
                            <th>CIF / NIF</th>
                            <th>Gestor</th>
                            <th>Notas gestor</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($requests as $r): ?>
                        <?php
                            $rid = (int) ($r['id'] ?? 0);
                            $tid = (int) ($r['technician_id'] ?? 0);
                            $st = (string) ($r['status'] ?? '');
                            $created = app_datetime($r['created_at'] ?? null);
                            $rowClass = ($focusId > 0 && $focusId === $rid) ? 'table-warning' : '';
                        ?>
                        <tr class="<?= $rowClass ?>" id="req-<?= $rid ?>">
                            <td data-label="Fecha" class="text-muted small"><?= htmlspecialchars($created) ?></td>
                            <td data-label="Técnico">
                                <a href="<?= $ab ?>/tecnicos/<?= $tid ?>" class="fw-semibold text-decoration-none">
                                    <?= htmlspecialchars((string) ($r['display_name'] ?? '-')) ?>
                                </a>
                                <div class="small text-muted"><?= htmlspecialchars((string) ($r['professions'] ?? '')) ?></div>
                            </td>
                            <td data-label="CIF / NIF">
                                <code class="small"><?= htmlspecialchars((string) ($r['tax_id'] ?? '-')) ?></code>
                                <div class="mt-1"><?= $entityBadge((string) ($r['entity_type'] ?? 'individual')) ?></div>
                            </td>
                            <td data-label="Gestor">
                                <?= htmlspecialchars((string) ($r['requester_name'] ?? '-')) ?>
                                <div class="small text-muted"><?= htmlspecialchars((string) ($r['requester_email'] ?? '')) ?></div>
                                <div class="small text-muted">Empresa gestora #<?= (int) ($r['manager_company_id'] ?? 0) ?></div>
                            </td>
                            <td data-label="Notas gestor" class="small"><?= htmlspecialchars((string) ($r['gestor_notes'] ?? '—')) ?></td>
                            <td data-label="Estado">
                                <span class="badge <?= $statusBadge($st) ?>"><?= htmlspecialchars($statusLabel($st)) ?></span>
                                <?php if ($st !== 'pending' && !empty($r['admin_notes'])): ?>
                                    <div class="small text-muted mt-1" title="<?= htmlspecialchars((string) $r['admin_notes'], ENT_QUOTES, 'UTF-8') ?>">
                                        Admin: <?= htmlspecialchars((string) $r['admin_notes']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Acciones" class="text-end">
                                <?php if ($st === 'pending'): ?>
                                    <div class="d-flex flex-wrap gap-1 justify-content-end">
                                        <form method="post" action="<?= $ab ?>/tecnicos/solicitudes/<?= $rid ?>/aprobar" class="m-0">
                                            <button type="submit" class="btn btn-sm btn-success" title="Aprobar">
                                                <i class="bi bi-check-lg"></i><span class="d-none d-md-inline ms-1">Aprobar</span>
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                            data-bs-toggle="modal" data-bs-target="#rejectModal<?= $rid ?>" title="Rechazar">
                                            <i class="bi bi-x-lg"></i><span class="d-none d-md-inline ms-1">Rechazar</span>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php foreach ($requests as $r): ?>
    <?php if (($r['status'] ?? '') !== 'pending') continue; ?>
    <?php $rid = (int) ($r['id'] ?? 0); ?>
    <div class="modal fade" id="rejectModal<?= $rid ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="<?= $ab ?>/tecnicos/solicitudes/<?= $rid ?>/rechazar">
                    <div class="modal-header">
                        <h5 class="modal-title">Rechazar solicitud</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted mb-2">
                            Técnico: <strong><?= htmlspecialchars((string) ($r['display_name'] ?? '')) ?></strong>
                        </p>
                        <label class="form-label">Motivo del rechazo *</label>
                        <textarea name="admin_notes" class="form-control" rows="3" maxlength="500" required placeholder="Indica por qué no se aprueba la vinculación…"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Rechazar solicitud</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>
