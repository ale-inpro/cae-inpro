<?php declare(strict_types=1);
$reason = (string) ($reason ?? 'Enlace no disponible.');
?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="alert alert-warning shadow-sm p-4 text-center">
            <h1 class="h5 mb-2">No se puede firmar</h1>
            <p class="mb-0"><?= htmlspecialchars($reason) ?></p>
        </div>
    </div>
</div>
