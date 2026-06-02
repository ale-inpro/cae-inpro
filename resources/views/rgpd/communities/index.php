<?php declare(strict_types=1);
$ab = htmlspecialchars($areaBaseUrl ?? '');
$items = $communities ?? [];
$templatesForDownload = $templatesForDownload ?? [];

$contractLabel = static function (string $st): string {
    return match ($st) {
        'active' => 'Activo',
        'expired' => 'Vencido',
        'pending' => 'Activo',
        default => 'No subido',
    };
};

?>
<div class="page-header mb-4">
    <div>
        <h1 class="h3 page-title mb-1">Comunidades · RGPD</h1>
        <p class="page-meta mb-0">Vecinos, consentimientos y encargo de tratamiento RGPD por comunidad</p>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <table class="table table-hover align-middle mb-0" data-datatable data-page-length="15" data-empty="No hay comunidades">
            <thead>
            <tr>
                <th>Comunidad</th>
                <th>Dirección</th>
                <th>Localidad</th>
                <th class="text-center">Vecinos</th>
                <th class="text-center">Pend.</th>
                <th>Contrato</th>
                <th class="text-end" style="width:1%">Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($items === []): ?>
                <tr class="table-empty-row">
                    <td colspan="7" class="text-muted text-center py-4">No hay comunidades disponibles.</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($items as $row): ?>
                <?php
                $cst = (string) ($row['contract_status'] ?? '');
                $cBadge = match ($cst) {
                    'active' => 'text-bg-success',
                    'expired' => 'text-bg-danger',
                    'pending' => 'text-bg-success',
                    default => 'text-bg-secondary',
                };
                ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars((string) ($row['name'] ?? '')) ?></strong>
                    </td>
                    <td class="small text-muted"><?= htmlspecialchars((string) ($row['address'] ?? '—')) ?></td>
                    <td><?= htmlspecialchars((string) ($row['city'] ?? '—')) ?></td>
                    <td class="text-center"><?= (int) ($row['residents_count'] ?? 0) ?></td>
                    <td class="text-center">
                        <?php $pend = (int) ($row['pending_signatures'] ?? 0); ?>
                        <?php if ($pend > 0): ?>
                            <span class="badge text-bg-warning"><?= $pend ?></span>
                        <?php else: ?>
                            <span class="text-muted">0</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?= $cBadge ?>"><?= htmlspecialchars($contractLabel($cst)) ?></span></td>
                    <td class="text-end text-nowrap">
                        <div class="table-actions actions-cell justify-content-end">
                            <button type="button"
                                    class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#rgpdCommunitySignedDocsModal"
                                    data-community-id="<?= (int) ($row['id'] ?? 0) ?>"
                                    data-community-name="<?= htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    title="Descargar documentos firmados">
                                <i class="bi bi-file-earmark-pdf"></i>
                            </button>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= $ab ?>/rgpd/comunidades/<?= (int) ($row['id'] ?? 0) ?>" title="Abrir ficha">
                                <i class="bi bi-box-arrow-up-right"></i>
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="rgpdCommunitySignedDocsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" id="rgpdCommunitySignedDocsForm" class="modal-content border-0 shadow-lg">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title h6 mb-0">Descargar firmados de comunidad</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body pt-3">
                <p class="small text-muted mb-3">
                    Comunidad: <strong id="rgpdCommunitySignedDocsName">—</strong>
                </p>

                <label class="form-label small mb-2">Tipos de documento (plantillas)</label>
                <div class="border rounded p-2" style="max-height:220px; overflow:auto;">
                    <?php foreach ($templatesForDownload as $tf): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="template_ids[]" value="<?= (int) $tf['id'] ?>" id="rgpd_c_tpl_<?= (int) $tf['id'] ?>">
                            <label class="form-check-label small" for="rgpd_c_tpl_<?= (int) $tf['id'] ?>">
                                <?= htmlspecialchars((string) $tf['name']) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" name="include_paper" id="rgpd_c_include_paper" value="1">
                    <label class="form-check-label small" for="rgpd_c_include_paper">
                        Incluir firmas en papel
                    </label>
                </div>

                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" name="include_contract" id="rgpd_c_include_contract" value="1">
                    <label class="form-check-label small" for="rgpd_c_include_contract">
                        Incluir contrato RGPD de la comunidad (si existe)
                    </label>
                </div>
            </div>
            <div class="modal-footer border-top-0 gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary btn-sm">Descargar PDF</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('rgpdCommunitySignedDocsModal');
    const form = document.getElementById('rgpdCommunitySignedDocsForm');
    const nameEl = document.getElementById('rgpdCommunitySignedDocsName');
    const includeContract = document.getElementById('rgpd_c_include_contract');
    if (includeContract) {
        includeContract.checked = false;
    }

    modal?.addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        if (!btn || !form) return;

        const cid = btn.getAttribute('data-community-id') || '0';
        const cname = btn.getAttribute('data-community-name') || '—';

        if (nameEl) nameEl.textContent = cname;

        form.action = '<?= $ab ?>/rgpd/comunidades/' + encodeURIComponent(cid) + '/documentos-firmados';

        form.querySelectorAll('input[name="template_ids[]"]').forEach(function (el) {
            el.checked = false;
        });

        const includePaper = document.getElementById('rgpd_c_include_paper');
        if (includePaper) includePaper.checked = false;
    });
})();
</script>
