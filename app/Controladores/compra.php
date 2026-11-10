<?php

// api/compra.php — comprar libros de pago y listar los comprados.

$metodo = $_SERVER['REQUEST_METHOD'];
$compra = new Compra();

if ($metodo === 'GET') {
    if ($sesion->usuarioId() === null) {
        Respuesta::json(array('error' => 'Debes iniciar sesión para comprar libros.'), 401);
    }
    Respuesta::json(array('compras' => array_values($compra->de($sesion->usuarioId()))));
}

if ($metodo !== 'POST') {
    header('Allow: GET, POST');
    Respuesta::json(array('error' => 'Método no permitido.'), 405);
}

if ($sesion->usuarioId() === null) {
    Respuesta::json(array('error' => 'Debes iniciar sesión para comprar libros.'), 401);
}

$data = Respuesta::entrada('La solicitud de compra');
$id = (int) ($data['id'] ?? 0);
$idioma = (string) ($data['idioma'] ?? '');

Respuesta::exigir($id > 0, 'El libro seleccionado no es válido.');
Respuesta::exigir($idioma === 'en' || $idioma === 'es', 'El libro seleccionado no es válido.');

$libro = new Libro();
$fila = $libro->filaPago($id, $idioma);
if ($fila === null) {
    Respuesta::json(array('error' => 'Ese libro no está disponible para compra.'), 404);
}
if ($compra->posee($sesion->usuarioId(), $id, $idioma)) {
    Respuesta::json(array('error' => 'Este libro ya está en tu biblioteca.'), 409);
}

try {
    $comprobante = $compra->comprar($sesion->usuarioId(), $id, $idioma, (float) $fila['precio']);
} catch (DomainException $error) {
    $filaActualizada = $libro->filaPago($id, $idioma);
    Respuesta::json(array(
        'error' => $error->getMessage(),
        'stock' => $filaActualizada === null || $filaActualizada['stock'] === null
            ? null
            : (int) $filaActualizada['stock'],
    ), 409);
}

$filaActualizada = $libro->filaPago($id, $idioma);

Respuesta::json(array(
    'mensaje' => '¡Compra realizada! El libro ya está en tu biblioteca.',
    'compra' => $comprobante,
    'stock' => $filaActualizada['stock'] === null ? null : (int) $filaActualizada['stock'],
    'compras' => array_values($compra->de($sesion->usuarioId())),
), 201);
