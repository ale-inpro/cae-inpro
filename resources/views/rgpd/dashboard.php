<?php declare(strict_types=1);
$ab = htmlspecialchars($areaBaseUrl ?? '');
$bu = htmlspecialchars($baseUrl ?? '');
$s = $stats ?? [];
$recent = $recent ?? [];
$chartSignatures = $chartSignatures ?? ['labels' => [], 'series' => []];
$chartContracts = $chartContracts ?? ['labels' => [], 'series' => []];
$chartActivity = $chartActivity ?? ['labels' => [], 'series' => []];
$completionRate = (int) ($completionRate ?? 0);

$pending = (int) ($s['pending_signatures'] ?? 0);
$signed = (int) ($s['signed_signatures'] ?? 0);
$noContract = (int) ($s['communities_without_contract'] ?? 0);
$campaigns30 = (int) ($s['campaigns_30d'] ?? 0);
$communitiesTotal = (int) ($s['communities_total'] ?? 0);
?>
<div class="page-header page-header--balanced page-header--premium mb-4">
    <div class="page-header-left">
        <h1 class="h3 page-title mb-1">RGPD · Resumen</h1>
        <p class="page-meta mb-0">Consentimientos de vecinos, encargos de tratamiento por comunidad y actividad reciente.</p>
    </div>
    <div class="page-header-right">
        <a class="btn btn-success btn-sm" href="<?= $ab ?>/rgpd/envio-masivo">
            <i class="bi bi-send me-1"></i> Envío masivo
        </a>
    </div>
</div>

<div class="rgpd-dash-hero mb-4">
    <div class="rgpd-dash-hero-glow"></div>
    <div class="row align-items-center g-3 position-relative">
        <div class="col-lg-8">
            <div class="rgpd-dash-hero-kicker">Panel operativo RGPD</div>
            <h2 class="rgpd-dash-hero-title mb-2"><?= $communitiesTotal ?> comunidades en cartera</h2>
            <p class="rgpd-dash-hero-meta mb-0">
                <span><i class="bi bi-hourglass-split me-1"></i><?= $pending ?> firmas pendientes</span>
                <span class="mx-2">·</span>
                <span><i class="bi bi-patch-check me-1"></i><?= $signed ?> firmadas</span>
                <span class="mx-2">·</span>
                <span><i class="bi bi-send me-1"></i><?= $campaigns30 ?> campañas (30 días)</span>
            </p>
        </div>
        <div class="col-lg-4">
            <div class="rgpd-dash-hero-stat">
                <div class="rgpd-dash-hero-stat-value"><?= $completionRate ?>%</div>
                <div class="rgpd-dash-hero-stat-label">Tasa de cumplimiento</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="rgpd-dash-kpi rgpd-dash-kpi-warning">
            <div class="rgpd-dash-kpi-icon"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="rgpd-dash-kpi-value"><?= $pending ?></div>
                <div class="rgpd-dash-kpi-label">Firmas pendientes</div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="rgpd-dash-kpi rgpd-dash-kpi-success">
            <div class="rgpd-dash-kpi-icon"><i class="bi bi-patch-check"></i></div>
            <div>
                <div class="rgpd-dash-kpi-value"><?= $signed ?></div>
                <div class="rgpd-dash-kpi-label">Firmadas por vecinos</div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="rgpd-dash-kpi rgpd-dash-kpi-danger">
            <div class="rgpd-dash-kpi-icon"><i class="bi bi-file-earmark-x"></i></div>
            <div>
                <div class="rgpd-dash-kpi-value"><?= $noContract ?></div>
                <div class="rgpd-dash-kpi-label">Comunidades sin encargo</div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="rgpd-dash-kpi rgpd-dash-kpi-info">
            <div class="rgpd-dash-kpi-icon"><i class="bi bi-megaphone"></i></div>
            <div>
                <div class="rgpd-dash-kpi-value"><?= $campaigns30 ?></div>
                <div class="rgpd-dash-kpi-label">Campañas (30 días)</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="rgpd-dash-chart-card h-100">
            <div class="rgpd-dash-chart-head">
                <h3 class="rgpd-dash-chart-title mb-0">Estado de solicitudes</h3>
                <span class="small text-muted">Distribución global</span>
            </div>
            <div id="rgpdDashSignaturesChart" class="rgpd-dash-chart-host"
                 data-series='<?= json_encode(array_values($chartSignatures['series']), JSON_UNESCAPED_UNICODE) ?>'
                 data-labels='<?= json_encode(array_values($chartSignatures['labels']), JSON_UNESCAPED_UNICODE) ?>'>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="rgpd-dash-chart-card h-100">
            <div class="rgpd-dash-chart-head">
                <h3 class="rgpd-dash-chart-title mb-0">Encargo de tratamiento</h3>
                <span class="small text-muted">Contrato RGPD comunidad ↔ INPRO</span>
            </div>
            <div id="rgpdDashContractsChart" class="rgpd-dash-chart-host"
                 data-series='<?= json_encode(array_values($chartContracts['series']), JSON_UNESCAPED_UNICODE) ?>'
                 data-labels='<?= json_encode(array_values($chartContracts['labels']), JSON_UNESCAPED_UNICODE) ?>'>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="rgpd-dash-chart-card h-100">
            <div class="rgpd-dash-chart-head">
                <h3 class="rgpd-dash-chart-title mb-0">Accesos rápidos</h3>
                <span class="small text-muted">Módulos RGPD</span>
            </div>
            <div class="rgpd-dash-quick-links">
                <a href="<?= $ab ?>/rgpd/comunidades" class="rgpd-dash-quick-link">
                    <i class="bi bi-buildings"></i><span>Comunidades</span><i class="bi bi-arrow-right-short ms-auto"></i>
                </a>
                <a href="<?= $ab ?>/rgpd/contratos" class="rgpd-dash-quick-link">
                    <i class="bi bi-file-earmark-text"></i><span>Contratos RGPD</span><i class="bi bi-arrow-right-short ms-auto"></i>
                </a>
                <a href="<?= $ab ?>/rgpd/envio-masivo" class="rgpd-dash-quick-link">
                    <i class="bi bi-send"></i><span>Envío masivo</span><i class="bi bi-arrow-right-short ms-auto"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="rgpd-dash-chart-card mb-4">
    <div class="rgpd-dash-chart-head">
        <h3 class="rgpd-dash-chart-title mb-0">Actividad de solicitudes</h3>
        <span class="small text-muted">Últimos 14 días</span>
    </div>
    <div id="rgpdDashActivityChart" class="rgpd-dash-chart-host"
         data-series='<?= json_encode(array_values($chartActivity['series']), JSON_UNESCAPED_UNICODE) ?>'
         data-labels='<?= json_encode(array_values($chartActivity['labels']), JSON_UNESCAPED_UNICODE) ?>'>
    </div>
</div>

<div class="subpanel">
    <div class="subpanel-h d-flex justify-content-between align-items-center">
        <span>Actividad reciente</span>
        <a href="<?= $ab ?>/rgpd/comunidades" class="btn btn-outline-secondary btn-sm">Ver comunidades</a>
    </div>
    <div class="subpanel-b p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 rgpd-dash-table">
                <thead>
                <tr>
                    <th>Comunidad</th>
                    <th>Vecino</th>
                    <th>Documento</th>
                    <th>Estado</th>
                    <th>Fecha</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($recent === []): ?>
                    <tr><td colspan="5" class="text-muted text-center py-4">Sin solicitudes todavía.</td></tr>
                <?php else: ?>
                    <?php foreach ($recent as $row): ?>
                        <?php
                        $st = (string) ($row['status'] ?? '');
                        $badge = match ($st) {
                            'signed' => 'rgpd-dash-badge rgpd-dash-badge-signed',
                            'pending' => 'rgpd-dash-badge rgpd-dash-badge-pending',
                            'paper' => 'rgpd-dash-badge rgpd-dash-badge-paper',
                            'cancelled' => 'rgpd-dash-badge rgpd-dash-badge-cancelled',
                            default => 'rgpd-dash-badge',
                        };
                        $label = match ($st) {
                            'signed' => 'Firmado',
                            'pending' => 'Pendiente',
                            'paper' => 'En papel',
                            'cancelled' => 'Cancelado',
                            default => $st,
                        };
                        ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($row['community_name'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($row['resident_name'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($row['template_name'] ?? '')) ?></td>
                            <td><span class="<?= $badge ?>"><?= htmlspecialchars($label) ?></span></td>
                            <td class="text-nowrap"><?= app_datetime((string) ($row['created_at'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
