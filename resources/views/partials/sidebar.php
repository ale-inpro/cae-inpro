<?php declare(strict_types=1);
/** @var string $base */
/** @var callable $isActive */
$role = (string) ($_SESSION['user']['role'] ?? '');
$home = $role === 'admin' ? $base . '/admin/dashboard' : $base . '/gestor/dashboard';

$isGestor = $role === 'gestor';
$areaPrefix = $isGestor ? '/gestor' : '/admin';

$dashboardPath = $areaPrefix . '/dashboard';
$techPath = $areaPrefix . '/tecnicos';
$communityPath = $areaPrefix . '/comunidades';

$dashboardActive = $isActive($dashboardPath) !== '';
$techActive = $isActive($techPath) !== '';
$communityActive = $isActive($communityPath) !== '';
$caeGroupActive = $dashboardActive || $techActive || $communityActive;

$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$rgpdBase = $areaPrefix . '/rgpd';
$rgpdActive = str_contains($reqPath, '/rgpd');
?>
<aside class="sidebar p-3 d-flex flex-column">
    <a class="app-brand mb-4" href="<?= htmlspecialchars($home) ?>">
        <img src="<?= htmlspecialchars($base) ?>/assets/img/logo-inpro.png" alt="INPRO" class="app-brand-logo" onerror="this.style.display='none';this.nextElementSibling.style.display='inline';">
        <span class="app-brand-fallback" style="display:none;">INPRO</span>
    </a>

    <div class="sidebar-nav-scroll">

    <?php if ($role === 'gestor' || $role === 'admin'): ?>
        <div class="small app-side-label mb-2">
            <?= $isGestor ? 'Panel gestor' : 'Panel administración' ?>
        </div>

        <nav class="nav flex-column mb-3 app-nav">
            <button
                type="button"
                class="nav-link app-nav-group-toggle <?= $caeGroupActive ? 'active' : '' ?>"
                data-sidebar-toggle="cae-group"
                aria-expanded="<?= $caeGroupActive ? 'true' : 'false' ?>"
            >
                <span class="app-nav-icon"><i class="bi bi-shield-check"></i></span>
                <span class="app-nav-text">CAE</span>
                <span class="app-nav-caret"><i class="bi bi-chevron-down"></i></span>
            </button>

            <div
                id="cae-group"
                class="app-nav-group <?= $caeGroupActive ? 'open' : '' ?>"
                data-open="<?= $caeGroupActive ? '1' : '0' ?>"
            >
                <a class="nav-link app-sub-link <?= $dashboardActive ? 'active' : '' ?>" href="<?= htmlspecialchars($base . $dashboardPath) ?>">
                    <span class="app-nav-icon"><i class="bi bi-speedometer2"></i></span>
                    <span class="app-nav-text">Dashboard</span>
                </a>

                <a class="nav-link app-sub-link <?= $techActive ? 'active' : '' ?>" href="<?= htmlspecialchars($base . $techPath) ?>">
                    <span class="app-nav-icon"><i class="bi bi-people"></i></span>
                    <span class="app-nav-text">Técnicos</span>
                </a>

                <a class="nav-link app-sub-link <?= $communityActive ? 'active' : '' ?>" href="<?= htmlspecialchars($base . $communityPath) ?>">
                    <span class="app-nav-icon"><i class="bi bi-buildings"></i></span>
                    <span class="app-nav-text">Comunidades</span>
                </a>
            </div>
        </nav>

        <nav class="nav flex-column mb-3 app-nav">
            <button type="button"
                class="nav-link app-nav-group-toggle <?= $rgpdActive ? 'active' : '' ?>"
                data-sidebar-toggle="rgpd-group"
                aria-expanded="<?= $rgpdActive ? 'true' : 'false' ?>">
                <span class="app-nav-icon"><i class="bi bi-shield-lock"></i></span>
                <span class="app-nav-text">RGPD</span>
                <span class="app-nav-caret"><i class="bi bi-chevron-down"></i></span>
            </button>
            <div
                id="rgpd-group"
                class="app-nav-group <?= $rgpdActive ? 'open' : '' ?>"
                data-open="<?= $rgpdActive ? '1' : '0' ?>"
            >
                <a class="nav-link app-sub-link <?= preg_match('#/rgpd/?$#', $reqPath) ? 'active' : '' ?>"
                   href="<?= htmlspecialchars($base . $rgpdBase) ?>">
                    <span class="app-nav-icon"><i class="bi bi-speedometer2"></i></span>
                    <span class="app-nav-text">Resumen</span>
                </a>
                <a class="nav-link app-sub-link <?= str_contains($reqPath, '/rgpd/comunidades') ? 'active' : '' ?>"
                   href="<?= htmlspecialchars($base . $rgpdBase . '/comunidades') ?>">
                    <span class="app-nav-icon"><i class="bi bi-buildings"></i></span>
                    <span class="app-nav-text">Comunidades</span>
                </a>
                <a class="nav-link app-sub-link <?= str_contains($reqPath, '/rgpd/contratos') ? 'active' : '' ?>"
                   href="<?= htmlspecialchars($base . $rgpdBase . '/contratos') ?>">
                    <span class="app-nav-icon"><i class="bi bi-file-earmark-text"></i></span>
                    <span class="app-nav-text">Contratos RGPD</span>
                </a>
                <?php if ($role === 'admin'): ?>
                <a class="nav-link app-sub-link <?= str_contains($reqPath, '/rgpd/plantillas') ? 'active' : '' ?>"
                   href="<?= htmlspecialchars($base . $rgpdBase . '/plantillas') ?>">
                    <span class="app-nav-icon"><i class="bi bi-file-earmark-richtext"></i></span>
                    <span class="app-nav-text">Plantillas</span>
                </a>
                <?php endif; ?>
                <a class="nav-link app-sub-link <?= str_contains($reqPath, '/rgpd/envio-masivo') ? 'active' : '' ?>"
                   href="<?= htmlspecialchars($base . $rgpdBase . '/envio-masivo') ?>">
                    <span class="app-nav-icon"><i class="bi bi-send"></i></span>
                    <span class="app-nav-text">Envío masivo</span>
                </a>
            </div>
        </nav>
    <?php else: ?>
        <p class="small text-muted">Sesión sin rol válido.</p>
    <?php endif; ?>
    </div>

    <div class="mt-auto pt-4 small text-muted"><span class="app-version">v0.1</span></div>
</aside>