<?php declare(strict_types=1);
$ab = htmlspecialchars($areaBaseUrl ?? '');
$s = $stats ?? [];
$recent = $recent ?? [];
?>
<div class="page-header mb-4">
    <div>
        <h1 class="h3 page-title mb-1">RGPD · Resumen</h1>
        <p class="page-meta mb-0">Consentimientos vecinos, contratos marco y envíos masivos.</p>
    </div>
    <a class="btn btn-success btn-sm" href="<?= $ab ?>/rgpd/envio-masivo"><i class="bi bi-send me-1"></i> Envío masivo</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Firmas pendientes</div>
                <div class="display-6 fw-semibold text-warning"><?= (int) ($s['pending_signatures'] ?? 0) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Comunidades sin contrato activo</div>
                <div class="display-6 fw-semibold"><?= (int) ($s['communities_without_contract'] ?? 0) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Campañas (30 días)</div>
                <div class="display-6 fw-semibold text-success"><?= (int) ($s['campaigns_30d'] ?? 0) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pt-3">
        <h2 class="h6 mb-0">Actividad reciente</h2>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
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
                        'signed' => 'text-bg-success',
                        'pending' => 'text-bg-warning',
                        'paper' => 'text-bg-info',
                        default => 'text-bg-secondary',
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
                        <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($label) ?></span></td>
                        <td><?= app_datetime((string) ($row['created_at'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
