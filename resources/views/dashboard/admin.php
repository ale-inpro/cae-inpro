<?php declare(strict_types=1); ?>
<div class="d-flex align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="h4 mb-1"><?= htmlspecialchars((string) ($panelHeading ?? 'Centro Operativo Admin')) ?></h2>
        <p class="text-muted mb-0"><?= htmlspecialchars((string) ($panelSubheading ?? 'Control global y priorización de tareas.')) ?></p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="ops-stat-card h-100">
            <div class="ops-stat-label">Pendientes totales</div>
            <div class="ops-stat-value"><?= (int) (($opsTotals['pending'] ?? 0)) ?></div>
            <div class="ops-stat-sub">Trabajo pendiente acumulado</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="ops-stat-card h-100 ops-stat-card--danger">
            <div class="ops-stat-label">Atrasadas</div>
            <div class="ops-stat-value"><?= (int) (($opsTotals['overdue'] ?? 0)) ?></div>
            <div class="ops-stat-sub">Requieren atención prioritaria</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="ops-stat-card h-100">
            <div class="ops-stat-label">Comunidades</div>
            <div class="ops-stat-value"><?= (int) ($kpiCommunities ?? 0) ?></div>
            <div class="ops-stat-sub">Cartera activa</div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="ops-stat-card h-100">
            <div class="ops-stat-label">CAE aprobados</div>
            <div class="ops-stat-value"><?= (int) ($kpiApproved ?? 0) ?></div>
            <div class="ops-stat-sub">Sobre CAE vigentes</div>
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
        <div class="col-md-6 col-xl-3">
            <a href="<?= htmlspecialchars((string) ($card['url'] ?? '#')) ?>" class="ops-module-card <?= $severityClass ?>">
                <div class="ops-module-card__head">
                    <div class="ops-module-card__icon"><i class="bi <?= htmlspecialchars((string) ($card['icon'] ?? 'bi-grid')) ?>"></i></div>
                    <div class="ops-module-card__title"><?= htmlspecialchars((string) ($card['title'] ?? 'Módulo')) ?></div>
                </div>
                <div class="ops-module-card__kpis">
                    <div>
                        <span class="ops-chip">Pendientes</span>
                        <strong><?= $pending ?></strong>
                    </div>
                    <div>
                        <span class="ops-chip ops-chip--danger">Atrasadas</span>
                        <strong><?= $overdue ?></strong>
                    </div>
                </div>
                <p class="ops-module-card__hint mb-0"><?= htmlspecialchars((string) ($card['hint'] ?? '')) ?></p>
                <div class="ops-module-card__cta">Abrir módulo <i class="bi bi-arrow-right-short"></i></div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h3 class="h6 mb-3">Estado CAE (visión global)</h3>
        <div
            id="cae-status-chart"
            data-series='<?= json_encode(array_values($chartSeries ?? [0, 0, 0, 0, 0]), JSON_UNESCAPED_UNICODE) ?>'
            data-labels='<?= json_encode(array_values($chartLabels ?? ['Aprobado', 'En revisión', 'Pendiente', 'Pendiente docs.', 'Rechazado']), JSON_UNESCAPED_UNICODE) ?>'
        ></div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="h6 mb-0">Tareas urgentes</h3>
            <small class="text-muted">Ordenadas por prioridad y antigüedad</small>
        </div>

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 table-mobile-cards">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Referencia</th>
                        <th>Detalle</th>
                        <th>Antigüedad</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($urgentItems)): ?>
                        <tr><td colspan="5" class="text-muted">No hay tareas urgentes por ahora.</td></tr>
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