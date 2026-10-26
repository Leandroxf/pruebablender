<?php

// Conexión a MySQL con PDO: una sola conexión para toda la petición.

class BaseDatos
{
    private static $conexion = null;

    /** Devuelve la conexión ya abierta (la abre la primera vez). */
    public static function conexion()
    {
        if (self::$conexion !== null) {
            return self::$conexion;
        }

        $config = require PROJECT_ROOT . '/config/database.php';

        $dsn = 'mysql:host=' . $config['host'] . ';dbname=' . $config['database'] . ';charset=utf8mb4';
        self::$conexion = new PDO($dsn, $config['user'], $config['password']);
        self::$conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$conexion->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        self::crearTablas();

        return self::$conexion;
    }

    /** Crea las tablas si todavía no existen. */
    private static function crearTablas()
    {
        $sql_usuarios = 'CREATE TABLE IF NOT EXISTS usuarios (
            id_usuario INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nombre VARCHAR(100) NOT NULL,
            correo VARCHAR(150) NOT NULL,
            contrasenia VARCHAR(255) NOT NULL,
            rol VARCHAR(30) NOT NULL DEFAULT \'cliente\',
            fecha_alta TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_usuario),
            UNIQUE KEY usuarios_nombre_unico (nombre),
            UNIQUE KEY usuarios_correo_unico (correo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $sql_compras = 'CREATE TABLE IF NOT EXISTS compras_libros (
            id_usuario INT UNSIGNED NOT NULL,
            libro_id INT NOT NULL,
            idioma VARCHAR(2) NOT NULL,
            precio_pagado DECIMAL(10,2) NOT NULL,
            fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id_usuario, libro_id, idioma)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $sql_valoraciones = 'CREATE TABLE IF NOT EXISTS valoraciones_libros (
            libro_id INT NOT NULL,
            idioma VARCHAR(2) NOT NULL,
            usuario_hash CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            puntuacion TINYINT UNSIGNED NOT NULL,
            actualizado TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (libro_id, idioma, usuario_hash),
            CONSTRAINT chk_valoraciones_puntuacion CHECK (puntuacion BETWEEN 1 AND 5)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $consultas = array($sql_usuarios, $sql_compras, $sql_valoraciones);
        foreach ($consultas as $sql) {
            self::$conexion->exec($sql);
        }
    }
}
