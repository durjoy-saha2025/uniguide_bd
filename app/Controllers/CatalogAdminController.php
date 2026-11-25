<?php
declare(strict_types=1);

namespace UniGuide\App\Controllers;

use PDO;
use PDOException;
use UniGuide\App\Core\ApiResponse;
use UniGuide\App\Core\Csrf;
use UniGuide\App\Core\Request;
use UniGuide\App\Middleware\AuthMiddleware;
use UniGuide\App\Repositories\CatalogAdminRepository;
use UniGuide\App\Support\CatalogResources;

final class CatalogAdminController
{
    private CatalogAdminRepository $catalog;
    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
        $this->catalog = new CatalogAdminRepository($database);
    }

    public function handle(): void
    {
        $actorId = AuthMiddleware::requireRole(['admin', 'super_admin'], $this->database);
        $resource = $_GET['resource'] ?? '';
        $definition = is_string($resource) ? CatalogResources::find($resource) : null;
        if ($definition === null) {
            ApiResponse::error('invalid_resource', 'The catalog resource is invalid.', 422);
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'GET') {
            $this->read($resource, $definition);
        }
        if (in_array($method, ['POST', 'PUT'], true)) {
            $body = Request::jsonBody();
            Csrf::verify($body);
            $data = $this->validate($resource, $definition, $body);
            $id = $method === 'PUT' ? $this->requiredId() : null;
            try {
                if ($id === null) {
                    $id = $this->catalog->create($resource, $definition, $data, $actorId);
                } elseif (!$this->catalog->update($resource, $definition, $id, $data, $actorId)) {
                    ApiResponse::error('not_found', 'The catalog record was not found.', 404);
                }
            } catch (PDOException $exception) {
                $this->handleWriteException($exception);
            }
            ApiResponse::success(
                $this->catalog->find($resource, $definition, $id),
                null,
                $method === 'POST' ? 201 : 200
            );
        }
        if ($method === 'DELETE') {
            $body = Request::jsonBody();
            Csrf::verify($body);
            try {
                if (!$this->catalog->delete($resource, $definition, $this->requiredId(), $actorId)) {
                    ApiResponse::error('not_found', 'The catalog record was not found.', 404);
                }
            } catch (PDOException $exception) {
                $this->handleWriteException($exception);
            }
            ApiResponse::success(['deleted' => true]);
        }

        header('Allow: GET, POST, PUT, DELETE');
        ApiResponse::error('method_not_allowed', 'The request method is not allowed.', 405);
    }

    private function read(string $resource, array $definition): void
    {
        $id = $this->optionalId();
        if ($id !== null) {
            $record = $this->catalog->find($resource, $definition, $id);
            if ($record === null) {
                ApiResponse::error('not_found', 'The catalog record was not found.', 404);
            }
            ApiResponse::success($record);
        }

        $query = $this->queryString('q', 120);
        $filters = [];
        foreach ($definition['filters'] as $field => $rule) {
            if (!array_key_exists($field, $_GET)) {
                continue;
            }
            $value = $this->queryString($field, 120);
            if ($value === '') {
                continue;
            }
            if ($rule === 'integer') {
                if (!ctype_digit($value) || (int) $value < 1) {
                    ApiResponse::error('invalid_parameter', 'A catalog filter is invalid.', 422);
                }
                $filters[$field] = (int) $value;
            } elseif (is_array($rule)) {
                if (!in_array($value, $rule, true)) {
                    ApiResponse::error('invalid_parameter', 'A catalog filter is invalid.', 422);
                }
                $filters[$field] = $value;
            } else {
                $filters[$field] = $value;
            }
        }

        $page = $this->queryInteger('page', 1, 1000000);
        $perPage = $this->queryInteger('per_page', 20, 100);
        $sortable = array_merge($definition['columns'], ['id']);
        $sort = $this->queryString('sort', 40);
        if ($sort === '') {
            $sort = in_array('updated_at', $sortable, true) ? 'updated_at' : 'created_at';
        }
        if (!in_array($sort, $sortable, true)) {
            ApiResponse::error('invalid_parameter', 'The sort field is invalid.', 422);
        }
        $direction = strtoupper($this->queryString('direction', 4));
        if ($direction === '') {
            $direction = 'DESC';
        }
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            ApiResponse::error('invalid_parameter', 'The sort direction is invalid.', 422);
        }
        $result = $this->catalog->list(
            $resource,
            $definition,
            $query,
            $filters,
            $perPage,
            ($page - 1) * $perPage,
            $sort,
            $direction
        );
        ApiResponse::success($result['items'], [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $result['total'],
            'total_pages' => (int) ceil($result['total'] / $perPage),
        ]);
    }

    private function validate(string $resource, array $definition, array $body): array
    {
        $rules = $definition['rules'];
        if (array_diff(array_keys($body), array_keys($rules)) !== []) {
            ApiResponse::error('validation_failed', 'The request contains an unsupported field.', 422);
        }
        $data = [];
        foreach ($rules as $field => $rule) {
            $value = $body[$field] ?? ($rule['default'] ?? null);
            if ($value === '' && !($rule['required'] ?? false)) {
                $value = null;
            }
            if ($value === null) {
                if ($rule['required'] ?? false) {
                    ApiResponse::error('validation_failed', 'A required catalog field is missing.', 422);
                }
                if (array_key_exists('default', $rule)) {
                    $value = $rule['default'];
                } else {
                    $data[$field] = null;
                    continue;
                }
            }
            $data[$field] = $this->validateValue($field, $value, $rule);
        }

        if (isset($data['slug']) && !preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $data['slug'])) {
            ApiResponse::error('validation_failed', 'The slug format is invalid.', 422);
        }
        if (
            $resource === 'scholarships'
            && ($data['award_type'] ?? null) === 'percentage'
            && isset($data['award_value'])
            && (float) $data['award_value'] > 100
        ) {
            ApiResponse::error('validation_failed', 'A percentage scholarship cannot exceed 100.', 422);
        }
        if (
            $resource === 'program-fees'
            && isset($data['effective_from'], $data['effective_until'])
            && $data['effective_from'] > $data['effective_until']
        ) {
            ApiResponse::error('validation_failed', 'The fee effective date range is invalid.', 422);
        }
        $verification = $data['verification_status'] ?? null;
        if ($verification === 'verified') {
            $sourceId = $data['source_id'] ?? null;
            if (!is_int($sourceId) || !$this->catalog->sourceIsVerified($sourceId)) {
                ApiResponse::error('validation_failed', 'A verified source is required to verify this record.', 422);
            }
        }
        if (
            (($data['status'] ?? null) === 'published' || ($data['workflow_status'] ?? null) === 'published')
            && isset($data['verification_status'])
            && $data['verification_status'] !== 'verified'
        ) {
            ApiResponse::error('validation_failed', 'Only verified records may be published.', 422);
        }
        if (in_array($resource, ['program-fees', 'admission-requirements', 'rankings'], true)
            && $verification === 'verified'
            && (empty($data['source_id']) || !$this->catalog->sourceIsVerified((int) $data['source_id']))) {
            ApiResponse::error('validation_failed', 'A verified source is required to verify this record.', 422);
        }
        if ($resource === 'news' && ($data['workflow_status'] ?? '') === 'published') {
            if (empty($data['source_id']) || !$this->catalog->sourceIsVerified((int) $data['source_id'])) {
                ApiResponse::error('validation_failed', 'Published news requires a verified source.', 422);
            }
        }

        if (array_key_exists('verification_status', $data)) {
            $data['verified_at'] = $data['verification_status'] === 'verified' ? gmdate('Y-m-d H:i:s') : null;
        }
        if ($resource === 'guides') {
            $data['published_at'] = $data['status'] === 'published' ? gmdate('Y-m-d H:i:s') : null;
        }
        return $data;
    }

    private function validateValue(string $field, $value, array $rule)
    {
        switch ($rule['type']) {
            case 'string':
            case 'slug':
                if (!is_string($value)) {
                    $this->invalidField();
                }
                $value = trim($value);
                if ($value === '' || strlen($value) > $rule['max'] || strpos($value, "\0") !== false) {
                    $this->invalidField();
                }
                return $value;
            case 'integer':
                if (!is_int($value) && (!is_string($value) || !ctype_digit($value))) {
                    $this->invalidField();
                }
                $number = (int) $value;
                if ($number < ($rule['min'] ?? PHP_INT_MIN) || $number > ($rule['max'] ?? PHP_INT_MAX)) {
                    $this->invalidField();
                }
                return $number;
            case 'decimal':
                if ((!is_int($value) && !is_float($value) && !is_string($value)) || !is_numeric($value)) {
                    $this->invalidField();
                }
                $number = (float) $value;
                if (!is_finite($number) || $number < $rule['min'] || $number > $rule['max']) {
                    $this->invalidField();
                }
                return (string) $value;
            case 'enum':
                if (!is_string($value) || !in_array($value, $rule['values'], true)) {
                    $this->invalidField();
                }
                return $value;
            case 'boolean':
                if (!is_bool($value)) {
                    $this->invalidField();
                }
                return $value ? 1 : 0;
            case 'currency':
                if (!is_string($value) || !preg_match('/\A[A-Z]{3}\z/', $value)) {
                    $this->invalidField();
                }
                return $value;
            case 'date':
                if (!is_string($value) || !preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value)) {
                    $this->invalidField();
                }
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
                if ($date === false || $date->format('Y-m-d') !== $value) {
                    $this->invalidField();
                }
                return $value;
            case 'datetime':
                if (!is_string($value)) {
                    $this->invalidField();
                }
                $dateTime = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
                if ($dateTime === false || $dateTime->format('Y-m-d H:i:s') !== $value) {
                    $this->invalidField();
                }
                return $value;
            case 'json':
                if (!is_array($value)) {
                    $this->invalidField();
                }
                return json_encode($value, JSON_THROW_ON_ERROR);
        }

        $this->invalidField();
    }

    private function invalidField(): void
    {
        ApiResponse::error('validation_failed', 'A catalog field has an invalid value.', 422);
    }

    private function handleWriteException(PDOException $exception): void
    {
        if ($exception->getCode() === '23000') {
            ApiResponse::error('conflict', 'The record conflicts with existing catalog data or references.', 409);
        }

        throw $exception;
    }

    private function optionalId(): ?int
    {
        if (!array_key_exists('id', $_GET)) {
            return null;
        }
        $value = $_GET['id'];
        if (!is_string($value) || !ctype_digit($value) || (int) $value < 1) {
            ApiResponse::error('invalid_parameter', 'The catalog record id is invalid.', 422);
        }

        return (int) $value;
    }

    private function requiredId(): int
    {
        $id = $this->optionalId();
        if ($id === null) {
            ApiResponse::error('invalid_parameter', 'A catalog record id is required.', 422);
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
