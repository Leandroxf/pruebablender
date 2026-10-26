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
        $this->ejecutar(
            'INSERT INTO compras_libros (id_usuario, libro_id, idioma, precio_pagado) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE precio_pagado = VALUES(precio_pagado)',
            array($userId, $id, $idioma, $precio)
        );

        $compras = $this->de($userId);
        $clave = $id . ':' . $idioma;
        if (isset($compras[$clave])) {
            return $compras[$clave];
        }

        return array('id' => (int) $id, 'idioma' => $idioma, 'precio_pagado' => (float) $precio, 'fecha' => date('Y-m-d H:i:s'));
    }
}
