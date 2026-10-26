<?php

// Todo el proyecto se carga desde acá: funciones + sesión.

define('PROJECT_ROOT', dirname(__DIR__));

require_once __DIR__ . '/funciones/general.php';
require_once __DIR__ . '/funciones/db.php';
require_once __DIR__ . '/funciones/modelo.php';
require_once __DIR__ . '/funciones/sesion.php';
require_once __DIR__ . '/funciones/usuarios.php';
require_once __DIR__ . '/funciones/libros.php';
require_once __DIR__ . '/funciones/compras.php';
require_once __DIR__ . '/funciones/valoraciones.php';

// Sesión de PHP
$sesion = new Sesion();

// Navegador siempre debe pedir la página de nuevo
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
