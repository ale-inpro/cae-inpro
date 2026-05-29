<?php declare(strict_types=1);
$ab = htmlspecialchars($areaBaseUrl ?? '');
$t = $template ?? [];
$readOnly = !empty($readOnly);
$id = (int) ($t['id'] ?? 0);
$previewHtml = $previewHtml ?? (string) ($t['body_html'] ?? '');
?>
<div class="page-header page-header--balanced page-header--premium mb-3">
    <div class="page-header-left">
        <h1 class="h3 page-title mb-1"><?= htmlspecialchars((string) ($t['name'] ?? 'Plantilla')) ?></h1>
        <p class="page-meta mb-0"><?= $readOnly ? 'Plantilla de sistema (solo lectura)' : 'Plantilla personalizada' ?></p>
    </div>
    <div class="page-header-center"></div>
    <div class="page-header-right d-flex align-items-center gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="<?= $ab ?>/rgpd/plantillas" title="Volver"><i class="bi bi-arrow-left"></i></a>
        <?php if (!$readOnly): ?>
            <a class="btn btn-outline-success btn-sm" href="<?= $ab ?>/rgpd/plantillas/<?= $id ?>/editar" title="Editar">
                <i class="bi bi-pencil-square"></i>
            </a>
            <form method="post"
                  action="<?= $ab ?>/rgpd/plantillas/<?= $id ?>"
                  class="d-inline"
                  data-confirm="¿Eliminar esta plantilla? Esta acción no se puede deshacer.">
                <input type="hidden" name="_method" value="DELETE">
                <button type="submit" class="btn btn-outline-danger btn-sm" title="Eliminar">
                    <i class="bi bi-trash"></i>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="rgpd-doc-preview border rounded-3 p-4 bg-white mb-0">
    <?= $previewHtml ?>
</div>