<?php
declare(strict_types=1);

namespace UniGuide\App\Core;

final class Csrf
{
    public static function token(): string
    {
        SessionManager::start();
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function verify(array $body = []): void
    {
        $knownToken = self::token();
        $providedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($body['_csrf'] ?? '');

        if (!is_string($providedToken) || !hash_equals($knownToken, $providedToken)) {
            ApiResponse::error('csrf_failed', 'The security token is missing or invalid.', 403);
        }
    }
}
