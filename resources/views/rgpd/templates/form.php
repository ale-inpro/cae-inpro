<?php declare(strict_types=1);
$ab = htmlspecialchars($areaBaseUrl ?? '');
$bu = htmlspecialchars($baseUrl ?? '');
$mode = (string) ($mode ?? 'create');
$isEdit = $mode === 'edit';
$t = $template ?? [];
$errors = $errors ?? [];
$categories = $categories ?? [];
$tokens = $tokens ?? [];
$id = (int) ($t['id'] ?? 0);
$action = $isEdit ? $ab . '/rgpd/plantillas/' . $id : $ab . '/rgpd/plantillas';
$bodyHtml = (string) ($t['body_html'] ?? '');
$active = $t['is_active'] ?? true;
$activeOn = !isset($t['is_active']) || in_array($active, [true, 't', '1', 1, 'true'], true);
$previewSamples = \App\Services\Rgpd\RgpdTemplateTokens::previewSamples();
?>
<div class="page-header page-header--balanced page-header--premium mb-3">
    <div class="page-header-left">
        <h1 class="h3 page-title mb-1"><?= $isEdit ? 'Editar plantilla' : 'Nueva plantilla' ?></h1>
        <p class="page-meta mb-0">Redacte el documento con el editor visual. Use variables para personalizar cada envío.</p>
    </div>
    <div class="page-header-center"></div>
    <div class="page-header-right d-flex align-items-center gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="<?= $ab ?>/rgpd/plantillas" title="Volver"><i class="bi bi-arrow-left"></i></a>
    </div>
</div>

<form method="post"
      action="<?= $action ?>"
      id="rgpd-template-form"
      data-preview-samples="<?= htmlspecialchars(json_encode($previewSamples, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($isEdit): ?>
        <input type="hidden" name="_method" value="PUT">
    <?php endif; ?>
    <input type="hidden" name="body_html" id="body_html" value="<?= htmlspecialchars($bodyHtml, ENT_QUOTES, 'UTF-8') ?>">

    <div class="subpanel mb-3">
        <div class="subpanel-h">Datos de la plantilla</div>
        <div class="subpanel-b">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Nombre de la plantilla <span class="text-danger">*</span></label>
                    <input name="name" class="form-control" value="<?= htmlspecialchars((string) ($t['name'] ?? '')) ?>" required>
                    <?php if (!empty($errors['name'])): ?><div class="text-danger small"><?= htmlspecialchars($errors['name']) ?></div><?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Categoría <span class="text-danger">*</span></label>
                    <select name="category" class="form-select" required>
                        <option value="">— Seleccionar —</option>
                        <?php foreach ($categories as $key => $label): ?>
                            <option value="<?= htmlspecialchars($key) ?>" <?= ($t['category'] ?? '') === $key ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Descripción</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Uso interno: para qué sirve esta plantilla"><?= htmlspecialchars((string) ($t['description'] ?? '')) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="subpanel mb-3">
        <div class="subpanel-h d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span>Contenido <span class="text-danger">*</span></span>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="rgpdTplPreviewBtn">
                <i class="bi bi-eye me-1"></i> Vista previa
            </button>
        </div>
        <div class="subpanel-b">
            <label class="form-label small text-muted mb-2">Insertar variable</label>
            <div class="rgpd-token-bar d-flex flex-wrap gap-1 mb-3">
                <?php foreach ($tokens as $tok): ?>
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary rgpd-token-btn"
                            data-insert-token="<?= htmlspecialchars($tok['token']) ?>">
                        <?= htmlspecialchars($tok['token']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <div id="tpl_body_editor"><?= $bodyHtml ?></div>
            <?php if (!empty($errors['body_html'])): ?><div class="text-danger small mt-2"><?= htmlspecialchars($errors['body_html']) ?></div><?php endif; ?>
            <p class="small text-muted mt-2 mb-0">Las variables se sustituyen al enviar el documento a cada vecino.</p>
        </div>
    </div>

    <div class="subpanel mb-3">
        <div class="subpanel-b">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="tpl_active" <?= $activeOn ? 'checked' : '' ?>>
                <label class="form-check-label" for="tpl_active">Activar plantilla inmediatamente</label>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="<?= $ab ?>/rgpd/plantillas">
            <i class="bi bi-x-lg me-1"></i> Cancelar
        </a>
        <button type="submit" class="btn btn-success btn-sm">
            <i class="bi bi-check-lg me-1"></i> Guardar plantilla
        </button>
    </div>
</form>

<div class="modal fade" id="rgpdTplPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title h6 mb-0">Vista previa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body pt-0">
                <div id="rgpdTplPreviewBody" class="rgpd-doc-preview border rounded-3 p-4 bg-white"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.5/tinymce.min.js"></script>
<script src="<?= $bu ?>/assets/js/rgpd-template-editor.js"></script>