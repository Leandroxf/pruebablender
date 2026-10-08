<?php

return [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'user' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
    'database' => getenv('DB_NAME') ?: 'proyectodetodo',
];
