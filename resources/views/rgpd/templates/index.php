<?php declare(strict_types=1);
$ab = htmlspecialchars($areaBaseUrl ?? '');
$items = $templates ?? [];
$isAdmin = !empty($isAdmin);
?>
<div class="page-header mb-4">
    <div>
        <h1 class="h3 page-title mb-1">Plantillas RGPD</h1>
        <p class="page-meta mb-0">Las plantillas de sistema son de solo lectura. Variables: <code>[COMUNIDAD]</code>, <code>[EMAIL]</code>.</p>
    </div>
    <?php if ($isAdmin): ?>
        <a class="btn btn-success btn-sm" href="<?= $ab ?>/rgpd/plantillas/nueva">Nueva plantilla</a>
    <?php endif; ?>
</div>

<div class="row g-3">
    <?php foreach ($items as $tpl): ?>
        <?php
        $isSystem = ($tpl['kind'] ?? '') === 'system';
        $id = (int) ($tpl['id'] ?? 0);
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h2 class="h6 mb-0"><?= htmlspecialchars((string) ($tpl['name'] ?? '')) ?></h2>
                        <span class="badge <?= $isSystem ? 'text-bg-primary' : 'text-bg-secondary' ?>">
                            <?= $isSystem ? 'Sistema' : 'Personalizada' ?>
                        </span>
                    </div>
                    <p class="text-muted small flex-grow-1"><?= htmlspecialchars((string) ($tpl['description'] ?? 'Sin descripción')) ?></p>
                    <div class="d-flex gap-2">
                        <?php if ($isAdmin): ?>
                            <a class="btn btn-outline-primary btn-sm" href="<?= $ab ?>/rgpd/plantillas/<?= $id ?>">Ver</a>
                            <?php if (!$isSystem): ?>
                                <a class="btn btn-outline-secondary btn-sm" href="<?= $ab ?>/rgpd/plantillas/<?= $id ?>/editar">Editar</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
