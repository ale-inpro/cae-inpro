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
];