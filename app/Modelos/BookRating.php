<?php

class BookRating
{
    private mysqli $connection;

    public function __construct(?mysqli $connection = null)
    {
        $this->connection = $connection ?? Database::connect();
        $this->connection->query(
            'CREATE TABLE IF NOT EXISTS valoraciones_libros (
                libro_id INT NOT NULL,
                idioma VARCHAR(2) NOT NULL,
                usuario_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                puntuacion TINYINT UNSIGNED NOT NULL,
                actualizado TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (libro_id, idioma, usuario_hash),
                CONSTRAINT chk_valoraciones_puntuacion CHECK (puntuacion BETWEEN 1 AND 5)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function getAll(string $voterHash): array
    {
        $statement = $this->connection->prepare(
            'SELECT libro_id, idioma, ROUND(AVG(puntuacion), 1) AS promedio,
                    COUNT(*) AS cantidad,
                    MAX(CASE WHEN usuario_hash = ? THEN puntuacion END) AS mi_valoracion
             FROM valoraciones_libros
             GROUP BY libro_id, idioma'
        );
        $statement->bind_param('s', $voterHash);
        $statement->execute();
        $result = $statement->get_result();

        $ratings = [];
        while ($row = $result->fetch_assoc()) {
            $ratings[] = $this->formatRating($row);
        }
        $statement->close();

        return $ratings;
    }

    public function save(int $bookId, string $language, string $voterHash, int $rating): array
    {
        $statement = $this->connection->prepare(
            'INSERT INTO valoraciones_libros (libro_id, idioma, usuario_hash, puntuacion)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE puntuacion = VALUES(puntuacion)'
        );
        $statement->bind_param('issi', $bookId, $language, $voterHash, $rating);
        $statement->execute();
        $statement->close();

        $statement = $this->connection->prepare(
            'SELECT libro_id, idioma, ROUND(AVG(puntuacion), 1) AS promedio,
                    COUNT(*) AS cantidad,
                    MAX(CASE WHEN usuario_hash = ? THEN puntuacion END) AS mi_valoracion
             FROM valoraciones_libros
             WHERE libro_id = ? AND idioma = ?
             GROUP BY libro_id, idioma'
        );
        $statement->bind_param('sis', $voterHash, $bookId, $language);
        $statement->execute();
        $ratingSummary = $statement->get_result()->fetch_assoc();
        $statement->close();

        if ($ratingSummary === null) {
            throw new RuntimeException('No se pudo recuperar la valoración actualizada.');
        }

        return $this->formatRating($ratingSummary);
    }

    private function formatRating(array $row): array
    {
        return [
            'id' => (int)$row['libro_id'],
            'idioma' => $row['idioma'],
            'promedio' => (float)$row['promedio'],
            'cantidad' => (int)$row['cantidad'],
            'mi_valoracion' => (int)($row['mi_valoracion'] ?? 0),
        ];
    }
}
