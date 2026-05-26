<?php declare(strict_types=1);
$ab = htmlspecialchars($areaBaseUrl ?? '');
$bu = htmlspecialchars($baseUrl ?? '');
$items = $communities ?? [];

$contractState = static function (array $row): array {
    $contractId = $row['contract_id'] ?? null;
    if ($contractId === null || $contractId === '') {
        return ['label' => 'No subido', 'badge' => 'text-bg-secondary', 'hasContract' => false];
    }
    $status = (string) ($row['status'] ?? '');
    $expiresAt = (string) ($row['expires_at'] ?? '');
    $isExpired = $status === 'expired'
        || ($expiresAt !== '' && strtotime($expiresAt) < strtotime('today'));
    if ($isExpired) {
        return ['label' => 'Vencido', 'badge' => 'text-bg-danger', 'hasContract' => true];
    }
    return ['label' => 'Activo', 'badge' => 'text-bg-success', 'hasContract' => true];
};

?>
<div class="page-header mb-4">
    <div>
        <h1 class="h3 page-title mb-1">Contratos RGPD</h1>
        <p class="page-meta mb-0">Contrato marco de encargo de tratamiento (PDF o registro en papel).</p>
    </div>
</div>

<div class="card border-0 shadow-sm rgpd-contracts-table">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-datatable data-page-length="12" data-empty="No hay comunidades">
            <thead>
                <tr>
                    <th>Comunidad</th>
                    <th>Contrato</th>
                    <th>Firma</th>
                    <th>Vencimiento</th>
                    <th class="text-end" style="width:1%">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($items === []): ?>
                <tr class="table-empty-row">
                    <td colspan="5" class="text-muted text-center py-4">No hay comunidades.</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($items as $row): ?>
                <?php
                $cid = (int) ($row['id'] ?? 0);
                $state = $contractState($row);
                $hasContract = $state['hasContract'];
                $signedAt = (string) ($row['signed_at'] ?? '');
                $expiresAt = (string) ($row['expires_at'] ?? '');
                $pdfPath = (string) ($row['storage_path'] ?? '');
                ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars((string) ($row['name'] ?? '')) ?></strong>
                        <?php if (!empty($row['city'])): ?>
                            <div class="small text-muted"><?= htmlspecialchars((string) $row['city']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= $state['badge'] ?>"><?= htmlspecialchars($state['label']) ?></span>
                    </td>
                    <td class="text-nowrap">
                        <?= $hasContract && $signedAt !== '' ? app_date($signedAt) : '' ?>
                    </td>
                    <td class="text-nowrap">
                        <?= $hasContract && $expiresAt !== '' ? app_date($expiresAt) : '' ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <div class="table-actions actions-cell justify-content-end">
                            <?php if ($hasContract && $pdfPath !== ''): ?>
                                <a class="btn btn-sm btn-outline-secondary"
                                   href="<?= $bu . htmlspecialchars($pdfPath) ?>"
                                   target="_blank"
                                   rel="noopener"
                                   title="Ver contrato PDF">
                                    <i class="bi bi-eye"></i>
                                </a>
                            <?php endif; ?>
                            <button type="button"
                                    class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#uploadModal<?= $cid ?>"
                                    title="Subir PDF">
                                <i class="bi bi-upload"></i>
                            </button>
                            <button type="button"
                                    class="btn btn-sm btn-outline-secondary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#paperModal<?= $cid ?>"
                                    title="Registrar en papel">
                                <i class="bi bi-journal-text"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<?php foreach ($items as $row): ?>
    <?php $cid = (int) ($row['id'] ?? 0); ?>
    <div class="modal fade" id="uploadModal<?= $cid ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="post" action="<?= $ab ?>/rgpd/contratos/<?= $cid ?>/upload" enctype="multipart/form-data" class="modal-content border-0 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0">
                    <h3 class="modal-title h6 mb-0">Subir contrato — <?= htmlspecialchars((string) ($row['name'] ?? '')) ?></h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body pt-3">
                    <div class="mb-3">
                        <label class="form-label small">PDF del contrato</label>
                        <input type="file" name="contract_pdf" class="form-control" accept="application/pdf" required>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small">Fecha firma</label>
                            <input type="date" name="signed_at" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Vencimiento</label>
                            <input type="date" name="expires_at" class="form-control form-control-sm" value="<?= date('Y-m-d', strtotime('+1 year')) ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="paperModal<?= $cid ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="post" action="<?= $ab ?>/rgpd/contratos/<?= $cid ?>/papel" class="modal-content border-0 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0">
                    <h3 class="modal-title h6 mb-0">Contrato en papel — <?= htmlspecialchars((string) ($row['name'] ?? '')) ?></h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body pt-3">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small">Fecha firma</label>
                            <input type="date" name="signed_at" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Vencimiento</label>
                            <input type="date" name="expires_at" class="form-control form-control-sm" value="<?= date('Y-m-d', strtotime('+1 year')) ?>">
                        </div>
                    </div>
                    <label class="form-label small">Notas</label>
                    <textarea name="paper_notes" class="form-control form-control-sm" rows="2" placeholder="Opcional"></textarea>
                </div>
                <div class="modal-footer border-top-0 gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm">Registrar</button>
                </div>
            </form>
        </div>
    </div>
<?php endforeach; ?>
