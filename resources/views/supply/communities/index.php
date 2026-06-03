<?php declare(strict_types=1);
$ab = htmlspecialchars($areaBaseUrl ?? '');
$items = $communities ?? [];
$money = static fn($v) => number_format((float) $v, 2, ',', '.') . ' €';
?>
<div class="page-header page-header--balanced page-header--premium mb-4">
    <div class="page-header-left">
        <h1 class="h3 page-title mb-1">Suministros · Comunidades</h1>
        <p class="page-meta mb-0">Contratos de suministro por comunidad</p>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <table class="table table-hover align-middle mb-0" data-datatable data-page-length="15" data-empty="No hay comunidades">
            <thead>
            <tr>
                <th>Comunidad</th>
                <th>Localidad</th>
                <th class="text-center">Total</th>
                <th class="text-center">Activos</th>
                <th class="text-center">Próximos</th>
                <th class="text-center">Bajas</th>
                <th class="text-end">Comisión</th>
                <th class="text-end" style="width:1%">Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($items === []): ?>
                <tr class="table-empty-row"><td colspan="8" class="text-muted text-center py-4">No hay comunidades.</td></tr>
            <?php endif; ?>
            <?php foreach ($items as $row): ?>
                <tr>
                    <td><strong><?= htmlspecialchars((string) ($row['name'] ?? '')) ?></strong></td>
                    <td><?= htmlspecialchars((string) ($row['city'] ?? '—')) ?></td>
                    <td class="text-center"><?= (int) ($row['total_contracts_count'] ?? 0) ?></td>
                    <td class="text-center">
                        <?php $a = (int) ($row['active_count'] ?? 0); ?>
                        <?= $a > 0 ? '<span class="badge text-bg-success">' . $a . '</span>' : '<span class="text-muted">0</span>' ?>
                    </td>
                    <td class="text-center">
                        <?php $u = (int) ($row['upcoming_count'] ?? 0); ?>
                        <?= $u > 0 ? '<span class="badge text-bg-warning">' . $u . '</span>' : '<span class="text-muted">0</span>' ?>
                    </td>
                    <td class="text-center">
                        <?php $b = (int) ($row['inactive_count'] ?? 0); ?>
                        <?= $b > 0 ? '<span class="badge text-bg-danger">' . $b . '</span>' : '<span class="text-muted">0</span>' ?>
                    </td>
                    <td class="text-end"><?= $money($row['monthly_admin_fee_total_eur'] ?? 0) ?></td>
                    <td class="text-end text-nowrap">
                        <div class="table-actions actions-cell justify-content-end">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= $ab ?>/suministros/comunidades/<?= (int) ($row['id'] ?? 0) ?>" title="Entrar"><i class="bi bi-box-arrow-up-right"></i></a>
                            <a class="btn btn-sm btn-outline-primary" href="<?= $ab ?>/suministros/vecinos?community_id=<?= (int) ($row['id'] ?? 0) ?>" title="Vecinos"><i class="bi bi-people"></i></a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>