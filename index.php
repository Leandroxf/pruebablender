<?php

require_once __DIR__ . '/app/bootstrap.php';

session_set_cookie_params([
    'httponly' => true,
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Lax',
]);
session_start();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$projectPath = '/' . preg_quote(basename(PROJECT_ROOT), '#');
$requestPath = preg_replace('#^' . $projectPath . '(?=/|$)#i', '', $requestPath);
$route = trim($requestPath, '/');

try {
    if ($route === '' || $route === 'index.php') {
        (new HomeController())->index();
    } elseif ($route === 'admin.php') {
        (new AdminController())->handle();
    } elseif ($route === 'api/libro.php') {
        (new BookController())->show();
    } elseif ($route === 'api/lista.php') {
        (new CatalogController())->index();
    } else {
        http_response_code(404);
        if (str_starts_with($route, 'api/')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Ruta no encontrada'], JSON_UNESCAPED_UNICODE);
        } else {
            echo 'Página no encontrada';
        }
    }
} catch (Throwable $error) {
    error_log($error->getMessage());
    http_response_code(500);
    if (str_starts_with($route, 'api/')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Ocurrió un error al procesar la solicitud.'], JSON_UNESCAPED_UNICODE);
    } else {
        echo 'Ocurrió un error al cargar la página.';
    }
}
