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

try {
    (new AdminController())->handle();
} catch (Throwable $error) {
    error_log($error->getMessage());
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="es"><meta charset="utf-8"><title>Error</title><body><h1>No se pudo abrir el panel</h1><p>Revisa el registro de errores de PHP y vuelve a intentarlo.</p><p><a href="/proyectodetodo/admin.php">Volver al panel administrador</a></p></body></html>';
}
