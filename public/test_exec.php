<?php
// 1. ¿exec() está habilitado?
echo '<b>exec disponible:</b> ' . (function_exists('exec') ? 'SÍ' : 'NO') . '<br>';

// 2. ¿La ruta de Python existe?
$pyPath = 'C:\\Users\\aleja\\AppData\\Local\\Python\\pythoncore-3.14-64\\python.exe';
echo '<b>Python existe en ruta:</b> ' . (is_file($pyPath) ? 'SÍ' : 'NO') . '<br>';

// 3. ¿Apache puede ejecutarlo?
$out = []; $code = 0;
exec('"' . $pyPath . '" --version 2>&1', $out, $code);
echo '<b>Python --version:</b> ' . htmlspecialchars(implode('', $out)) . ' (exit: ' . $code . ')<br>';

// 4. ¿El script existe?
$script = realpath(__DIR__ . '/../scripts/extract_dates.py');
echo '<b>Script existe:</b> ' . ($script && is_file($script) ? 'SÍ → ' . $script : 'NO') . '<br>';

// 5. ¿Usuario con el que corre Apache?
echo '<b>Usuario Apache:</b> ' . get_current_user() . ' / ' . exec('whoami') . '<br>';

// 6. ¿El comando que genera PHP es correcto?
$scriptPath = 'C:\\xampp\\htdocs\\cae-inpro\\scripts\\extract_dates.py';
$testFile   = 'C:\\xampp\\htdocs\\cae-inpro\\no se.pdf';
$cmd = sprintf(
    '%s %s %s %s %s 2>&1',
    escapeshellcmd($pyPath),
    escapeshellarg($scriptPath),
    escapeshellarg($testFile),
    escapeshellarg('Póliza de Responsabilidad Civil'),
    escapeshellarg('application/pdf')
);
echo '<b>Comando generado:</b><pre>' . htmlspecialchars($cmd) . '</pre>';

$out2 = []; $code2 = 0;
exec($cmd, $out2, $code2);
echo '<b>Salida Python:</b><pre>' . htmlspecialchars(implode("\n", $out2)) . '</pre>';
echo '<b>Exit code:</b> ' . $code2;

echo '<b>OPcache activo:</b> ' . (function_exists('opcache_get_status') ? 'SÍ' : 'NO') . '<br>';
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo '<b>OPcache limpiado</b><br>';
}

// 7. ¿findPython() lee config/app.php correctamente?
$cfgPath = 'C:\\xampp\\htdocs\\cae-inpro\\config\\app.php';
$cfg = require $cfgPath;
echo '<b>Config tipo:</b> ' . gettype($cfg) . '<br>';
echo '<b>python_path en config:</b> ' . htmlspecialchars(is_array($cfg) ? ($cfg['python_path'] ?? 'NO EXISTE') : 'NO ES ARRAY') . '<br>';

// 8. ¿El archivo subido existe en la ruta que ve el servicio?
// Copia aquí la ruta de uno de los archivos de cae-docs que acaba de subirse
$uploadedFile = 'C:\\xampp\\htdocs\\cae-inpro\\public\\uploads\\cae-docs\\1\\';
$files = glob($uploadedFile . '*.pdf');
echo '<b>Archivos en cae-docs/1:</b> ' . count($files) . '<br>';
if (!empty($files)) {
    $testFile2 = $files[0];
    echo '<b>Probando con:</b> ' . htmlspecialchars($testFile2) . '<br>';
    $out3 = []; $code3 = 0;
    $cmd2 = sprintf('"%s" "%s" "%s" "%s" "%s" 2>&1',
        $pyPath, $scriptPath, $testFile2,
        'Certificado de estar al corriente con Seguridad Social',
        'application/pdf'
    );
    exec($cmd2, $out3, $code3);
    $raw = implode('', $out3);
    echo '<b>JSON válido:</b> ' . (json_decode($raw) ? 'SÍ' : 'NO (raw: ' . htmlspecialchars(substr($raw, 0, 200)) . ')') . '<br>';
    echo '<b>ok=true:</b> ' . (!empty(json_decode($raw, true)['ok']) ? 'SÍ' : 'NO') . '<br>';
}

// 9. Test exacto del nuevo tmpFile (mismo código que el servicio ahora usa)
$tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cae_test_' . uniqid() . '.json';
echo '<b>Temp dir:</b> ' . htmlspecialchars(sys_get_temp_dir()) . '<br>';
echo '<b>Temp file path:</b> ' . htmlspecialchars($tmpFile) . '<br>';
echo '<b>Temp dir escribible:</b> ' . (is_writable(sys_get_temp_dir()) ? 'SÍ' : 'NO') . '<br>';

// Usa el mismo archivo que existe en cae-docs
$uploadedFiles = glob('C:\\xampp\\htdocs\\cae-inpro\\public\\uploads\\cae-docs\\1\\*.pdf');
$testFile3 = !empty($uploadedFiles) ? $uploadedFiles[0] : '';

if ($testFile3) {
    $cmd3 = sprintf('%s %s %s %s %s',
        escapeshellcmd($pyPath),
        escapeshellarg($scriptPath),
        escapeshellarg($testFile3),
        escapeshellarg('Certificado de estar al corriente con Seguridad Social'),
        escapeshellarg('application/pdf')
    );
    $fullCmd = $cmd3 . ' > ' . escapeshellarg($tmpFile) . ' 2>&1';
    echo '<b>Comando completo:</b><pre>' . htmlspecialchars($fullCmd) . '</pre>';
    
    $returnCode3 = 0;
    exec($fullCmd, $unused3, $returnCode3);
    echo '<b>Exit code:</b> ' . $returnCode3 . '<br>';
    echo '<b>Temp file creado:</b> ' . (is_file($tmpFile) ? 'SÍ' : 'NO') . '<br>';
    if (is_file($tmpFile)) {
        $content = (string) file_get_contents($tmpFile);
        echo '<b>Tamaño:</b> ' . strlen($content) . ' bytes<br>';
        $json4 = json_decode($content, true);
        echo '<b>JSON válido:</b> ' . (is_array($json4) ? 'SÍ' : 'NO') . '<br>';
        echo '<b>ok=true:</b> ' . (!empty($json4['ok']) ? 'SÍ' : 'NO') . '<br>';
        @unlink($tmpFile);
    } else {
        echo '<b style="color:red">El fichero temporal NO se creó — problema de permisos o el redirect > no funciona</b><br>';
    }
}

// 10. Diagnosticar por qué json_decode falla en el tmpFile
$diagFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cae_diag_' . uniqid() . '.json';
$cmdDiag = sprintf('%s %s %s %s %s',
    escapeshellcmd($pyPath),
    escapeshellarg($scriptPath),
    escapeshellarg($testFile3),
    escapeshellarg('Certificado de estar al corriente con Seguridad Social'),
    escapeshellarg('application/pdf')
);
exec($cmdDiag . ' > ' . escapeshellarg($diagFile) . ' 2>&1');
if (is_file($diagFile)) {
    $content = (string) file_get_contents($diagFile);
    echo '<b>Tamaño:</b> ' . strlen($content) . ' bytes<br>';
    echo '<b>Primeros 300 chars:</b><pre>' . htmlspecialchars(substr($content, 0, 300)) . '</pre>';
    echo '<b>Últimos 100 chars:</b><pre>' . htmlspecialchars(substr($content, -100)) . '</pre>';
    json_decode($content);
    echo '<b>Error JSON:</b> ' . json_last_error_msg() . '<br>';
    // Detectar encoding
    echo '<b>Es UTF-8 válido:</b> ' . (mb_detect_encoding($content, 'UTF-8', true) === 'UTF-8' ? 'SÍ' : 'NO') . '<br>';
    @unlink($diagFile);
}