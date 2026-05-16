<?php

namespace App\Infrastructure\Http\Filters;

/**
 * Request-scoped store for the authenticated admin user.
 * Set by AdminAuth filter; read by controllers and AdminOnlyAuth filter.
 * Static properties are reset per PHP request lifecycle.
 */
class AdminAuthContext
{
    private static ?array $user = null;

    public static function set(array $user): void
    {
        self::$user = $user;
    }

    public static function get(): ?array
    {
        return self::$user;
    }

    public static function userId(): ?int
    {
        return isset(self::$user['user_id']) ? (int) self::$user['user_id'] : null;
    }

    public static function role(): ?string
    {
        return self::$user['role'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return (self::$user['role'] ?? null) === 'admin';
    }
}
