<?php

// Funciones de entrada y salida para toda la aplicación.

class Respuesta
{
    /** Manda una respuesta JSON y termina el programa. */
    public static function json($datos, $estado = 200)
    {
        http_response_code($estado);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos);
        exit;
    }

    /** Manda una redirección y termina el programa. */
    public static function redirigir($url = './')
    {
        header('Location: ' . $url, true, 303);
        exit;
    }

    /** Lee el JSON que mandó el navegador. Si está mal, corta con error 400. */
    public static function entrada($titulo)
    {
        $crudo = file_get_contents('php://input');
        $datos = json_decode($crudo, true);
        if (!is_array($datos)) {
            self::json(array('error' => $titulo . ' tiene un formato inválido.'), 400);
        }

        return $datos;
    }

    /** Si la condición falla, corta con error 400 y el mensaje dado. */
    public static function exigir($condicion, $mensaje)
    {
        if (!$condicion) {
            throw new InvalidArgumentException($mensaje);
        }
    }

    /** Idiomas que soporta el catálogo. */
    public static function idiomas()
    {
        return array('en', 'es');
    }
}
