<?php declare(strict_types=1);
$ab = htmlspecialchars($areaBaseUrl ?? '/cae-inpro/public/gestor');
$taxId = (string) ($taxId ?? '');
$lookup = $lookup ?? null;
$errors = $errors ?? [];
?>
<div class="page-header mb-4">
    <div>
        <h1 class="h3 page-title mb-1">Vincular técnico</h1>
        <p class="page-meta mb-0">Busca por NIF/CIF. Si no existe, podrás darlo de alta; si ya existe en el sistema, solicitarás asociación a tu cartera.</p>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="<?= $ab ?>/tecnicos">Volver al listado</a>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="post" action="<?= $ab ?>/tecnicos/vincular" class="row g-3 align-items-end">
            <div class="col-md-6">
                <label class="form-label">NIF / CIF</label>
                <input name="tax_id" class="form-control text-uppercase" value="<?= htmlspecialchars($taxId) ?>" required autocomplete="off">
                <?php if (!empty($errors['tax_id'])): ?><div class="text-danger small mt-1"><?= htmlspecialchars($errors['tax_id']) ?></div><?php endif; ?>
            </div>
            <div class="col-md-6">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Buscar</button>
            </div>
        </form>
    </div>
</div>

<?php if (is_array($lookup)): ?>
    <?php
        $state = (string) ($lookup['state'] ?? '');
        $tech = $lookup['tech'] ?? [];
        $tid = (int) ($tech['id'] ?? 0);
    ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <?php if ($state === 'not_found'): ?>
                <p class="mb-3">No hay ningún técnico con el identificador <code><?= htmlspecialchars((string) ($lookup['tax_id'] ?? $taxId)) ?></code>.</p>
                <a class="btn btn-success" href="<?= $ab ?>/tecnicos/nuevo?tax_id=<?= urlencode((string) ($lookup['tax_id'] ?? $taxId)) ?>">
                    <i class="bi bi-person-plus me-1"></i>Crear técnico nuevo
                </a>
            <?php elseif ($state === 'inactive_global'): ?>
                <div class="alert alert-warning mb-0">Este técnico existe pero está desactivado en el sistema. Contacta con el administrador.</div>
            <?php elseif ($state === 'in_portfolio'): ?>
                <p class="mb-3">El técnico <strong><?= htmlspecialchars((string) ($tech['display_name'] ?? '')) ?></strong> ya está en tu cartera.</p>
                <a class="btn btn-outline-primary" href="<?= $ab ?>/tecnicos/<?= $tid ?>">Ver ficha</a>
            <?php elseif ($state === 'pending_request'): ?>
                <div class="alert alert-info mb-0">
                    Ya enviaste una solicitud de asociación para <strong><?= htmlspecialchars((string) ($tech['display_name'] ?? '')) ?></strong>.
                    Está pendiente de aprobación por el administrador.
                </div>
            <?php elseif ($state === 'can_request'): ?>
                <p class="mb-2">El técnico <strong><?= htmlspecialchars((string) ($tech['display_name'] ?? '')) ?></strong>
                    (<code><?= htmlspecialchars((string) ($tech['tax_id'] ?? '')) ?></code>) ya existe en INPRO pero no está en tu cartera.</p>
                <form method="post" action="<?= $ab ?>/tecnicos/solicitar-asociacion" class="mt-3">
                    <input type="hidden" name="technician_id" value="<?= $tid ?>">
                    <label class="form-label">Notas para el administrador (opcional)</label>
                    <textarea name="gestor_notes" class="form-control mb-3" rows="2" maxlength="500"></textarea>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-send me-1"></i>Solicitar asociación a mi cartera
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>