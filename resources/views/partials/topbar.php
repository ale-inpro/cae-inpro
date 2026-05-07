<?php declare(strict_types=1);
$u = $_SESSION['user'] ?? [];
$display = trim((string) ($u['full_name'] ?? '')) !== '' ? (string) $u['full_name'] : (string) ($u['email'] ?? 'Usuario');
$role = (string) ($u['role'] ?? '');
$roleLabel = $role === 'admin' ? 'Administrador' : ($role === 'gestor' ? 'Gestor' : 'Usuario');
$bu = htmlspecialchars(rtrim((string) (($base ?? '') ?: '/cae-inpro/public'), '/'));

// Campanita: cargar notificaciones del usuario actual
$topNotifications = [];
$unreadCount = 0;
$currentUserId = (int) ($u['id'] ?? 0);

if ($currentUserId > 0) {
    try {
        $pdo = \App\Core\Database::connection();

        $stmtN = $pdo->prepare("
            SELECT id, type, title, message, payload_json, is_read, created_at
            FROM notifications
            WHERE user_id = :uid
            ORDER BY created_at DESC
            LIMIT 8
        ");
        $stmtN->execute(['uid' => $currentUserId]);
        $topNotifications = $stmtN->fetchAll(\PDO::FETCH_ASSOC);

        $stmtC = $pdo->prepare("
            SELECT COUNT(*) FROM notifications
            WHERE user_id = :uid AND is_read = FALSE
        ");
        $stmtC->execute(['uid' => $currentUserId]);
        $unreadCount = (int) $stmtC->fetchColumn();
    } catch (\Throwable $e) {
        // Si falla la consulta de notificaciones, no rompemos la UI
    }
}
?>
<header class="app-topbar">
    <div class="app-topbar-title">
        <button
            class="btn btn-outline-secondary btn-sm app-mobile-menu-btn"
            type="button"
            data-sidebar-mobile-toggle
            aria-label="Abrir menú"
            title="Menú"
        >
            <i class="bi bi-list"></i>
        </button>
        <h1 class="app-topbar-heading"><?= htmlspecialchars((string) ($title ?? 'Panel')) ?></h1>
        <p class="app-topbar-sub">Gestión CAE · panel operativo</p>
    </div>

    <div class="app-topbar-actions">
        <span class="app-pill app-pill--ok">En línea</span>

        <!-- Campanita de notificaciones -->
        <div class="dropdown">
            <button
                class="btn btn-warning btn-sm position-relative app-notif-btn"
                type="button"
                data-bs-toggle="dropdown"
                aria-expanded="false"
                title="Notificaciones"
            >
                <i class="bi bi-bell"></i>
                <?php if ($unreadCount > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger app-notif-badge" style="font-size: 0.6rem;">
                        <?= $unreadCount > 99 ? '99+' : $unreadCount ?>
                    </span>
                <?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end p-2 shadow" style="min-width:320px; max-height:420px; overflow-y:auto;">
                <div class="d-flex justify-content-between align-items-center px-1 pb-1 border-bottom mb-2">
                    <strong class="small">Notificaciones</strong>
                    <a class="small text-decoration-none" href="<?= $bu ?>/<?= htmlspecialchars($role) ?>/notificaciones">Ver todas</a>
                </div>

                <div class="app-notif-list">
                    <?php if (empty($topNotifications)): ?>
                        <p class="text-muted small mb-0 px-1">No tienes notificaciones.</p>
                    <?php else: ?>
                        <?php foreach ($topNotifications as $n):
                            $nPayload  = json_decode((string) ($n['payload_json'] ?? '{}'), true) ?? [];
                            $nCommId   = (int) ($nPayload['community_id'] ?? 0);
                            $nType     = (string) ($n['type'] ?? '');
                            $isClickable = ($role === 'admin' && $nType === 'rl_request_created' && $nCommId > 0);
                            $openUrl   = $isClickable ? $bu . '/admin/notificaciones/' . (int)$n['id'] . '/open' : null;
                            // PostgreSQL devuelve bool como 't'/'f'; in_array maneja ambos casos
                            $nIsRead   = in_array($n['is_read'], [true, 't', '1'], true);
                            $readClass = $nIsRead ? 'text-muted' : 'fw-semibold';
                        ?>
                            <?php if ($openUrl): ?>
                            <a href="<?= htmlspecialchars($openUrl) ?>" class="px-2 py-2 small border-bottom d-block text-decoration-none text-reset">
                            <?php else: ?>
                            <div class="px-2 py-2 small border-bottom">
                            <?php endif; ?>
                                <div class="<?= $readClass ?> mb-1"><?= htmlspecialchars((string) $n['title']) ?></div>
                                <div class="fw-normal text-truncate"><?= htmlspecialchars((string) $n['message']) ?></div>
                                <div class="text-muted" style="font-size:0.7rem;">
                                    <?= date('d/m H:i', strtotime((string) $n['created_at'])) ?>
                                </div>
                            <?php if ($openUrl): ?></a><?php else: ?></div><?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Usuario -->
        <div class="dropdown">
            <button class="app-user-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="app-avatar" aria-hidden="true"><?= htmlspecialchars(strtoupper(substr($display, 0, 1))) ?></span>
                <span class="app-user-text">
                    <span class="app-user-name"><?= htmlspecialchars($display) ?></span>
                    <span class="app-user-role"><?= htmlspecialchars($roleLabel) ?></span>
                </span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0 app-user-menu">
                <li><span class="dropdown-item-text small text-muted"><?= htmlspecialchars((string) ($u['email'] ?? '')) ?></span></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="post" action="<?= $bu ?>/logout" class="px-3 py-1">
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">Cerrar sesión</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>