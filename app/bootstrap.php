<?php

define('PROJECT_ROOT', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $directories = [
        PROJECT_ROOT . '/app/Controladores/',
        PROJECT_ROOT . '/app/Modelos/',
    ];

    foreach ($directories as $directory) {
        $file = $directory . $class . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});
