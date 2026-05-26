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
            LIMIT 5
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
        <?php $notifUrl = $bu . '/' . htmlspecialchars($role) . '/notificaciones'; ?>
        <div class="dropdown app-notif-wrap">
            <a
                class="btn btn-warning btn-sm position-relative app-notif-btn"
                href="<?= $notifUrl ?>"
                title="Notificaciones"
                aria-label="Ir a notificaciones"
            >
                <i class="bi bi-bell"></i>
                <?php if ($unreadCount > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger app-notif-badge" style="font-size: 0.6rem;">
                        <?= $unreadCount > 99 ? '99+' : $unreadCount ?>
                    </span>
                <?php endif; ?>
            </a>

            <div class="dropdown-menu dropdown-menu-end p-0 shadow app-notif-hover-menu app-notif-hover-card">
                <div class="app-notif-hover-head">
                    <strong>Última notificación</strong>
                </div>

                <div class="app-notif-list">
                    <?php if (empty($topNotifications)): ?>
                        <div class="app-notif-empty">
                            <i class="bi bi-bell-slash me-1"></i> No tienes notificaciones.
                        </div>
                    <?php else: ?>
                        <?php
                            $n = $topNotifications[0];
                            $nPayload  = json_decode((string) ($n['payload_json'] ?? '{}'), true) ?? [];
                            $nCommId   = (int) ($nPayload['community_id'] ?? 0);
                            $nType     = (string) ($n['type'] ?? '');
                            $nId       = (int) ($n['id'] ?? 0);
                            $adminTechTypes = ['technician_association_requested', 'technician_created_by_gestor'];
                            $adminCommTypes = ['rl_request_created', 'rl_report_uploaded_by_gestor', 'community_tech_not_preferred'];
                            $gestorTechTypes = ['technician_association_approved', 'technician_association_rejected'];
                            $isClickable = $nId > 0 && (
                                ($role === 'admin' && (
                                    in_array($nType, $adminTechTypes, true)
                                    || ($nCommId > 0 && in_array($nType, $adminCommTypes, true))
                                ))
                                || ($role === 'gestor' && in_array($nType, $gestorTechTypes, true))
                            );
                            $openUrl = $isClickable
                                ? $bu . '/' . ($role === 'admin' ? 'admin' : 'gestor') . '/notificaciones/' . $nId . '/open'
                                : null;
                            $nIsRead   = in_array($n['is_read'], [true, 't', '1'], true);
                            $readClass = $nIsRead ? 'text-muted' : 'fw-semibold';
                        ?>
                        <?php if ($openUrl): ?>
                        <a href="<?= htmlspecialchars($openUrl) ?>" class="app-notif-item text-decoration-none text-reset d-block">
                        <?php else: ?>
                        <div class="app-notif-item">
                        <?php endif; ?>
                            <div class="<?= $readClass ?> mb-1"><?= htmlspecialchars((string) $n['title']) ?></div>
                            <div class="fw-normal"><?= htmlspecialchars((string) $n['message']) ?></div>
                            <div class="text-muted app-notif-time">
                                <?= app_datetime((string) ($n['created_at'] ?? '')) ?>
                            </div>
                        <?php if ($openUrl): ?></a><?php else: ?></div><?php endif; ?>
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