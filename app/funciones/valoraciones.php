<?php

// Valoraciones de los libros (estrellitas).

class Valoracion extends Modelo
{
    /** Promedio de valoraciones de todos los libros. */
    public function todas($votante)
    {
        return $this->armar($this->traerFilas($votante, 0, ''));
    }

    /** Guarda la valoración de un usuario y devuelve el resumen nuevo. */
    public function guardar($id, $idioma, $votante, $puntuacion)
    {
        $this->ejecutar(
            'INSERT INTO valoraciones_libros (libro_id, idioma, usuario_hash, puntuacion) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE puntuacion = VALUES(puntuacion)',
            array($id, $idioma, $votante, $puntuacion)
        );

        $filas = $this->traerFilas($votante, $id, $idioma);
        if (count($filas) === 0) {
            throw new RuntimeException('No se pudo recuperar la valoracion actualizada.');
        }

        return $this->armar($filas);
    }

    /** Filas de valoraciones. Si $id es 0, trae las de todos los libros. */
    private function traerFilas($votante, $id, $idioma)
    {
        $sql = 'SELECT libro_id, idioma, ROUND(AVG(puntuacion), 1) AS promedio,
                       COUNT(*) AS cantidad,
                       MAX(CASE WHEN usuario_hash = ? THEN puntuacion END) AS mi_valoracion
                FROM valoraciones_libros';

        if ($id === 0) {
            return $this->filas($sql . ' GROUP BY libro_id, idioma', array($votante));
        }

        return $this->filas($sql . ' WHERE libro_id = ? AND idioma = ? GROUP BY libro_id, idioma', array($votante, $id, $idioma));
    }

    /** Convierte las filas de la base en la respuesta para el navegador. */
    private function armar($filas)
    {
        $lista = array();
        foreach ($filas as $fila) {
            $mio = (int) ($fila['mi_valoracion'] ?? 0);

            $lista[] = array(
                'id' => (int) $fila['libro_id'],
                'idioma' => $fila['idioma'],
                'promedio' => (float) $fila['promedio'],
                'cantidad' => (int) $fila['cantidad'],
                'mi_valoracion' => $mio,
            );
        }

        return $lista;
    }
}
