<?php

// api/registro.php — crear una cuenta nueva.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    Respuesta::json(array('error' => 'Método no permitido.'), 405);
}

if ($sesion->haySesion()) {
    Respuesta::json(array('error' => 'Ya hay una sesión activa. Cierra sesión antes de crear otra cuenta.'), 409);
}

$data = Respuesta::entrada('La solicitud de registro');
$nombre = trim((string) ($data['name'] ?? ''));
$correo = trim((string) ($data['email'] ?? ''));
$password = (string) ($data['password'] ?? '');

$largoNombre = strlen($nombre);
if ($largoNombre < 2 || $largoNombre > 100) {
    Respuesta::json(array('error' => 'El nombre debe tener entre 2 y 100 caracteres.'), 422);
}
if (strlen($correo) > 150 || strpos($correo, '@') === false) {
    Respuesta::json(array('error' => 'Ingresá un correo electrónico válido.'), 422);
}
$largoPassword = strlen($password);
if ($largoPassword < 8 || $largoPassword > 72) {
    Respuesta::json(array('error' => 'La contraseña debe tener entre 8 y 72 caracteres.'), 422);
}

$cuenta = new Usuario();
if ($cuenta->existeCorreo($correo)) {
    Respuesta::json(array('error' => 'Ya existe una cuenta con ese correo electrónico. Probá iniciar sesión.'), 422);
}
if ($cuenta->existeNombre($nombre)) {
    Respuesta::json(array('error' => 'Ya existe una cuenta con ese nombre de usuario. Elegí otro.'), 422);
}

$usuario = $cuenta->crear($nombre, $correo, $password);
$sesion->entrarUsuario($usuario['id'], $usuario['nombre']);

Respuesta::json(array(
    'usuario' => array('id' => $usuario['id'], 'nombre' => $usuario['nombre'], 'correo' => $usuario['correo']),
    'mensaje' => '¡Cuenta creada! Bienvenido, ' . $usuario['nombre'] . '.',
), 201);
