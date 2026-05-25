<?php

namespace Cheer\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name((string) Config::get('session.name', 'cheer_session'));
        session_set_cookie_params([
            'lifetime' => (int) Config::get('session.lifetime', 7200),
            'path' => (string) Config::get('session.path', '/'),
            'domain' => (string) Config::get('session.domain', ''),
            'secure' => (bool) Config::get('session.secure', false),
            'httponly' => (bool) Config::get('session.http_only', true),
            'samesite' => (string) Config::get('session.same_site', 'Lax'),
        ]);

        session_start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }
}
