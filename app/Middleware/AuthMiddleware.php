<?php
declare(strict_types=1);

namespace UniGuide\App\Middleware;

use PDO;
use UniGuide\App\Core\ApiResponse;
use UniGuide\App\Core\SessionManager;
use UniGuide\App\Repositories\UserRepository;

final class AuthMiddleware
{
    public static function requireUser(PDO $database): int
    {
        $userId = SessionManager::userId();
        if ($userId === null) {
            ApiResponse::error('authentication_required', 'Please sign in to continue.', 401);
        }

        $user = (new UserRepository($database))->findPublicById($userId);
        if ($user === null) {
            SessionManager::logout();
            ApiResponse::error('authentication_required', 'Please sign in to continue.', 401);
        }

        return $userId;
    }

    public static function requireRole(array $allowedRoles, PDO $database): int
    {
        $userId = self::requireUser($database);
        $user = (new UserRepository($database))->findPublicById($userId);
        if ($user === null || !in_array($user['role'], $allowedRoles, true)) {
            ApiResponse::error('forbidden', 'You do not have permission to perform this action.', 403);
        }

        return $userId;
    }
}
