<?php declare(strict_types=1);
$ab = htmlspecialchars($areaBaseUrl ?? '');
$residents = $residents ?? [];
$communities = $communities ?? [];
$filterCommunityId = (int) ($filterCommunityId ?? 0);
?>

<div class="page-header page-header--balanced page-header--premium mb-4">
    <div class="page-header-left">
        <h1 class="h3 page-title mb-1">Suministros · Vecinos</h1>
        <p class="page-meta mb-0">Contratos de suministro por vecino</p>
    </div>
    <div class="page-header-center"></div>
    <div class="page-header-right"></div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label small mb-1">Filtrar por comunidad</label>
                <select name="community_id" class="form-select form-select-sm">
                    <option value="0">Todas las comunidades</option>
                    <?php foreach ($communities as $c): ?>
                        <option value="<?= (int) ($c['id'] ?? 0) ?>" <?= $filterCommunityId === (int) ($c['id'] ?? 0) ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) ($c['name'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-primary btn-sm">Aplicar filtro</button>
                <?php if ($filterCommunityId > 0): ?>
                    <a href="<?= $ab ?>/suministros/vecinos" class="btn btn-outline-secondary btn-sm">Quitar filtro</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <table class="table table-hover align-middle mb-0" data-datatable data-page-length="15" data-empty="No hay vecinos">
            <thead>
            <tr>
                <th>Vecino</th>
                <th>Comunidad</th>
                <th class="text-center">Total</th>
                <th class="text-center">Activos</th>
                <th class="text-center">Próximos</th>
                <th class="text-center">Bajas</th>
                <th class="text-end">Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($residents === []): ?>
                <tr class="table-empty-row">
                    <td colspan="7" class="text-muted text-center py-4">No hay vecinos para este filtro.</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($residents as $r): ?>
                <tr>
                    <td><strong><?= htmlspecialchars((string) ($r['display_name'] ?? '')) ?></strong></td>
                    <td><?= htmlspecialchars((string) ($r['community_name'] ?? '—')) ?></td>
                    <td class="text-center"><?= (int) ($r['total_contracts_count'] ?? 0) ?></td>
                    <td class="text-center">
                        <?php $a = (int) ($r['active_count'] ?? 0); ?>
                        <?= $a > 0 ? '<span class="badge text-bg-success">' . $a . '</span>' : '<span class="text-muted">0</span>' ?>
                    </td>
                    <td class="text-center">
                        <?php $u = (int) ($r['upcoming_count'] ?? 0); ?>
                        <?= $u > 0 ? '<span class="badge text-bg-warning">' . $u . '</span>' : '<span class="text-muted">0</span>' ?>
                    </td>
                    <td class="text-center">
                        <?php $b = (int) ($r['inactive_count'] ?? 0); ?>
                        <?= $b > 0 ? '<span class="badge text-bg-danger">' . $b . '</span>' : '<span class="text-muted">0</span>' ?>
                    </td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary"
                           href="<?= $ab ?>/suministros/vecinos/<?= (int) ($r['id'] ?? 0) ?>"
                           title="Entrar">
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>