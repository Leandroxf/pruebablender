<?php

/**
 * Compras de libros con precio.
 *
 * Un libro con `precio` no es de acceso libre: su texto completo vive en
 * MySQL (no en el JSON publico) y solo se entrega a quien lo compro.
 */
class Purchase
{
    private mysqli $connection;

    public function __construct(?mysqli $connection = null)
    {
        $this->connection = $connection ?? Database::connect();

        // La tabla compras_libros referencia usuarios: hay que garantizar
        // que exista antes de crear la clave foranea.
        new Usuario($this->connection);

        $this->connection->query(
            'CREATE TABLE IF NOT EXISTS compras_libros (
                id_compra      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                id_usuario    INT UNSIGNED NOT NULL,
                libro_id      INT UNSIGNED NOT NULL,
                idioma        VARCHAR(2) NOT NULL,
                precio_pagado DECIMAL(10,2) NOT NULL,
                fecha         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_compras_usuario FOREIGN KEY (id_usuario)
                    REFERENCES usuarios (id_usuario) ON DELETE CASCADE,
                CONSTRAINT uq_compras_usuario_libro UNIQUE (id_usuario, libro_id, idioma)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /**
     * Libros comprados por el usuario, indexados por "id:idioma".
     *
     * @return array<string, array>
     */
    public function ownedByUser(int $userId): array
    {
        $statement = $this->connection->prepare(
            'SELECT libro_id, idioma, precio_pagado, fecha
             FROM compras_libros
             WHERE id_usuario = ?'
        );
        $statement->bind_param('i', $userId);
        $statement->execute();
        $result = $statement->get_result();

        $owned = [];
        while ($row = $result->fetch_assoc()) {
            $owned[$row['libro_id'] . ':' . $row['idioma']] = [
                'id' => (int)$row['libro_id'],
                'idioma' => $row['idioma'],
                'precio_pagado' => (float)$row['precio_pagado'],
                'fecha' => $row['fecha'],
            ];
        }
        $statement->close();

        return $owned;
    }

    public function owns(int $userId, int $bookId, string $language): bool
    {
        $statement = $this->connection->prepare(
            'SELECT 1 FROM compras_libros WHERE id_usuario = ? AND libro_id = ? AND idioma = ? LIMIT 1'
        );
        $statement->bind_param('iis', $userId, $bookId, $language);
        $statement->execute();
        $owns = $statement->get_result()->fetch_row() !== null;
        $statement->close();

        return $owns;
    }

    /**
     * Registra la compra y devuelve el comprobante ya guardado.
     */
    public function purchase(int $userId, int $bookId, string $language, float $price): array
    {
        $statement = $this->connection->prepare(
            'INSERT INTO compras_libros (id_usuario, libro_id, idioma, precio_pagado)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE precio_pagado = VALUES(precio_pagado)'
        );
        $statement->bind_param('iisd', $userId, $bookId, $language, $price);
        $statement->execute();
        $statement->close();

        $owned = $this->ownedByUser($userId);
        $key = $bookId . ':' . $language;

        return $owned[$key] ?? [
            'id' => $bookId,
            'idioma' => $language,
            'precio_pagado' => $price,
            'fecha' => date('Y-m-d H:i:s'),
        ];
    }
}