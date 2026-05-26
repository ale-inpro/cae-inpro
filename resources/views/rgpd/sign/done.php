<?php declare(strict_types=1);
$req = $request ?? [];
?>
<div class="row justify-content-center">
    <div class="col-md-6 text-center">
        <div class="card border-0 shadow-lg p-5">
            <div class="display-4 text-success mb-3"><i class="bi bi-check-circle"></i></div>
            <h1 class="h4">Firma registrada</h1>
            <p class="text-muted mb-0">
                Gracias, <strong><?= htmlspecialchars((string) ($req['resident_name'] ?? '')) ?></strong>.
                Su consentimiento para <em><?= htmlspecialchars((string) ($req['template_name'] ?? '')) ?></em>
                ha quedado guardado correctamente.
            </p>
        </div>
    </div>
</div>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
