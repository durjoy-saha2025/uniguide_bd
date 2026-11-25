<?php
declare(strict_types=1);

namespace UniGuide\App\Core;

final class SessionManager
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Strict');

        $secureCookie = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_name('uniguide_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secureCookie,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }

    public static function login(int $userId, string $role): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION = [
            'user_id' => $userId,
            'role' => $role,
        ];
    }

    public static function userId(): ?int
    {
        self::start();
        $userId = $_SESSION['user_id'] ?? null;

        return is_int($userId) && $userId > 0 ? $userId : null;
    }

    public static function role(): ?string
    {
        self::start();
        $role = $_SESSION['role'] ?? null;

        return is_string($role) ? $role : null;
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $parameters['path'],
                'domain' => $parameters['domain'],
                'secure' => $parameters['secure'],
                'httponly' => $parameters['httponly'],
                'samesite' => $parameters['samesite'] ?? 'Strict',
            ]);
        }

        session_destroy();
    }
}
