<?php declare(strict_types=1); ?>
<?php
$flash = $_SESSION['flash'] ?? null;
if ($flash) {
    unset($_SESSION['flash']);
}
?>
<?php if (!empty($flash['message'])): ?>
    <?php
    $type = $flash['type'] ?? 'info'; // success, danger, warning, info
    $title = $flash['title'] ?? 'Aviso';
    $toastClass = match ($type) {
        'success' => 'flash-toast--success',
        'danger' => 'flash-toast--danger',
        'warning' => 'flash-toast--warning',
        default => 'flash-toast--info',
    };
    ?>
    <div class="toast-stack">
        <div class="toast flash-toast <?= htmlspecialchars($toastClass) ?> border-0" role="alert" aria-live="assertive" aria-atomic="true" data-autohide="true" data-delay="4500">
            <div class="toast-header">
                <strong class="me-auto"><?= htmlspecialchars((string) $title) ?></strong>
                <small>ahora</small>
                <button type="button" class="btn-close ms-2 mb-1" data-bs-dismiss="toast" aria-label="Cerrar"></button>
            </div>
            <div class="toast-body">
                <?= htmlspecialchars((string) $flash['message']) ?>
            </div>
        </div>
    </div>
<?php endif; ?>