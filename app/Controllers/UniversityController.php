<?php
declare(strict_types=1);

namespace UniGuide\App\Controllers;

use UniGuide\App\Core\ApiResponse;
use UniGuide\App\Repositories\UniversityRepository;

final class UniversityController
{
    public function __construct(private UniversityRepository $universities)
    {
    }

    public function index(): void
    {
        $slug = $this->stringParameter('slug', 100);
        if (array_key_exists('slug', $_GET)) {
            if (!preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $slug)) {
                ApiResponse::error('invalid_slug', 'The university slug is invalid.', 422);
            }

            $university = $this->universities->findPublishedBySlug($slug);
            if ($university === null) {
                ApiResponse::error('not_found', 'The university was not found.', 404);
            }

            ApiResponse::success($university);
        }

        $query = $this->stringParameter('q', 120);
        $city = $this->stringParameter('city', 100);
        $page = $this->integerParameter('page', 1, 1000000);
        $perPage = $this->integerParameter('per_page', 20, 100);
        $total = $this->universities->countPublished($query, $city);
        $offset = ($page - 1) * $perPage;
        $items = $this->universities->listPublished($query, $city, $perPage, $offset);

        ApiResponse::success($items, [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => (int) ceil($total / $perPage),
        ]);
    }

    private function stringParameter(string $name, int $maximumLength): string
    {
        if (!array_key_exists($name, $_GET)) {
            return '';
        }

        $value = $_GET[$name];
        if (!is_string($value)) {
            ApiResponse::error('invalid_parameter', 'A query parameter has an invalid format.', 422);
        }

        $value = trim($value);
        if (strlen($value) > $maximumLength || strpos($value, "\0") !== false) {
            ApiResponse::error('invalid_parameter', 'A query parameter is too long or invalid.', 422);
        }

        return $value;
    }

    private function integerParameter(string $name, int $default, int $maximum): int
    {
        if (!array_key_exists($name, $_GET)) {
            return $default;
        }

        $value = $_GET[$name];
        if (!is_string($value) || !ctype_digit($value)) {
            ApiResponse::error('invalid_parameter', 'A pagination parameter must be a positive integer.', 422);
        }

        $number = (int) $value;
        if ($number < 1 || $number > $maximum) {
            ApiResponse::error('invalid_parameter', 'A pagination parameter is outside the allowed range.', 422);
        }

        return $number;
    }
}
