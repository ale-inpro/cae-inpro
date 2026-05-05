<?php declare(strict_types=1);
$ab = htmlspecialchars($areaBaseUrl ?? '/cae-inpro/public/admin');
$mode = (string) ($mode ?? 'create');
$isEdit = $mode === 'edit';
$t = $tech ?? [];
$errors = $errors ?? [];
$returnTo = (string) ($returnTo ?? ($ab . '/tecnicos'));
$action = $isEdit
    ? $ab . '/tecnicos/' . (int) ($t['id'] ?? 0)
    : $ab . '/tecnicos';
?>
<div class="page-header">
    <div>
        <h1 class="h3 page-title mb-1"><?= $isEdit ? 'Editar técnico' : 'Nuevo técnico' ?></h1>
        <p class="page-meta mb-0">Completa la información básica del técnico.</p>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($returnTo) ?>">Volver</a>
</div>

<form method="post" action="<?= $action ?>">
    <?php if ($isEdit): ?>
        <input type="hidden" name="_method" value="PUT">
        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo) ?>">
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Nombre *</label>
            <input name="first_name" class="form-control" value="<?= htmlspecialchars((string) ($t['first_name'] ?? '')) ?>">
            <?php if (!empty($errors['first_name'])): ?><div class="text-danger small mt-1"><?= htmlspecialchars($errors['first_name']) ?></div><?php endif; ?>
        </div>
        <div class="col-md-6">
            <label class="form-label">Apellidos *</label>
            <input name="last_name" class="form-control" value="<?= htmlspecialchars((string) ($t['last_name'] ?? '')) ?>">
            <?php if (!empty($errors['last_name'])): ?><div class="text-danger small mt-1"><?= htmlspecialchars($errors['last_name']) ?></div><?php endif; ?>
        </div>
        <div class="col-md-4">
            <label class="form-label">DNI/NIE *</label>
            <input name="dni_nie" class="form-control" value="<?= htmlspecialchars((string) ($t['dni_nie'] ?? '')) ?>">
            <?php if (!empty($errors['dni_nie'])): ?><div class="text-danger small mt-1"><?= htmlspecialchars($errors['dni_nie']) ?></div><?php endif; ?>
        </div>
        <div class="col-md-4">
            <label class="form-label">Email</label>
            <input name="email" type="email" class="form-control" value="<?= htmlspecialchars((string) ($t['email'] ?? '')) ?>">
            <?php if (!empty($errors['email'])): ?><div class="text-danger small mt-1"><?= htmlspecialchars($errors['email']) ?></div><?php endif; ?>
        </div>
        <div class="col-md-4">
            <label class="form-label">Teléfono</label>
            <input name="phone" class="form-control" value="<?= htmlspecialchars((string) ($t['phone'] ?? '')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Profesión *</label>
            <input name="professions" class="form-control" value="<?= htmlspecialchars((string) ($t['professions'] ?? '')) ?>">
            <?php if (!empty($errors['professions'])): ?><div class="text-danger small mt-1"><?= htmlspecialchars($errors['professions']) ?></div><?php endif; ?>
        </div>
        <div class="col-md-4">
            <label class="form-label">Ciudad</label>
            <input name="city" class="form-control" value="<?= htmlspecialchars((string) ($t['city'] ?? '')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Provincia</label>
            <input name="province" class="form-control" value="<?= htmlspecialchars((string) ($t['province'] ?? '')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Código postal</label>
            <input name="postal_code" class="form-control" value="<?= htmlspecialchars((string) ($t['postal_code'] ?? '')) ?>">
        </div>
        <div class="col-md-8">
            <label class="form-label">Dirección</label>
            <input name="address" class="form-control" value="<?= htmlspecialchars((string) ($t['address'] ?? '')) ?>">
        </div>
    </div>

    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-success" type="submit"><?= $isEdit ? 'Guardar cambios' : 'Crear técnico' ?></button>
        <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($returnTo) ?>">Cancelar</a>
    </div>
</form>