<?php

// Cuentas de usuario guardadas en MySQL.

class Usuario extends Modelo
{
    /** Crea una cuenta nueva y devuelve sus datos. */
    public function crear($nombre, $correo, $password, $rol = 'cliente')
    {
        $this->ejecutar(
            'INSERT INTO usuarios (nombre, correo, contrasenia, rol) VALUES (?, ?, ?, ?)',
            array($nombre, $correo, password_hash($password, PASSWORD_DEFAULT), $rol)
        );

        return array('id' => $this->ultimoId(), 'nombre' => $nombre, 'correo' => $correo, 'rol' => $rol);
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
