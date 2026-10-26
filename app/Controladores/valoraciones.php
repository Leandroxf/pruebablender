<?php

// api/valoraciones.php — leer y guardar estrellitas.

$metodo = $_SERVER['REQUEST_METHOD'];
$votante = md5(session_id());

if ($metodo === 'GET') {
    $valoracion = new Valoracion();
    Respuesta::json(array('valoraciones' => $valoracion->todas($votante)));
}

if ($metodo !== 'POST') {
    header('Allow: GET, POST');
    Respuesta::json(array('error' => 'Método no permitido.'), 405);
}

if (!$sesion->haySesion()) {
    Respuesta::json(array('error' => 'Debes iniciar sesión para valorar los libros.'), 401);
}

$data = Respuesta::entrada('La solicitud de valoración');
$id = (int) ($data['id'] ?? 0);
$puntuacion = (int) ($data['valoracion'] ?? 0);
$idioma = (string) ($data['idioma'] ?? '');

$mensaje = 'Selecciona un libro y una valoración de 1 a 5 estrellas.';
Respuesta::exigir($id > 0, $mensaje);
Respuesta::exigir($idioma === 'en' || $idioma === 'es', $mensaje);
Respuesta::exigir($puntuacion >= 1 && $puntuacion <= 5, $mensaje);

$libro = new Libro();
$catalogo = $libro->cargar($idioma);
if (!isset($catalogo[$id]) && $libro->filaPago($id, $idioma) === null) {
    Respuesta::json(array('error' => 'El libro seleccionado no existe.'), 404);
}

$valoracion = new Valoracion();
$todas = $valoracion->guardar($id, $idioma, $votante, $puntuacion);

Respuesta::json(array('valoracion' => $todas[0]));
