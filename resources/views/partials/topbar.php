<?php declare(strict_types=1);
$u = $_SESSION['user'] ?? [];
$display = trim((string) ($u['full_name'] ?? '')) !== '' ? (string) $u['full_name'] : (string) ($u['email'] ?? 'Usuario');
$role = (string) ($u['role'] ?? '');
$roleLabel = $role === 'admin' ? 'Administrador' : ($role === 'gestor' ? 'Gestor' : 'Usuario');
$bu = htmlspecialchars(rtrim((string) (($base ?? '') ?: '/cae-inpro/public'), '/'));
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