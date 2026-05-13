<?php declare(strict_types=1);
$appCfg    = require __DIR__ . '/../../../config/app.php';
$assetBase = rtrim((string) ($appCfg['url'] ?? '/cae-inpro/public'), '/');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($title) ? htmlspecialchars((string) $title) : 'Portal CAE' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="<?= htmlspecialchars($assetBase) ?>/assets/css/app.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: radial-gradient(900px 500px at 10% -10%, #d1fae5, transparent 60%),
                        radial-gradient(900px 500px at 100% 0%, #ecfeff, transparent 60%), #f8fafc;
        }
        .portal-header {
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            padding: .85rem 0;
            margin-bottom: 2.5rem;
        }
        .portal-card { max-width: 680px; margin: 0 auto; }
    </style>
</head>
<body>
<header class="portal-header">
    <div class="container">
        <div class="d-flex align-items-center gap-2">
            <img src="<?= htmlspecialchars($assetBase) ?>/img/logo.png" alt="INPRO" height="30"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='inline'">
            <span style="display:none;font-weight:700;color:#059669;font-size:1.1rem">INPRO</span>
            <span class="text-muted small ms-1">· Portal de documentos CAE</span>
        </div>
    </div>
</header>
<main class="container pb-5">
    <?= $content ?? '' ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>