<?php declare(strict_types=1); ?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="h4 mb-1"><?= htmlspecialchars((string) ($panelHeading ?? 'Dashboard')) ?></h2>
        <p class="text-muted mb-0"><?= htmlspecialchars((string) ($panelSubheading ?? 'Resumen operativo del sistema.')) ?></p>
    </div>
    <button class="btn btn-outline-success btn-sm"><i class="bi bi-download me-1"></i>Exportar</button>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon">C</div>
                <div>
                    <div class="text-muted small">Comunidades</div>
                    <div class="fs-4 fw-bold"><?= (int) ($kpiCommunities ?? 0) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon">T</div>
                <div>
                    <div class="text-muted small">Técnicos</div>
                    <div class="fs-4 fw-bold"><?= (int) ($kpiTechnicians ?? 0) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon">A</div>
                <div>
                    <div class="text-muted small">CAE aprobados</div>
                    <div class="fs-4 fw-bold"><?= (int) ($kpiApproved ?? 0) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h3 class="h6 mb-3">Estado CAE</h3>
        <div
            id="cae-status-chart"
            data-series='<?= json_encode(array_values($chartSeries ?? [0, 0, 0, 0, 0]), JSON_UNESCAPED_UNICODE) ?>'
            data-labels='<?= json_encode(array_values($chartLabels ?? ['Aprobado', 'En revisión', 'Pendiente', 'Pendiente docs.', 'Rechazado']), JSON_UNESCAPED_UNICODE) ?>'
        ></div>
    </div>
</div>