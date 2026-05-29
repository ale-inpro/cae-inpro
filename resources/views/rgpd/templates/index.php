<?php declare(strict_types=1);
$ab = htmlspecialchars($areaBaseUrl ?? '');
$items = $templates ?? [];
$categories = $categories ?? [];
$isAdmin = !empty($isAdmin);
?>
<div class="page-header page-header--balanced page-header--premium mb-4">
    <div class="page-header-left">
        <h1 class="h3 page-title mb-1">Plantillas RGPD</h1>
        <p class="page-meta mb-0">Plantillas de sistema (solo lectura) y personalizadas (editar / eliminar).</p>
    </div>
    <div class="page-header-center"></div>
    <div class="page-header-right d-flex align-items-center gap-2">
        <?php if ($isAdmin): ?>
            <a class="btn btn-success btn-sm" href="<?= $ab ?>/rgpd/plantillas/nueva" title="Nueva plantilla">
                <i class="bi bi-plus-lg"></i>
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <?php foreach ($items as $tpl): ?>
        <?php
        $isSystem = ($tpl['kind'] ?? '') === 'system';
        $id = (int) ($tpl['id'] ?? 0);
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100 rgpd-template-list-card">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-2 gap-2">
                        <h2 class="h6 mb-0"><?= htmlspecialchars((string) ($tpl['name'] ?? '')) ?></h2>
                        <span class="badge <?= $isSystem ? 'text-bg-primary' : 'text-bg-secondary' ?>">
                            <?= $isSystem ? 'Sistema' : 'Personalizada' ?>
                        </span>
                    </div>
                    <p class="text-muted small flex-grow-1 mb-2"><?= htmlspecialchars((string) ($tpl['description'] ?? 'Sin descripción')) ?></p>
                    <?php if (!$isSystem && !empty($tpl['category'])): ?>
                        <?php
                        $catKey = (string) $tpl['category'];
                        $catLabel = $categories[$catKey] ?? $catKey;
                        ?>
                        <p class="small text-muted mb-2"><?= htmlspecialchars($catLabel) ?></p>
                    <?php endif; ?>
                    <div class="d-flex justify-content-end">
                        <div class="table-actions actions-cell justify-content-end">
                            <a class="btn btn-sm btn-outline-secondary"
                               href="<?= $ab ?>/rgpd/plantillas/<?= $id ?>"
                               title="Ver plantilla">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if ($isAdmin && !$isSystem): ?>
                                <a class="btn btn-sm btn-outline-success"
                                   href="<?= $ab ?>/rgpd/plantillas/<?= $id ?>/editar"
                                   title="Editar">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <form method="post"
                                      action="<?= $ab ?>/rgpd/plantillas/<?= $id ?>"
                                      class="d-inline"
                                      data-confirm="¿Eliminar esta plantilla? Esta acción no se puede deshacer.">
                                    <input type="hidden" name="_method" value="DELETE">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>