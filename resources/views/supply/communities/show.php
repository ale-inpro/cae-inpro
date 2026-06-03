<?php declare(strict_types=1);

$ab = htmlspecialchars($areaBaseUrl ?? '');
$community = $community ?? [];
$contracts = $contracts ?? [];
$companies = $companies ?? [];
$formErrors = $formErrors ?? [];
$old = $old ?? [];
$activeTab = $activeTab ?? 'suministros';

$typeLabel = static function (string $t): string {
    return match ($t) {
        'electricity' => 'Electricidad',
        'gas' => 'Gas',
        'water' => 'Agua',
        'telecom' => 'Telecom',
        default => 'Otro',
    };
};

$statusMeta = static function (string $s): array {
    return match ($s) {
        'active' => ['Activo', 'text-bg-success'],
        'pending_renewal' => ['Próxima renovación', 'text-bg-warning'],
        'expired' => ['Vencido', 'text-bg-danger'],
        'cancelled' => ['Cancelado', 'text-bg-secondary'],
        default => ['Borrador', 'text-bg-secondary'],
    };
};

$activeSuministros = $activeTab === 'suministros';
$activeNuevo = $activeTab === 'nuevo';
?>

<div class="page-header page-header--balanced page-header--premium mb-4">
    <div class="page-header-left">
        <h1 class="h3 page-title mb-1">Suministros · <?= htmlspecialchars((string) ($community['name'] ?? 'Comunidad')) ?></h1>
        <p class="page-meta mb-0">
            <?= htmlspecialchars((string) ($community['address'] ?? '—')) ?> · <?= htmlspecialchars((string) ($community['city'] ?? '—')) ?>
        </p>
    </div>
    <div class="page-header-center"></div>
    <div class="page-header-right">
        <a class="btn btn-outline-secondary btn-sm" href="<?= $ab ?>/suministros/comunidades" title="Volver">
            <i class="bi bi-arrow-left"></i>
        </a>
    </div>
</div>

<ul class="nav nav-tabs nav-tabs--modern mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeSuministros ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-suministros" type="button" role="tab">
            Suministros
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeNuevo ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-nuevo" type="button" role="tab">
            Nuevo suministro
        </button>
    </li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade <?= $activeSuministros ? 'show active' : '' ?>" id="tab-suministros" role="tabpanel">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <table class="table table-hover align-middle mb-0" data-datatable data-page-length="10" data-empty="No hay contratos">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Comercializadora</th>
                            <th>Distribuidora</th>
                            <th>Nº Contrato</th>
                            <th>CUPS</th>
                            <th>Estado</th>
                            <th>Vencimiento</th>
                            <th class="text-end">Comisión</th>
                            <th class="text-end" style="width:1%">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($contracts === []): ?>
                        <tr class="table-empty-row">
                            <td colspan="9" class="text-muted text-center py-4">No hay contratos para esta comunidad.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($contracts as $c): ?>
                        <?php [$lbl, $badge] = $statusMeta((string) ($c['status'] ?? 'draft')); ?>
                        <tr>
                            <td><?= htmlspecialchars($typeLabel((string) ($c['supply_type'] ?? 'other'))) ?></td>
                            <td><?= htmlspecialchars((string) ($c['marketer_name'] ?? '—')) ?></td>
                            <td><?= htmlspecialchars((string) ($c['distributor_name'] ?? '—')) ?></td>
                            <td><?= htmlspecialchars((string) ($c['contract_number'] ?? '')) ?></td>
                            <td><?= htmlspecialchars((string) ($c['cups'] ?? '—')) ?></td>
                            <td><span class="badge <?= $badge ?>"><?= htmlspecialchars($lbl) ?></span></td>
                            <td><?= htmlspecialchars((string) ($c['end_date'] ?? '—')) ?></td>
                            <td class="text-end"><?= number_format((float) ($c['admin_fee_eur'] ?? 0), 2, ',', '.') ?> €</td>
                            <td class="text-end text-nowrap">
                                <div class="table-actions actions-cell justify-content-end">
                                    <?php if ((int) ($c['documents_count'] ?? 0) > 0): ?>
                                        <a class="btn btn-sm btn-outline-secondary"
                                           href="<?= $ab ?>/suministros/contratos/<?= (int) ($c['id'] ?? 0) ?>/documento/ver"
                                           target="_blank" title="Previsualizar">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a class="btn btn-sm btn-outline-primary"
                                           href="<?= $ab ?>/suministros/contratos/<?= (int) ($c['id'] ?? 0) ?>/documento"
                                           title="Descargar">
                                            <i class="bi bi-download"></i>
                                        </a>
                                    <?php endif; ?>

                                    <button type="button"
                                            class="btn btn-sm btn-outline-success"
                                            title="Editar"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editSupplyContractModal"
                                            data-contract-id="<?= (int) ($c['id'] ?? 0) ?>"
                                            data-supply-type="<?= htmlspecialchars((string) ($c['supply_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-contract-number="<?= htmlspecialchars((string) ($c['contract_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-marketer-id="<?= (int) ($c['marketer_company_id'] ?? 0) ?>"
                                            data-distributor-id="<?= (int) ($c['distributor_company_id'] ?? 0) ?>"
                                            data-cups="<?= htmlspecialchars((string) ($c['cups'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-start-date="<?= htmlspecialchars((string) ($c['start_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-end-date="<?= htmlspecialchars((string) ($c['end_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-admin-fee="<?= htmlspecialchars((string) ($c['admin_fee_eur'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>"
                                            data-auto-renew="<?= !empty($c['auto_renew']) && in_array($c['auto_renew'], [true, 't', '1', 1], true) ? '1' : '0' ?>"
                                            data-supply-address="<?= htmlspecialchars((string) ($c['supply_address'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-notes="<?= htmlspecialchars((string) ($c['notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>

                                    <form method="post"
                                          action="<?= $ab ?>/suministros/comunidades/<?= (int) ($community['id'] ?? 0) ?>/contratos/<?= (int) ($c['id'] ?? 0) ?>/delete"
                                          class="d-inline"
                                          data-confirm="¿Eliminar este suministro? Esta acción no se puede deshacer.">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-pane fade <?= $activeNuevo ? 'show active' : '' ?>" id="tab-nuevo" role="tabpanel">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <?php if ($formErrors !== []): ?>
                    <div class="alert alert-warning">
                        <div class="fw-semibold mb-2">Revisa los siguientes errores:</div>
                        <ul class="mb-0">
                            <?php foreach ($formErrors as $err): ?>
                                <li><?= htmlspecialchars((string) $err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post"
                      action="<?= $ab ?>/suministros/comunidades/<?= (int) ($community['id'] ?? 0) ?>/contratos"
                      enctype="multipart/form-data"
                      class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label">Tipo de suministro *</label>
                        <select name="supply_type" class="form-select" required>
                            <?php
                            $types = [
                                'electricity' => 'Electricidad',
                                'gas' => 'Gas',
                                'water' => 'Agua',
                                'telecom' => 'Telecom',
                                'other' => 'Otro',
                            ];
                            $selectedType = (string) ($old['supply_type'] ?? '');
                            foreach ($types as $k => $v):
                            ?>
                                <option value="<?= htmlspecialchars($k) ?>" <?= $selectedType === $k ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Comercializadora</label>
                        <select name="marketer_company_id" class="form-select">
                            <option value="">Seleccionar comercializadora</option>
                            <?php foreach ($companies as $co): ?>
                                <?php
                                $id = (int) ($co['id'] ?? 0);
                                $selected = (string) ($old['marketer_company_id'] ?? '') === (string) $id;
                                ?>
                                <option value="<?= $id ?>" <?= $selected ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) ($co['name'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6" id="distributor_field_wrap">
                        <label class="form-label">Distribuidora</label>
                        <select name="distributor_company_id" class="form-select">
                            <option value="">Seleccionar distribuidora</option>
                            <?php foreach ($companies as $co): ?>
                                <?php
                                $id = (int) ($co['id'] ?? 0);
                                $selected = (string) ($old['distributor_company_id'] ?? '') === (string) $id;
                                ?>
                                <option value="<?= $id ?>" <?= $selected ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) ($co['name'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Solo para suministros de electricidad</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Número de contrato *</label>
                        <input type="text" class="form-control" name="contract_number" required
                               value="<?= htmlspecialchars((string) ($old['contract_number'] ?? '')) ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">CUPS</label>
                        <input type="text" class="form-control" name="cups"
                               value="<?= htmlspecialchars((string) ($old['cups'] ?? '')) ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Fecha de inicio *</label>
                        <input type="date" class="form-control" name="start_date" required
                               value="<?= htmlspecialchars((string) ($old['start_date'] ?? '')) ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Fecha de fin</label>
                        <input type="date" class="form-control" name="end_date"
                               value="<?= htmlspecialchars((string) ($old['end_date'] ?? '')) ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Comisión administrativa (€)</label>
                        <input type="text" class="form-control" name="admin_fee_eur"
                               value="<?= htmlspecialchars((string) ($old['admin_fee_eur'] ?? '0')) ?>">
                    </div>

                    <div class="col-md-6">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="auto_renew" id="auto_renew"
                                   <?= (string) ($old['auto_renew'] ?? '0') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="auto_renew">
                                Renovación automática
                            </label>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Dirección del punto de suministro *</label>
                        <textarea class="form-control" name="supply_address" rows="2" required><?= htmlspecialchars((string) ($old['supply_address'] ?? '')) ?></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Observaciones</label>
                        <textarea class="form-control" name="notes" rows="3"><?= htmlspecialchars((string) ($old['notes'] ?? '')) ?></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Documento PDF</label>
                        <input type="file" class="form-control" name="contract_pdf" accept="application/pdf,.pdf">
                        <div class="form-text">Opcional. Solo PDF.</div>
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2 pt-2">
                        <a href="<?= $ab ?>/suministros/comunidades/<?= (int) ($community['id'] ?? 0) ?>" class="btn btn-outline-secondary btn-sm">Cancelar</a>
                        <button type="submit" class="btn btn-primary btn-sm">Guardar suministro</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editSupplyContractModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <form method="post" id="editSupplyContractForm" enctype="multipart/form-data" class="modal-content border-0 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title h6 mb-0">Editar contrato</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body pt-3">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Tipo de suministro *</label>
                            <select name="supply_type" id="edit_supply_type" class="form-select" required>
                                <option value="electricity">Electricidad</option>
                                <option value="gas">Gas</option>
                                <option value="water">Agua</option>
                                <option value="telecom">Telecom</option>
                                <option value="other">Otro</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Comercializadora</label>
                            <select name="marketer_company_id" id="edit_marketer_company_id" class="form-select">
                                <option value="">Seleccionar comercializadora</option>
                                <?php foreach ($companies as $co): ?>
                                    <option value="<?= (int) ($co['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($co['name'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6" id="edit_distributor_field_wrap">
                            <label class="form-label">Distribuidora</label>
                            <select name="distributor_company_id" id="edit_distributor_company_id" class="form-select">
                                <option value="">Seleccionar distribuidora</option>
                                <?php foreach ($companies as $co): ?>
                                    <option value="<?= (int) ($co['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($co['name'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Solo para electricidad</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Número de contrato *</label>
                            <input type="text" name="contract_number" id="edit_contract_number" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">CUPS</label>
                            <input type="text" name="cups" id="edit_cups" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Fecha de inicio *</label>
                            <input type="date" name="start_date" id="edit_start_date" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Fecha de fin</label>
                            <input type="date" name="end_date" id="edit_end_date" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Comisión administrativa (€)</label>
                            <input type="text" name="admin_fee_eur" id="edit_admin_fee_eur" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" name="auto_renew" id="edit_auto_renew">
                                <label class="form-check-label" for="edit_auto_renew">Renovación automática</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Dirección del punto de suministro *</label>
                            <textarea name="supply_address" id="edit_supply_address" class="form-control" rows="2" required></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Observaciones</label>
                            <textarea name="notes" id="edit_notes" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Nuevo documento PDF (opcional)</label>
                            <input type="file" name="contract_pdf" class="form-control" accept="application/pdf,.pdf">
                        </div>
                    </div>
                    <p class="form-text mb-0 mt-2">El estado se calcula automáticamente según las fechas.</p>
                </div>
                <div class="modal-footer border-top-0 gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    (function () {
        const hash = window.location.hash;
        if (hash === '#tab-nuevo') {
            const tabBtn = document.querySelector('[data-bs-target="#tab-nuevo"]');
            if (tabBtn && window.bootstrap?.Tab) {
                bootstrap.Tab.getOrCreateInstance(tabBtn).show();
            }
        }

        const modal = document.getElementById('editSupplyContractModal');
        const form = document.getElementById('editSupplyContractForm');

        modal?.addEventListener('show.bs.modal', function (event) {
            const btn = event.relatedTarget;
            if (!btn || !form) return;

            const contractId = btn.getAttribute('data-contract-id') || '0';
            form.action = '<?= $ab ?>/suministros/comunidades/<?= (int) ($community['id'] ?? 0) ?>/contratos/' + encodeURIComponent(contractId) + '/update';

            const set = (id, attr) => {
                const el = document.getElementById(id);
                if (el) el.value = btn.getAttribute(attr) || '';
            };
            set('edit_supply_type', 'data-supply-type');
            set('edit_contract_number', 'data-contract-number');
            set('edit_marketer_company_id', 'data-marketer-id');
            set('edit_distributor_company_id', 'data-distributor-id');
            set('edit_cups', 'data-cups');
            set('edit_start_date', 'data-start-date');
            set('edit_end_date', 'data-end-date');
            set('edit_admin_fee_eur', 'data-admin-fee');
            set('edit_supply_address', 'data-supply-address');
            set('edit_notes', 'data-notes');

            const ar = document.getElementById('edit_auto_renew');
            if (ar) ar.checked = btn.getAttribute('data-auto-renew') === '1';

            syncEditDistributorVisibility();
        });

        const editSupplyType = document.getElementById('edit_supply_type');
        const editDistributorWrap = document.getElementById('edit_distributor_field_wrap');
        function syncEditDistributorVisibility() {
            if (!editSupplyType || !editDistributorWrap) return;
            const isElectric = editSupplyType.value === 'electricity';
            editDistributorWrap.style.display = isElectric ? '' : 'none';
            if (!isElectric) {
                const dist = document.getElementById('edit_distributor_company_id');
                if (dist) dist.value = '';
            }
        }
        editSupplyType?.addEventListener('change', syncEditDistributorVisibility);

        const supplyType = document.querySelector('#tab-nuevo select[name="supply_type"]');
        const distributorWrap = document.getElementById('distributor_field_wrap');
        function syncDistributorVisibility() {
            if (!supplyType || !distributorWrap) return;
            const isElectric = supplyType.value === 'electricity';
            distributorWrap.style.display = isElectric ? '' : 'none';
            if (!isElectric) {
                const dist = distributorWrap.querySelector('select[name="distributor_company_id"]');
                if (dist) dist.value = '';
            }
        }
        supplyType?.addEventListener('change', syncDistributorVisibility);
        syncDistributorVisibility();
    })();
    </script>
</div>