<?php declare(strict_types=1);
$state     = (string) ($state    ?? 'error');
$token     = (string) ($token    ?? '');
$baseUrl   = (string) ($baseUrl  ?? '');
$docs      = (array)  ($docs     ?? []);
$techName  = (string) ($techName ?? 'Técnico/a');
$formError = (string) ($formError ?? '');
$uploaded  = (int)    ($uploaded  ?? 0);
$msg       = (string) ($msg      ?? '');
$uploadUrl = rtrim($baseUrl, '/') . '/portal/' . $token . '/upload';
?>
<div class="portal-card">

<?php if ($state === 'error'): ?>
    <div class="card border-0 shadow-sm text-center p-5">
        <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size:3rem"></i>
        <h2 class="h4 mt-3 mb-2">Enlace no disponible</h2>
        <p class="text-muted"><?= htmlspecialchars($msg) ?></p>
    </div>

<?php elseif ($state === 'used'): ?>
    <div class="card border-0 shadow-sm text-center p-5">
        <i class="bi bi-check-circle-fill text-success" style="font-size:3rem"></i>
        <h2 class="h4 mt-3 mb-2">Documentos ya enviados</h2>
        <p class="text-muted"><?= htmlspecialchars($msg) ?></p>
    </div>

<?php elseif ($state === 'success'): ?>
    <div class="card border-0 shadow-sm text-center p-5">
        <i class="bi bi-check-circle-fill text-success" style="font-size:3.5rem"></i>
        <h2 class="h4 mt-3 mb-2">¡Documentos enviados!</h2>
        <p class="text-muted">
            Hemos recibido <strong><?= $uploaded ?> documento(s)</strong> correctamente.<br>
            El administrador los revisará próximamente. Gracias, <strong><?= htmlspecialchars($techName) ?></strong>.
        </p>
    </div>

<?php else: /* state === 'form' */ ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4 p-md-5">

            <h2 class="h5 fw-semibold mb-1">Hola, <?= htmlspecialchars($techName) ?></h2>
            <p class="text-muted small mb-4">
                El administrador necesita que subas la siguiente documentación para gestionar tu CAE.
                Adjunta cada archivo y pulsa <strong>Enviar documentos</strong>.
            </p>

            <?php if ($formError !== ''): ?>
                <div class="alert alert-warning d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <?= htmlspecialchars($formError) ?>
                </div>
            <?php endif; ?>

            <?php
            $request = $request ?? [];
            $customMsg = trim((string) ($request['custom_message'] ?? ''));
            if ($customMsg !== ''): ?>
                <div class="alert alert-light border small mb-4">
                    <i class="bi bi-chat-left-text me-1 text-success"></i>
                    <strong>Mensaje del administrador:</strong><br>
                    <?= nl2br(htmlspecialchars($customMsg)) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= htmlspecialchars($uploadUrl) ?>" enctype="multipart/form-data">
                <div class="mb-4">
                    <?php foreach ($docs as $doc): ?>
                        <?php $dtId = (int) ($doc['id'] ?? 0); ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">
                                <i class="bi bi-file-earmark-text me-1 text-success"></i>
                                <?= htmlspecialchars((string) ($doc['name'] ?? '')) ?>
                            </label>
                            <input
                                type="file"
                                class="form-control"
                                name="files[<?= $dtId ?>]"
                                accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                            >
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="bi bi-upload me-2"></i>Enviar documentos
                    </button>
                </div>
            </form>

            <p class="text-center text-muted small mt-4 mb-0">
                <i class="bi bi-lock me-1"></i>
                Enlace personal · Un solo uso · Caduca en 7 días
            </p>
        </div>
    </div>
<?php endif; ?>

</div>