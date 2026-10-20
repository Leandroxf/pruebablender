<?php

/**
 * Libros de pago: su texto completo vive en MySQL y no en el JSON publico.
 *
 * El catalogo publico (api/libros_es.json) solo guarda los metadatos de estos
 * libros (titulo, autor, cover, precio, isbn). El texto se lee de la tabla
 * `libros` y solo se entrega a quien tiene la compra registrada.
 */
class PaidBook
{
    private mysqli $connection;

    public function __construct(?mysqli $connection = null)
    {
        $this->connection = $connection ?? Database::connect();
    }

    /**
     * Datos publicos de un libro de pago (sin el texto).
     *
     * @return array<string, mixed>|null
     */
    public function findMetadata(int $bookId, string $language): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, titulo, autor, genero, cover, isbn, precio, secciones, idioma, fuente, introducciones
             FROM libros
             WHERE id = ? AND idioma = ? AND precio IS NOT NULL AND activo = 1
             LIMIT 1'
        );
        $statement->bind_param('is', $bookId, $language);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();
        $statement->close();

        if ($row === null) {
            return null;
        }

        return [
            'id' => (int)$row['id'],
            'titulo' => $row['titulo'],
            'autor' => $row['autor'],
            'genero' => $row['genero'],
            'cover' => $row['cover'],
            'idioma' => $row['idioma'],
            'isbn' => (string)($row['isbn'] ?? ''),
            'precio' => (float)$row['precio'],
            'source' => (string)($row['fuente'] ?? ''),
            'es_texto_protegido' => true,
        ];
    }

    /**
     * Texto completo del libro de pago, tal como se guarda en la base.
     *
     * @return array<string, mixed>|null
     */
    public function findFull(int $bookId, string $language): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id, titulo, autor, genero, cover, isbn, precio, secciones, idioma, fuente, introducciones
             FROM libros
             WHERE id = ? AND idioma = ? AND precio IS NOT NULL AND activo = 1
             LIMIT 1'
        );
        $statement->bind_param('is', $bookId, $language);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();
        $statement->close();

        if ($row === null) {
            return null;
        }

        $secciones = $this->decodeSecciones((string)$row['secciones']);
        if ($secciones === null) {
            return null;
        }

        $introducciones = $this->decodeIntroducciones((string)$row['introducciones']);

        return [
            'id' => (int)$row['id'],
            'titulo' => $row['titulo'],
            'autor' => $row['autor'],
            'genero' => $row['genero'],
            'cover' => $row['cover'],
            'idioma' => $row['idioma'],
            'isbn' => (string)($row['isbn'] ?? ''),
            'precio' => (float)$row['precio'],
            'source' => (string)($row['fuente'] ?? ''),
            'introducciones' => $introducciones,
            'secciones' => $secciones,
            'es_texto_protegido' => true,
        ];
    }

    /**
     * @return array<int, array<string, mixed>> lista de libros de pago del catalogo.
     */
    public function listPaidBooks(string $language): array
    {
        $statement = $this->connection->prepare(
            'SELECT id, titulo, autor, genero, cover, isbn, precio, secciones, idioma, fuente
             FROM libros
             WHERE idioma = ? AND precio IS NOT NULL AND activo = 1
             ORDER BY id'
        );
        $statement->bind_param('s', $language);
        $statement->execute();
        $result = $statement->get_result();

        $books = [];
        while ($row = $result->fetch_assoc()) {
            $books[] = [
                'id' => (int)$row['id'],
                'titulo' => $row['titulo'],
                'autor' => $row['autor'],
                'genero' => $row['genero'],
                'cover' => $row['cover'],
                'idioma' => $row['idioma'],
                'isbn' => (string)($row['isbn'] ?? ''),
                'precio' => (float)$row['precio'],
                'source' => (string)($row['fuente'] ?? ''),
                'bytes' => strlen((string)$row['secciones']),
                'num_secciones' => $this->countSections((string)$row['secciones']),
                'es_texto_protegido' => true,
                'requiere_compra' => true,
            ];
        }
        $statement->close();

        return $books;
    }

    private function decodeSecciones(string $raw): ?array
    {
        $secciones = json_decode($raw, true);
        if (!is_array($secciones) || $secciones === []) {
            return null;
        }

        return $secciones;
    }

    private function decodeIntroducciones(string $raw): array
    {
        $introducciones = json_decode($raw, true);
        if (!is_array($introducciones)) {
            return [];
        }

        return $introducciones;
    }

    private function countSections(string $raw): int
    {
        $secciones = json_decode($raw, true);
        return is_array($secciones) ? count($secciones) : 0;
    }
}