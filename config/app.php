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
    |--------------------------------------------------------------------------
    | AEAT CotejoInternetV1 (pruebas con certificado de sello → prewww10)
    |--------------------------------------------------------------------------
    */
    'aeat_cotejo_debug_enabled' => true,
    'aeat_cotejo_endpoint' => 'https://prewww10.aeat.es/wlpl/KATA-APLI/CotejoInternetV1SOAP',
    'aeat_cotejo_client_cert_path' => 'C:\\xampp\\htdocs\\cae-inpro\\storage\\certs\\aeat-pre-sello.p12',
    'aeat_cotejo_client_cert_password' => 'password',
    'aeat_cotejo_ca_bundle' => '', // opcional: ruta a cacert.pem si falla la verificación SSL
    'aeat_cotejo_use_mock' => true,
    'aeat_cotejo_mock_scenario' => 'success',
    // success | not_found | revoked | not_cotejable | transport_error
    'aeat_csv_auto_verify_document_type_ids' => [],
    // IDs extra (además del tipo «Certificado de estar al corriente con Hacienda») para verificación tras subida PDF.
    // Hacienda se verifica siempre tras publicarse el complementario; [] aquí está bien si no necesitas otros tipos.
];