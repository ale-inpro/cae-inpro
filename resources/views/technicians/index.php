<?php declare(strict_types=1);
$ab = htmlspecialchars($areaBaseUrl ?? '/cae-inpro/public/gestor');
$role = (string) ($area ?? 'gestor');
$items = $technicians ?? [];

$badgeClass = static function (string $status): string {
    return match ($status) {
        'approved' => 'text-bg-success',
        'in_review' => 'text-bg-warning',
        'rejected' => 'text-bg-danger',
        'pending_docs' => 'text-bg-secondary',
        default => 'text-bg-light text-dark',
    };
};

$label = static function (string $status): string {
    return match ($status) {
        'approved' => 'Aprobado',
        'in_review' => 'En revisión',
        'rejected' => 'Rechazado',
        'pending_docs' => 'Pendiente',
        default => ucfirst($status),
    };
};
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h4 mb-1">Gestión de Técnicos</h2>
        <p class="text-muted mb-0">Listado real de técnicos según tu rol.</p>
    </div>
    <?php if ($role === 'admin'): ?>
        <a class="btn btn-success" href="<?= $ab ?>/tecnicos/create">Nuevo técnico</a>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <table class="table table-hover align-middle mb-0 table-mobile-cards" data-datatable data-page-length="10" data-empty="No hay técnicos disponibles">
            <thead>
            <tr>
                <th>Nombre</th>
                <th>Categoría</th>
                <th>Ciudad</th>
                <th>Email</th>
                <th>Estado CAE</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $row): ?>
                <?php
                $id = (int) ($row['id'] ?? 0);
                $name = trim(((string) ($row['first_name'] ?? '')) . ' ' . ((string) ($row['last_name'] ?? '')));
                $status = (string) ($row['cae_status'] ?? 'pending_docs');
                ?>
                <tr>
                    <td data-label="Nombre"><?= htmlspecialchars($name) ?></td>
                    <td data-label="Categoría"><?= htmlspecialchars((string) ($row['professions'] ?? '-')) ?></td>
                    <td data-label="Ciudad"><?= htmlspecialchars((string) ($row['city'] ?? '-')) ?></td>
                    <td data-label="Email"><?= htmlspecialchars((string) ($row['email'] ?? '-')) ?></td>
                    <td data-label="Estado CAE"><span class="badge <?= $badgeClass($status) ?>"><?= htmlspecialchars($label($status)) ?></span></td>
                    <td data-label="Acciones" class="d-flex gap-1 actions-cell">
                        <a href="<?= $ab ?>/tecnicos/<?= $id ?>" class="btn btn-sm btn-outline-secondary" title="Abrir ficha">
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>

                        <?php if ($role === 'admin'): ?>
                            <a href="<?= $ab ?>/tecnicos/<?= $id ?>/edit?return_to=<?= urlencode($ab . '/tecnicos/' . $id . '#pane-info') ?>" class="btn btn-sm btn-outline-success" title="Editar">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            <form method="post" action="<?= $ab ?>/tecnicos/<?= $id ?>" data-confirm="¿Desactivar este técnico?">
                                <input type="hidden" name="_method" value="DELETE">
                                <button class="btn btn-sm btn-outline-danger" type="submit" title="Desactivar">
                                    <i class="bi bi-person-x"></i>
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