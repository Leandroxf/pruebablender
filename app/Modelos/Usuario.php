<?php

final class Usuario
{
    private const ROLES = ['cliente', 'vendedor', 'administrador'];

    private mysqli $connection;

    public function __construct(?mysqli $connection = null)
    {
        $this->connection = $connection ?? Database::connect();
        $this->connection->query(
            'CREATE TABLE IF NOT EXISTS usuarios (
                id_usuario INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(100) NOT NULL,
                correo VARCHAR(150) NOT NULL UNIQUE,
                contrasenia VARCHAR(255) NOT NULL,
                rol ENUM(\'cliente\', \'vendedor\', \'administrador\') NOT NULL DEFAULT \'cliente\',
                fecha_alta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function create(string $nombre, string $correo, string $password, string $rol = 'cliente'): array
    {
        if (!in_array($rol, self::ROLES, true)) {
            $rol = 'cliente';
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new RuntimeException('No se pudo proteger la contraseña.');
        }

        $statement = $this->connection->prepare(
            'INSERT INTO usuarios (nombre, correo, contrasenia, rol) VALUES (?, ?, ?, ?)'
        );
        $statement->bind_param('ssss', $nombre, $correo, $hash, $rol);
        $statement->execute();
        $id = (int)$this->connection->insert_id;
        $statement->close();

        return ['id' => $id, 'nombre' => $nombre, 'correo' => $correo, 'rol' => $rol];
    }

    public function emailExists(string $correo): bool
    {
        $statement = $this->connection->prepare('SELECT 1 FROM usuarios WHERE correo = ? LIMIT 1');
        $statement->bind_param('s', $correo);
        $statement->execute();
        $exists = $statement->get_result()->fetch_row() !== null;
        $statement->close();

        return $exists;
    }

    public function nameExists(string $nombre): bool
    {
        $statement = $this->connection->prepare('SELECT 1 FROM usuarios WHERE nombre = ? LIMIT 1');
        $statement->bind_param('s', $nombre);
        $statement->execute();
        $exists = $statement->get_result()->fetch_row() !== null;
        $statement->close();

        return $exists;
    }

    public function findByEmail(string $correo): ?array
    {
        return $this->findBy('correo', $correo);
    }

    public function findByName(string $nombre): ?array
    {
        return $this->findBy('nombre', $nombre);
    }

    public function authenticate(string $login, string $password): ?array
    {
        $user = $this->findByEmail($login) ?? $this->findByName($login);
        if ($user === null || !password_verify($password, $user['contrasenia'])) {
            return null;
        }

        return [
            'id_usuario' => (int)$user['id_usuario'],
            'nombre' => $user['nombre'],
            'correo' => $user['correo'],
            'rol' => $user['rol'],
        ];
    }

    private function findBy(string $column, string $value): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT id_usuario, nombre, correo, contrasenia, rol FROM usuarios WHERE ' . $column . ' = ? LIMIT 1'
        );
        $statement->bind_param('s', $value);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();
        $statement->close();

        return $row ?: null;
    }
}
