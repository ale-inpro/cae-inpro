<?php declare(strict_types=1);
$ab = htmlspecialchars($areaBaseUrl ?? '');
$step = (int) ($step ?? 1);
$wizard = $wizard ?? [];
$communities = $communities ?? [];
$templates = $templates ?? [];
$systemTemplateIds = $systemTemplateIds ?? [];
$preview = $preview ?? null;
$selectedCommunity = (int) ($wizard['community_id'] ?? 0);
$selectedTemplates = array_map('intval', (array) ($wizard['template_ids'] ?? []));
$audience = (string) ($wizard['audience'] ?? 'both');
?>
<div class="page-header mb-4">
    <div>
        <h1 class="h3 page-title mb-1">Envío masivo RGPD</h1>
        <p class="page-meta mb-0">Cada plantilla genera una solicitud y un correo independiente por vecino.</p>
    </div>
    <form method="post" action="<?= $ab ?>/rgpd/envio-masivo" class="d-inline">
        <input type="hidden" name="wizard_action" value="reset">
        <button type="submit" class="btn btn-outline-secondary btn-sm">Reiniciar</button>
    </form>
</div>

<div class="rgpd-wizard-stepper">
    <div class="rgpd-wizard-step <?= $step === 1 ? 'is-active' : '' ?>">1. Comunidad</div>
    <div class="rgpd-wizard-step <?= $step === 2 ? 'is-active' : '' ?>">2. Documentos</div>
    <div class="rgpd-wizard-step <?= $step === 3 ? 'is-active' : '' ?>">3. Confirmar</div>
</div>

<?php if ($step === 1): ?>
<form method="post" action="<?= $ab ?>/rgpd/envio-masivo" class="card border-0 shadow-sm">
    <input type="hidden" name="wizard_action" value="step1">
    <div class="card-body">
        <label class="form-label">Comunidad</label>
        <select name="community_id" class="form-select" required>
            <option value="">— Seleccionar —</option>
            <?php foreach ($communities as $c): ?>
                <option value="<?= (int) ($c['id'] ?? 0) ?>" <?= (int)($c['id']??0) === $selectedCommunity ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string) ($c['name'] ?? '')) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="card-footer bg-white border-0">
        <button type="submit" class="btn btn-success">Continuar</button>
    </div>
</form>
<?php endif; ?>

<?php if ($step === 2): ?>
<form method="post" action="<?= $ab ?>/rgpd/envio-masivo" class="card border-0 shadow-sm">
    <input type="hidden" name="wizard_action" value="step2">
    <div class="card-body">
        <p class="text-muted small">Seleccione las plantillas a enviar (recomendado: las 3 de sistema).</p>
        <div class="row g-3 mb-4">
            <?php foreach ($templates as $tpl): ?>
                <?php
                $tid = (int) ($tpl['id'] ?? 0);
                $checked = in_array($tid, $selectedTemplates, true)
                    || ($selectedTemplates === [] && in_array($tid, $systemTemplateIds, true));
                ?>
                <div class="col-md-6">
                    <label class="rgpd-template-card d-block mb-0 <?= $checked ? 'is-selected' : '' ?>">
                        <input type="checkbox" class="form-check-input me-2" name="template_ids[]" value="<?= $tid ?>" <?= $checked ? 'checked' : '' ?>>
                        <strong><?= htmlspecialchars((string) ($tpl['name'] ?? '')) ?></strong>
                        <span class="badge text-bg-light text-dark ms-1"><?= ($tpl['kind'] ?? '') === 'system' ? 'Sistema' : 'Usuario' ?></span>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>

        <label class="form-label">Audiencia</label>
        <select name="audience" class="form-select mb-3">
            <option value="both" <?= $audience === 'both' ? 'selected' : '' ?>>Todos los vecinos activos</option>
            <option value="owners" <?= $audience === 'owners' ? 'selected' : '' ?>>Solo propietarios</option>
            <option value="presidents" <?= $audience === 'presidents' ? 'selected' : '' ?>>Solo presidentes</option>
        </select>

        <label class="form-label">Notas internas (opcional)</label>
        <textarea name="notes" class="form-control form-control-sm" rows="2"><?= htmlspecialchars((string) ($wizard['notes'] ?? '')) ?></textarea>
    </div>
    <div class="card-footer bg-white border-0 d-flex gap-2">
        <a class="btn btn-outline-secondary" href="<?= $ab ?>/rgpd/envio-masivo?step=1">Atrás</a>
        <button type="submit" class="btn btn-success">Continuar</button>
    </div>
</form>
<?php endif; ?>

<?php if ($step === 3): ?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <?php if ($preview): ?>
            <ul class="mb-0">
                <li><strong><?= (int) ($preview['residents'] ?? 0) ?></strong> vecinos en la audiencia seleccionada</li>
                <li><strong><?= (int) ($preview['residents_with_email'] ?? 0) ?></strong> con envío por email activo</li>
                <li><strong><?= (int) ($preview['templates'] ?? 0) ?></strong> plantillas</li>
                <li>Se crearán hasta <strong><?= (int) ($preview['requests'] ?? 0) ?></strong> solicitudes de firma</li>
                <li>Correos previstos: <strong><?= (int) ($preview['emails_planned'] ?? 0) ?></strong> (sin email o solo postal no se envía correo)</li>
                <li class="text-muted small">Las solicitudes ya pendientes para el mismo vecino y plantilla se omiten.</li>
            </ul>
        <?php else: ?>
            <p class="text-warning mb-0">Complete los pasos anteriores antes de lanzar.</p>
        <?php endif; ?>
    </div>
</div>
<form method="post" action="<?= $ab ?>/rgpd/envio-masivo" onsubmit="return confirm('¿Lanzar campaña y enviar correos?');">
    <input type="hidden" name="wizard_action" value="launch">
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="<?= $ab ?>/rgpd/envio-masivo?step=2">Atrás</a>
        <button type="submit" class="btn btn-success" <?= $preview ? '' : 'disabled' ?>>Lanzar envío</button>
    </div>
</form>
<?php endif; ?>

<script>
document.querySelectorAll('.rgpd-template-card').forEach(function(card) {
    card.addEventListener('click', function(e) {
        if (e.target.tagName === 'INPUT') return;
        const cb = card.querySelector('input[type=checkbox]');
        if (cb) { cb.checked = !cb.checked; card.classList.toggle('is-selected', cb.checked); }
    });
    const cb = card.querySelector('input[type=checkbox]');
    if (cb) cb.addEventListener('change', function() { card.classList.toggle('is-selected', cb.checked); });
});
</script>
