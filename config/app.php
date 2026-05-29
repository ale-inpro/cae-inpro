<?php

declare(strict_types=1);

return [
    'name' => 'CAE Inpro',
    'env' => 'local',
    'url' => 'http://localhost/cae-inpro/public',

    /*
    |--------------------------------------------------------------------------
    | Python executable path
    |--------------------------------------------------------------------------
    | Ruta absoluta al ejecutable Python 3.
    | - Desarrollo Windows XAMPP: ruta completa al python.exe real
    | - Producción Linux:         '/usr/bin/python3'  (o dejar vacío para autodetectar)
    | NO usar la ruta del stub de WindowsApps — no funciona desde servicios Apache.
    */
    'python_path' => 'C:\\Users\\aleja\\AppData\\Local\\Python\\pythoncore-3.14-64\\python.exe',

    
    /*
    | AEAT CotejoInternetV1 — certificado empresa INPRO (producción)
    */
    'aeat_cotejo_debug_enabled' => true,  // false en producción si no quieres el endpoint de prueba
    'aeat_cotejo_endpoint' => 'https://www10.agenciatributaria.gob.es/wlpl/KATA-APLI/CotejoInternetV1SOAP',
    'aeat_cotejo_client_cert_path' => 'C:\\xampp\\htdocs\\cae-inpro\\storage\\certs\\CERTIFICADO_INPRO.p12',
    'aeat_cotejo_client_cert_password' => 'IP@2026_ek',
    'aeat_cotejo_ca_bundle' => '',
    'aeat_cotejo_use_mock' => false,   // ← imprescindible: desactiva la simulación
    'aeat_cotejo_mock_scenario' => 'success',
    // success | not_found | revoked | not_cotejable | transport_error
    'aeat_csv_auto_verify_document_type_ids' => [],
    // IDs extra (además del tipo «Certificado de estar al corriente con Hacienda») para verificación tras subida PDF.
    // Hacienda se verifica siempre tras publicarse el complementario; [] aquí está bien si no necesitas otros tipos.
];