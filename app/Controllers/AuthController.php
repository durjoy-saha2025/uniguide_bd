<?php
declare(strict_types=1);

namespace UniGuide\App\Controllers;

use PDO;
use UniGuide\App\Core\AuthRateLimiter;
use UniGuide\App\Core\ApiResponse;
use UniGuide\App\Core\Csrf;
use UniGuide\App\Core\Request;
use UniGuide\App\Core\SessionManager;
use UniGuide\App\Middleware\AuthMiddleware;
use UniGuide\App\Repositories\UserRepository;

final class AuthController
{
    private PDO $database;
    private UserRepository $users;
    private AuthRateLimiter $rateLimiter;

    public function __construct(PDO $database)
    {
        $this->database = $database;
        $this->users = new UserRepository($database);
        $this->rateLimiter = new AuthRateLimiter($database);
    }

    public function handle(): void
    {
        SessionManager::start();
        $action = $_GET['action'] ?? '';
        if (!is_string($action)) {
            ApiResponse::error('invalid_action', 'The requested action is invalid.', 400);
        }

        switch ($action) {
            case 'register':
                $this->register();
                return;
            case 'login':
                $this->login();
                return;
            case 'me':
                $this->currentUser();
                return;
            case 'logout':
                $this->logout();
                return;
            case 'password':
                $this->changePassword();
                return;
            case 'delete-account':
                $this->deleteAccount();
                return;
            default:
                ApiResponse::error('not_found', 'The requested authentication action was not found.', 404);
        }
    }

    private function register(): void
    {
        Request::requireMethod('POST');
        $body = Request::jsonBody();
        Csrf::verify($body);
        $name = $this->requiredString($body, 'name', 150);
        $email = strtolower($this->requiredString($body, 'email', 254));
        $password = $this->requiredString($body, 'password', 128);
        $confirmation = $this->requiredString($body, 'password_confirmation', 128);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ApiResponse::error('validation_failed', 'Enter a valid email address.', 422);
        }
        $this->validatePassword($password);
        if (!hash_equals($password, $confirmation)) {
            ApiResponse::error('validation_failed', 'The password confirmation does not match.', 422);
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            throw new \RuntimeException('Password hashing failed.');
        }

        $userId = $this->users->createStudent($name, $email, $passwordHash);
        if ($userId === null) {
            ApiResponse::error('email_unavailable', 'An account with that email already exists.', 409);
        }

        SessionManager::login($userId, 'student');
        ApiResponse::success($this->users->findPublicById($userId), null, 201);
    }

    private function login(): void
    {
        Request::requireMethod('POST');
        $body = Request::jsonBody();
        Csrf::verify($body);
        $email = strtolower($this->requiredString($body, 'email', 254));
        $password = $this->requiredString($body, 'password', 128);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($this->rateLimiter->isBlocked($email, $ipAddress)) {
            header('Retry-After: 900');
            ApiResponse::error('too_many_attempts', 'Too many sign-in attempts. Try again in 15 minutes.', 429);
        }
        $user = $this->users->findByEmail($email);

        if (
            $user === null
            || $user['account_status'] !== 'active'
            || !password_verify($password, $user['password_hash'])
        ) {
            $this->rateLimiter->recordFailure($email, $ipAddress);
            ApiResponse::error('invalid_credentials', 'The email or password is incorrect.', 401);
        }

        $this->rateLimiter->clear($email, $ipAddress);
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            if ($newHash === false) {
                throw new \RuntimeException('Password hashing failed.');
            }
            $this->users->updatePasswordHash((int) $user['id'], $newHash);
        }

        SessionManager::login((int) $user['id'], $user['role']);
        ApiResponse::success($this->users->findPublicById((int) $user['id']));
    }

    private function currentUser(): void
    {
        Request::requireMethod('GET');
        $userId = AuthMiddleware::requireUser($this->database);
        $user = $this->users->findPublicById($userId);
        if ($user === null) {
            SessionManager::logout();
            ApiResponse::error('authentication_required', 'Please sign in to continue.', 401);
        }

        ApiResponse::success($user);
    }

    private function logout(): void
    {
        Request::requireMethod('POST');
        $body = Request::jsonBody();
        Csrf::verify($body);
        AuthMiddleware::requireUser($this->database);
        SessionManager::logout();
        ApiResponse::success(['logged_out' => true]);
    }

    private function changePassword(): void
    {
        Request::requireMethod('POST');
        $userId = AuthMiddleware::requireUser($this->database);
        $body = Request::jsonBody();
        Csrf::verify($body);
        $currentPassword = $this->requiredString($body, 'current_password', 128);
        $newPassword = $this->requiredString($body, 'new_password', 128);
        $user = $this->users->findForAuthenticationById($userId);

        if (
            $user === null
            || $user['account_status'] !== 'active'
            || !password_verify($currentPassword, $user['password_hash'])
        ) {
            ApiResponse::error('invalid_credentials', 'The current password is incorrect.', 401);
        }
        $this->validatePassword($newPassword);

        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            throw new \RuntimeException('Password hashing failed.');
        }
        $this->users->updatePasswordHash($userId, $passwordHash);
        SessionManager::login($userId, $user['role']);
        ApiResponse::success(['password_updated' => true]);
    }

    private function deleteAccount(): void
    {
        Request::requireMethod('DELETE');
        $userId = AuthMiddleware::requireUser($this->database);
        $body = Request::jsonBody();
        Csrf::verify($body);
        $password = $this->requiredString($body, 'password', 128);
        $user = $this->users->findForAuthenticationById($userId);

        if (
            $user === null
            || $user['role'] !== 'student'
            || $user['account_status'] !== 'active'
            || !password_verify($password, $user['password_hash'])
        ) {
            ApiResponse::error('invalid_credentials', 'The password is incorrect.', 401);
        }

        $this->users->deleteAccount($userId);
        SessionManager::logout();
        ApiResponse::success(['account_deleted' => true]);
    }

    private function requiredString(array $body, string $field, int $maximumLength): string
    {
        $value = $body[$field] ?? null;
        if (!is_string($value)) {
            ApiResponse::error('validation_failed', 'A required field is missing or invalid.', 422);
        }

        $value = trim($value);
        if ($value === '' || strlen($value) > $maximumLength || strpos($value, "\0") !== false) {
            ApiResponse::error('validation_failed', 'A field is empty, too long, or invalid.', 422);
        }

        return $value;
    }

    private function validatePassword(string $password): void
    {
        if (strlen($password) < 12) {
            ApiResponse::error('validation_failed', 'Passwords must contain at least 12 characters.', 422);
        }
    }
}
