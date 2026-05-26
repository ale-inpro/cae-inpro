<?php declare(strict_types=1);
$ab = htmlspecialchars($areaBaseUrl ?? '');
$mode = (string) ($mode ?? 'create');
$isEdit = $mode === 'edit';
$t = $template ?? [];
$errors = $errors ?? [];
$id = (int) ($t['id'] ?? 0);
$action = $isEdit ? $ab . '/rgpd/plantillas/' . $id : $ab . '/rgpd/plantillas';
?>
<div class="page-header mb-4">
    <div>
        <h1 class="h3 page-title mb-1"><?= $isEdit ? 'Editar plantilla' : 'Nueva plantilla' ?></h1>
        <p class="page-meta mb-0">Use HTML básico. Placeholders: <code>[COMUNIDAD]</code>, <code>[EMAIL]</code>.</p>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="<?= $ab ?>/rgpd/plantillas">Volver</a>
</div>

<form method="post" action="<?= $action ?>">
    <?php if ($isEdit): ?>
        <input type="hidden" name="_method" value="PUT">
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label">Nombre</label>
            <input name="name" class="form-control" value="<?= htmlspecialchars((string) ($t['name'] ?? '')) ?>" required>
            <?php if (!empty($errors['name'])): ?><div class="text-danger small"><?= htmlspecialchars($errors['name']) ?></div><?php endif; ?>
        </div>
        <div class="col-md-4">
            <label class="form-label">Categoría</label>
            <input name="category" class="form-control" value="<?= htmlspecialchars((string) ($t['category'] ?? 'consentimiento')) ?>">
        </div>
        <div class="col-12">
            <label class="form-label">Descripción</label>
            <input name="description" class="form-control" value="<?= htmlspecialchars((string) ($t['description'] ?? '')) ?>">
        </div>
        <div class="col-12">
            <label class="form-label">Contenido HTML</label>
            <textarea name="body_html" class="form-control font-monospace" rows="14" required><?= htmlspecialchars((string) ($t['body_html'] ?? '')) ?></textarea>
            <?php if (!empty($errors['body_html'])): ?><div class="text-danger small"><?= htmlspecialchars($errors['body_html']) ?></div><?php endif; ?>
        </div>
        <div class="col-12">
            <div class="form-check">
                <?php
                $active = $t['is_active'] ?? true;
                $activeOn = !isset($t['is_active']) || in_array($active, [true, 't', '1', 1, 'true'], true);
                ?>
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="tpl_active" <?= $activeOn ? 'checked' : '' ?>>
                <label class="form-check-label" for="tpl_active">Activa</label>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <button type="submit" class="btn btn-success">Guardar</button>
    </div>
</form>
