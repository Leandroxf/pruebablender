<?php

final class Lang
{
    public const EN = 'en';
    public const ES = 'es';

    private function __construct()
    {
    }

    /** @return string[] */
    public static function all(): array
    {
        return [self::EN, self::ES];
    }

    public static function isValid(mixed $language): bool
    {
        return is_string($language) && in_array($language, self::all(), true);
    }

    public static function file(string $language): string
    {
        return $language === self::ES ? 'libros_es.json' : 'libros.json';
    }
}
