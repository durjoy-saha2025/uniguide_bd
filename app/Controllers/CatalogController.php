<?php
declare(strict_types=1);

namespace UniGuide\App\Controllers;

use PDO;
use UniGuide\App\Core\ApiResponse;
use UniGuide\App\Core\Request;
use UniGuide\App\Repositories\CatalogRepository;
use UniGuide\App\Support\CatalogResources;

final class CatalogController
{
    private CatalogRepository $catalog;

    public function __construct(PDO $database)
    {
        $this->catalog = new CatalogRepository($database);
    }

    public function handle(): void
    {
        Request::requireMethod('GET');
        $resource = $_GET['resource'] ?? '';
        $definition = is_string($resource) ? CatalogResources::find($resource) : null;
        if ($definition === null) {
            ApiResponse::error('invalid_resource', 'The catalog resource is invalid.', 422);
        }
        $query = $this->queryString('q', 120);
        $filters = [];
        foreach ($definition['public_filters'] as $field => $type) {
            if (!array_key_exists($field, $_GET)) {
                continue;
            }
            $value = $this->queryString($field, 180);
            if ($value === '') {
                continue;
            }
            if ($type === 'integer') {
                if (!ctype_digit($value) || (int) $value < 1) {
                    ApiResponse::error('invalid_parameter', 'A catalog filter is invalid.', 422);
                }
                $filters[$field] = (int) $value;
            } else {
                $filters[$field] = $value;
            }
        }
        $id = null;
        if (array_key_exists('id', $_GET)) {
            $value = $_GET['id'];
            if (!is_string($value) || !ctype_digit($value) || (int) $value < 1) {
                ApiResponse::error('invalid_parameter', 'The catalog record id is invalid.', 422);
            }
            $id = (int) $value;
        }
        $page = $this->queryInteger('page', 1, 1000000);
        $perPage = $this->queryInteger('per_page', 20, 100);
        if ($id !== null) {
            $record = $this->catalog->find($resource, $definition, $id);
            if ($record === null) {
                ApiResponse::error('not_found', 'The catalog record was not found.', 404);
            }
            ApiResponse::success($record);
        }
        $result = $this->catalog->list($resource, $definition, $query, $filters, $perPage, ($page - 1) * $perPage);
        ApiResponse::success($result['items'], [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $result['total'],
            'total_pages' => (int) ceil($result['total'] / $perPage),
        ]);
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
