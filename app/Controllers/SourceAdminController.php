<?php
declare(strict_types=1);

namespace UniGuide\App\Controllers;

use PDO;
use PDOException;
use UniGuide\App\Core\ApiResponse;
use UniGuide\App\Core\Csrf;
use UniGuide\App\Core\Request;
use UniGuide\App\Middleware\AuthMiddleware;
use UniGuide\App\Repositories\SourceAdminRepository;

final class SourceAdminController
{
    private SourceAdminRepository $sources;
    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
        $this->sources = new SourceAdminRepository($database);
    }

    public function handle(): void
    {
        $actorId = AuthMiddleware::requireRole(['admin', 'super_admin'], $this->database);
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'GET') {
            $this->read();
        }
        if ($method === 'POST' || $method === 'PUT') {
            $body = Request::jsonBody();
            Csrf::verify($body);
            $source = $this->validate($body);
            $id = $method === 'POST' ? null : $this->requiredId();
            if ($id === null) {
                $id = $this->sources->create($source, $actorId);
            } elseif (!$this->sources->update($id, $source, $actorId)) {
                ApiResponse::error('not_found', 'The source was not found.', 404);
            }
            ApiResponse::success($this->sources->findById($id), null, $method === 'POST' ? 201 : 200);
        }
        if ($method === 'DELETE') {
            $body = Request::jsonBody();
            Csrf::verify($body);
            try {
                if (!$this->sources->delete($this->requiredId(), $actorId)) {
                    ApiResponse::error('not_found', 'The source was not found.', 404);
                }
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') {
                    ApiResponse::error('conflict', 'This source is referenced by university data and cannot be deleted.', 409);
                }
                throw $exception;
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
            $source = $this->sources->findById($id);
            if ($source === null) {
                ApiResponse::error('not_found', 'The source was not found.', 404);
            }
            ApiResponse::success($source);
        }

        $query = $this->queryString('q', 120);
        $status = $this->queryString('verification_status', 20);
        if ($status !== '' && !in_array($status, ['pending', 'verified', 'rejected'], true)) {
            ApiResponse::error('invalid_parameter', 'The verification status filter is invalid.', 422);
        }
        $page = $this->queryInteger('page', 1, 1000000);
        $perPage = $this->queryInteger('per_page', 20, 100);
        $result = $this->sources->list($query, $status, $perPage, ($page - 1) * $perPage);
        ApiResponse::success($result['items'], [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $result['total'],
            'total_pages' => (int) ceil($result['total'] / $perPage),
        ]);
    }

    private function validate(array $body): array
    {
        $allowed = ['title', 'source_type', 'url', 'publisher', 'notes', 'verification_status'];
        if (array_diff(array_keys($body), $allowed) !== []) {
            ApiResponse::error('validation_failed', 'The request contains an unsupported field.', 422);
        }
        $title = $this->requiredString($body, 'title', 255);
        $type = $this->requiredString($body, 'source_type', 80);
        $url = $this->requiredString($body, 'url', 2048);
        $parts = parse_url($url);
        if (
            filter_var($url, FILTER_VALIDATE_URL) === false
            || !is_array($parts)
            || !isset($parts['scheme'])
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
        ) {
            ApiResponse::error('validation_failed', 'The source URL must be a valid HTTP or HTTPS address.', 422);
        }
        $status = $body['verification_status'] ?? 'pending';
        if (!is_string($status) || !in_array($status, ['pending', 'verified', 'rejected'], true)) {
            ApiResponse::error('validation_failed', 'The source verification status is invalid.', 422);
        }

        return [
            'title' => $title,
            'source_type' => $type,
            'url' => $url,
            'publisher' => $this->optionalString($body, 'publisher', 180),
            'notes' => $this->optionalString($body, 'notes', 5000),
            'verification_status' => $status,
            'verified_at' => $status === 'verified' ? gmdate('Y-m-d H:i:s') : null,
        ];
    }

    private function requiredString(array $body, string $field, int $maximumLength): string
    {
        $value = $body[$field] ?? null;
        if (!is_string($value) || trim($value) === '' || strlen(trim($value)) > $maximumLength) {
            ApiResponse::error('validation_failed', 'A required source field is missing or invalid.', 422);
        }

        return trim($value);
    }

    private function optionalString(array $body, string $field, int $maximumLength): ?string
    {
        $value = $body[$field] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || strlen($value) > $maximumLength) {
            ApiResponse::error('validation_failed', 'An optional source field is invalid.', 422);
        }

        return trim($value);
    }

    private function optionalId(): ?int
    {
        if (!array_key_exists('id', $_GET)) {
            return null;
        }
        $id = $_GET['id'];
        if (!is_string($id) || !ctype_digit($id) || (int) $id < 1) {
            ApiResponse::error('invalid_parameter', 'The source id is invalid.', 422);
        }

        return (int) $id;
    }

    private function requiredId(): int
    {
        $id = $this->optionalId();
        if ($id === null) {
            ApiResponse::error('invalid_parameter', 'A source id is required.', 422);
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
