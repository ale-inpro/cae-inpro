<?php declare(strict_types=1);
$ab = htmlspecialchars($areaBaseUrl ?? '');
$items = $companies ?? [];

$roleLabel = static fn(string $r): string => match ($r) {
    'marketer' => 'Comercializadora',
    'distributor' => 'Distribuidora',
    default => 'Mixta',
};
?>
<div class="page-header page-header--balanced page-header--premium mb-4">
    <div class="page-header-left">
        <h1 class="h3 page-title mb-1">Suministros · Empresas</h1>
        <p class="page-meta mb-0">Comercializadoras y distribuidoras para contratos de suministro.</p>
    </div>
    <div class="page-header-center"></div>
    <div class="page-header-right">
        <a class="btn btn-success btn-sm" href="<?= $ab ?>/suministros/empresas/nueva" title="Nueva empresa">
            <i class="bi bi-plus-lg"></i>
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <table class="table table-hover align-middle mb-0" data-datatable data-page-length="15" data-empty="No hay empresas">
            <thead>
            <tr>
                <th>Nombre</th>
                <th>Tipo</th>
                <th>Teléfono</th>
                <th>Email</th>
                <th>Web</th>
                <th class="text-center">Estado</th>
                <th class="text-end">Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($items === []): ?>
                <tr><td colspan="7" class="text-muted text-center py-4">No hay empresas registradas.</td></tr>
            <?php endif; ?>
            <?php foreach ($items as $co): ?>
                <?php $id = (int) ($co['id'] ?? 0); $active = !empty($co['is_active']); ?>
                <tr class="<?= $active ? '' : 'text-muted' ?>">
                    <td><strong><?= htmlspecialchars((string) ($co['name'] ?? '')) ?></strong></td>
                    <td><?= htmlspecialchars($roleLabel((string) ($co['company_role'] ?? 'mixed'))) ?></td>
                    <td><?= htmlspecialchars((string) ($co['phone'] ?? '—')) ?></td>
                    <td><?= htmlspecialchars((string) ($co['email'] ?? '—')) ?></td>
                    <td><?= htmlspecialchars((string) ($co['website'] ?? '—')) ?></td>
                    <td class="text-center">
                        <span class="badge <?= $active ? 'text-bg-success' : 'text-bg-secondary' ?>">
                            <?= $active ? 'Activa' : 'Inactiva' ?>
                        </span>
                    </td>
                    <td class="text-end text-nowrap">
                        <div class="table-actions actions-cell justify-content-end">
                            <a class="btn btn-sm btn-outline-success" href="<?= $ab ?>/suministros/empresas/<?= $id ?>/editar" title="Editar">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            <?php if ($active): ?>
                            <form method="post" action="<?= $ab ?>/suministros/empresas/<?= $id ?>/delete" class="d-inline"
                                  data-confirm="¿Desactivar esta empresa? No se borrarán los contratos ya vinculados.">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Desactivar">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>