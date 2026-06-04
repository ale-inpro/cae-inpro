<?php declare(strict_types=1);
$ab = htmlspecialchars($areaBaseUrl ?? '');
$items = $templates ?? [];
$categories = $categories ?? [];
$isAdmin = !empty($isAdmin);
$communities = $communities ?? [];
$blankResidentsByCommunityTemplate = $blankResidentsByCommunityTemplate ?? [];

?>
<div class="page-header page-header--balanced page-header--premium mb-4">
    <div class="page-header-left">
        <h1 class="h3 page-title mb-1">Plantillas RGPD</h1>
        <p class="page-meta mb-0">Plantillas de sistema (solo lectura) y personalizadas (editar / eliminar).</p>
    </div>
    <div class="page-header-center"></div>
    <div class="page-header-right d-flex align-items-center gap-2">
        <?php if ($isAdmin): ?>
            <a class="btn btn-success btn-sm" href="<?= $ab ?>/rgpd/plantillas/nueva" title="Nueva plantilla">
                <i class="bi bi-plus-lg"></i>
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <?php foreach ($items as $tpl): ?>
        <?php
        $isSystem = ($tpl['kind'] ?? '') === 'system';
        $id = (int) ($tpl['id'] ?? 0);
        $isActive = in_array($tpl['is_active'] ?? true, [true, 't', '1', 1, 'true'], true);
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100 rgpd-template-list-card<?= !$isActive ? ' opacity-75' : '' ?>">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-2 gap-2">
                        <h2 class="h6 mb-0"><?= htmlspecialchars((string) ($tpl['name'] ?? '')) ?></h2>
                        <div class="d-flex flex-wrap gap-1 justify-content-end">
                            <span class="badge <?= $isSystem ? 'text-bg-primary' : 'text-bg-secondary' ?>">
                                <?= $isSystem ? 'Sistema' : 'Personalizada' ?>
                            </span>
                            <?php if (!$isActive): ?>
                                <span class="badge text-bg-warning text-dark">Desactivada</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <p class="text-muted small flex-grow-1 mb-2"><?= htmlspecialchars((string) ($tpl['description'] ?? 'Sin descripción')) ?></p>
                    <?php if (!$isSystem && !empty($tpl['category'])): ?>
                        <?php
                        $catKey = (string) $tpl['category'];
                        $catLabel = $categories[$catKey] ?? $catKey;
                        ?>
                        <p class="small text-muted mb-2"><?= htmlspecialchars($catLabel) ?></p>
                    <?php endif; ?>
                    <div class="d-flex justify-content-end">
                        <div class="table-actions actions-cell justify-content-end">
                            <?php if ($isActive): ?>
                            <button type="button"
                                    class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#rgpdTemplateBlankModal"
                                    data-template-id="<?= $id ?>"
                                    data-template-name="<?= htmlspecialchars((string) ($tpl['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    title="Descargar plantilla en blanco">
                                <i class="bi bi-download"></i>
                            </button>
                            <?php endif; ?>
                            <a class="btn btn-sm btn-outline-secondary"
                               href="<?= $ab ?>/rgpd/plantillas/<?= $id ?>"
                               title="Ver plantilla">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if ($isAdmin && !$isSystem): ?>
                                <a class="btn btn-sm btn-outline-success"
                                   href="<?= $ab ?>/rgpd/plantillas/<?= $id ?>/editar"
                                   title="Editar">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <form method="post"
                                      action="<?= $ab ?>/rgpd/plantillas/<?= $id ?>"
                                      class="d-inline"
                                      data-confirm="¿Eliminar esta plantilla? Esta acción no se puede deshacer.">
                                    <input type="hidden" name="_method" value="DELETE">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="modal fade" id="rgpdTemplateBlankModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="post" id="rgpdTemplateBlankForm" class="modal-content border-0 shadow-lg">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title h6 mb-0">Descargar plantilla</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-3">
                <p class="small text-muted mb-2">Plantilla: <strong id="rgpdTplBlankName">—</strong></p>
                <label class="form-label small">Comunidad</label>
                <select class="form-select form-select-sm mb-3" name="community_id" id="rgpdTplBlankCommunity" required>
                    <option value="">— Seleccionar —</option>
                    <?php foreach ($communities as $c): ?>
                        <option value="<?= (int) ($c['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($c['name'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="mb-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="rgpdTplBlankAll">Todos</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="rgpdTplBlankNone">Ninguno</button>
                </div>
                <div class="border rounded p-2" id="rgpdTplBlankResidents" style="max-height:280px;overflow:auto;"></div>
            </div>
            <div class="modal-footer border-top-0 gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm">Descargar ZIP</button>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    const map = <?= json_encode($blankResidentsByCommunityTemplate, JSON_UNESCAPED_UNICODE) ?>;
    const modal = document.getElementById('rgpdTemplateBlankModal');
    const form = document.getElementById('rgpdTemplateBlankForm');
    const nameEl = document.getElementById('rgpdTplBlankName');
    const commSel = document.getElementById('rgpdTplBlankCommunity');
    const list = document.getElementById('rgpdTplBlankResidents');
    let templateId = '0';

    function renderResidents() {
        const cid = commSel.value;
        list.innerHTML = '';
        const residents = (map[cid] && map[cid][templateId]) ? map[cid][templateId]
            : (map[cid] && map[cid][parseInt(templateId, 10)]) ? map[cid][parseInt(templateId, 10)] : [];
        if (!cid) return;
        if (!residents.length) {
            list.innerHTML = '<p class="text-muted small mb-0">No hay vecinos pendientes.</p>';
            return;
        }
        residents.forEach(function (r) {
            const id = r.id;
            const label = r.resident_name || ('Vecino ' + id);
            const div = document.createElement('div');
            div.className = 'form-check';
            div.innerHTML =
                '<input class="form-check-input" type="checkbox" name="resident_ids[]" value="' + id + '" checked>' +
                '<label class="form-check-label small">' + label + '</label>';
            list.appendChild(div);
        });
    }

    modal?.addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        if (!btn || !form) return;
        templateId = btn.getAttribute('data-template-id') || '0';
        if (nameEl) nameEl.textContent = btn.getAttribute('data-template-name') || '—';
        form.action = '<?= $ab ?>/rgpd/plantillas/' + encodeURIComponent(templateId) + '/descargar-en-blanco';
        commSel.value = '';
        list.innerHTML = '';
    });
    commSel?.addEventListener('change', renderResidents);
    document.getElementById('rgpdTplBlankAll')?.addEventListener('click', function () {
        list.querySelectorAll('input[type=checkbox]').forEach(function (el) { el.checked = true; });
    });
    document.getElementById('rgpdTplBlankNone')?.addEventListener('click', function () {
        list.querySelectorAll('input[type=checkbox]').forEach(function (el) { el.checked = false; });
    });
})();
</script>