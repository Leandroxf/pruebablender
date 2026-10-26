<?php

// api/libro.php — un libro completo, con control de compra.

header('Access-Control-Allow-Origin: *');

$id = (int) ($_GET['id'] ?? 0);
$idioma = (string) ($_GET['lang'] ?? '');

Respuesta::exigir($id > 0, 'ID inválido.');
Respuesta::exigir($idioma === 'en' || $idioma === 'es', 'Idioma inválido.');

$libro = new Libro();
$compra = new Compra();

$fila = $libro->filaPago($id, $idioma);

if ($fila !== null) {
    if ($sesion->usuarioId() === null) {
        Respuesta::json(array('error' => 'Necesitás iniciar sesión para leer este libro.', 'requiere_compra' => true, 'libro' => $libro->metadatos($fila)), 401);
    }
    if (!$compra->posee($sesion->usuarioId(), $id, $idioma)) {
        Respuesta::json(array('error' => 'Este libro es de pago. Compralo para poder leerlo.', 'requiere_compra' => true, 'libro' => $libro->metadatos($fila)), 403);
    }

    Respuesta::json($libro->texto($fila));
}

$catalogo = $libro->cargar($idioma);
if (isset($catalogo[$id]) && is_array($catalogo[$id])) {
    $entrada = $catalogo[$id];
    $entrada['id'] = (int) $id;
    $entrada['idioma'] = $idioma;
    Respuesta::json($entrada);
}

Respuesta::json(array('error' => 'Libro no encontrado'), 404);
