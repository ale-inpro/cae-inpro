<?php declare(strict_types=1);
$ab = htmlspecialchars($areaBaseUrl ?? '/cae-inpro/public/gestor');
$c = $community ?? [];
$risk = $riskReport ?? null;
$techs = $communityTechnicians ?? [];
$docs = $communityDocuments ?? [];
$isAdmin = (($area ?? 'gestor') === 'admin');
$available = $availableTechnicians ?? [];
$currentUrl = $ab . '/comunidades/' . (int) ($c['id'] ?? 0) . '#c-info';
$editUrl = $ab . '/comunidades/' . (int) ($c['id'] ?? 0) . '/edit?return_to=' . urlencode($currentUrl);
$pendingRlRequests = $pendingRlRequests ?? [];
$communityFormerTechnicians = $communityFormerTechnicians ?? [];
$communityTechSyncVersion = (string) ($communityTechSyncVersion ?? '');

$riskLabel = static function (string $status): string {
    return match ($status) {
        'completed' => 'Completado',
        'in_progress' => 'En proceso',
        'rejected' => 'Rechazado',
        'pending' => 'Pendiente',
        default => ucfirst($status),
    };
};

$riskBadge = static function (string $status): string {
    return match ($status) {
        'completed' => 'text-bg-success',
        'in_progress' => 'text-bg-warning',
        'rejected' => 'text-bg-danger',
        'pending' => 'text-bg-secondary',
        default => 'text-bg-light text-dark',
    };
};

$caeLabel = static function (string $status): string {
    return match ($status) {
        'approved'     => 'Aprobado',
        'in_review'    => 'En revisión',
        'pending'      => 'Pendiente',
        'pending_docs' => 'Pendiente docs.',
        'rejected'     => 'Rechazado',
        'expired'      => 'Caducado',
        default        => ucfirst($status),
    };
};

$caeBadge = static function (string $status): string {
    return match ($status) {
        'approved'     => 'text-bg-success',
        'in_review'    => 'text-bg-warning',
        'pending'      => 'text-bg-secondary',
        'pending_docs' => 'text-bg-warning text-dark',
        'rejected'     => 'text-bg-danger',
        'expired'      => 'text-bg-dark',
        default        => 'text-bg-light text-dark',
    };
};

$feedbackSentimentLabel = static function (?string $s): string {
    return match ($s) {
        'preferred' => 'Preferido por la comunidad',
        'not_preferred' => 'No recomendado por la comunidad',
        'neutral' => 'Neutral',
        default => '',
    };
};

$feedbackReasonCategoryLabel = static function (?string $r): string {
    return match ($r) {
        'quality' => 'Calidad del trabajo',
        'deadlines' => 'Plazos',
        'conduct' => 'Trato / conducta',
        'communication' => 'Comunicación',
        'other' => 'Otro',
        default => '',
    };
};

?>
<div class="panel-identity mb-3">
    <div class="panel-identity-icon"><i class="bi bi-buildings-fill"></i></div>
    <div>
        <p class="panel-identity-kicker mb-1">Panel operativo</p>
        <h2 class="panel-identity-title mb-0">Panel de Comunidad</h2>
    </div>
</div>

<div class="page-header page-header--balanced page-header--premium">
    <div class="page-header-left">
        <h1 class="h3 page-title mb-1"><?= htmlspecialchars((string) ($c['name'] ?? 'Comunidad')) ?></h1>
        <p class="page-meta mb-0">
            <?= htmlspecialchars((string) ($c['address'] ?? '-')) ?> ·
            <?= htmlspecialchars((string) ($c['city'] ?? '-')) ?> ·
            <?php if ($risk): ?>
                <span class="badge <?= $riskBadge((string) $risk['status']) ?>"><?= htmlspecialchars($riskLabel((string) $risk['status'])) ?></span>
            <?php else: ?>
                <span class="badge text-bg-secondary">Sin informe RL</span>
            <?php endif; ?>
        </p>
    </div>

    <div class="page-header-center"></div>

    <div class="page-header-right d-flex align-items-center gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="<?= $ab ?>/comunidades" title="Volver"><i class="bi bi-arrow-left"></i></a>
        <?php if ($isAdmin): ?>
            <a class="btn btn-success btn-sm" href="<?= htmlspecialchars($editUrl) ?>" title="Editar"><i class="bi bi-pencil-square"></i></a>
            <form method="post" action="<?= $ab ?>/comunidades/<?= (int) ($c['id'] ?? 0) ?>" data-confirm="¿Desactivar esta comunidad?" class="m-0">
                <input type="hidden" name="_method" value="DELETE">
                <button class="btn btn-outline-danger btn-sm" type="submit" title="Desactivar"><i class="bi bi-building-x"></i></button>
            </form>
        <?php endif; ?>
    </div>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#c-info" type="button">Datos</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#c-tech" type="button">Técnicos</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#c-docs" type="button">Documentos</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#c-rl" type="button">Informe RL</button></li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="c-info">
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="subpanel">
                    <div class="subpanel-h">Identificación</div>
                    <div class="subpanel-b">
                        <dl class="row mb-0 small">
                            <dt class="col-sm-4 text-muted">CIF</dt><dd class="col-sm-8"><?= htmlspecialchars((string) ($c['cif'] ?? '-')) ?></dd>
                            <dt class="col-sm-4 text-muted">Ciudad</dt><dd class="col-sm-8"><?= htmlspecialchars((string) ($c['city'] ?? '-')) ?></dd>
                            <dt class="col-sm-4 text-muted">Provincia</dt><dd class="col-sm-8"><?= htmlspecialchars((string) ($c['province'] ?? '-')) ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="subpanel">
                    <div class="subpanel-h">Contacto</div>
                    <div class="subpanel-b">
                        <dl class="row mb-0 small">
                            <dt class="col-sm-4 text-muted">Persona</dt><dd class="col-sm-8"><?= htmlspecialchars((string) ($c['contact_name'] ?? '-')) ?></dd>
                            <dt class="col-sm-4 text-muted">Teléfono</dt><dd class="col-sm-8"><?= htmlspecialchars((string) ($c['contact_phone'] ?? '-')) ?></dd>
                            <dt class="col-sm-4 text-muted">Email</dt><dd class="col-sm-8"><?= htmlspecialchars((string) ($c['contact_email'] ?? '-')) ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="c-tech"
        data-community-id="<?= (int) ($c['id'] ?? 0) ?>"
        data-tech-sync-version="<?= htmlspecialchars($communityTechSyncVersion, ENT_QUOTES, 'UTF-8') ?>">
        <div class="subpanel mb-3">
            <div class="subpanel-h d-flex justify-content-between align-items-center">
                <span>Técnicos asignados</span>
                <span class="badge text-bg-success"><?= count($techs) ?></span>
            </div>
            <div class="subpanel-b">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 table-mobile-cards">
                        <thead><tr><th>Técnico</th><th>Profesión</th><th>Estado CAE</th><?php if (!$isAdmin): ?><th class="text-nowrap">Valoración comunidad</th><?php endif; ?><?php if ($isAdmin): ?><th></th><?php endif; ?></tr></thead>
                        <tbody>
                        <?php foreach ($techs as $t): ?>
                            <?php
                            $status = (string) ($t['cae_status'] ?? 'pending_docs');
                            $name = trim((string) ($t['display_name'] ?? ''));
                            $tid = (int) ($t['id'] ?? 0);
                            ?>
                            <tr class="community-tech-assigned">
                                <td data-label="Técnico"><?= htmlspecialchars($name) ?></td>
                                <td data-label="Profesión"><?= htmlspecialchars((string) ($t['professions'] ?? '-')) ?></td>
                                <td data-label="Estado CAE"><span class="badge <?= $caeBadge($status) ?>"><?= htmlspecialchars($caeLabel($status)) ?></span></td>
                                <?php if (!$isAdmin): ?>
                                    <td data-label="Valoración">
                                        <?php
                                            $fs = (string) ($t['feedback_sentiment'] ?? '');
                                            $fcb = match ($fs) {
                                                'preferred' => 'success',
                                                'not_preferred' => 'danger',
                                                'neutral' => 'secondary',
                                                default => 'secondary',
                                            };
                                        ?>
                                        <?php if ($fs === ''): ?>
                                            <span class="text-muted small">Sin valorar</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-<?= $fcb ?>"><?= htmlspecialchars($feedbackSentimentLabel($fs)) ?></span>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary ms-2"
                                            data-bs-toggle="modal" data-bs-target="#cfbAssignedModal<?= $tid ?>"><i class="bi bi-chat-square-text me-1"></i>Valorar</button>
                                    </td>
                                <?php endif; ?>
                                <?php if ($isAdmin): ?>
                                    <td data-label="Acciones" class="text-end">
                                        <form method="post" action="<?= $ab ?>/comunidades/<?= (int) ($c['id'] ?? 0) ?>/tecnicos/<?= $tid ?>" data-confirm="¿Desasignar este técnico de la comunidad?">
                                            <input type="hidden" name="_method" value="DELETE">
                                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Quitar"><i class="bi bi-person-dash"></i></button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if (!$isAdmin): ?>
            <?php foreach ($techs as $t): ?>
                <?php
                    $tid = (int) ($t['id'] ?? 0);
                    $fname = trim((string) ($t['display_name'] ?? ''));
                    $cfbAssignedTitleId = 'cfbAssignedTitle' . $tid;
                ?>
                <div class="modal fade community-feedback-modal" id="cfbAssignedModal<?= $tid ?>" tabindex="-1" aria-labelledby="<?= htmlspecialchars($cfbAssignedTitleId, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                        <div class="modal-content shadow-lg border-0">
                            <form method="post" action="<?= $ab ?>/comunidades/<?= (int) ($c['id'] ?? 0) ?>/tecnicos/<?= $tid ?>/feedback">
                                <input type="hidden" name="return_to" value="<?= htmlspecialchars($ab . '/comunidades/' . (int) ($c['id'] ?? 0) . '#c-tech', ENT_QUOTES, 'UTF-8') ?>">
                                <div class="modal-header align-items-start gap-2 border-bottom-0 pb-0">
                                    <div class="d-flex gap-3 flex-grow-1 min-w-0">
                                        <span class="community-feedback-modal-icon flex-shrink-0" aria-hidden="true"><i class="bi bi-chat-heart-fill"></i></span>
                                        <div class="min-w-0">
                                            <h5 class="modal-title mb-1" id="<?= htmlspecialchars($cfbAssignedTitleId, ENT_QUOTES, 'UTF-8') ?>">Valoración de la comunidad</h5>
                                            <p class="mb-0 small text-muted text-truncate" title="<?= htmlspecialchars($fname, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($fname) ?></p>
                                        </div>
                                    </div>
                                    <button type="button" class="btn-close flex-shrink-0 mt-1" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                </div>
                                <div class="modal-body pt-3">
                                    <label class="form-label fw-semibold"><i class="bi bi-sliders me-1 text-primary"></i>Impresión general</label>
                                    <select name="sentiment" class="form-select mb-3" required>
                                        <option value="neutral" <?= (($t['feedback_sentiment'] ?? '') === 'neutral' || ($t['feedback_sentiment'] ?? '') === '') ? 'selected' : '' ?>>Neutral — sin preferencia marcada</option>
                                        <option value="preferred" <?= (($t['feedback_sentiment'] ?? '') === 'preferred') ? 'selected' : '' ?>>Buena experiencia (preferido)</option>
                                        <option value="not_preferred" <?= (($t['feedback_sentiment'] ?? '') === 'not_preferred') ? 'selected' : '' ?>>Mala experiencia (no recomendado)</option>
                                    </select>
                                    <label class="form-label fw-semibold"><i class="bi bi-tag me-1 text-secondary"></i>Motivo <span class="fw-normal text-muted">(opcional)</span></label>
                                    <select name="reason_category" class="form-select mb-3">
                                        <option value="">—</option>
                                        <option value="quality" <?= (($t['feedback_reason_category'] ?? '') === 'quality') ? 'selected' : '' ?>>Calidad del trabajo</option>
                                        <option value="deadlines" <?= (($t['feedback_reason_category'] ?? '') === 'deadlines') ? 'selected' : '' ?>>Plazos</option>
                                        <option value="conduct" <?= (($t['feedback_reason_category'] ?? '') === 'conduct') ? 'selected' : '' ?>>Trato / conducta</option>
                                        <option value="communication" <?= (($t['feedback_reason_category'] ?? '') === 'communication') ? 'selected' : '' ?>>Comunicación</option>
                                        <option value="other" <?= (($t['feedback_reason_category'] ?? '') === 'other') ? 'selected' : '' ?>>Otro</option>
                                    </select>
                                    <label class="form-label fw-semibold"><i class="bi bi-chat-left-text me-1 text-secondary"></i>Comentario breve <span class="fw-normal text-muted">(opcional, máx. 280)</span></label>
                                    <textarea name="comment" class="form-control" rows="3" maxlength="280" placeholder="Ej.: puntualidad, resolución de incidencias…"><?= htmlspecialchars((string) ($t['feedback_comment'] ?? '')) ?></textarea>
                                </div>
                                <div class="modal-footer border-top-0 pt-0 gap-2">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancelar</button>
                                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar valoración</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($isAdmin && !empty($available)): ?>
            <div class="subpanel mb-3">
                <div class="subpanel-h d-flex justify-content-between align-items-center">
                    <span>Técnicos disponibles</span>
                    <span class="badge text-bg-secondary"><?= count($available) ?></span>
                </div>
                <div class="subpanel-b">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 table-mobile-cards">
                            <thead><tr><th>Técnico</th><th>Profesión</th><th class="text-nowrap">Notas comunidad</th><th class="text-end" style="width:1%">Acciones</th></tr></thead>
                            <tbody>
                            <?php foreach ($available as $a): ?>
                                <?php
                                    $tid = (int) ($a['id'] ?? 0);
                                    $aName = trim((string) ($a['display_name'] ?? ''));
                                    $afs = (string) ($a['feedback_sentiment'] ?? '');
                                    $aReasonKey = (string) ($a['feedback_reason_category'] ?? '');
                                    $aReasonLbl = $feedbackReasonCategoryLabel($aReasonKey !== '' ? $aReasonKey : null);
                                    $aComment = trim((string) ($a['feedback_comment'] ?? ''));
                                    $assignWarn = ($afs === 'not_preferred');
                                ?>
                                <tr class="community-tech-available">
                                    <td data-label="Técnico"><?= htmlspecialchars($aName) ?></td>
                                    <td data-label="Profesión"><?= htmlspecialchars((string) ($a['professions'] ?? '-')) ?></td>
                                    <td data-label="Notas comunidad" class="community-feedback-badge-wrap">
                                        <?php if ($afs === 'not_preferred'): ?>
                                            <span class="badge text-bg-danger" title="<?= htmlspecialchars($aComment, ENT_QUOTES, 'UTF-8') ?>">No recomendado</span>
                                        <?php elseif ($afs === 'preferred'): ?>
                                            <span class="badge text-bg-success" title="<?= htmlspecialchars($aComment, ENT_QUOTES, 'UTF-8') ?>">Preferido</span>
                                        <?php elseif ($afs === 'neutral'): ?>
                                            <span class="badge text-bg-secondary">Neutral</span>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Acciones" class="text-end">
                                        <form method="post" action="<?= $ab ?>/comunidades/<?= (int) ($c['id'] ?? 0) ?>/tecnicos/<?= $tid ?>" class="m-0 community-assign-tech-form">
                                            <button
                                                class="btn btn-sm btn-outline-success"
                                                type="<?= $assignWarn ? 'button' : 'submit' ?>"
                                                title="Asignar"
                                                <?php if ($assignWarn): ?>
                                                data-assign-not-preferred-warn="1"
                                                data-tech-name="<?= htmlspecialchars($aName, ENT_QUOTES, 'UTF-8') ?>"
                                                data-community-name="<?= htmlspecialchars((string) ($c['name'] ?? 'Comunidad'), ENT_QUOTES, 'UTF-8') ?>"
                                                data-reason-label="<?= htmlspecialchars($aReasonLbl, ENT_QUOTES, 'UTF-8') ?>"
                                                data-comment="<?= htmlspecialchars($aComment, ENT_QUOTES, 'UTF-8') ?>"
                                                <?php endif; ?>
                                            ><i class="bi bi-person-plus"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($isAdmin && !empty($available)): ?>
        <div class="modal fade" id="modalAssignNotPreferred" tabindex="-1" aria-labelledby="modalAssignNotPreferredLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header border-bottom-0 pb-0">
                        <div class="d-flex gap-3 align-items-start">
                            <span class="community-feedback-modal-icon community-feedback-modal-icon--former flex-shrink-0" aria-hidden="true">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                            </span>
                            <div>
                                <h5 class="modal-title mb-1" id="modalAssignNotPreferredLabel">Asignar técnico no recomendado</h5>
                                <p class="small text-muted mb-0">La comunidad ha valorado negativamente a este técnico.</p>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body pt-3">
                        <p class="mb-0" id="modalAssignNotPreferredText"></p>
                    </div>
                    <div class="modal-footer border-top-0 gap-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-1"></i>Cancelar
                        </button>
                        <button type="button" class="btn btn-warning" id="modalAssignNotPreferredConfirm">
                            <i class="bi bi-person-plus me-1"></i>Asignar de todos modos
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$isAdmin && !empty($communityFormerTechnicians)): ?>
        <div class="subpanel mb-3">
            <div class="subpanel-h d-flex justify-content-between align-items-center">
                <span>Técnicos desasignados recientes</span>
                <span class="badge text-bg-secondary"><?= count($communityFormerTechnicians) ?></span>
            </div>
            <div class="subpanel-b">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 table-mobile-cards">
                        <thead>
                            <tr>
                                <th>Técnico</th>
                                <th>Profesión</th>
                                <th class="text-nowrap">Desasignado</th>
                                <th class="text-nowrap">Valoración</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($communityFormerTechnicians as $ft): ?>
                            <?php
                                $tid = (int) ($ft['id'] ?? 0);
                                $fname = trim((string) ($ft['display_name'] ?? ''));
                                $fs = (string) ($ft['feedback_sentiment'] ?? '');
                                $fcb = match ($fs) {
                                    'preferred' => 'success',
                                    'not_preferred' => 'danger',
                                    'neutral' => 'secondary',
                                    default => 'secondary',
                                };
                                $ua = $ft['unassigned_at'] ?? null;
                                $uaFmt = $ua ? app_datetime((string) $ua) : '—';
                            ?>
                            <tr class="community-tech-former">
                                <td data-label="Técnico"><?= htmlspecialchars($fname) ?></td>
                                <td data-label="Profesión"><?= htmlspecialchars((string) ($ft['professions'] ?? '-')) ?></td>
                                <td data-label="Desasignado" class="text-muted small"><?= htmlspecialchars($uaFmt) ?></td>
                                <td data-label="Valoración">
                                    <?php if ($fs === ''): ?>
                                        <span class="text-muted small">Sin valorar</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-<?= $fcb ?>"><?= htmlspecialchars($feedbackSentimentLabel($fs)) ?></span>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary ms-2"
                                        data-bs-toggle="modal" data-bs-target="#cfbFormerModal<?= $tid ?>"><i class="bi bi-chat-square-text me-1"></i>Valorar</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php foreach ($communityFormerTechnicians as $ft): ?>
            <?php
                $tid = (int) ($ft['id'] ?? 0);
                $fname = trim((string) ($ft['display_name'] ?? ''));
                $cfbFormerTitleId = 'cfbFormerTitle' . $tid;
            ?>
            <div class="modal fade community-feedback-modal" id="cfbFormerModal<?= $tid ?>" tabindex="-1" aria-labelledby="<?= htmlspecialchars($cfbFormerTitleId, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content shadow-lg border-0">
                        <form method="post" action="<?= $ab ?>/comunidades/<?= (int) ($c['id'] ?? 0) ?>/tecnicos/<?= $tid ?>/feedback">
                            <input type="hidden" name="return_to" value="<?= htmlspecialchars($ab . '/comunidades/' . (int) ($c['id'] ?? 0) . '#c-tech', ENT_QUOTES, 'UTF-8') ?>">
                            <div class="modal-header align-items-start gap-2 border-bottom-0 pb-0">
                                <div class="d-flex gap-3 flex-grow-1 min-w-0">
                                    <span class="community-feedback-modal-icon community-feedback-modal-icon--former flex-shrink-0" aria-hidden="true"><i class="bi bi-person-dash"></i></span>
                                    <div class="min-w-0">
                                        <h5 class="modal-title mb-1" id="<?= htmlspecialchars($cfbFormerTitleId, ENT_QUOTES, 'UTF-8') ?>">Valoración · técnico desasignado</h5>
                                        <p class="mb-0 small text-muted text-truncate" title="<?= htmlspecialchars($fname, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($fname) ?></p>
                                    </div>
                                </div>
                                <button type="button" class="btn-close flex-shrink-0 mt-1" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <div class="modal-body pt-3">
                                <label class="form-label fw-semibold"><i class="bi bi-sliders me-1 text-primary"></i>Impresión general</label>
                                <select name="sentiment" class="form-select mb-3" required>
                                    <option value="neutral" <?= (($ft['feedback_sentiment'] ?? '') === 'neutral' || ($ft['feedback_sentiment'] ?? '') === '') ? 'selected' : '' ?>>Neutral — sin preferencia marcada</option>
                                    <option value="preferred" <?= (($ft['feedback_sentiment'] ?? '') === 'preferred') ? 'selected' : '' ?>>Buena experiencia (preferido)</option>
                                    <option value="not_preferred" <?= (($ft['feedback_sentiment'] ?? '') === 'not_preferred') ? 'selected' : '' ?>>Mala experiencia (no recomendado)</option>
                                </select>
                                <label class="form-label fw-semibold"><i class="bi bi-tag me-1 text-secondary"></i>Motivo <span class="fw-normal text-muted">(opcional)</span></label>
                                <select name="reason_category" class="form-select mb-3">
                                    <option value="">—</option>
                                    <option value="quality" <?= (($ft['feedback_reason_category'] ?? '') === 'quality') ? 'selected' : '' ?>>Calidad del trabajo</option>
                                    <option value="deadlines" <?= (($ft['feedback_reason_category'] ?? '') === 'deadlines') ? 'selected' : '' ?>>Plazos</option>
                                    <option value="conduct" <?= (($ft['feedback_reason_category'] ?? '') === 'conduct') ? 'selected' : '' ?>>Trato / conducta</option>
                                    <option value="communication" <?= (($ft['feedback_reason_category'] ?? '') === 'communication') ? 'selected' : '' ?>>Comunicación</option>
                                    <option value="other" <?= (($ft['feedback_reason_category'] ?? '') === 'other') ? 'selected' : '' ?>>Otro</option>
                                </select>
                                <label class="form-label fw-semibold"><i class="bi bi-chat-left-text me-1 text-secondary"></i>Comentario breve <span class="fw-normal text-muted">(opcional, máx. 280)</span></label>
                                <textarea name="comment" class="form-control" rows="3" maxlength="280" placeholder="Ej.: puntualidad, resolución de incidencias…"><?= htmlspecialchars((string) ($ft['feedback_comment'] ?? '')) ?></textarea>
                            </div>
                            <div class="modal-footer border-top-0 pt-0 gap-2">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancelar</button>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar valoración</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php if (empty($techs)): ?>
            <div class="community-empty-note">
                <i class="bi bi-info-circle me-1"></i> Esta comunidad aún no tiene técnicos asignados.
            </div>
        <?php endif; ?>
    </div>

    <div class="tab-pane fade" id="c-docs">
        <?php $docTypes = $communityDocTypes ?? []; ?>

        <?php if ($isAdmin && !empty($docTypes)): ?>
            <div class="subpanel mb-3">
                <div class="subpanel-h">Subir documento de comunidad</div>
                <div class="subpanel-b">
                    <form method="post" enctype="multipart/form-data" action="<?= $ab ?>/comunidades/<?= (int) ($c['id'] ?? 0) ?>/documentos" class="row g-2">
                        <input type="hidden" name="return_to" value="<?= $ab ?>/comunidades/<?= (int) ($c['id'] ?? 0) ?>#c-docs">
                        <div class="col-md-4">
                            <select name="document_type_id" class="form-select" required>
                                <option value="">Tipo de documento</option>
                                <?php foreach ($docTypes as $dt): ?>
                                    <option value="<?= (int) ($dt['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($dt['name'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <input type="file" name="document_file" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-success w-100" type="submit"><i class="bi bi-upload"></i></button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 table-mobile-cards">
                <thead><tr><th>Documento</th><th>Archivo</th><th>Subida</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($docs as $d): ?>
                    <tr>
                        <td data-label="Documento"><?= htmlspecialchars((string) ($d['document_name'] ?? '-')) ?></td>
                        <td data-label="Archivo"><?= htmlspecialchars((string) ($d['original_filename'] ?? '-')) ?></td>
                        <td data-label="Subida"><?= app_datetime($d['uploaded_at'] ?? null) ?></td>
                        <td data-label="Acciones" class="text-end">
                            <div class="table-actions">
                                <?php if (!empty($d['storage_path'])): ?>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-secondary"
                                        data-file-preview
                                        data-file-preview-url="<?= htmlspecialchars((string) (($baseUrl ?? '') . ($d['storage_path'] ?? ''))) ?>"
                                        data-file-preview-name="<?= htmlspecialchars((string) ($d['original_filename'] ?? 'Documento')) ?>"
                                        title="Ver"
                                    ><i class="bi bi-eye"></i></button>
                                <?php endif; ?>
                                <a
                                    class="btn btn-sm btn-outline-primary"
                                    href="<?= $ab ?>/comunidades/documentos/<?= (int) ($d['id'] ?? 0) ?>/download"
                                    title="Descargar"
                                ><i class="bi bi-download"></i></a>
                                <?php if ($isAdmin): ?>
                                    <form method="post" action="<?= $ab ?>/comunidades/documentos/<?= (int) ($d['id'] ?? 0) ?>" data-confirm="¿Eliminar este documento?">
                                        <input type="hidden" name="_method" value="DELETE">
                                        <input type="hidden" name="return_to" value="<?= $ab ?>/comunidades/<?= (int) ($c['id'] ?? 0) ?>#c-docs">
                                        <button class="btn btn-sm btn-outline-danger" type="submit" title="Quitar"><i class="bi bi-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="tab-pane fade" id="c-rl">
        <div class="subpanel rl-panel">
            <div class="subpanel-h d-flex justify-content-between align-items-center">
                <span>Informe de riesgos laborales</span>
                <?php if ($risk): ?>
                    <span class="badge <?= $riskBadge((string) $risk['status']) ?>">
                        <?= htmlspecialchars($riskLabel((string) $risk['status'])) ?>
                    </span>
                <?php else: ?>
                    <span class="badge text-bg-secondary">Sin informe RL</span>
                <?php endif; ?>
            </div>

            <div class="subpanel-b small">
                <?php if ($isAdmin): ?>
                    <?php if (!empty($pendingRlRequests)): ?>
                        <section class="rl-card mb-3" style="border-left: 3px solid var(--bs-warning);">
                            <h4 class="rl-card-title d-flex align-items-center gap-2">
                                <i class="bi bi-bell-fill text-warning"></i>
                                Solicitudes pendientes del gestor
                                <span class="badge text-bg-warning ms-1"><?= count($pendingRlRequests) ?></span>
                            </h4>
                            <p class="rl-card-sub mb-3">El gestor de esta comunidad ha solicitado este informe. Puedes rechazar la solicitud o atenderla subiendo el informe.</p>
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($pendingRlRequests as $req): ?>
                                    <li class="d-flex align-items-start justify-content-between gap-3 py-2 border-bottom">
                                        <div>
                                            <div class="fw-semibold small"><?= htmlspecialchars((string) ($req['requester_name'] ?? '—')) ?></div>
                                            <div class="text-muted small"><?= htmlspecialchars((string) ($req['requester_email'] ?? '')) ?></div>
                                            <?php if (!empty($req['request_notes'])): ?>
                                                <div class="text-muted small fst-italic mt-1">"<?= htmlspecialchars((string) $req['request_notes']) ?>"</div>
                                            <?php endif; ?>
                                            <div class="text-muted" style="font-size:0.7rem;">
                                                <?= app_datetime((string) ($req['requested_at'] ?? null)) ?>
                                                &nbsp;·&nbsp;
                                                <?php $reqStatusLabel = match((string)$req['status']) { 'requested' => 'Pendiente', 'in_progress' => 'En proceso', default => ucfirst((string)$req['status']) }; ?>
                                                <span class="badge text-bg-secondary"><?= $reqStatusLabel ?></span>
                                            </div>
                                        </div>
                                        <form method="post" action="<?= $ab ?>/comunidades/<?= (int) ($c['id'] ?? 0) ?>/riesgos/requests/<?= (int) $req['id'] ?>/reject" data-confirm="¿Rechazar esta solicitud? El gestor será notificado.">
                                            <button class="btn btn-sm btn-outline-danger" type="submit">
                                                <i class="bi bi-x-circle me-1"></i> Rechazar
                                            </button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </section>
                    <?php endif; ?>
                    
                    <div class="rl-admin-grid mb-3">
                        <section class="rl-card">
                            <h4 class="rl-card-title">Subir o reemplazar informe</h4>
                            <p class="rl-card-sub mb-3">Carga la version mas reciente del informe RL de la comunidad.</p>
                            <form method="post" enctype="multipart/form-data" action="<?= $ab ?>/comunidades/<?= (int) ($c['id'] ?? 0) ?>/riesgos/upload" class="row g-2">
                                <div class="col-xl-8">
                                    <input type="file" name="report_file" class="form-control" required>
                                </div>
                                <div class="col-xl-4">
                                <button class="btn btn-success w-100" type="submit"><i class="bi bi-upload"></i></button>
                                </div>
                            </form>
                        </section>

                        <section class="rl-card">
                            <h4 class="rl-card-title">Estado y observaciones</h4>
                            <p class="rl-card-sub mb-3">Actualiza el estado operativo y las notas para el gestor.</p>
                            <form method="post" action="<?= $ab ?>/comunidades/<?= (int) ($c['id'] ?? 0) ?>/riesgos" class="row g-2">
                                <input type="hidden" name="_method" value="PUT">
                                <div class="col-12">
                                    <?php $currentStatus = (string) ($risk['status'] ?? 'pending'); ?>
                                    <select name="status" class="form-select" required>
                                        <option value="pending" <?= $currentStatus === 'pending' ? 'selected' : '' ?>>Pendiente</option>
                                        <option value="in_progress" <?= $currentStatus === 'in_progress' ? 'selected' : '' ?>>En proceso</option>
                                        <option value="completed" <?= $currentStatus === 'completed' ? 'selected' : '' ?>>Completado</option>
                                        <option value="rejected" <?= $currentStatus === 'rejected' ? 'selected' : '' ?>>Rechazado</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <textarea name="notes" class="form-control" rows="3" placeholder="Notas del informe RL"><?= htmlspecialchars((string) ($risk['notes'] ?? '')) ?></textarea>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-outline-primary w-100" type="submit"><i class="bi bi-check2-circle"></i></button>
                                </div>
                            </form>
                        </section>
                    </div>
                <?php endif; ?>

                <?php if (!$isAdmin): ?>
                    <div class="rl-admin-grid mb-3">
                        <section class="rl-card">
                            <h4 class="rl-card-title">Subir informe RL</h4>
                            <p class="rl-card-sub mb-3">
                                Puedes cargar tú mismo el informe de riesgos laborales de esta comunidad.
                                <?php if (!empty($risk) && !empty($risk['report_path'])): ?>
                                    Al subir uno nuevo, sustituirá el archivo actual.
                                <?php endif; ?>
                            </p>
                            <form method="post" enctype="multipart/form-data"
                                  action="<?= $ab ?>/comunidades/<?= (int) ($c['id'] ?? 0) ?>/riesgos/upload"
                                  class="row g-2">
                                <div class="col-xl-8">
                                    <input type="file" name="report_file" class="form-control" accept=".pdf,application/pdf" required>
                                </div>
                                <div class="col-xl-4">
                                    <button class="btn btn-success w-100" type="submit">
                                        <i class="bi bi-upload me-1"></i> Subir informe
                                    </button>
                                </div>
                            </form>
                        </section>

                        <section class="rl-card">
                            <h4 class="rl-card-title">Solicitar al administrador</h4>
                            <p class="rl-card-sub mb-3">Si prefieres que lo gestione el administrador, envía una solicitud.</p>
                            <form method="post" action="<?= $ab ?>/comunidades/<?= (int) ($c['id'] ?? 0) ?>/riesgos/request">
                                <div class="mb-2">
                                    <textarea name="request_notes" class="form-control form-control-sm" rows="2"
                                        placeholder="Notas para el administrador (opcional)"></textarea>
                                </div>
                                <button class="btn btn-warning btn-sm w-100" type="submit">
                                    <i class="bi bi-send me-1"></i> Solicitar informe RL al admin
                                </button>
                            </form>
                        </section>
                    </div>
                <?php endif; ?>

                <?php if ($risk): ?>
                    <section class="rl-file-card">
                        <div class="rl-file-head">
                            <div>
                                <p class="rl-file-label mb-1">Archivo actual</p>
                                <p class="rl-file-name mb-0"><?= htmlspecialchars((string) ($risk['report_filename'] ?? 'No disponible')) ?></p>
                            </div>
                            <?php if (!empty($risk['completed_at'])): ?>
                                <span class="rl-date-chip">Completado: <?= app_datetime((string) ($risk['completed_at'] ?? null)) ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($risk['report_path'])): ?>
                            <?php $previewRlUrl = htmlspecialchars((string) (($baseUrl ?? '') . ($risk['report_path'] ?? ''))); ?>
                            <div class="rl-actions mt-3">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary"
                                    data-file-preview
                                    data-file-preview-url="<?= $previewRlUrl ?>"
                                    data-file-preview-name="<?= htmlspecialchars((string) ($risk['report_filename'] ?? 'Informe RL')) ?>"
                                    title="Ver archivo"
                                ><i class="bi bi-eye"></i></button>
                                <a class="btn btn-sm btn-outline-primary" href="<?= $ab ?>/comunidades/<?= (int) ($c['id'] ?? 0) ?>/riesgos/download" title="Descargar"><i class="bi bi-download"></i></a>
                                <?php if ($isAdmin): ?>
                                    <form method="post" action="<?= $ab ?>/comunidades/<?= (int) ($c['id'] ?? 0) ?>/riesgos" data-confirm="¿Quitar informe RL de esta comunidad?">
                                        <input type="hidden" name="_method" value="DELETE">
                                        <button class="btn btn-sm btn-outline-danger" type="submit" title="Quitar"><i class="bi bi-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($risk['notes'])): ?>
                            <div class="rl-notes mt-3">
                                <p class="rl-file-label mb-1">Notas</p>
                                <p class="mb-0 text-muted"><?= htmlspecialchars((string) ($risk['notes'] ?? '')) ?></p>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php elseif ($isAdmin): ?>
                    <section class="rl-empty-state">
                        <p class="mb-1 fw-semibold">No hay informe RL cargado</p>
                        <p class="mb-0 text-muted">Cuando subas un archivo, aparecerá aquí con acciones de visualización y descarga.</p>
                    </section>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($isAdmin && !empty($available)): ?>
        <script>
    document.addEventListener('DOMContentLoaded', function () {
        const modalEl = document.getElementById('modalAssignNotPreferred');
        if (!modalEl || !window.bootstrap) return;

        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        const textEl = document.getElementById('modalAssignNotPreferredText');
        const confirmBtn = document.getElementById('modalAssignNotPreferredConfirm');
        if (!textEl || !confirmBtn) return;

        let pendingForm = null;

        document.querySelectorAll('[data-assign-not-preferred-warn="1"]').forEach((btn) => {
            btn.addEventListener('click', () => {
                pendingForm = btn.closest('form');
                const community = btn.dataset.communityName || 'la comunidad';
                const tech = btn.dataset.techName || 'este técnico';
                const reason = btn.dataset.reasonLabel || '';
                const comment = btn.dataset.comment || '';

                let msg = 'La comunidad «' + community + '» no está satisfecha con el técnico «' + tech + '»';
                if (reason) msg += ' por ' + reason.toLowerCase();
                msg += '.';
                if (comment) msg += ' Comentario: «' + comment + '».';
                msg += ' ¿Deseas asignarlo de todos modos?';

                textEl.textContent = msg;
                modal.show();
            });
        });

        confirmBtn.addEventListener('click', () => {
            if (pendingForm) pendingForm.submit();
            pendingForm = null;
            modal.hide();
        });

        modalEl.addEventListener('hidden.bs.modal', () => {
            pendingForm = null;
        });
    });
    </script>
    <?php endif; ?>
</div>