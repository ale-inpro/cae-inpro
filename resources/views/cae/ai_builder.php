<?php declare(strict_types=1);

$ab = $areaBaseUrl ?? '/cae-inpro/public/admin';
$base = $baseUrl ?? '/cae-inpro/public';
$t = $tech ?? [];
$docs = $existingDocs ?? [];
$gens = $generations ?? [];
$tid = (int) ($t['id'] ?? 0);
$name = htmlspecialchars((string) ($t['full_name'] ?? 'Técnico'));
?>

<div class="panel-identity mb-3">
    <div class="panel-identity-icon"><i class="bi bi-robot"></i></div>
    <div>
        <p class="panel-identity-kicker mb-1">IA generativa · CAE</p>
        <h2 class="panel-identity-title mb-0">Generar CAE con IA</h2>
    </div>
</div>

<div class="page-header page-header--balanced page-header--premium mb-3">
    <div class="page-header-left">
        <h1 class="h4 page-title mb-1"><?= $name ?></h1>
        <p class="page-meta mb-0">Selecciona fuentes documentales y genera un PDF profesional.</p>
    </div>
    <div class="page-header-center"></div>
    <div class="page-header-right">
        <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($ab) ?>/tecnicos/<?= $tid ?>/cae">Volver</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="subpanel">
            <div class="subpanel-h">Fuentes para generación IA</div>
            <div class="subpanel-b">
                <form method="post" action="<?= htmlspecialchars($ab) ?>/tecnicos/<?= $tid ?>/cae/ia/generate" enctype="multipart/form-data">
                    <label class="form-label fw-semibold">Documentos ya subidos</label>
                    <div class="row g-2 mb-3">
                        <?php if (empty($docs)): ?>
                            <p class="small text-muted mb-0">No hay documentos existentes.</p>
                        <?php else: ?>
                            <?php foreach ($docs as $d): ?>
                                <div class="col-md-6">
                                    <label class="form-check border rounded p-2 h-100">
                                        <input class="form-check-input me-2" type="checkbox" name="existing_doc_ids[]" value="<?= (int) $d['id'] ?>">
                                        <span class="form-check-label"><?= htmlspecialchars((string) $d['original_filename']) ?></span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <label class="form-label fw-semibold">Adjuntar nuevos documentos (opcional)</label>
                    <input type="file" name="new_docs[]" class="form-control mb-3" multiple>

                    <label class="form-label fw-semibold">Indicaciones para IA (opcional)</label>
                    <textarea name="extra_notes" class="form-control mb-3" rows="4" placeholder="Ej: priorizar aptitud médica y PRL."></textarea>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-stars me-1"></i> Generar CAE PDF con IA
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="subpanel">
            <div class="subpanel-h">Generaciones recientes</div>
            <div class="subpanel-b">
                <?php if (empty($gens)): ?>
                    <p class="small text-muted mb-0">Sin generaciones aún.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($gens as $g): ?>
                            <li class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="small fw-semibold">#<?= (int) $g['id'] ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars((string) ($g['generated_at'] ?? '-')) ?></div>
                                    </div>
                                    <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($ab) ?>/cae/ia/<?= (int) $g['id'] ?>/download">
                                        <i class="bi bi-download"></i>
                                    </a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>