<?php declare(strict_types=1);
$ab = htmlspecialchars($areaBaseUrl ?? '');
$mode = (string) ($mode ?? 'create');
$co = $company ?? [];
$errors = $errors ?? [];
$id = (int) ($co['id'] ?? 0);
$isEdit = $mode === 'edit';
$action = $isEdit ? $ab . '/suministros/empresas/' . $id . '/update' : $ab . '/suministros/empresas';
?>
<div class="page-header page-header--balanced page-header--premium mb-4">
    <div class="page-header-left">
        <h1 class="h3 page-title mb-1"><?= $isEdit ? 'Editar empresa' : 'Nueva empresa' ?></h1>
    </div>
    <div class="page-header-center"></div>
    <div class="page-header-right">
        <a class="btn btn-outline-secondary btn-sm" href="<?= $ab ?>/suministros/empresas" title="Volver">
            <i class="bi bi-arrow-left"></i>
        </a>
    </div>
</div>

<?php if ($errors !== []): ?>
    <div class="alert alert-warning">
        <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars((string) $e) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="post" action="<?= $action ?>" class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Nombre *</label>
                <input type="text" name="name" class="form-control" required
                       value="<?= htmlspecialchars((string) ($co['name'] ?? '')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Tipo *</label>
                <select name="company_role" class="form-select" required>
                    <?php
                    $roles = ['marketer' => 'Comercializadora', 'distributor' => 'Distribuidora', 'mixed' => 'Mixta'];
                    $sel = (string) ($co['company_role'] ?? 'mixed');
                    foreach ($roles as $k => $lbl):
                    ?>
                        <option value="<?= $k ?>" <?= $sel === $k ? 'selected' : '' ?>><?= htmlspecialchars($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Teléfono</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars((string) ($co['phone'] ?? '')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars((string) ($co['email'] ?? '')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Web</label>
                <input type="url" name="website" class="form-control" value="<?= htmlspecialchars((string) ($co['website'] ?? '')) ?>">
            </div>
            <?php if ($isEdit): ?>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                        <?= !empty($co['is_active']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_active">Empresa activa</label>
                </div>
            </div>
            <?php endif; ?>
            <div class="col-12 d-flex justify-content-end gap-2">
                <a href="<?= $ab ?>/suministros/empresas" class="btn btn-outline-secondary btn-sm">Cancelar</a>
                <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
            </div>
        </form>
    </div>
</div>