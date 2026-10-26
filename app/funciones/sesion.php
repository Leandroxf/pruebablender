<?php

// Quién es el usuario que está navegando, y el token CSRF.

class Sesion
{
    public function __construct()
    {
        session_start();
    }

    public function esAdmin()
    {
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    }

    public function hayUsuario()
    {
        return isset($_SESSION['user_id']);
    }

    public function usuarioId()
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        return (int) $_SESSION['user_id'];
    }

    public function usuarioNombre()
    {
        if (isset($_SESSION['user_name'])) {
            return (string) $_SESSION['user_name'];
        }

        return '';
    }

    public function usuarioInicial()
    {
        return mb_substr($this->usuarioNombre(), 0, 1);
    }

    /** ¿Hay alguien adentro (admin o lector)? */
    public function haySesion()
    {
        return $this->esAdmin() || $this->hayUsuario();
    }

    /** Entra como administrador del panel con una cuenta interna para compras. */
    public function entrarAdmin($id = null, $nombre = 'Administrador')
    {
        $datos = array('is_admin' => true, 'user_name' => (string) $nombre);
        if ($id !== null) {
            $datos['user_id'] = (int) $id;
        }
        $this->reiniciar($datos);
    }

    /** Entra como lector con su cuenta. */
    public function entrarUsuario($id, $nombre)
    {
        $this->reiniciar(array('user_id' => (int) $id, 'user_name' => (string) $nombre));
    }

    /** Cierra la sesión de cualquiera. */
    public function salir()
    {
        $_SESSION = array();
    }

    /** Vacía la sesión anterior y deja solo los datos nuevos. */
    private function reiniciar($datos)
    {
        $_SESSION = array();
        foreach ($datos as $clave => $valor) {
            $_SESSION[$clave] = $valor;
        }
    }
}
