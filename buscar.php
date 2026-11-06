<?php
$com = "COM3";
$ean = isset($_GET['ean']) ? trim($_GET['ean']) : '';
$ean = preg_replace('/[^0-9Xx]/', '', $ean);

$archivo = __DIR__ . '/api/libros.json';
$libros = array();
if (file_exists($archivo)) {
    $json = file_get_contents($archivo);
    $libros = json_decode($json, true);
    if (!is_array($libros)) {
        $libros = array();
    }
}

$tituloEncontrado = '';

// Buscar por ISBN
foreach ($libros as $id => $libro) {
    $isbnLibro = '';
    if (isset($libro['cover']) && preg_match('/isbn\/([0-9Xx]+)/i', $libro['cover'], $m)) {
        $isbnLibro = strtoupper(preg_replace('/[^0-9Xx]/', '', $m[1]));
    }
    if (isset($libro['isbn'])) {
        $isbnLibro2 = strtoupper(preg_replace('/[^0-9Xx]/', '', $libro['isbn']));
        if ($isbnLibro2 !== '') {
            $isbnLibro = $isbnLibro === '' ? $isbnLibro2 : $isbnLibro;
        }
    }
    if ($isbnLibro !== '' && strtoupper($ean) !== '' && $isbnLibro === strtoupper($ean)) {
        if (isset($libro['titulo'])) {
            $tituloEncontrado = $libro['titulo'];
            break;
        }
    }
    if ($ean !== '' && $id === $ean && isset($libro['titulo'])) {
        $tituloEncontrado = $libro['titulo'];
        break;
    }
}

// Buscar por titulo si viene por GET
if ($tituloEncontrado === '' && isset($_GET['titulo'])) {
    $tituloEncontrado = strip_tags($_GET['titulo']);
}

if ($tituloEncontrado !== '') {
    if (function_exists('iconv')) {
        $tmp = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $tituloEncontrado);
        if ($tmp !== false) {
            $tituloEncontrado = $tmp;
        }
    }
    $tituloEncontrado = preg_replace('/[^\x20-\x7E]/', ' ', $tituloEncontrado);
    $tituloEncontrado = preg_replace('/\s+/', ' ', $tituloEncontrado);
    $tituloEncontrado = trim($tituloEncontrado);
    $tituloEncontrado = substr($tituloEncontrado, 0, 32);
    $mensaje = $tituloEncontrado . ".\n";
} else {
    $mensaje = "error.\n";
}

@error_reporting(0);

@exec("mode $com: BAUD=9600 PARITY=N DATA=8 STOP=1 2>&1");
$completo = "\\.\$com";
$puerto = @fopen($completo, "w");
if ($puerto === false) {
    $puerto = @fopen($com . ":", "w");
}
if ($puerto === false) {
    $puerto = @fopen($com, "w");
}

if ($puerto !== false) {
    @sleep(2);
    @fwrite($puerto, $mensaje);
    @fclose($puerto);
}

header("Location: index.php");
exit;
