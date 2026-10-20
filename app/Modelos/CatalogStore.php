<?php

/** Lectura y escritura del catálogo JSON con bloqueo exclusivo. */
final class CatalogStore
{
    private function __construct()
    {
    }

    public static function read(string $language, string $label): array
    {
        $content = file_get_contents(self::path($language));
        if ($content === false) {
            throw new RuntimeException("No se pudo leer el archivo $label.");
        }
        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException("El archivo $label contiene JSON inválido.", 0, $error);
        }
        if (!is_array($data)) {
            throw new RuntimeException("El archivo $label tiene un formato inválido.");
        }

        return $data;
    }

    public static function transaction(string $language, string $label, callable $update): void
    {
        $lock = fopen(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'proyectodetodo-' . Lang::file($language) . '.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            throw new RuntimeException('No se pudo bloquear el archivo del catálogo.');
        }
        try {
            $catalog = self::read($language, $label);
            $update($catalog);
            self::write($language, $catalog);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public static function path(string $language): string
    {
        return PROJECT_ROOT . '/api/' . Lang::file($language);
    }

    private static function write(string $language, array $catalog): void
    {
        $path = self::path($language);
        $temporary = tempnam(dirname($path), '.catalog-');
        if ($temporary === false) {
            throw new RuntimeException('No se pudo preparar el archivo actualizado del catálogo.');
        }
        try {
            $json = json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (file_put_contents($temporary, $json, LOCK_EX) !== strlen($json)) {
                throw new RuntimeException('No se pudo guardar el archivo actualizado del catálogo.');
            }
            if (!rename($temporary, $path)) {
                throw new RuntimeException('No se pudo reemplazar el archivo del catálogo.');
            }
        } finally {
            if (file_exists($temporary)) {
                unlink($temporary);
            }
        }
    }
}
