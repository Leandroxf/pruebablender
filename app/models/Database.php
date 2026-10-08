<?php

class Database
{
    public static function connect(): mysqli
    {
        $config = require PROJECT_ROOT . '/config/database.php';
        $connection = new mysqli(
            $config['host'],
            $config['user'],
            $config['password'],
            $config['database']
        );

        if ($connection->connect_error) {
            throw new RuntimeException('No se pudo conectar a la base de datos: ' . $connection->connect_error);
        }

        if (!$connection->set_charset('utf8mb4')) {
            throw new RuntimeException('No se pudo configurar la codificación de la base de datos: ' . $connection->error);
        }

        return $connection;
    }
}
