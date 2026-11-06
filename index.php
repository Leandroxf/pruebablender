<?php

require_once __DIR__ . '/app/bootstrap.php';

$ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$ruta = ltrim($ruta, '/');
$carpeta = basename(PROJECT_ROOT);
if (strpos($ruta, $carpeta . '/') === 0) {
    $ruta = substr($ruta, strlen($carpeta) + 1);
}

$esApi = strpos($ruta, 'api/') === 0;

try {
    if ($ruta === '' || $ruta === 'index.php') {
        require PROJECT_ROOT . '/app/Vistas/home.php';
    } elseif ($ruta === 'admin.php') {
        require PROJECT_ROOT . '/app/Controladores/admin.php';
    } elseif ($ruta === 'api/libro.php') {
        require PROJECT_ROOT . '/app/Controladores/libro.php';
    } elseif ($ruta === 'api/lista.php') {
        Respuesta::json((new Libro())->resumen());
    } elseif ($ruta === 'api/arduino_lcd.php') {
        require PROJECT_ROOT . '/api/arduino_lcd.php';
        exit;
    } elseif ($ruta === 'api/valoraciones.php') {
        require PROJECT_ROOT . '/app/Controladores/valoraciones.php';
    } elseif ($ruta === 'api/registro.php') {
        require PROJECT_ROOT . '/app/Controladores/registro.php';
    } elseif ($ruta === 'api/compra.php') {
        require PROJECT_ROOT . '/app/Controladores/compra.php';
    } elseif ($esApi) {
        Respuesta::json(array('error' => 'Ruta no encontrada'), 404);
    } else {
        http_response_code(404);
        echo 'Página no encontrada';
    }
} catch (InvalidArgumentException $error) {
    Respuesta::json(array('error' => $error->getMessage()), 400);
} catch (Exception $error) {
    error_log($error->getMessage());
    if ($esApi) {
        Respuesta::json(array('error' => 'Ocurrió un error al procesar la solicitud.'), 500);
    }
    http_response_code(500);
    echo 'Ocurrió un error al cargar la página.';
}
