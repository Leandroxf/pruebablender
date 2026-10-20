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
$route = trim((string)preg_replace('#^' . $projectPath . '(?=/|$)#i', '', $requestPath), '/');

$router = (new Router())
    ->add('', static function (): void { (new HomeController())->index(); })
    ->add('index.php', static function (): void { (new HomeController())->index(); })
    ->add('admin.php', static function (): void { (new AdminController())->handle(); })
    ->add('api/libro.php', static function (): void { (new BookController())->show(); })
    ->add('api/lista.php', static function (): void { (new CatalogController())->index(); })
    ->add('api/valoraciones.php', static function (): void { (new RatingController())->handle(); })
    ->add('api/registro.php', static function (): void { (new AuthController())->register(); })
    ->add('api/compra.php', static function (): void { (new PurchaseController())->handle(); });

try {
    $router->dispatch($route, static function () use ($route): void {
        http_response_code(404);
        if (str_starts_with($route, 'api/')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Ruta no encontrada'], JSON_UNESCAPED_UNICODE);
        } else {
            echo 'Página no encontrada';
        }
    });
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
