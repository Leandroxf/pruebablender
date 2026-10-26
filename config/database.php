<?php

// Datos de conexión a MariaDB. En Docker Compose se usan los nombres
// de servicio y las variables de entorno del archivo docker-compose.yml.

return array(
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'user' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
    'database' => getenv('DB_NAME') ?: 'proyectodetodo',
);
