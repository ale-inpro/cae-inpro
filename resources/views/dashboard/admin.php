<?php declare(strict_types=1);
$bu = htmlspecialchars(rtrim((string) ($baseUrl ?? ''), '/'));
$ap = htmlspecialchars((string) ($areaPrefix ?? '/admin'));
$pendingTotal = (int) (($opsTotals['pending'] ?? 0));
$overdueTotal = (int) (($opsTotals['overdue'] ?? 0));
$completionRate = (int) ($completionRate ?? 0);
$caeOpen = (int) ($kpiCaeOpen ?? 0);
$chartSeriesJson = json_encode(array_values($chartSeries ?? [0, 0, 0, 0, 0]), JSON_UNESCAPED_UNICODE);
$chartLabelsJson = json_encode(array_values($chartLabels ?? []), JSON_UNESCAPED_UNICODE);
?>
<div class="page-header page-header--balanced page-header--premium mb-4">
    <div class="page-header-left">
        <h1 class="h3 page-title mb-1"><?= htmlspecialchars((string) ($panelHeading ?? 'Dashboard CAE')) ?></h1>
        <p class="page-meta mb-0"><?= htmlspecialchars((string) ($panelSubheading ?? '')) ?></p>
    </div>
    <div class="page-header-right">
        <a class="btn btn-outline-secondary btn-sm" href="<?= $bu ?><?= $ap ?>/tecnicos?focus=cae_pending">
            <i class="bi bi-shield-check me-1"></i> Revisar CAE
        </a>
    </div>
</div>

<div class="cae-dash-hero">
    <div class="cae-dash-hero-glow"></div>
    <div class="row align-items-center g-3 position-relative">
        <div class="col-lg-8">
            <div class="cae-dash-hero-kicker">Módulo CAE</div>
            <h2 class="cae-dash-hero-title mb-2"><?= (int) ($kpiTechnicians ?? 0) ?> técnicos con CAE vigente</h2>
            <p class="cae-dash-hero-meta mb-0">
                <span><i class="bi bi-hourglass-split me-1"></i><?= $caeOpen ?> CAE abiertos</span>
                <span class="mx-2">·</span>
                <span><i class="bi bi-exclamation-triangle me-1"></i><?= $overdueTotal ?> atrasados</span>
                <span class="mx-2">·</span>
                <span><i class="bi bi-patch-check me-1"></i><?= (int) ($kpiApproved ?? 0) ?> aprobados</span>
            </p>
        </div>
        <div class="col-lg-4">
            <div class="cae-dash-hero-stat">
                <div class="cae-dash-hero-stat-value"><?= $completionRate ?>%</div>
                <div class="cae-dash-hero-stat-label">Tasa de aprobación CAE</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="cae-dash-kpi cae-dash-kpi-warning">
            <div class="cae-dash-kpi-icon"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="cae-dash-kpi-value"><?= $caeOpen ?></div>
                <div class="cae-dash-kpi-label">CAE abiertos</div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="cae-dash-kpi cae-dash-kpi-primary">
            <div class="cae-dash-kpi-icon"><i class="bi bi-search"></i></div>
            <div>
                <div class="cae-dash-kpi-value"><?= (int) ($kpiInReview ?? 0) ?></div>
                <div class="cae-dash-kpi-label">En revisión</div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="cae-dash-kpi cae-dash-kpi-danger">
            <div class="cae-dash-kpi-icon"><i class="bi bi-file-earmark-x"></i></div>
            <div>
                <div class="cae-dash-kpi-value"><?= (int) ($kpiPendingDocs ?? 0) ?></div>
                <div class="cae-dash-kpi-label">Pendiente documentación</div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="cae-dash-kpi cae-dash-kpi-success">
            <div class="cae-dash-kpi-icon"><i class="bi bi-patch-check"></i></div>
            <div>
                <div class="cae-dash-kpi-value"><?= (int) ($kpiApproved ?? 0) ?></div>
                <div class="cae-dash-kpi-label">CAE aprobados</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php foreach ((array) ($opsCards ?? []) as $card): ?>
        <?php
        $pending = (int) ($card['pending'] ?? 0);
        $overdue = (int) ($card['overdue'] ?? 0);
        $severityClass = $overdue > 0 ? 'is-danger' : ($pending > 0 ? 'is-warning' : 'is-ok');
        ?>
        <div class="col-md-6 col-xl-4">
            <a href="<?= htmlspecialchars((string) ($card['url'] ?? '#')) ?>" class="ops-module-card <?= $severityClass ?>">
                <div class="ops-module-card__head">
                    <div class="ops-module-card__icon"><i class="bi <?= htmlspecialchars((string) ($card['icon'] ?? 'bi-grid')) ?>"></i></div>
                    <div class="ops-module-card__title"><?= htmlspecialchars((string) ($card['title'] ?? 'Módulo')) ?></div>
                </div>
                <div class="ops-module-card__kpis">
                    <div><span class="ops-chip">Pendientes</span><strong><?= $pending ?></strong></div>
                    <div><span class="ops-chip ops-chip--danger">Atrasadas</span><strong><?= $overdue ?></strong></div>
                </div>
                <p class="ops-module-card__hint mb-0"><?= htmlspecialchars((string) ($card['hint'] ?? '')) ?></p>
                <div class="ops-module-card__cta">Abrir <i class="bi bi-arrow-right-short"></i></div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="cae-dash-chart-card">
            <div class="cae-dash-chart-head">
                <h3 class="cae-dash-chart-title mb-0">Estado CAE</h3>
                <span class="small text-muted">Distribución global</span>
            </div>
            <div id="dash-cae-chart" class="cae-dash-chart-host"
                 data-series='<?= $chartSeriesJson ?>'
                 data-labels='<?= $chartLabelsJson ?>'>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="cae-dash-chart-card">
            <div class="cae-dash-chart-head">
                <h3 class="cae-dash-chart-title mb-0">CAE por estado</h3>
                <span class="small text-muted">Solo estados con registros</span>
            </div>
            <div id="dash-cae-bar-chart" class="cae-dash-chart-host"
                 data-series='<?= $chartSeriesJson ?>'
                 data-labels='<?= $chartLabelsJson ?>'>
            </div>
        </div>
    </div>
</div>

<div class="cae-dash-chart-card mb-4">
    <div class="cae-dash-chart-head">
        <h3 class="cae-dash-chart-title mb-0">Actividad CAE</h3>
        <span class="small text-muted">Registros creados · últimos 14 días</span>
    </div>
    <div id="dash-activity-chart" class="cae-dash-chart-host"
         data-series='<?= json_encode(array_values(($chartActivity['series'] ?? [])), JSON_UNESCAPED_UNICODE) ?>'
         data-labels='<?= json_encode(array_values(($chartActivity['labels'] ?? [])), JSON_UNESCAPED_UNICODE) ?>'>
    </div>
</div>

<div class="subpanel">
    <div class="subpanel-h d-flex justify-content-between align-items-center">
        <span>CAE urgentes</span>
        <small class="text-muted"><?= $pendingTotal ?> pendientes operativos</small>
    </div>
    <div class="subpanel-b p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 cae-dash-table table-mobile-cards">
                <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Técnico / referencia</th>
                    <th>Detalle</th>
                    <th>Antigüedad</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($urgentItems)): ?>
                    <tr><td colspan="5" class="text-muted text-center py-4">No hay CAE urgentes por ahora.</td></tr>
                <?php else: ?>
                    <?php foreach ($urgentItems as $item): ?>
                        <?php
                        $age = (int) ($item['age_days'] ?? 0);
                        $ageBadge = $age >= 7 ? 'text-bg-danger' : ($age >= 3 ? 'text-bg-warning text-dark' : 'text-bg-secondary');
                        ?>
                        <tr>
                            <td data-label="Tipo"><?= htmlspecialchars((string) ($item['type'] ?? '')) ?></td>
                            <td data-label="Referencia"><?= htmlspecialchars((string) ($item['label'] ?? '')) ?></td>
                            <td data-label="Detalle" class="text-muted"><?= htmlspecialchars((string) ($item['detail'] ?? '')) ?></td>
                            <td data-label="Antigüedad"><span class="badge <?= $ageBadge ?>"><?= $age ?> días</span></td>
                            <td data-label="Acción" class="text-end">
                                <a href="<?= htmlspecialchars((string) ($item['url'] ?? '#')) ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
