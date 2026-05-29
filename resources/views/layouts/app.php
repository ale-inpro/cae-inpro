<?php 

declare(strict_types=1);

$appCfg = require __DIR__ . '/../../../config/app.php';
$base = rtrim((string) ($appCfg['url'] ?? '/cae-inpro/public'), '/');
$basePath = rtrim((string) (parse_url($base, PHP_URL_PATH) ?? $base), '/');
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$isActive = static function (string $path) use ($basePath, $currentPath): string {
    return str_starts_with($currentPath, $basePath . $path) ? 'active' : '';
};

?>
<!doctype html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="app-base-url" content="<?= htmlspecialchars($base ?? '') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <title><?= isset($title) ? htmlspecialchars((string) $title) : 'CAE Inpro' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@2.0.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="<?= htmlspecialchars($base) ?>/assets/css/app.css" rel="stylesheet">
</head>
<body>
<div class="app-shell d-flex">
    <?php $this->partial('partials.sidebar', ['base' => $base, 'isActive' => $isActive]); ?>

    <div class="content flex-grow-1">
        <?php $this->partial('partials.topbar', ['title' => $title ?? '', 'base' => $base]); ?>

        <?php $this->partial('partials.flash'); ?>

        <main class="container-fluid py-4">
            <div class="panel p-4">
                <?= $content ?? '' ?>
            </div>
        </main>
    </div>
</div>

<?php require __DIR__ . '/../partials/doc_analyze_overlay.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="<?= htmlspecialchars($base) ?>/assets/js/main-dashboard.js"></script>
<script src="<?= htmlspecialchars($base) ?>/assets/js/rgpd-dashboard.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@2.0.8/js/dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@2.0.8/js/dataTables.bootstrap5.min.js"></script>
<script src="<?= htmlspecialchars($base) ?>/assets/js/app.js"></script>
<script src="<?= htmlspecialchars($base) ?>/assets/js/doc-analyze-overlay.js"></script>
<script src="<?= htmlspecialchars($base) ?>/assets/js/modules/tables.js"></script>
</body>
</html>