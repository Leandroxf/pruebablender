<?php

// Quién es el usuario que está navegando, y el token CSRF.

class Sesion
{
    public function __construct()
    {
        session_start();
        if (!isset($_SESSION['user_id']) && !empty($_COOKIE['recordarme'])) {
            $this->restaurarRecordarme();
        }
    }

    /** Reconoce las variantes del rol usadas por la base de datos. */
    public static function rolEsAdmin($rol)
    {
        $rol = strtolower(trim((string) $rol));
        return $rol === 'admin' || $rol === 'administrador';
    }

    public function esAdmin()
    {
        if (array_key_exists('is_admin', $_SESSION)) {
            return $_SESSION['is_admin'] === true;
        }

        if ($this->hayUsuario()) {
            $id = $this->usuarioId();
            if ($id !== null) {
                try {
                    $usuario = new Usuario();
                    $esAdmin = $usuario->esAdministrador($id);
                    $_SESSION['is_admin'] = $esAdmin;
                    if ($esAdmin) {
                        return true;
                    }
                } catch (Throwable $e) {
                    error_log('No se pudo verificar el rol de sesión: ' . $e->getMessage());
                }
            }
        }

        return false;
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

    /** Busca y consume tokens de sesión persistente de un solo dispositivo. */
    public function restaurarRecordarme()
    {
        if ($this->hayUsuario() || empty($_COOKIE['recordarme'])) {
            return false;
        }

        $partes = explode(':', (string) $_COOKIE['recordarme'], 2);
        if (count($partes) !== 2 || !preg_match('/^[a-f0-9]{32}$/', $partes[0]) || !preg_match('/^[a-f0-9]{64}$/', $partes[1])) {
            $this->borrarCookieRecordarme();
            return false;
        }

        try {
            $modelo = new ModeloSesionPersistente();
            $sesion = $modelo->consumirToken($partes[0], $partes[1]);
            if ($sesion === null) {
                $this->borrarCookieRecordarme();
                return false;
            }

            if (self::rolEsAdmin($sesion['rol'])) {
                $this->entrarAdmin($sesion['id'], $sesion['nombre']);
            } else {
                $this->entrarUsuario($sesion['id'], $sesion['nombre']);
            }
            $this->crearCookieRecordarme($sesion['id']);
            return true;
        } catch (Exception $e) {
            $this->borrarCookieRecordarme();
            return false;
        }
    }

    public function configurarRecordarme($idUsuario, $recordar)
    {
        if ($recordar) {
            $this->crearCookieRecordarme($idUsuario);
            return;
        }
        $this->revocarRecordarmeActual();
    }

    private function revocarRecordarmeActual()
    {
        if (!empty($_COOKIE['recordarme'])) {
            $partes = explode(':', (string) $_COOKIE['recordarme'], 2);
            if (count($partes) === 2 && preg_match('/^[a-f0-9]{32}$/', $partes[0])) {
                try {
                    (new ModeloSesionPersistente())->borrarToken($partes[0]);
                } catch (Exception $e) {
                    // La cookie se elimina igualmente aunque falle la revocación.
                }
            }
        }
        $this->borrarCookieRecordarme();
    }

    /** Cierra sesión y revoca la cookie persistente si existe. */
    public function salir()
    {
        $this->revocarRecordarmeActual();
        $_SESSION = array();
    }

    /** Emite una cookie persistente cuyo valor no se almacena en claro en la base. */
    public function crearCookieRecordarme($idUsuario)
    {
        $selector = bin2hex(random_bytes(16));
        $token = bin2hex(random_bytes(32));
        $vence = time() + 60 * 60 * 24 * 30;
        (new ModeloSesionPersistente())->guardarToken($selector, (int) $idUsuario, hash('sha256', $token), $vence);
        setcookie('recordarme', $selector . ':' . $token, $this->opcionesCookie($vence));
    }

    private function borrarCookieRecordarme()
    {
        setcookie('recordarme', '', $this->opcionesCookie(time() - 3600));
        unset($_COOKIE['recordarme']);
    }

    private function opcionesCookie($vence)
    {
        return array(
            'expires' => $vence,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        );
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
        $this->reiniciar(array(
            'user_id' => (int) $id,
            'user_name' => (string) $nombre,
            'is_admin' => false,
        ));
    }

    /** Vacía la sesión anterior y deja solo los datos nuevos. */
    private function reiniciar($datos)
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION = array();
        foreach ($datos as $clave => $valor) {
            $_SESSION[$clave] = $valor;
        }
    }
}
