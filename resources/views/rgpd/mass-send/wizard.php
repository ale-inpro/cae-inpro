<?php declare(strict_types=1);
$ab = htmlspecialchars($areaBaseUrl ?? '');
$bu = htmlspecialchars($baseUrl ?? '');
$step = (int) ($step ?? 1);
$wizard = $wizard ?? [];
$communities = $communities ?? [];
$templates = $templates ?? [];
$wizardResidentsByCommunity = $wizardResidentsByCommunity ?? [];
$preview = $preview ?? null;
$confirmMeta = $confirmMeta ?? null;
$selectedCommunityIds = array_map('intval', (array) ($selectedCommunityIds ?? []));
$residentSelections = $residentSelections ?? [];
$selectedTemplates = array_map('intval', (array) ($wizard['template_ids'] ?? []));
$hasSelectedCommunities = $selectedCommunityIds !== [];
$hasResidentsLoaded = $wizardResidentsByCommunity !== [];
?>
<div class="page-header page-header--balanced page-header--premium mb-4">
    <div class="page-header-left">
        <h1 class="h3 page-title mb-1">Envío masivo RGPD</h1>
        <p class="page-meta mb-0">Seleccione plantillas, elija comunidades y vecinos, y confirme el envío.</p>
    </div>
    <div class="page-header-center"></div>
    <div class="page-header-right">
        <form method="post" action="<?= $ab ?>/rgpd/envio-masivo" class="d-inline">
            <input type="hidden" name="wizard_action" value="reset">
            <button type="submit" class="btn btn-outline-secondary btn-sm">Reiniciar</button>
        </form>
    </div>
</div>

<div class="rgpd-wizard-progress-wrap mb-4">
    <ol class="rgpd-wizard-stepper rgpd-wizard-stepper--checkout list-unstyled mb-0">
        <li class="rgpd-wizard-step-item <?= $step >= 1 ? 'is-active' : '' ?> <?= $step > 1 ? 'is-done' : '' ?>">
            <span class="rgpd-wizard-step-dot"><?= $step > 1 ? '<i class="bi bi-check-lg"></i>' : '1' ?></span>
            <span class="rgpd-wizard-step-label">Plantillas</span>
        </li>
        <li class="rgpd-wizard-step-connector <?= $step > 1 ? 'is-done' : '' ?>"></li>
        <li class="rgpd-wizard-step-item <?= $step >= 2 ? 'is-active' : '' ?> <?= $step > 2 ? 'is-done' : '' ?>">
            <span class="rgpd-wizard-step-dot"><?= $step > 2 ? '<i class="bi bi-check-lg"></i>' : '2' ?></span>
            <span class="rgpd-wizard-step-label">Vecinos</span>
        </li>
        <li class="rgpd-wizard-step-connector <?= $step > 2 ? 'is-done' : '' ?>"></li>
        <li class="rgpd-wizard-step-item <?= $step >= 3 ? 'is-active' : '' ?> <?= $step === 3 ? 'is-done' : '' ?>">
            <span class="rgpd-wizard-step-dot">3</span>
            <span class="rgpd-wizard-step-label">Confirmar</span>
        </li>
    </ol>
</div>

<?php if ($step === 1): ?>
<form method="post" action="<?= $ab ?>/rgpd/envio-masivo" id="rgpdMassSendStep1" class="subpanel rgpd-wizard-panel">
    <input type="hidden" name="wizard_action" value="step1">
    <div class="subpanel-h d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>Paso 1 · Seleccione las plantillas</span>
        <span class="small text-muted" id="rgpdWizardTemplateCount">0 plantillas seleccionadas</span>
    </div>
    <div class="subpanel-b">
        <div class="row g-3">
            <?php foreach ($templates as $tpl): ?>
                <?php
                $tid = (int) ($tpl['id'] ?? 0);
                $checked = in_array($tid, $selectedTemplates, true);
                $isSystem = ($tpl['kind'] ?? '') === 'system';
                ?>
                <div class="col-md-6 col-lg-4">
                    <label class="rgpd-wizard-template-card <?= $checked ? 'is-selected' : '' ?>">
                        <input type="checkbox"
                               class="rgpd-wizard-template-cb visually-hidden"
                               name="template_ids[]"
                               value="<?= $tid ?>"
                               <?= $checked ? 'checked' : '' ?>>
                        <span class="rgpd-wizard-template-check"><i class="bi bi-check-lg"></i></span>
                        <span class="rgpd-wizard-template-icon">
                            <i class="bi <?= $isSystem ? 'bi-shield-check' : 'bi-file-earmark-text' ?>"></i>
                        </span>
                        <span class="rgpd-wizard-template-title"><?= htmlspecialchars((string) ($tpl['name'] ?? '')) ?></span>
                        <span class="badge <?= $isSystem ? 'text-bg-primary' : 'text-bg-secondary' ?>">
                            <?= $isSystem ? 'Sistema' : 'Usuario' ?>
                        </span>
                        <?php if (!empty($tpl['description'])): ?>
                            <span class="rgpd-wizard-template-desc"><?= htmlspecialchars((string) $tpl['description']) ?></span>
                        <?php endif; ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="subpanel-f d-flex justify-content-end">
        <button type="submit" class="btn btn-success btn-sm" id="rgpdWizardStep1Submit" disabled>
            Continuar <i class="bi bi-arrow-right ms-1"></i>
        </button>
    </div>
</form>
<?php endif; ?>

<?php if ($step === 2): ?>
<form method="post" action="<?= $ab ?>/rgpd/envio-masivo" id="rgpdMassSendStep2" class="subpanel rgpd-wizard-panel">
    <input type="hidden" name="wizard_action" value="step2">
    <div class="subpanel-h">Paso 2 · Seleccione comunidades y vecinos destinatarios</div>
    <div class="subpanel-b">
        <div class="rgpd-wizard-info-banner mb-3">
            <i class="bi bi-shield-check"></i>
            <span>Los vecinos con <strong>todas las plantillas firmadas</strong> no se pueden seleccionar. Los pendientes recibirán un recordatorio con enlace nuevo.</span>
        </div>

        <div class="mb-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <label class="form-label small fw-semibold mb-0">Comunidades</label>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="rgpdWizardSelectAllCommunities">
                        <i class="bi bi-check2-all me-1"></i> Seleccionar todas
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="rgpdWizardClearAllCommunities">
                        <i class="bi bi-x-lg me-1"></i> Deseleccionar todas
                    </button>
                </div>
            </div>
            <div class="row g-2" id="rgpdWizardCommunityPicker">
                <?php foreach ($communities as $c): ?>
                    <?php
                    $cid = (int) ($c['id'] ?? 0);
                    $checked = in_array($cid, $selectedCommunityIds, true);
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <label class="rgpd-wizard-community-card <?= $checked ? 'is-selected' : '' ?>">
                            <input type="checkbox"
                                   class="rgpd-wizard-community-cb visually-hidden"
                                   value="<?= $cid ?>"
                                   <?= $checked ? 'checked' : '' ?>>
                            <span class="rgpd-wizard-community-check"><i class="bi bi-check-lg"></i></span>
                            <span class="rgpd-wizard-community-icon"><i class="bi bi-buildings"></i></span>
                            <span class="rgpd-wizard-community-title"><?= htmlspecialchars((string) ($c['name'] ?? '')) ?></span>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="small text-muted mt-2" id="rgpdWizardCommunityCount">
                <?= count($selectedCommunityIds) ?> comunidad<?= count($selectedCommunityIds) === 1 ? '' : 'es' ?> seleccionada<?= count($selectedCommunityIds) === 1 ? '' : 's' ?>
            </div>
        </div>

        <?php if (!$hasSelectedCommunities): ?>
            <div class="rgpd-wizard-empty-state">
                <i class="bi bi-building"></i>
                <p class="mb-0">Seleccione al menos una comunidad para ver los vecinos.</p>
            </div>
        <?php else: ?>
            <?php foreach ($selectedCommunityIds as $cid): ?>
                <input type="hidden" name="community_ids[]" value="<?= $cid ?>">
            <?php endforeach; ?>

            <div class="row g-2 mb-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label small">Rol</label>
                    <select id="rgpdWizardFilterRole" class="form-select form-select-sm">
                        <option value="all">Todos</option>
                        <option value="presidents">Solo presidentes</option>
                        <option value="residents">Solo vecinos</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small">Buscar</label>
                    <input type="search" id="rgpdWizardSearch" class="form-control form-control-sm" placeholder="Buscar por nombre...">
                </div>
            </div>

            <div class="rgpd-wizard-filter-chips mb-3">
                <span class="small text-muted me-1">Mostrar:</span>
                <button type="button" class="rgpd-wizard-chip is-active" data-filter="selectable">Seleccionables</button>
                <button type="button" class="rgpd-wizard-chip" data-filter="pending">Con pendientes</button>
                <button type="button" class="rgpd-wizard-chip" data-filter="unsent">Sin enviar</button>
                <button type="button" class="rgpd-wizard-chip" data-filter="all">Todos</button>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="rgpdWizardSelectVisible">
                    <i class="bi bi-check2-square me-1"></i> Seleccionar visibles
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="rgpdWizardClearSelection">
                    <i class="bi bi-x-lg me-1"></i> Limpiar selección
                </button>
                <span class="small text-muted ms-auto" id="rgpdWizardSelectedCount">0 vecinos seleccionados</span>
            </div>

            <?php if (!$hasResidentsLoaded): ?>
                <div class="rgpd-wizard-empty-state">
                    <i class="bi bi-people"></i>
                    <p class="mb-0">No hay vecinos activos en las comunidades seleccionadas.</p>
                </div>
            <?php else: ?>
                <?php foreach ($wizardResidentsByCommunity as $block): ?>
                    <?php
                    $communityId = (int) ($block['community_id'] ?? 0);
                    $communityName = (string) ($block['community_name'] ?? '');
                    $residents = $block['residents'] ?? [];
                    $selectedForCommunity = array_map('intval', (array) ($residentSelections[$communityId] ?? []));
                    ?>
                    <section class="rgpd-wizard-community-block mb-4"
                             data-community-id="<?= $communityId ?>">
                        <div class="rgpd-wizard-community-block-head">
                            <div>
                                <h3 class="rgpd-wizard-community-block-title mb-0">
                                    <i class="bi bi-buildings me-1"></i><?= htmlspecialchars($communityName) ?>
                                </h3>
                                <div class="small text-muted rgpd-wizard-community-block-count"
                                     data-community-count="<?= $communityId ?>">
                                    0 seleccionados
                                </div>
                            </div>
                            <button type="button"
                                    class="btn btn-outline-secondary btn-sm rgpd-wizard-toggle-all-community"
                                    data-community-id="<?= $communityId ?>">
                                <i class="bi bi-check2-all me-1"></i>
                                <span class="rgpd-wizard-toggle-all-label">Seleccionar todos</span>
                            </button>
                        </div>

                        <?php if ($residents === []): ?>
                            <div class="rgpd-wizard-empty-state rgpd-wizard-empty-state--compact">
                                <p class="mb-0">No hay vecinos activos en esta comunidad.</p>
                            </div>
                        <?php else: ?>
                            <div class="row g-3 rgpd-wizard-residents-grid">
                                <?php foreach ($residents as $wr): ?>
                                    <?php
                                    $rid = (int) ($wr['id'] ?? 0);
                                    $selectable = !empty($wr['selectable']);
                                    $isFullySigned = !empty($wr['is_fully_signed']);
                                    $isPresident = in_array($wr['is_president'] ?? false, [true, 't', '1', 1], true);
                                    $isChecked = in_array($rid, $selectedForCommunity, true);
                                    $badges = $wr['status_badges'] ?? [];
                                    $pendingN = (int) ($wr['pending_n'] ?? 0);
                                    $unsentN = (int) ($wr['unsent_n'] ?? 0);
                                    $cancelledN = (int) ($wr['cancelled_n'] ?? 0);
                                    $freshUnsent = max(0, $unsentN - $cancelledN);
                                    ?>
                                    <div class="col-md-6 col-lg-4 rgpd-wizard-resident-col"
                                         data-president="<?= $isPresident ? '1' : '0' ?>"
                                         data-name="<?= htmlspecialchars(mb_strtolower((string) ($wr['resident_name'] ?? ''))) ?>"
                                         data-selectable="<?= $selectable ? '1' : '0' ?>"
                                         data-fully-signed="<?= $isFullySigned ? '1' : '0' ?>"
                                         data-pending="<?= $pendingN ?>"
                                         data-unsent="<?= $freshUnsent + $cancelledN ?>">
                                        <?php if ($selectable): ?>
                                        <label class="rgpd-wizard-resident-card <?= $isChecked ? 'is-selected' : '' ?>">
                                            <input type="checkbox"
                                                   class="rgpd-wizard-resident-cb visually-hidden"
                                                   name="resident_ids[<?= $communityId ?>][]"
                                                   value="<?= $rid ?>"
                                                   <?= $isChecked ? 'checked' : '' ?>>
                                            <span class="rgpd-wizard-resident-check"><i class="bi bi-check-lg"></i></span>
                                        <?php else: ?>
                                        <div class="rgpd-wizard-resident-card is-disabled <?= $isFullySigned ? 'is-fully-signed' : '' ?>">
                                            <span class="rgpd-wizard-resident-lock" title="No requiere envío"><i class="bi bi-lock-fill"></i></span>
                                        <?php endif; ?>
                                            <div class="rgpd-wizard-resident-top">
                                                <div class="rgpd-wizard-resident-icon">
                                                    <i class="bi <?= $isPresident ? 'bi-award' : 'bi-person' ?>"></i>
                                                </div>
                                                <div class="flex-grow-1 min-w-0">
                                                    <div class="fw-semibold text-truncate"><?= htmlspecialchars((string) ($wr['resident_name'] ?? '')) ?></div>
                                                    <div class="small text-muted text-truncate"><?= htmlspecialchars((string) ($wr['email'] ?? 'Sin email')) ?></div>
                                                </div>
                                                <?php if ($isPresident): ?>
                                                    <span class="badge text-bg-primary">Presidente</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($isFullySigned): ?>
                                                <div class="rgpd-wizard-fully-signed-msg">
                                                    <i class="bi bi-patch-check-fill me-1"></i> Cumplimiento completo — no requiere envío
                                                </div>
                                            <?php endif; ?>
                                            <div class="rgpd-wizard-resident-badges">
                                                <?php foreach ($badges as $badge): ?>
                                                    <span class="<?= htmlspecialchars((string) ($badge['class'] ?? 'rgpd-wizard-badge')) ?>">
                                                        <?= htmlspecialchars((string) ($badge['label'] ?? '')) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php if ($selectable): ?>
                                        </label>
                                        <?php else: ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <div class="subpanel-f d-flex justify-content-between">
        <a class="btn btn-outline-secondary btn-sm" href="<?= $ab ?>/rgpd/envio-masivo?step=1&amp;preserve=1"><i class="bi bi-arrow-left me-1"></i> Anterior</a>
        <button type="submit" class="btn btn-success btn-sm" <?= $hasSelectedCommunities ? '' : 'disabled' ?>>Continuar <i class="bi bi-arrow-right ms-1"></i></button>
    </div>
</form>
<?php endif; ?>

<?php if ($step === 3): ?>
<div class="subpanel rgpd-wizard-panel mb-3">
    <div class="subpanel-h">Paso 3 · Confirmar envío</div>
    <div class="subpanel-b">
        <?php if ($preview && $confirmMeta): ?>
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="rgpd-wizard-summary-card">
                        <div class="rgpd-wizard-summary-card-label">Comunidades</div>
                        <div class="rgpd-wizard-summary-card-value"><?= (int) ($preview['communities'] ?? 0) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="rgpd-wizard-summary-card">
                        <div class="rgpd-wizard-summary-card-label">Vecinos seleccionados</div>
                        <div class="rgpd-wizard-summary-card-value"><?= (int) ($preview['residents'] ?? 0) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="rgpd-wizard-summary-card">
                        <div class="rgpd-wizard-summary-card-label">Plantillas</div>
                        <div class="rgpd-wizard-summary-card-value"><?= (int) ($preview['templates'] ?? 0) ?></div>
                    </div>
                </div>
            </div>

            <?php if (!empty($confirmMeta['community_rows'])): ?>
                <div class="rgpd-wizard-summary-card mb-3">
                    <div class="rgpd-wizard-summary-card-label mb-2">Desglose por comunidad</div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Comunidad</th>
                                <th class="text-end">Vecinos</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($confirmMeta['community_rows'] as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) ($row['community_name'] ?? '')) ?></td>
                                    <td class="text-end"><?= (int) ($row['residents'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <div class="rgpd-wizard-summary-card mb-3">
                <div class="rgpd-wizard-summary-card-label mb-2">Plantillas</div>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach (($confirmMeta['template_names'] ?? []) as $tn): ?>
                        <span class="badge text-bg-light text-dark border"><?= htmlspecialchars((string) $tn) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <div class="rgpd-wizard-metric rgpd-wizard-metric-invite">
                        <div class="rgpd-wizard-metric-value"><?= (int) ($preview['invites'] ?? 0) ?></div>
                        <div class="rgpd-wizard-metric-label">Invitaciones nuevas</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="rgpd-wizard-metric rgpd-wizard-metric-reminder">
                        <div class="rgpd-wizard-metric-value"><?= (int) ($preview['reminders'] ?? 0) ?></div>
                        <div class="rgpd-wizard-metric-label">Recordatorios</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="rgpd-wizard-metric rgpd-wizard-metric-skip">
                        <div class="rgpd-wizard-metric-value"><?= (int) ($preview['skipped'] ?? 0) ?></div>
                        <div class="rgpd-wizard-metric-label">Ya firmadas (omitidas)</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="rgpd-wizard-metric rgpd-wizard-metric-email">
                        <div class="rgpd-wizard-metric-value"><?= (int) ($preview['emails_planned'] ?? 0) ?></div>
                        <div class="rgpd-wizard-metric-label">Correos previstos</div>
                    </div>
                </div>
            </div>

            <?php if ((int) ($preview['reminders'] ?? 0) > 0): ?>
                <div class="rgpd-wizard-info-banner rgpd-wizard-info-banner-warning mb-3">
                    <i class="bi bi-exclamation-triangle"></i>
                    <span>Los recordatorios <strong>sustituyen el enlace anterior</strong> de firma por uno nuevo.</span>
                </div>
            <?php endif; ?>

            <?php if (!empty($confirmMeta['resident_names_by_community'])): ?>
                <details class="rgpd-wizard-resident-summary">
                    <summary>Ver listado de vecinos por comunidad</summary>
                    <?php foreach ($confirmMeta['resident_names_by_community'] as $group): ?>
                        <div class="mt-3">
                            <div class="fw-semibold small"><?= htmlspecialchars((string) ($group['community_name'] ?? '')) ?></div>
                            <ul class="mb-0 mt-1 small">
                                <?php foreach (($group['resident_names'] ?? []) as $rn): ?>
                                    <li><?= htmlspecialchars((string) $rn) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </details>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-warning mb-0">Complete los pasos anteriores antes de lanzar.</p>
        <?php endif; ?>
    </div>
</div>
<form method="post" action="<?= $ab ?>/rgpd/envio-masivo" data-confirm="¿Lanzar campaña y enviar correos?">
    <input type="hidden" name="wizard_action" value="launch">
    <div class="d-flex justify-content-between align-items-center">
        <a class="btn btn-outline-secondary btn-sm" href="<?= $ab ?>/rgpd/envio-masivo?step=2<?php
            if ($selectedCommunityIds !== []) {
                echo '&amp;' . http_build_query(['community_ids' => $selectedCommunityIds], '', '&amp;');
            }
        ?>"><i class="bi bi-arrow-left me-1"></i> Anterior</a>
        <button type="submit" class="btn btn-success" <?= ($preview && $confirmMeta) ? '' : 'disabled' ?>>
            <i class="bi bi-send me-1"></i> Lanzar envío
        </button>
    </div>
</form>
<?php endif; ?>

<script src="<?= $bu ?>/assets/js/rgpd-mass-send-wizard.js"></script>