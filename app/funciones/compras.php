<?php

// Compras de libros de pago.

class Compra extends Modelo
{
    /** Todas las compras del usuario, indexadas por "id:idioma". */
    public function de($userId)
    {
        $filas = $this->filas('SELECT libro_id, idioma, precio_pagado, fecha FROM compras_libros WHERE id_usuario = ? ORDER BY fecha DESC', array($userId));

        $compras = array();
        foreach ($filas as $fila) {
            $compras[$fila['libro_id'] . ':' . $fila['idioma']] = array(
                'id' => (int) $fila['libro_id'],
                'idioma' => $fila['idioma'],
                'precio_pagado' => (float) $fila['precio_pagado'],
                'fecha' => $fila['fecha'],
            );
        }

        return $compras;
    }

    /** ¿Ya compró ese libro? */
    public function posee($userId, $id, $idioma)
    {
        return $this->fila('SELECT 1 FROM compras_libros WHERE id_usuario = ? AND libro_id = ? AND idioma = ? LIMIT 1', array($userId, $id, $idioma)) !== null;
    }

    /** Registra la compra y devuelve el comprobante. */
    public function comprar($userId, $id, $idioma, $precio)
    {
        $this->conexion->beginTransaction();
        try {
            $libro = $this->fila(
                'SELECT stock FROM libros WHERE id = ? AND idioma = ? AND precio IS NOT NULL AND activo = 1 FOR UPDATE',
                array($id, $idioma)
            );
            if ($libro === null) {
                throw new DomainException('Ese libro ya no está disponible para compra.');
            }
            if ($this->posee($userId, $id, $idioma)) {
                throw new DomainException('Este libro ya está en tu biblioteca.');
            }
            if ($libro['stock'] !== null) {
                if ((int) $libro['stock'] < 1) {
                    throw new DomainException('No quedan unidades disponibles de este libro.');
                }
                $this->ejecutar('UPDATE libros SET stock = stock - 1 WHERE id = ? AND idioma = ? AND stock > 0', array($id, $idioma));
            }
            $this->ejecutar(
                'INSERT INTO compras_libros (id_usuario, libro_id, idioma, precio_pagado) VALUES (?, ?, ?, ?)',
                array($userId, $id, $idioma, $precio)
            );
            $this->conexion->commit();
        } catch (Exception $error) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $error;
        }

        $compras = $this->de($userId);
        $clave = $id . ':' . $idioma;
        if (isset($compras[$clave])) {
            return $compras[$clave];
        }

        return array('id' => (int) $id, 'idioma' => $idioma, 'precio_pagado' => (float) $precio, 'fecha' => date('Y-m-d H:i:s'));
    }
}
