<?php declare(strict_types=1);
$ab = htmlspecialchars($areaBaseUrl ?? '');
$t = $template ?? [];
$readOnly = !empty($readOnly);
$id = (int) ($t['id'] ?? 0);
?>
<div class="page-header mb-4">
    <div>
        <h1 class="h3 page-title mb-1"><?= htmlspecialchars((string) ($t['name'] ?? 'Plantilla')) ?></h1>
        <p class="page-meta mb-0"><?= $readOnly ? 'Plantilla de sistema (solo lectura)' : 'Plantilla personalizada' ?></p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="<?= $ab ?>/rgpd/plantillas">Volver</a>
        <?php if (!$readOnly): ?>
            <a class="btn btn-primary btn-sm" href="<?= $ab ?>/rgpd/plantillas/<?= $id ?>/editar">Editar</a>
        <?php endif; ?>
    </div>
</div>

<div class="rgpd-doc-preview border rounded-3 p-4 bg-white mb-3">
    <?= (string) ($t['body_html'] ?? '') ?>
</div>

<?php if (!$readOnly): ?>
<form method="post" action="<?= $ab ?>/rgpd/plantillas/<?= $id ?>" onsubmit="return confirm('¿Eliminar esta plantilla?');">
    <input type="hidden" name="_method" value="DELETE">
    <button type="submit" class="btn btn-outline-danger btn-sm">Eliminar plantilla</button>
</form>
<?php endif; ?>
