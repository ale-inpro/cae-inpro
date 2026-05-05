<?php declare(strict_types=1);

$ab = $areaBaseUrl ?? '/cae-inpro/public/admin';
$t = $tech ?? [];
$current = $currentCae ?? null;
$isValid = (bool) ($isCurrentValid ?? false);
$doc = $currentCaeDoc ?? null;

$techId = (int) ($t['id'] ?? 0);
$techName = trim(((string) ($t['first_name'] ?? '')) . ' ' . ((string) ($t['last_name'] ?? '')));
$returnToForm = $ab . '/tecnicos/' . $techId . '/cae';
$returnToTech = $ab . '/tecnicos/' . $techId . '#pane-hist';

$statusLabel = static function (string $status): string {
    return match ($status) {
        'approved' => 'Aprobado',
        'in_review' => 'En revisión',
        'pending_docs' => 'Pendiente',
        'rejected' => 'Rechazado',
        'expired' => 'Caducado',
        default => ucfirst($status),
    };
};

$statusBadge = static function (string $status): string {
    return match ($status) {
        'approved' => 'text-bg-success',
        'in_review' => 'text-bg-warning',
        'pending_docs' => 'text-bg-secondary',
        'rejected' => 'text-bg-danger',
        'expired' => 'text-bg-dark',
        default => 'text-bg-light text-dark',
    };
};
?>
<div class="panel-identity mb-3">
    <div class="panel-identity-icon"><i class="bi bi-file-earmark-medical-fill"></i></div>
    <div>
        <p class="panel-identity-kicker mb-1">Gestión CAE · panel operativo</p>
        <h2 class="panel-identity-title mb-0">Revisión de CAE vigente</h2>
    </div>
</div>

<div class="page-header page-header--balanced page-header--premium mb-3">
    <div class="page-header-left">
        <h1 class="h3 page-title mb-1"><?= htmlspecialchars($techName !== '' ? $techName : 'Técnico') ?></h1>
        <p class="page-meta mb-0">
            <?= htmlspecialchars((string) ($t['professions'] ?? '-')) ?> ·
            <?= htmlspecialchars((string) ($t['city'] ?? '-')) ?>
            <?php if ($current): ?>
                · <span class="badge <?= $statusBadge((string) $current['status']) ?>">
                    <?= htmlspecialchars($statusLabel((string) $current['status'])) ?>
                </span>
                <span class="badge <?= $isValid ? 'text-bg-success' : 'text-bg-warning' ?>">
                    <?= $isValid ? 'Vigente' : 'Caducado' ?>
                </span>
            <?php else: ?>
                · <span class="badge text-bg-secondary">Sin CAE actual</span>
            <?php endif; ?>
        </p>
    </div>
    <div class="page-header-center"></div>
    <div class="page-header-right d-flex align-items-center gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($returnToTech) ?>" title="Volver">
            <i class="bi bi-arrow-left"></i>
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-12">
        <div class="subpanel">
            <div class="subpanel-h">Resumen del CAE actual</div>
            <div class="subpanel-b">
                <?php if ($current): ?>
                    <div class="row g-2 small">
                        <div class="col-md-3"><strong>Estado:</strong> <?= htmlspecialchars($statusLabel((string) ($current['status'] ?? ''))) ?></div>
                        <div class="col-md-3"><strong>Válido desde:</strong> <?= htmlspecialchars((string) ($current['valid_from'] ?? '-')) ?></div>
                        <div class="col-md-3"><strong>Válido hasta:</strong> <?= htmlspecialchars((string) ($current['valid_until'] ?? '-')) ?></div>
                        <div class="col-md-3"><strong>Situación:</strong> <?= $isValid ? 'Vigente' : 'Caducado' ?></div>
                    </div>
                    <?php if (!empty($current['notes'])): ?>
                        <p class="text-muted small mb-0 mt-2"><?= htmlspecialchars((string) $current['notes']) ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-muted mb-0">No hay revisión CAE actual. Crea una nueva para empezar.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="subpanel">
            <div class="subpanel-h">Gestión de CAE vigente</div>
            <div class="subpanel-b">
                <form id="cae-operation-form" method="post" action="<?= htmlspecialchars($ab) ?>/tecnicos/<?= $techId ?>/cae" class="row g-3">
                    <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnToForm) ?>">

                    <div class="col-md-6">
                        <label class="form-label">Estado</label>
                        <select name="status" class="form-select" required>
                            <option value="pending_docs">Pendiente</option>
                            <option value="in_review" <?= (($current['status'] ?? '') === 'in_review') ? 'selected' : '' ?>>En revisión</option>
                            <option value="approved" <?= (($current['status'] ?? '') === 'approved') ? 'selected' : '' ?>>Aprobado</option>
                            <option value="rejected" <?= (($current['status'] ?? '') === 'rejected') ? 'selected' : '' ?>>Rechazado</option>
                            <option value="expired" <?= (($current['status'] ?? '') === 'expired') ? 'selected' : '' ?>>Caducado</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Válido desde</label>
                        <input type="date" name="valid_from" id="valid_from" class="form-control" value="<?= htmlspecialchars((string) ($current['valid_from'] ?? date('Y-m-d'))) ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Válido hasta</label>
                        <input type="date" name="valid_until" id="valid_until" class="form-control" value="<?= htmlspecialchars((string) ($current['valid_until'] ?? '')) ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Notas</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Observaciones"><?= htmlspecialchars((string) ($current['notes'] ?? '')) ?></textarea>
                    </div>

                    <div class="col-12 d-flex gap-2">
                    <button type="submit" id="save-cae-btn" class="btn btn-success">
                        <i class="bi bi-check2-circle me-1"></i>Guardar
                    </button>
                        <a href="<?= htmlspecialchars($returnToTech) ?>" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="subpanel cae-file-panel">
            <div class="subpanel-h d-flex align-items-center justify-content-between">
                <span>Archivo CAE actual</span>
                <span class="badge text-bg-light text-dark">1 archivo</span>
            </div>
            <div class="subpanel-b">
                <?php if ($current): ?>
                    <form method="post" enctype="multipart/form-data" action="<?= htmlspecialchars($ab) ?>/cae/<?= (int) ($current['id'] ?? 0) ?>/documentos" class="cae-upload-form mb-3">
                        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnToForm) ?>">
                        <input type="hidden" name="upload_mode" value="cae_main">
                        <label class="form-label fw-semibold">Subir o sustituir archivo</label>
                        <input type="file" name="document_file" class="form-control mb-2" required>
                        <button class="btn btn-success w-100" type="submit">
                            <i class="bi bi-upload me-1"></i>
                            <?= $doc ? 'Sustituir archivo CAE' : 'Subir archivo CAE' ?>
                        </button>
                    </form>

                    <?php if ($doc): ?>
                        <div class="cae-file-card">
                            <div class="cae-file-card__icon"><i class="bi bi-file-earmark-text"></i></div>
                            <div class="cae-file-card__body">
                                <p class="cae-file-card__name mb-1"><?= htmlspecialchars((string) ($doc['original_filename'] ?? '-')) ?></p>
                                <p class="cae-file-card__meta mb-2">Subido: <?= htmlspecialchars((string) ($doc['uploaded_at'] ?? '-')) ?></p>
                                <div class="d-flex gap-2">
                                    <?php if (!empty($doc['storage_path'])): ?>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-secondary"
                                            data-file-preview
                                            data-file-preview-url="<?= htmlspecialchars((string) (($baseUrl ?? '') . ($doc['storage_path'] ?? ''))) ?>"
                                            data-file-preview-name="<?= htmlspecialchars((string) ($doc['original_filename'] ?? 'Documento CAE')) ?>"
                                            title="Ver"
                                        ><i class="bi bi-eye"></i></button>
                                    <?php endif; ?>
                                    <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($ab) ?>/cae/documentos/<?= (int) ($doc['id'] ?? 0) ?>/download" title="Descargar"><i class="bi bi-download"></i></a>
                                    <form method="post" action="<?= htmlspecialchars($ab) ?>/cae/documentos/<?= (int) ($doc['id'] ?? 0) ?>" data-confirm="¿Quitar archivo CAE actual?" class="m-0">
                                        <input type="hidden" name="_method" value="DELETE">
                                        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnToForm) ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit" title="Quitar"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light border small mb-0">No hay archivo CAE subido todavia para la revision actual.</div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-light border small mb-0">Crea primero una revision CAE para habilitar la subida de su archivo.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('cae-operation-form');
    const saveBtn = document.getElementById('save-cae-btn');
    const statusEl = form.querySelector('select[name="status"]');
    const fromEl = form.querySelector('input[name="valid_from"]');
    const untilEl = form.querySelector('input[name="valid_until"]');
    const notesEl = form.querySelector('textarea[name="notes"]');

    const hasCurrent = <?= $current ? 'true' : 'false' ?>;
    const isCurrentValid = <?= $isValid ? 'true' : 'false' ?>;

    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    const todayStr = `${yyyy}-${mm}-${dd}`;

    const plus3 = new Date(today);
    plus3.setMonth(plus3.getMonth() + 3);
    const yyyy2 = plus3.getFullYear();
    const mm2 = String(plus3.getMonth() + 1).padStart(2, '0');
    const dd2 = String(plus3.getDate()).padStart(2, '0');
    const plus3Str = `${yyyy2}-${mm2}-${dd2}`;

    const createMode = !hasCurrent || !isCurrentValid;

    // Si no hay vigente, valores por defecto para nueva creación
    if (createMode) {
        if (!fromEl.value) fromEl.value = todayStr;
        if (!untilEl.value) untilEl.value = plus3Str;
        notesEl.value = '';
    }

    // Siempre editables (como pediste)
    fromEl.disabled = false;
    untilEl.disabled = false;
    fromEl.required = true;
    untilEl.required = true;

    // Captura estado inicial para desactivar Guardar si no hay cambios
    const initial = JSON.stringify({
        status: statusEl.value,
        valid_from: fromEl.value,
        valid_until: untilEl.value,
        notes: notesEl.value
    });

    const syncDirty = () => {
        const current = JSON.stringify({
            status: statusEl.value,
            valid_from: fromEl.value,
            valid_until: untilEl.value,
            notes: notesEl.value
        });
        saveBtn.disabled = (initial === current);
    };

    statusEl.addEventListener('change', syncDirty);
    fromEl.addEventListener('change', syncDirty);
    untilEl.addEventListener('change', syncDirty);
    notesEl.addEventListener('input', syncDirty);

    syncDirty();
})();
</script>