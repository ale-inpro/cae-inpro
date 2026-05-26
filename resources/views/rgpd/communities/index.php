<?php declare(strict_types=1);
$ab = htmlspecialchars($areaBaseUrl ?? '');
$items = $communities ?? [];

$contractLabel = static function (string $st): string {
    return match ($st) {
        'active' => 'Activo',
        'expired' => 'Vencido',
        'pending' => 'Activo',
        default => 'No subido',
    };
};

?>
<div class="page-header mb-4">
    <div>
        <h1 class="h3 page-title mb-1">Comunidades · RGPD</h1>
        <p class="page-meta mb-0">Vecinos, consentimientos y contrato marco por comunidad.</p>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <table class="table table-hover align-middle mb-0" data-datatable data-page-length="15" data-empty="No hay comunidades">
            <thead>
            <tr>
                <th>Comunidad</th>
                <th>Dirección</th>
                <th>Localidad</th>
                <th class="text-center">Vecinos</th>
                <th class="text-center">Pend.</th>
                <th>Contrato</th>
                <th class="text-end" style="width:1%">Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($items === []): ?>
                <tr class="table-empty-row">
                    <td colspan="7" class="text-muted text-center py-4">No hay comunidades disponibles.</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($items as $row): ?>
                <?php
                $cst = (string) ($row['contract_status'] ?? '');
                $cBadge = match ($cst) {
                    'active' => 'text-bg-success',
                    'expired' => 'text-bg-danger',
                    'pending' => 'text-bg-success',
                    default => 'text-bg-secondary',
                };
                ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars((string) ($row['name'] ?? '')) ?></strong>
                    </td>
                    <td class="small text-muted"><?= htmlspecialchars((string) ($row['address'] ?? '—')) ?></td>
                    <td><?= htmlspecialchars((string) ($row['city'] ?? '—')) ?></td>
                    <td class="text-center"><?= (int) ($row['residents_count'] ?? 0) ?></td>
                    <td class="text-center">
                        <?php $pend = (int) ($row['pending_signatures'] ?? 0); ?>
                        <?php if ($pend > 0): ?>
                            <span class="badge text-bg-warning"><?= $pend ?></span>
                        <?php else: ?>
                            <span class="text-muted">0</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?= $cBadge ?>"><?= htmlspecialchars($contractLabel($cst)) ?></span></td>
                    <td class="text-end text-nowrap">
                        <div class="table-actions actions-cell justify-content-end">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= $ab ?>/rgpd/comunidades/<?= (int) ($row['id'] ?? 0) ?>" title="Abrir ficha">
                                <i class="bi bi-box-arrow-up-right"></i>
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
