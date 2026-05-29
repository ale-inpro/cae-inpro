<?php
$p = __DIR__ . '/storage/certs/CERTIFICADO_INPRO.p12';
$pass = 'IP@2026_ek'; // la misma que en config/app.php

if (!is_file($p)) {
    echo "Archivo no encontrado: $p\n";
    exit(1);
}

$raw = file_get_contents($p);
$certs = [];
$ok = openssl_pkcs12_read($raw, $certs, $pass);

if ($ok) {
    echo "OK: certificado P12 y contraseña validos\n";
    exit(0);
}

echo "ERROR:\n";
while ($e = openssl_error_string()) {
    echo $e . "\n";
}
exit(1);