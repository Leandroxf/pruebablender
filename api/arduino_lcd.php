<?php
header('Content-Type: application/json; charset=utf-8');
$debug = 'D:\pruebablender\arduino_debug.log';

$titulo = isset($_GET['titulo']) ? $_GET['titulo'] : (isset($_POST['titulo']) ? $_POST['titulo'] : '');
$titulo = strip_tags($titulo);
$titulo = str_replace(array("\r", "\n", "\t"), ' ', $titulo);
$titulo = trim($titulo);

@file_put_contents($debug, date('Y-m-d H:i:s') . ' | RECIBIDO_BRUTO: [' . $titulo . ']' . PHP_EOL, FILE_APPEND);

if ($titulo === '') {
    echo json_encode(array('ok' => false, 'error' => 'sin_titulo'));
    @file_put_contents($debug, date('Y-m-d H:i:s') . ' | VACIO' . PHP_EOL, FILE_APPEND);
    exit;
}

// Limpiar pero SIN dejarlo vacío si es legible
$original = $titulo;
if (function_exists('iconv')) {
    $tmp = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $titulo);
    if ($tmp !== false && trim($tmp) !== '') {
        $titulo = $tmp;
    }
}
// Solo quitar caracteres realmente problemáticos
$titulo = preg_replace('/[^\x20-\x7E]/', ' ', $titulo);
$titulo = preg_replace('/\s+/', ' ', $titulo);
$titulo = trim($titulo);
if ($titulo === '') {
    $titulo = $original;
    $titulo = preg_replace('/\s+/', ' ', $titulo);
    $titulo = trim($titulo);
}
$titulo = substr($titulo, 0, 32);

$puerto = 'COM3';
@error_reporting(0);

$scriptPy = 'D:\pruebablender\arduino_send.py';
if (file_exists($scriptPy)) {
    $tmp = sys_get_temp_dir() . '\arduino_' . uniqid() . '.txt';
    @file_put_contents($tmp, $titulo);
    // Probar python y py
    $cmd = 'cd /d "D:\pruebablender" && (python "' . $scriptPy . '" < "' . $tmp . '" 2>&1 || py "' . $scriptPy . '" < "' . $tmp . '" 2>&1)';
    @exec($cmd, $outPy, $codePy);
    @unlink($tmp);
    $outStr = implode(' ', $outPy);
    @file_put_contents($debug, date('Y-m-d H:i:s') . ' | ENVIADO: [' . $titulo . '] | OUT: ' . $outStr . ' | CODE: ' . $codePy . PHP_EOL, FILE_APPEND);
    if (stripos($outStr, 'OK') !== false) {
        echo json_encode(array('ok' => true, 'metodo' => 'python', 'puerto' => $puerto, 'titulo' => $titulo));
        exit;
    }
}

@exec("mode {$puerto} BAUD=9600 PARITY=N DATA=8 STOP=1 2>&1");
$com = "\\.\$puerto";
$fp = @fopen($com, "w");
if ($fp === false) { $fp = @fopen($puerto, "w"); }
if ($fp !== false) {
    @sleep(2);
    @fwrite($fp, $titulo . "\n");
    @fclose($fp);
    @file_put_contents($debug, date('Y-m-d H:i:s') . ' | DIRECTO: [' . $titulo . ']' . PHP_EOL, FILE_APPEND);
    echo json_encode(array('ok' => true, 'metodo' => 'directo', 'puerto' => $puerto, 'titulo' => $titulo));
    exit;
}

echo json_encode(array('ok' => false, 'error' => 'no_enviado'));
