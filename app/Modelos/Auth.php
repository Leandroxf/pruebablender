<?php

final class Auth
{
    private function __construct()
    {
    }

    public static function isAdmin(): bool
    {
        return ($_SESSION['is_admin'] ?? false) === true;
    }

    public static function isUser(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function userId(): ?int
    {
        return self::isUser() ? (int)$_SESSION['user_id'] : null;
    }

    public static function userName(): string
    {
        return (string)($_SESSION['user_name'] ?? '');
    }

    public static function userRole(): string
    {
        return (string)($_SESSION['user_role'] ?? 'cliente');
    }

    public static function userInitial(): string
    {
        return preg_match('/\S/u', self::userName(), $matches) === 1
            ? strtoupper($matches[0])
            : '?';
    }

    public static function isLoggedIn(): bool
    {
        return self::isAdmin() || self::isUser();
    }

    public static function loginAs(string $role): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['is_admin'] = true;
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    }

    public static function loginUser(int $id, string $name, string $role = 'cliente'): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['user_id'] = $id;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_role'] = $role;
        $_SESSION['user_csrf'] = bin2hex(random_bytes(32));
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    public static function csrfKey(): string
    {
        if (self::isAdmin()) {
            return 'admin_csrf';
        }

        return 'user_csrf';
    }

    public static function checkCsrf(string $key, string $token): bool
    {
        $expected = (string)($_SESSION[$key] ?? '');
        return $expected !== '' && hash_equals($expected, $token);
    }
}
