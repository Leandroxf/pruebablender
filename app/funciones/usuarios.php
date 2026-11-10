<?php

// Cuentas de usuario guardadas en MySQL.

class ModeloSesionPersistente extends Modelo
{
    public function guardarToken($selector, $idUsuario, $tokenHash, $vence)
    {
        $this->ejecutar(
            'INSERT INTO tokens_recordar (selector, id_usuario, token_hash, vence) VALUES (?, ?, ?, FROM_UNIXTIME(?))',
            array($selector, $idUsuario, $tokenHash, $vence)
        );
    }

    public function consumirToken($selector, $token)
    {
        $fila = $this->fila(
            'SELECT tr.id_usuario, tr.token_hash, u.nombre, u.rol
             FROM tokens_recordar tr
             INNER JOIN usuarios u ON u.id_usuario = tr.id_usuario
             WHERE tr.selector = ? AND tr.vence > NOW()
             LIMIT 1',
            array($selector)
        );
        if ($fila === null || !hash_equals((string) $fila['token_hash'], hash('sha256', $token))) {
            return null;
        }

        $this->borrarToken($selector);
        return array(
            'id' => (int) $fila['id_usuario'],
            'nombre' => (string) $fila['nombre'],
            'rol' => (string) $fila['rol'],
        );
    }

    public function borrarToken($selector)
    {
        $this->ejecutar('DELETE FROM tokens_recordar WHERE selector = ?', array($selector));
    }
}

class Usuario extends Modelo
{
    /** Crea una cuenta nueva y devuelve sus datos. */
    public function crear($nombre, $correo, $password, $rol = 'cliente')
    {
        $nombre = trim((string) $nombre);
        $correo = trim((string) $correo);
        $rol = in_array($rol, array('cliente', 'vendedor', 'administrador'), true) ? $rol : 'cliente';
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new RuntimeException('No se pudo proteger la contraseña.');
        }

        $this->ejecutar(
            'INSERT INTO usuarios (nombre, correo, contrasenia, rol) VALUES (?, ?, ?, ?)',
            array($nombre, $correo, $hash, $rol)
        );

        return array('id' => $this->ultimoId(), 'nombre' => $nombre, 'correo' => $correo, 'rol' => $rol);
    }

    /** Crea una cuenta, inicia su sesión y devuelve la respuesta JSON. */
    public static function registrar($sesion, $nombre, $correo, $password)
    {
        try {
            $usuario = (new Usuario())->crear($nombre, $correo, $password, 'cliente');
            $sesion->entrarUsuario($usuario['id'], $usuario['nombre']);

            Respuesta::json(array(
                'usuario' => array('id' => $usuario['id'], 'nombre' => $usuario['nombre'], 'correo' => $usuario['correo']),
                'mensaje' => 'Cuenta creada correctamente. Ya puedes iniciar sesión.',
            ), 201);
        } catch (PDOException $error) {
            error_log('Error creando usuario: ' . $error->getMessage());
            if ((string) $error->getCode() === '23000') {
                Respuesta::json(array('error' => 'El correo o nombre ya está registrado.'), 409);
            }
            Respuesta::json(array('error' => 'No se pudo guardar la cuenta en la base de datos. Verifica la conexión e inténtalo nuevamente.'), 500);
        } catch (Throwable $error) {
            error_log('Error creando usuario: ' . $error->getMessage());
            Respuesta::json(array('error' => 'No se pudo crear la cuenta. Inténtalo nuevamente.'), 500);
        }
    }

    /** Comprueba el rol administrativo consultándolo dentro del modelo. */
    public function esAdministrador($idUsuario)
    {
        $fila = $this->fila('SELECT rol FROM usuarios WHERE id_usuario = ? LIMIT 1', array((int) $idUsuario));
        return $fila !== null && Sesion::rolEsAdmin($fila['rol'] ?? '');
    }

    /** Garantiza una cuenta interna para que el administrador pueda comprar libros. */
    public function obtenerOCrearAdmin($nombre, $password)
    {
        $fila = $this->fila('SELECT id_usuario FROM usuarios WHERE nombre = ? LIMIT 1', array($nombre));
        if ($fila !== null) {
            return (int) $fila['id_usuario'];
        }

        $correo = $nombre . '@local.invalid';
        $this->crear($nombre, $correo, $password, 'administrador');
        return $this->ultimoId();
    }

    /** ¿Existe ya ese correo? */
    public function existeCorreo($correo)
    {
        return $this->fila('SELECT id_usuario FROM usuarios WHERE correo = ? LIMIT 1', array($correo)) !== null;
    }

    /** ¿Existe ya ese nombre? */
    public function existeNombre($nombre)
    {
        return $this->fila('SELECT id_usuario FROM usuarios WHERE nombre = ? LIMIT 1', array($nombre)) !== null;
    }

    /** Entra si la contraseña coincide y devuelve la cuenta; null si no. */
    public function autenticar($login, $password)
    {
        $fila = $this->fila(
            'SELECT id_usuario, nombre, correo, contrasenia, rol FROM usuarios WHERE correo = ? OR nombre = ? LIMIT 1',
            array($login, $login)
        );

        if ($fila === null) {
            return null;
        }

        $hashValido = password_verify($password, $fila['contrasenia']);
        if (!$hashValido && hash_equals((string) $fila['contrasenia'], md5($password))) {
            $hashValido = true;
            $this->ejecutar(
                'UPDATE usuarios SET contrasenia = ? WHERE id_usuario = ?',
                array(password_hash($password, PASSWORD_DEFAULT), $fila['id_usuario'])
            );
        }
        if (!$hashValido) {
            return null;
        }

        return array(
            'id' => (int) $fila['id_usuario'],
            'nombre' => $fila['nombre'],
            'correo' => $fila['correo'],
            'rol' => $fila['rol'],
        );
    }
}
