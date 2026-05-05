<?php declare(strict_types=1);
$ab = htmlspecialchars($areaBaseUrl ?? '/cae-inpro/public/admin');
$mode = (string) ($mode ?? 'create');
$isEdit = $mode === 'edit';
$c = $community ?? [];
$errors = $errors ?? [];
$returnTo = (string) ($returnTo ?? ($ab . '/comunidades'));
$action = $isEdit
    ? $ab . '/comunidades/' . (int) ($c['id'] ?? 0)
    : $ab . '/comunidades';
?>
<div class="page-header">
    <div>
        <h1 class="h3 page-title mb-1"><?= $isEdit ? 'Editar comunidad' : 'Nueva comunidad' ?></h1>
        <p class="page-meta mb-0">Completa la información básica de la comunidad.</p>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($returnTo) ?>">Volver</a>
</div>

<form method="post" action="<?= $action ?>">
    <?php if ($isEdit): ?>
        <input type="hidden" name="_method" value="PUT">
        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo) ?>">
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">ID empresa gestora *</label>
            <input name="manager_company_id" class="form-control" value="<?= htmlspecialchars((string) ($c['manager_company_id'] ?? '')) ?>">
            <?php if (!empty($errors['manager_company_id'])): ?><div class="text-danger small mt-1"><?= htmlspecialchars($errors['manager_company_id']) ?></div><?php endif; ?>
        </div>
        <div class="col-md-8">
            <label class="form-label">Nombre *</label>
            <input name="name" class="form-control" value="<?= htmlspecialchars((string) ($c['name'] ?? '')) ?>">
            <?php if (!empty($errors['name'])): ?><div class="text-danger small mt-1"><?= htmlspecialchars($errors['name']) ?></div><?php endif; ?>
        </div>
        <div class="col-md-4">
            <label class="form-label">CIF</label>
            <input name="cif" class="form-control" value="<?= htmlspecialchars((string) ($c['cif'] ?? '')) ?>">
            <?php if (!empty($errors['cif'])): ?><div class="text-danger small mt-1"><?= htmlspecialchars($errors['cif']) ?></div><?php endif; ?>
        </div>
        <div class="col-md-8">
            <label class="form-label">Dirección</label>
            <input name="address" class="form-control" value="<?= htmlspecialchars((string) ($c['address'] ?? '')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Ciudad</label>
            <input name="city" class="form-control" value="<?= htmlspecialchars((string) ($c['city'] ?? '')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Provincia</label>
            <input name="province" class="form-control" value="<?= htmlspecialchars((string) ($c['province'] ?? '')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Código postal</label>
            <input name="postal_code" class="form-control" value="<?= htmlspecialchars((string) ($c['postal_code'] ?? '')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Contacto</label>
            <input name="contact_name" class="form-control" value="<?= htmlspecialchars((string) ($c['contact_name'] ?? '')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Teléfono contacto</label>
            <input name="contact_phone" class="form-control" value="<?= htmlspecialchars((string) ($c['contact_phone'] ?? '')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Email contacto</label>
            <input name="contact_email" type="email" class="form-control" value="<?= htmlspecialchars((string) ($c['contact_email'] ?? '')) ?>">
            <?php if (!empty($errors['contact_email'])): ?><div class="text-danger small mt-1"><?= htmlspecialchars($errors['contact_email']) ?></div><?php endif; ?>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-success" type="submit"><?= $isEdit ? 'Guardar cambios' : 'Crear comunidad' ?></button>
        <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($returnTo) ?>">Cancelar</a>
    </div>
</form>