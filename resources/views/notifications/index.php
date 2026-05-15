<?php declare(strict_types=1);
/** @var array<array<string, mixed>> $notifications */
$ab   = htmlspecialchars(rtrim($areaBaseUrl ?? '', '/'));
$role = (string) ($_SESSION['user']['role'] ?? 'gestor');

// Construye la URL de destino según el rol, tipo y payload.
// - Admin + rl_request_created → endpoint /open que marca como leída y redirige al #c-rl.
// - Gestor o cualquier otro tipo → sin enlace (se queda en notificaciones).
$notifUrl = static function (array $n) use ($ab, $role): string {
    if ($role !== 'admin') {
        return ''; // el gestor no navega desde notificaciones
    }

    $type = (string) ($n['type'] ?? '');
    $id   = (int) ($n['id'] ?? 0);

    if ($id > 0 && in_array($type, ['rl_request_created', 'rl_report_uploaded_by_gestor'], true)) {
        // Pasa por el endpoint que marca como leída y luego redirige
        return $ab . '/notificaciones/' . $id . '/open';
    }

    return '';
};
?>

<div class="panel-identity mb-3">
    <div class="panel-identity-icon"><i class="bi bi-bell-fill"></i></div>
    <div>
        <p class="panel-identity-kicker mb-1">Centro de alertas</p>
        <h2 class="panel-identity-title mb-0">Notificaciones</h2>
    </div>
</div>

<div class="page-header page-header--balanced page-header--premium mb-4">
    <div class="page-header-left">
        <h1 class="h4 page-title mb-1">Tus notificaciones</h1>
        <p class="page-meta mb-0">Alertas y mensajes del sistema.</p>
    </div>
    <div class="page-header-center"></div>
    <div class="page-header-right d-flex gap-2">
        <form method="post" action="<?= $ab ?>/notificaciones/read-all">
            <button class="btn btn-outline-secondary btn-sm" type="submit" title="Marcar todo como leído">
                <i class="bi bi-check2-all me-1"></i> Marcar leídas
            </button>
        </form>
        <?php if (!empty($notifications)): ?>
            <form method="post" action="<?= $ab ?>/notificaciones/delete-all" data-confirm="¿Eliminar todas las notificaciones? Esta acción no se puede deshacer.">
                <button class="btn btn-outline-danger btn-sm" type="submit" title="Eliminar todas">
                    <i class="bi bi-trash me-1"></i> Eliminar todas
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="subpanel">
    <div class="subpanel-b p-0">
        <?php if (empty($notifications)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-bell-slash fs-3 d-block mb-2"></i>
                No tienes notificaciones todavía.
            </div>
        <?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($notifications as $n): ?>
                    <?php
                    // PostgreSQL devuelve bool como 't'/'f' — convertir correctamente
                    $isRead  = in_array($n['is_read'] ?? false, [true, 't', '1'], true);
                    $created = date('d/m/Y H:i', strtotime((string) ($n['created_at'] ?? 'now')));
                    $destUrl = $notifUrl($n);
                    $icon = match ((string) ($n['type'] ?? '')) {
                        'rl_request_created'  => 'bi-file-earmark-diff text-warning',
                        'rl_report_uploaded'  => 'bi-file-earmark-arrow-up text-primary',
                        'rl_report_uploaded_by_gestor' => 'bi-file-earmark-arrow-up text-success',
                        'rl_report_completed' => 'bi-file-earmark-check text-success',
                        'rl_request_rejected',
                        'rl_report_rejected'  => 'bi-x-circle text-danger',
                        'rl_report_deleted'   => 'bi-trash text-secondary',
                        default               => 'bi-info-circle text-secondary',
                    };
                    ?>
                    <li class="list-group-item border-bottom py-3 d-flex align-items-start gap-3 <?= $isRead ? 'bg-light' : '' ?>">
                        <div class="fs-4 pt-1"><i class="bi <?= $icon ?>"></i></div>

                        <!-- Contenido clicable si tiene URL destino -->
                        <?php if ($destUrl !== ''): ?>
                            <a href="<?= htmlspecialchars($destUrl) ?>" class="flex-grow-1 text-decoration-none text-reset">
                        <?php else: ?>
                            <div class="flex-grow-1">
                        <?php endif; ?>
                                <div class="<?= $isRead ? 'text-muted' : 'fw-semibold' ?> mb-1">
                                    <?= htmlspecialchars((string) $n['title']) ?>
                                    <?php if ($destUrl !== ''): ?>
                                        <i class="bi bi-arrow-right-short text-muted" style="font-size:.85rem;"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="small <?= $isRead ? 'text-muted' : '' ?>">
                                    <?= htmlspecialchars((string) $n['message']) ?>
                                </div>
                                <small class="text-muted"><?= $created ?></small>
                        <?php if ($destUrl !== ''): ?>
                            </a>
                        <?php else: ?>
                            </div>
                        <?php endif; ?>

                        <!-- Acciones -->
                        <div class="d-flex gap-1 align-self-center">
                            <?php if (!$isRead): ?>
                                <form method="post" action="<?= $ab ?>/notificaciones/<?= (int) $n['id'] ?>/read">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Marcar como leída">
                                        <i class="bi bi-check-lg"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= $ab ?>/notificaciones/<?= (int) $n['id'] ?>/delete" data-confirm="¿Eliminar esta notificación?">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>