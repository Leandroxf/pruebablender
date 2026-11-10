<?php

// Catálogo de libros: archivos JSON públicos + libros de pago en MySQL.

class Libro extends Modelo
{
    private $catalogoEn = null;
    private $catalogoEs = null;
    private $tablaCp850 = null;

    /** Lee el catálogo JSON de un idioma (ej: api/libros.json). */
    private function leer($idioma)
    {
        $archivo = 'libros.json';
        if ($idioma === 'es') {
            $archivo = 'libros_es.json';
        }

        $ruta = PROJECT_ROOT . '/api/' . $archivo;
        if (!is_file($ruta)) {
            return array();
        }

        $texto = file_get_contents($ruta);
        if ($texto === false) {
            return array();
        }

        $datos = json_decode($texto, true);
        if (!is_array($datos)) {
            return array();
        }

        return $datos;
    }

    /** Guarda el catálogo de un idioma completo. */
    private function guardar($idioma, $catalogo)
    {
        $archivo = 'libros.json';
        if ($idioma === 'es') {
            $archivo = 'libros_es.json';
        }

        $ruta = PROJECT_ROOT . '/api/' . $archivo;
        $json = json_encode($catalogo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('No se pudo preparar el catálogo.');
        }

        if (file_put_contents($ruta, $json) === false) {
            throw new RuntimeException('No se pudo guardar el catálogo.');
        }

        if ($idioma === 'es') {
            $this->catalogoEs = null;
        } else {
            $this->catalogoEn = null;
        }
    }

    /** El catálogo de un idioma, leído una sola vez por petición. */
    public function cargar($idioma)
    {
        if ($idioma === 'es') {
            if ($this->catalogoEs === null) {
                $this->catalogoEs = $this->leer('es');
            }
            return $this->catalogoEs;
        }

        if ($this->catalogoEn === null) {
            $this->catalogoEn = $this->leer('en');
        }

        return $this->catalogoEn;
    }

    /** Resumen de todos los libros, para las tarjetas del catálogo. */
    public function resumen()
    {
        $resumen = array();

        $idsCatalogo = array();
        foreach (Respuesta::idiomas() as $idioma) {
            foreach ($this->cargar($idioma) as $clave => $entrada) {
                if ($idioma === 'es' && (int) $clave === 86001) {
                    continue;
                }
                if (!is_array($entrada)) {
                    continue;
                }

                $precio = null;
                if (isset($entrada['precio'])) {
                    $precio = (float) $entrada['precio'];
                }
                $titulo = '';
                if (isset($entrada['titulo'])) {
                    $titulo = (string) $entrada['titulo'];
                }
                $autor = '';
                if (isset($entrada['autor'])) {
                    $autor = (string) $entrada['autor'];
                }
                $genero = '';
                if (isset($entrada['genero'])) {
                    $genero = (string) $entrada['genero'];
                }
                $cover = '';
                if (isset($entrada['cover'])) {
                    $cover = (string) $entrada['cover'];
                }
                $isbn = '';
                if (isset($entrada['isbn'])) {
                    $isbn = (string) $entrada['isbn'];
                }
                $source = '';
                if (isset($entrada['source'])) {
                    $source = (string) $entrada['source'];
                }

                $idsCatalogo[$idioma . ':' . (int) $clave] = true;
                $resumen[] = array(
                    'id' => (int) $clave,
                    'titulo' => $titulo,
                    'autor' => $autor,
                    'genero' => $genero,
                    'cover' => $cover,
                    'idioma' => $idioma,
                    'isbn' => $isbn,
                    'precio' => $precio,
                    'stock' => isset($entrada['stock']) ? (int) $entrada['stock'] : null,
                    'source' => $source,
                    'presentacion' => false,
                    'lectura_disponible' => !empty($entrada['secciones']),
                );
            }
        }

        $pagos = $this->filas('SELECT id, titulo, autor, genero, cover, isbn, precio, stock, secciones, idioma, fuente FROM libros WHERE precio IS NOT NULL AND activo = 1 ORDER BY titulo');
        foreach ($pagos as $pago) {
            $clavePago = $pago['idioma'] . ':' . (int) $pago['id'];
            if (isset($idsCatalogo[$clavePago])) {
                continue;
            }
            $secciones = json_decode((string) $pago['secciones'], true);
            $resumen[] = array(
                'id' => (int) $pago['id'],
                'titulo' => $pago['titulo'],
                'autor' => $pago['autor'],
                'genero' => $pago['genero'],
                'cover' => $pago['cover'],
                'idioma' => $pago['idioma'],
                'isbn' => (string) ($pago['isbn'] ?? ''),
                'precio' => (float) $pago['precio'],
                'stock' => $pago['stock'] === null ? null : (int) $pago['stock'],
                'source' => (string) ($pago['fuente'] ?? ''),
                'presentacion' => true,
                'lectura_disponible' => is_array($secciones) && count($secciones) > 0,
            );
        }

        return $resumen;
    }

    /** Agrega un producto vendible sin cargar el texto completo. */
    public function agregarProducto($datos)
    {
        $titulo = trim((string) ($datos['titulo'] ?? ''));
        $autor = trim((string) ($datos['autor'] ?? ''));
        $cover = trim((string) ($datos['cover'] ?? ''));
        $precioTexto = trim((string) ($datos['precio'] ?? ''));
        $stockTexto = trim((string) ($datos['stock'] ?? ''));

        Respuesta::exigir($titulo !== '' && mb_strlen($titulo) <= 255, 'Ingresá un título de hasta 255 caracteres.');
        Respuesta::exigir($autor !== '' && mb_strlen($autor) <= 255, 'Ingresá un autor de hasta 255 caracteres.');
        Respuesta::exigir((bool) preg_match('#^fotos/[A-Za-z0-9._-]+\.(jpg|png|webp)$#i', $cover), 'Subí una foto de carátula válida.');
        Respuesta::exigir(is_numeric($precioTexto), 'Ingresá un precio válido.');
        $precio = (float) $precioTexto;
        Respuesta::exigir(is_finite($precio) && $precio > 0 && $precio <= 99999999.99, 'El precio debe ser mayor a cero y no superar 99.999.999,99.');

        $stock = filter_var($stockTexto, FILTER_VALIDATE_INT, array(
            'options' => array('min_range' => 0, 'max_range' => 4294967295),
        ));
        Respuesta::exigir($stock !== false, 'El stock debe ser un número entero igual o mayor a cero.');

        $idMaximo = 0;
        foreach (Respuesta::idiomas() as $idioma) {
            foreach ($this->cargar($idioma) as $clave => $entrada) {
                $idMaximo = max($idMaximo, (int) $clave);
            }
        }
        $ultimoId = $this->fila('SELECT COALESCE(MAX(id), 0) AS id FROM libros');
        $id = max($idMaximo, (int) $ultimoId['id']) + 1;
        Respuesta::exigir($id <= 4294967295, 'No se pudo asignar un ID al libro.');

        $this->ejecutar(
            "INSERT INTO libros (id, titulo, autor, genero, cover, precio, stock, secciones, idioma, fuente, introducciones)
             VALUES (?, ?, ?, 'novela', ?, ?, ?, '[]', 'es', NULL, '{}')",
            array($id, $titulo, $autor, $cover, $precio, $stock)
        );

        return $id;
    }

    /** Ajusta las existencias de un libro vendible. */
    public function actualizarStock($id, $idioma, $stockTexto)
    {
        Respuesta::exigir(is_int($id) && $id > 0, 'El libro seleccionado no es válido.');
        Respuesta::exigir($idioma === 'en' || $idioma === 'es', 'El libro seleccionado no es válido.');
        $stock = filter_var((string) $stockTexto, FILTER_VALIDATE_INT, array(
            'options' => array('min_range' => 0, 'max_range' => 4294967295),
        ));
        Respuesta::exigir($stock !== false, 'El stock debe ser un número entero igual o mayor a cero.');

        $existe = $this->fila('SELECT 1 FROM libros WHERE id = ? AND idioma = ? AND precio IS NOT NULL AND activo = 1 LIMIT 1', array($id, $idioma));
        Respuesta::exigir($existe !== null, 'No se encontró el libro vendible seleccionado.');
        $this->ejecutar('UPDATE libros SET stock = ? WHERE id = ? AND idioma = ?', array($stock, $id, $idioma));
    }

    /**
     * Agrega un libro nuevo al catálogo de un idioma.
     * Devuelve el id con el que quedó guardado.
     */
    public function agregar($idioma, $datos)
    {
        Respuesta::exigir($idioma === 'en' || $idioma === 'es', 'Selecciona un idioma válido.');

        $titulo = '';
        if (isset($datos['titulo'])) {
            $titulo = trim((string) $datos['titulo']);
        }
        $autor = '';
        if (isset($datos['autor'])) {
            $autor = trim((string) $datos['autor']);
        }
        $genero = '';
        if (isset($datos['genero'])) {
            $genero = trim((string) $datos['genero']);
        }
        $cover = '';
        if (isset($datos['cover'])) {
            $cover = trim((string) $datos['cover']);
        }
        $fuente = '';
        if (isset($datos['source'])) {
            $fuente = trim((string) $datos['source']);
        }
        $texto = '';
        if (isset($datos['texto'])) {
            $texto = trim((string) $datos['texto']);
        }
        $idTexto = '';
        if (isset($datos['id'])) {
            $idTexto = trim((string) $datos['id']);
        }

        Respuesta::exigir($titulo !== '', 'Ingresá un título.');
        Respuesta::exigir($autor !== '', 'Ingresá un autor.');
        Respuesta::exigir(in_array($genero, $this->generos()), 'Selecciona un género válido.');
        if ($cover !== '' && substr($cover, 0, 4) !== 'http') {
            Respuesta::exigir(false, 'La URL de la portada no es válida.');
        }
        if ($fuente !== '' && substr($fuente, 0, 4) !== 'http') {
            Respuesta::exigir(false, 'La URL de la fuente no es válida.');
        }
        Respuesta::exigir($texto !== '', 'Pega el texto del libro.');
        Respuesta::exigir(strlen($texto) <= 5000000, 'El texto no puede superar los 5 MB.');

        $parrafos = preg_split('/\r?\n\s*\r?\n/', $texto);
        $bloques = array();
        foreach ($parrafos as $parrafo) {
            $parrafo = trim($parrafo);
            if ($parrafo !== '') {
                $bloques[] = array('heading' => false, 'text' => $parrafo);
            }
        }
        Respuesta::exigir(count($bloques) > 0, 'Pega el texto del libro.');

        $catalogo = $this->cargar($idioma);

        if ($idTexto === '') {
            $id = 1;
            foreach ($catalogo as $clave => $entrada) {
                if ((int) $clave >= $id) {
                    $id = (int) $clave + 1;
                }
            }
        } else {
            Respuesta::exigir(is_numeric($idTexto), 'El ID debe ser un número mayor a cero.');
            $id = (int) $idTexto;
            Respuesta::exigir($id > 0, 'El ID debe ser un número mayor a cero.');
            Respuesta::exigir(!isset($catalogo[$id]), 'Ese ID ya está en uso. Elegí otro.');
        }

        $coverGuardado = null;
        if ($cover !== '') {
            $coverGuardado = $cover;
        }

        $fuenteGuardada = null;
        if ($fuente !== '') {
            $fuenteGuardada = $fuente;
        }

        $catalogo[$id] = array(
            'id' => $id,
            'titulo' => $titulo,
            'autor' => $autor,
            'genero' => $genero,
            'cover' => $coverGuardado,
            'idioma' => $idioma,
            'isbn' => null,
            'precio' => null,
            'source' => $fuenteGuardada,
            'introducciones' => array(),
            'secciones' => array(
                array('title' => $titulo, 'blocks' => $bloques),
            ),
        );
        ksort($catalogo);

        $this->guardar($idioma, $catalogo);

        return $id;
    }

    /** Saca un libro del catálogo (y de la base, si estaba ahí). */
    public function borrar($idioma, $id)
    {
        Respuesta::exigir($idioma === 'en' || $idioma === 'es', 'Selecciona un idioma válido.');
        Respuesta::exigir(is_int($id) && $id > 0, 'El libro seleccionado no es válido.');

        $catalogo = $this->cargar($idioma);
        $existeEnCatalogo = isset($catalogo[$id]);
        $filaBase = $this->fila('SELECT cover FROM libros WHERE id = ? AND idioma = ? LIMIT 1', array($id, $idioma));
        Respuesta::exigir($existeEnCatalogo || $filaBase !== null, 'Ese libro no está en el catálogo.');

        if ($existeEnCatalogo) {
            unset($catalogo[$id]);
            $this->guardar($idioma, $catalogo);
        }
        $this->ejecutar('DELETE FROM libros WHERE id = ? AND idioma = ?', array($id, $idioma));

        if ($filaBase !== null && preg_match('#^fotos/libro-[a-f0-9]{32}\.(jpg|png|webp)$#i', (string) $filaBase['cover'])) {
            $caratula = PROJECT_ROOT . '/' . str_replace('/', DIRECTORY_SEPARATOR, $filaBase['cover']);
            if (is_file($caratula) && !unlink($caratula)) {
                error_log('No se pudo eliminar la carátula del libro #' . $id . '.');
            }
        }
    }

    /** Los géneros que acepta el formulario del panel. */
    public function generos()
    {
        return array(
            'aventura',
            'ciencia-ficcion',
            'cuentos',
            'fantasia',
            'filosofia',
            'historia',
            'misterio',
            'novela',
            'poesia',
            'romance',
            'terror',
        );
    }

    /** Fila de la tabla libros: solo los libros de pago activos. */
    public function filaPago($id, $idioma)
    {
        return $this->fila('SELECT id, titulo, autor, genero, cover, isbn, precio, stock, secciones, idioma, fuente, introducciones
             FROM libros
             WHERE id = ? AND idioma = ? AND precio IS NOT NULL AND activo = 1
             LIMIT 1', array($id, $idioma));
    }

    /** Datos públicos de un libro de pago (sin el texto). */
    public function metadatos($fila)
    {
        $isbn = '';
        if (isset($fila['isbn'])) {
            $isbn = (string) $fila['isbn'];
        }

        $fuente = '';
        if (isset($fila['fuente'])) {
            $fuente = (string) $fila['fuente'];
        }

        return array(
            'id' => (int) $fila['id'],
            'titulo' => $fila['titulo'],
            'autor' => $fila['autor'],
            'genero' => $fila['genero'],
            'cover' => $fila['cover'],
            'idioma' => $fila['idioma'],
            'isbn' => $isbn,
            'precio' => (float) $fila['precio'],
            'stock' => $fila['stock'] === null ? null : (int) $fila['stock'],
            'lectura_disponible' => !empty(json_decode((string) $fila['secciones'], true)),
            'source' => $fuente,
            'es_texto_protegido' => true,
        );
    }

    /** El libro de pago completo: datos + capítulos + introducciones. */
}
