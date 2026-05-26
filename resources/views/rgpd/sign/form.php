<?php declare(strict_types=1);
$req = $request ?? [];
$bu = htmlspecialchars($baseUrl ?? '');
$token = htmlspecialchars((string) ($token ?? ''));
$error = (string) ($error ?? '');
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-lg">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    <h1 class="h4 mb-1">Firma de documento RGPD</h1>
                    <p class="text-muted mb-0">
                        <?= htmlspecialchars((string) ($req['resident_name'] ?? '')) ?> ·
                        <?= htmlspecialchars((string) ($req['community_name'] ?? '')) ?>
                    </p>
                    <p class="small text-muted"><?= htmlspecialchars((string) ($req['template_name'] ?? '')) ?></p>
                </div>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-warning"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <div class="rgpd-doc-preview border rounded-3 p-3 mb-4 bg-light">
                    <?= (string) ($req['rendered_html'] ?? '') ?>
                </div>

                <form method="post" action="<?= $bu ?>/rgpd/firmar/<?= $token ?>" id="rgpdSignForm">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="accept_terms" value="1" id="accept_terms" required>
                        <label class="form-check-label" for="accept_terms">
                            He leído el documento y acepto su contenido.
                        </label>
                    </div>

                    <label class="form-label">Firma manuscrita (ratón o dedo)</label>
                    <div class="rgpd-signature-pad border rounded-3 bg-white mb-2">
                        <canvas id="rgpdSignatureCanvas" width="600" height="180"></canvas>
                    </div>
                    <div class="d-flex gap-2 mb-3">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="rgpdClearSig">Borrar firma</button>
                    </div>
                    <input type="hidden" name="signature_data" id="signature_data" value="">

                    <button type="submit" class="btn btn-success w-100">Firmar y enviar</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="<?= $bu ?>/assets/js/rgpd-sign.js"></script>
