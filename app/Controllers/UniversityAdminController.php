<?php
declare(strict_types=1);

namespace UniGuide\App\Controllers;

use PDO;
use PDOException;
use UniGuide\App\Core\ApiResponse;
use UniGuide\App\Core\Csrf;
use UniGuide\App\Core\Request;
use UniGuide\App\Core\SessionManager;
use UniGuide\App\Middleware\AuthMiddleware;
use UniGuide\App\Repositories\UniversityAdminRepository;

final class UniversityAdminController
{
    private PDO $database;
    private UniversityAdminRepository $universities;

    public function __construct(PDO $database)
    {
        $this->database = $database;
        $this->universities = new UniversityAdminRepository($database);
    }

    public function handle(): void
    {
        $actorId = AuthMiddleware::requireRole(['admin', 'super_admin'], $this->database);
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'GET') {
            $this->read();
        }
        if ($method === 'POST') {
            $body = Request::jsonBody();
            Csrf::verify($body);
            $university = $this->validate($body, false);
            $this->create($university, $actorId);
        }
        if ($method === 'PUT') {
            $body = Request::jsonBody();
            Csrf::verify($body);
            $id = $this->requiredId();
            $university = $this->validate($body, false);
            $this->update($id, $university, $actorId);
        }
        if ($method === 'DELETE') {
            $body = Request::jsonBody();
            Csrf::verify($body);
            $id = $this->requiredId();
            if (!$this->universities->delete($id, $actorId)) {
                ApiResponse::error('not_found', 'The university was not found.', 404);
            }
            ApiResponse::success(['deleted' => true]);
        }

        header('Allow: GET, POST, PUT, DELETE');
        ApiResponse::error('method_not_allowed', 'The request method is not allowed.', 405);
    }

    private function read(): void
    {
        $id = $this->optionalId();
        if ($id !== null) {
            $university = $this->universities->findById($id);
            if ($university === null) {
                ApiResponse::error('not_found', 'The university was not found.', 404);
            }
            ApiResponse::success($university);
        }

        $query = $this->queryString('q', 120);
        $status = $this->queryString('status', 20);
        if ($status !== '' && !in_array($status, ['draft', 'published', 'archived'], true)) {
            ApiResponse::error('invalid_parameter', 'The status filter is invalid.', 422);
        }
        $page = $this->queryInteger('page', 1, 1000000);
        $perPage = $this->queryInteger('per_page', 20, 100);
        $sort = $this->queryString('sort', 40);
        if ($sort === '') {
            $sort = 'updated_at';
        }
        $direction = strtoupper($this->queryString('direction', 4));
        if ($direction === '') {
            $direction = 'DESC';
        }
        if (!in_array($sort, ['name', 'city', 'status', 'verification_status', 'created_at', 'updated_at', 'id'], true)
            || !in_array($direction, ['ASC', 'DESC'], true)) {
            ApiResponse::error('invalid_parameter', 'The sort option is invalid.', 422);
        }
        $result = $this->universities->list($query, $status, $perPage, ($page - 1) * $perPage, $sort, $direction);

        ApiResponse::success($result['items'], [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $result['total'],
            'total_pages' => (int) ceil($result['total'] / $perPage),
        ]);
    }

    private function create(array $university, int $actorId): void
    {
        try {
            $id = $this->universities->create($university, $actorId);
        } catch (PDOException $exception) {
            $this->handleWriteException($exception);
        }

        ApiResponse::success($this->universities->findById($id), null, 201);
    }

    private function update(int $id, array $university, int $actorId): void
    {
        try {
            if (!$this->universities->update($id, $university, $actorId)) {
                ApiResponse::error('not_found', 'The university was not found.', 404);
            }
        } catch (PDOException $exception) {
            $this->handleWriteException($exception);
        }

        ApiResponse::success($this->universities->findById($id));
    }

    private function handleWriteException(PDOException $exception): void
    {
        if ($exception->getCode() === '23000') {
            ApiResponse::error('conflict', 'A university with that slug already exists.', 409);
        }

        throw $exception;
    }

    private function validate(array $body, bool $partial): array
    {
        $allowed = [
            'name', 'slug', 'summary', 'description', 'city', 'address',
            'campus_type', 'website_url', 'admissions_url', 'logo_url',
            'cover_image_url', 'source_id', 'ugc_status', 'verification_status', 'status',
        ];
        foreach ($body as $field => $value) {
            if (!in_array($field, $allowed, true)) {
                ApiResponse::error('validation_failed', 'The request contains an unsupported field.', 422);
            }
        }

        if ($partial) {
            ApiResponse::error('validation_failed', 'Partial updates are not supported; send all editable fields.', 422);
        }

        $name = $this->requiredString($body, 'name', 200);
        $slug = strtolower($this->requiredString($body, 'slug', 120));
        if (!preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $slug)) {
            ApiResponse::error('validation_failed', 'The slug must contain lowercase letters, numbers, and hyphens.', 422);
        }

        $status = $this->enum($body, 'status', ['draft', 'published', 'archived'], 'draft');
        $verification = $this->enum(
            $body,
            'verification_status',
            ['draft', 'pending', 'verified', 'rejected'],
            'draft'
        );
        if ($status === 'published' && $verification !== 'verified') {
            ApiResponse::error('validation_failed', 'Only verified universities may be published.', 422);
        }
        $sourceId = $this->optionalInteger($body, 'source_id');
        if ($verification === 'verified') {
            if ($sourceId === null || !$this->universities->isVerifiedSource($sourceId)) {
                ApiResponse::error('validation_failed', 'A verified source is required to verify a university.', 422);
            }
        }

        return [
            'name' => $name,
            'slug' => $slug,
            'summary' => $this->optionalString($body, 'summary', 500),
            'description' => $this->optionalString($body, 'description', 60000),
            'city' => $this->optionalString($body, 'city', 100),
            'address' => $this->optionalString($body, 'address', 500),
            'campus_type' => $this->optionalString($body, 'campus_type', 80),
            'website_url' => $this->optionalUrl($body, 'website_url'),
            'admissions_url' => $this->optionalUrl($body, 'admissions_url'),
            'logo_url' => $this->optionalUrl($body, 'logo_url'),
            'cover_image_url' => $this->optionalUrl($body, 'cover_image_url'),
            'source_id' => $sourceId,
            'ugc_status' => $this->enum(
                $body,
                'ugc_status',
                ['unknown', 'pending', 'verified', 'not_verified'],
                'unknown'
            ),
            'verification_status' => $verification,
            'verified_at' => $verification === 'verified' ? gmdate('Y-m-d H:i:s') : null,
            'status' => $status,
        ];
    }

    private function optionalInteger(array $body, string $field): ?int
    {
        $value = $body[$field] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_int($value) && (!is_string($value) || !ctype_digit($value))) {
            ApiResponse::error('validation_failed', 'A numeric field has an invalid value.', 422);
        }
        $integer = (int) $value;
        if ($integer < 1) {
            ApiResponse::error('validation_failed', 'A numeric field has an invalid value.', 422);
        }

        return $integer;
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

    private function optionalString(array $body, string $field, int $maximumLength): ?string
    {
        $value = $body[$field] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || strlen($value) > $maximumLength || strpos($value, "\0") !== false) {
            ApiResponse::error('validation_failed', 'A text field has an invalid value.', 422);
        }

        return trim($value);
    }

    private function optionalUrl(array $body, string $field): ?string
    {
        $url = $this->optionalString($body, $field, 2048);
        if ($url === null) {
            return null;
        }

        $parts = parse_url($url);
        if (
            filter_var($url, FILTER_VALIDATE_URL) === false
            || !is_array($parts)
            || !isset($parts['scheme'])
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
        ) {
            ApiResponse::error('validation_failed', 'A URL field must be a valid HTTP or HTTPS address.', 422);
        }

        return $url;
    }

    private function enum(array $body, string $field, array $allowed, string $default): string
    {
        $value = $body[$field] ?? $default;
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            ApiResponse::error('validation_failed', 'A status field has an invalid value.', 422);
        }

        return $value;
    }

    private function optionalId(): ?int
    {
        if (!array_key_exists('id', $_GET)) {
            return null;
        }
        $value = $_GET['id'];
        if (!is_string($value) || !ctype_digit($value) || (int) $value < 1) {
            ApiResponse::error('invalid_parameter', 'The university id is invalid.', 422);
        }

        return (int) $value;
    }

    private function requiredId(): int
    {
        $id = $this->optionalId();
        if ($id === null) {
            ApiResponse::error('invalid_parameter', 'A university id is required.', 422);
        }

        return $id;
    }

    private function queryString(string $name, int $maximumLength): string
    {
        $value = $_GET[$name] ?? '';
        if (!is_string($value) || strlen($value) > $maximumLength) {
            ApiResponse::error('invalid_parameter', 'A query parameter is invalid.', 422);
        }

        return trim($value);
    }

    private function queryInteger(string $name, int $default, int $maximum): int
    {
        if (!array_key_exists($name, $_GET)) {
            return $default;
        }
        $value = $_GET[$name];
        if (!is_string($value) || !ctype_digit($value) || (int) $value < 1 || (int) $value > $maximum) {
            ApiResponse::error('invalid_parameter', 'A pagination parameter is invalid.', 422);
        }

        return (int) $value;
    }
}
