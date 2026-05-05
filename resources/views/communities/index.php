<?php declare(strict_types=1);
$ab = htmlspecialchars($areaBaseUrl ?? '/cae-inpro/public/gestor');
$role = (string) ($area ?? 'gestor');
$items = $communities ?? [];

$badgeClass = static function (string $status): string {
    return match ($status) {
        'completed' => 'text-bg-success',
        'in_progress' => 'text-bg-warning',
        'rejected' => 'text-bg-danger',
        'pending' => 'text-bg-secondary',
        default => 'text-bg-light text-dark',
    };
};

$label = static function (string $status): string {
    return match ($status) {
        'completed' => 'Completado',
        'in_progress' => 'En proceso',
        'rejected' => 'Rechazado',
        'pending' => 'Pendiente',
        default => ucfirst($status),
    };
};
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h4 mb-1">Gestión de Comunidades</h2>
        <p class="text-muted mb-0">Listado real de comunidades según tu rol.</p>
    </div>
    <?php if ($role === 'admin'): ?>
        <a class="btn btn-success" href="<?= $ab ?>/comunidades/create">Nueva comunidad</a>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <table class="table table-hover align-middle mb-0 table-mobile-cards" data-datatable data-page-length="10" data-empty="No hay comunidades disponibles">
            <thead>
            <tr>
                <th>Nombre</th>
                <th>Dirección</th>
                <th>Localidad</th>
                <th>Informe RL</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $row): ?>
                <?php
                $id = (int) ($row['id'] ?? 0);
                $status = (string) ($row['risk_status'] ?? 'pending');
                ?>
                <tr>
                    <td data-label="Nombre"><?= htmlspecialchars((string) ($row['name'] ?? '-')) ?></td>
                    <td data-label="Dirección"><?= htmlspecialchars((string) ($row['address'] ?? '-')) ?></td>
                    <td data-label="Localidad"><?= htmlspecialchars((string) ($row['city'] ?? '-')) ?></td>
                    <td data-label="Informe RL"><span class="badge <?= $badgeClass($status) ?>"><?= htmlspecialchars($label($status)) ?></span></td>
                    <td data-label="Acciones" class="d-flex gap-1 actions-cell">
                        <a href="<?= $ab ?>/comunidades/<?= $id ?>" class="btn btn-sm btn-outline-secondary" title="Abrir ficha">
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>

                        <?php if ($role === 'admin'): ?>
                            <a href="<?= $ab ?>/comunidades/<?= $id ?>/edit?return_to=<?= urlencode($ab . '/comunidades/' . $id . '#c-info') ?>" class="btn btn-sm btn-outline-success" title="Editar">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            <form method="post" action="<?= $ab ?>/comunidades/<?= $id ?>" data-confirm="¿Desactivar esta comunidad?">
                                <input type="hidden" name="_method" value="DELETE">
                                <button class="btn btn-sm btn-outline-danger" type="submit" title="Desactivar">
                                    <i class="bi bi-building-x"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>