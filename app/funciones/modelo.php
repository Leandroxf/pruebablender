<?php

// Clase base de la que heredan los modelos que usan la base de datos.

class Modelo
{
    protected $conexion;

    public function __construct()
    {
        $this->conexion = BaseDatos::conexion();
    }

    /** Corre una consulta y devuelve todas las filas. */
    protected function filas($sql, $valores = array())
    {
        if (count($valores) === 0) {
            return $this->conexion->query($sql)->fetchAll();
        }

        return $this->preparar($sql, $valores)->fetchAll();
    }

    /** Corre una consulta y devuelve la primera fila (o null). */
    protected function fila($sql, $valores = array())
    {
        $filas = $this->filas($sql, $valores);
        if (count($filas) === 0) {
            return null;
        }

        return $filas[0];
    }

    /** Corre una consulta que cambia datos (INSERT/UPDATE/DELETE). */
    protected function ejecutar($sql, $valores = array())
    {
        if (count($valores) === 0) {
            $this->conexion->exec($sql);
            return;
        }

        $this->preparar($sql, $valores);
    }

    /** Prepara y ejecuta una consulta con parámetros, sin guardar el statement. */
    private function preparar($sql, $valores)
    {
        $sentencia = $this->conexion->prepare($sql);
        $sentencia->execute($valores);

        return $sentencia;
    }

    /** El id del último INSERT. */
    protected function ultimoId()
    {
        return (int) $this->conexion->lastInsertId();
    }
}
