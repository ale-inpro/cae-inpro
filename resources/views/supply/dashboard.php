<?php declare(strict_types=1);
$ab = htmlspecialchars($areaBaseUrl ?? '');
$s = $stats ?? [];
$c = $counts ?? [];
$recent = $recent ?? [];
$chartStatus = $chartStatus ?? ['labels' => [], 'series' => []];
$chartScope = $chartScope ?? ['labels' => [], 'series' => []];
$isAdmin = !empty($isAdmin);
$companiesTotal = (int) ($companiesTotal ?? 0);

$total = (int) ($c['total_contracts'] ?? 0);
$active = (int) ($c['active_count'] ?? 0);
$upcoming = (int) ($c['upcoming_count'] ?? 0);
$inactive = (int) ($c['inactive_count'] ?? 0);
$expiring = (int) ($s['expiring_60d'] ?? 0);
$fee = (float) ($s['monthly_fee_total'] ?? 0);

$statusMeta = static function (string $st): array {
    return match ($st) {
        'active' => ['Activo', 'rgpd-dash-badge rgpd-dash-badge-signed'],
        'pending_renewal' => ['Próxima renovación', 'rgpd-dash-badge rgpd-dash-badge-pending'],
        'expired' => ['Vencido', 'rgpd-dash-badge rgpd-dash-badge-cancelled'],
        'cancelled' => ['Baja', 'rgpd-dash-badge rgpd-dash-badge-cancelled'],
        default => [$st, 'rgpd-dash-badge'],
    };
};

$typeLabel = static fn(string $t): string => match ($t) {
    'electricity' => 'Electricidad',
    'gas' => 'Gas',
    'water' => 'Agua',
    'telecom' => 'Telecom',
    default => 'Otro',
};
?>
<div class="page-header page-header--balanced page-header--premium mb-4">
    <div class="page-header-left">
        <h1 class="h3 page-title mb-1">Suministros · Resumen</h1>
        <p class="page-meta mb-0">Contratos, vencimientos y comisiones administrativas.</p>
    </div>
    <div class="page-header-center"></div>
    <div class="page-header-right d-flex gap-2">
        <?php if ($isAdmin): ?>
            <a class="btn btn-success btn-sm" href="<?= $ab ?>/suministros/empresas/nueva" title="Nueva empresa">
                <i class="bi bi-plus-lg"></i>
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="rgpd-dash-hero mb-4">
    <div class="rgpd-dash-hero-glow"></div>
    <div class="row align-items-center g-3 position-relative">
        <div class="col-lg-8">
            <div class="rgpd-dash-hero-kicker">Panel operativo Suministros</div>
            <h2 class="rgpd-dash-hero-title mb-2"><?= $total ?> contratos en cartera</h2>
            <p class="rgpd-dash-hero-meta mb-0">
                <span><i class="bi bi-check-circle me-1"></i><?= $active ?> activos</span>
                <span class="mx-2">·</span>
                <span><i class="bi bi-hourglass-split me-1"></i><?= $upcoming ?> próximos</span>
                <span class="mx-2">·</span>
                <span><i class="bi bi-calendar-x me-1"></i><?= $expiring ?> vencen ≤ 60 días</span>
            </p>
        </div>
        <div class="col-lg-4">
            <div class="rgpd-dash-hero-stat">
                <div class="rgpd-dash-hero-stat-value"><?= number_format($fee, 2, ',', '.') ?> €</div>
                <div class="rgpd-dash-hero-stat-label">Comisión mensual estimada</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="rgpd-dash-kpi rgpd-dash-kpi-success">
            <div class="rgpd-dash-kpi-icon"><i class="bi bi-lightning-charge"></i></div>
            <div>
                <div class="rgpd-dash-kpi-value"><?= $active ?></div>
                <div class="rgpd-dash-kpi-label">Activos</div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="rgpd-dash-kpi rgpd-dash-kpi-warning">
            <div class="rgpd-dash-kpi-icon"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="rgpd-dash-kpi-value"><?= $upcoming ?></div>
                <div class="rgpd-dash-kpi-label">Próxima renovación</div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="rgpd-dash-kpi rgpd-dash-kpi-danger">
            <div class="rgpd-dash-kpi-icon"><i class="bi bi-x-circle"></i></div>
            <div>
                <div class="rgpd-dash-kpi-value"><?= $inactive ?></div>
                <div class="rgpd-dash-kpi-label">Bajas / vencidos</div>
            </div>
        </div>
    </div>
    <?php if ($isAdmin): ?>
    <div class="col-md-6 col-xl-3">
        <div class="rgpd-dash-kpi rgpd-dash-kpi-info">
            <div class="rgpd-dash-kpi-icon"><i class="bi bi-building"></i></div>
            <div>
                <div class="rgpd-dash-kpi-value"><?= $companiesTotal ?></div>
                <div class="rgpd-dash-kpi-label">Empresas activas</div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="col-md-6 col-xl-3">
        <div class="rgpd-dash-kpi rgpd-dash-kpi-warning">
            <div class="rgpd-dash-kpi-icon"><i class="bi bi-calendar-x"></i></div>
            <div>
                <div class="rgpd-dash-kpi-value"><?= $expiring ?></div>
                <div class="rgpd-dash-kpi-label">Vencen ≤ 60 días</div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="rgpd-dash-chart-card h-100">
            <div class="rgpd-dash-chart-head">
                <h3 class="rgpd-dash-chart-title mb-0">Estado de contratos</h3>
                <span class="small text-muted">Activos · Próximos · Bajas</span>
            </div>
            <div class="p-3">
                <?php foreach ($chartStatus['labels'] as $i => $lbl): ?>
                    <?php $val = (int) ($chartStatus['series'][$i] ?? 0); ?>
                    <div class="d-flex justify-content-between small mb-1">
                        <span><?= htmlspecialchars($lbl) ?></span>
                        <strong><?= $val ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="rgpd-dash-chart-card h-100">
            <div class="rgpd-dash-chart-head">
                <h3 class="rgpd-dash-chart-title mb-0">Contratos activos por ámbito</h3>
                <span class="small text-muted">Comunidad · Vecino</span>
            </div>
            <div class="p-3">
                <?php foreach ($chartScope['labels'] as $i => $lbl): ?>
                    <?php $val = (int) ($chartScope['series'][$i] ?? 0); ?>
                    <div class="d-flex justify-content-between small mb-1">
                        <span><?= htmlspecialchars($lbl) ?></span>
                        <strong><?= $val ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="rgpd-dash-chart-card h-100">
            <div class="rgpd-dash-chart-head">
                <h3 class="rgpd-dash-chart-title mb-0">Accesos rápidos</h3>
                <span class="small text-muted">Módulo Suministros</span>
            </div>
            <div class="rgpd-dash-quick-links">
                <a href="<?= $ab ?>/suministros/comunidades" class="rgpd-dash-quick-link">
                    <i class="bi bi-buildings"></i><span>Comunidades</span><i class="bi bi-arrow-right-short ms-auto"></i>
                </a>
                <a href="<?= $ab ?>/suministros/vecinos" class="rgpd-dash-quick-link">
                    <i class="bi bi-people"></i><span>Vecinos</span><i class="bi bi-arrow-right-short ms-auto"></i>
                </a>
                <?php if ($isAdmin): ?>
                <a href="<?= $ab ?>/suministros/empresas" class="rgpd-dash-quick-link">
                    <i class="bi bi-building-gear"></i><span>Empresas</span><i class="bi bi-arrow-right-short ms-auto"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="subpanel">
    <div class="subpanel-h d-flex justify-content-between align-items-center">
        <span>Últimos contratos</span>
        <a href="<?= $ab ?>/suministros/comunidades" class="btn btn-outline-secondary btn-sm">Ver comunidades</a>
    </div>
    <div class="subpanel-b p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 rgpd-dash-table">
                <thead>
                <tr>
                    <th>Ámbito</th>
                    <th>Comunidad</th>
                    <th>Vecino</th>
                    <th>Tipo</th>
                    <th>Nº contrato</th>
                    <th>Estado</th>
                    <th>Vencimiento</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($recent === []): ?>
                    <tr><td colspan="7" class="text-muted text-center py-4">No hay contratos recientes.</td></tr>
                <?php else: ?>
                    <?php foreach ($recent as $row): ?>
                        <?php [$lbl, $badge] = $statusMeta((string) ($row['status'] ?? '')); ?>
                        <tr>
                            <td><?= ($row['scope'] ?? '') === 'resident' ? 'Vecino' : 'Comunidad' ?></td>
                            <td><?= htmlspecialchars((string) ($row['community_name'] ?? '—')) ?></td>
                            <td><?= htmlspecialchars((string) ($row['resident_name'] ?? '—')) ?></td>
                            <td><?= htmlspecialchars($typeLabel((string) ($row['supply_type'] ?? ''))) ?></td>
                            <td><?= htmlspecialchars((string) ($row['contract_number'] ?? '')) ?></td>
                            <td><span class="<?= $badge ?>"><?= htmlspecialchars($lbl) ?></span></td>
                            <td class="text-nowrap"><?= htmlspecialchars((string) ($row['end_date'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>