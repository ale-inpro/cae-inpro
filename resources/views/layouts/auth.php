<?php declare(strict_types=1);
$appCfg = require __DIR__ . '/../../../config/app.php';
$assetBase = rtrim((string) ($appCfg['url'] ?? '/cae-inpro/public'), '/');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($title) ? htmlspecialchars((string) $title) : 'CAE Inpro' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= htmlspecialchars($assetBase) ?>/assets/css/app.css" rel="stylesheet">
    <style>
        body{
            min-height:100vh;
            background:
                radial-gradient(900px 500px at 10% -10%, #d1fae5, transparent 60%),
                radial-gradient(900px 500px at 100% 0%, #ecfeff, transparent 60%),
                #f8fafc;
        }
    </style>
</head>
<body class="d-flex align-items-center">
<?php $this->partial('partials.flash'); ?>
<main class="container py-5">
    <?= $content ?? '' ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>