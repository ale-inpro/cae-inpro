<?php declare(strict_types=1);
$ab = htmlspecialchars($areaBaseUrl ?? '/cae-inpro/public/admin');
$mode = (string) ($mode ?? 'create');
$isEdit = $mode === 'edit';
$isGestorCreate = $mode === 'gestor_create';
$t = $tech ?? [];
$taxIdLocked = $isGestorCreate && trim((string) ($t['tax_id'] ?? '')) !== '';
$errors = $errors ?? [];
$returnTo = (string) ($returnTo ?? ($ab . '/tecnicos'));
$action = match ($mode) {
    'edit' => $ab . '/tecnicos/' . (int) ($t['id'] ?? 0),
    'gestor_create' => $ab . '/tecnicos/nuevo',
    default => $ab . '/tecnicos',
};
?>
<div class="page-header">
    <div>
        <h1 class="h3 page-title mb-1"><?= $isEdit ? 'Editar técnico' : ($isGestorCreate ? 'Alta de técnico' : 'Nuevo técnico') ?></h1>
        <p class="page-meta mb-0"><?= $isGestorCreate ? 'El técnico se añadirá a tu cartera al guardar.' : 'Completa la información básica del técnico.' ?></p>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($returnTo) ?>">Volver</a>
</div>

<form method="post" action="<?= $action ?>">
    <?php if ($isEdit): ?>
        <input type="hidden" name="_method" value="PUT">
        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo) ?>">
    <?php endif; ?>

    <?php
        $entityType = (string) ($t['entity_type'] ?? 'individual');
        if (!in_array($entityType, ['individual', 'company'], true)) {
            $entityType = 'individual';
        }
    ?>
    <div class="row g-3">
        <div class="col-12">
            <label class="form-label d-block">Tipo de entidad *</label>
            <div class="d-flex flex-wrap gap-3">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="entity_type" id="entity_individual" value="individual" <?= $entityType === 'individual' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="entity_individual">Persona física / autónomo</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="entity_type" id="entity_company" value="company" <?= $entityType === 'company' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="entity_company">Persona jurídica (empresa)</label>
                </div>
            </div>
            <?php if (!empty($errors['entity_type'])): ?><div class="text-danger small mt-1"><?= htmlspecialchars($errors['entity_type']) ?></div><?php endif; ?>
        </div>
        <div class="col-md-8">
            <label class="form-label" id="label-display-name" data-label-individual="Nombre completo *" data-label-company="Razón social *">Nombre completo *</label>
            <input name="display_name" class="form-control" value="<?= htmlspecialchars((string) ($t['display_name'] ?? '')) ?>">
            <?php if (!empty($errors['display_name'])): ?><div class="text-danger small mt-1"><?= htmlspecialchars($errors['display_name']) ?></div><?php endif; ?>
        </div>
        <div class="col-md-4">
            <label class="form-label" id="label-tax-id" data-label-individual="NIF / DNI / NIE *" data-label-company="CIF *">NIF / DNI / NIE *</label>
            <input name="tax_id" class="form-control text-uppercase" value="<?= htmlspecialchars((string) ($t['tax_id'] ?? '')) ?>" autocomplete="off"<?= $taxIdLocked ? ' readonly' : '' ?>>
            <?php if (!empty($errors['tax_id'])): ?><div class="text-danger small mt-1"><?= htmlspecialchars($errors['tax_id']) ?></div><?php endif; ?>
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

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const radios = document.querySelectorAll('input[name="entity_type"]');
        const labelName = document.getElementById('label-display-name');
        const labelTax = document.getElementById('label-tax-id');
        if (!radios.length || !labelName || !labelTax) return;

        const sync = () => {
            const isCompany = document.getElementById('entity_company')?.checked;
            labelName.textContent = isCompany
                ? labelName.dataset.labelCompany
                : labelName.dataset.labelIndividual;
            labelTax.textContent = isCompany
                ? labelTax.dataset.labelCompany
                : labelTax.dataset.labelIndividual;
        };
        radios.forEach((r) => r.addEventListener('change', sync));
        sync();
    });
    </script>

    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-success" type="submit"><?= $isEdit ? 'Guardar cambios' : ($isGestorCreate ? 'Crear y añadir a mi cartera' : 'Crear técnico') ?></button>
        <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($returnTo) ?>">Cancelar</a>
    </div>
</form>