<?php
declare(strict_types=1);

namespace UniGuide\App\Controllers;

use PDO;
use UniGuide\App\Core\ApiResponse;
use UniGuide\App\Core\Csrf;
use UniGuide\App\Core\Request;
use UniGuide\App\Middleware\AuthMiddleware;
use UniGuide\App\Repositories\StudentProfileRepository;

final class StudentProfileController
{
    private PDO $database;
    private StudentProfileRepository $profiles;

    public function __construct(PDO $database)
    {
        $this->database = $database;
        $this->profiles = new StudentProfileRepository($database);
    }

    public function handle(): void
    {
        $userId = AuthMiddleware::requireUser($this->database);
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method === 'GET') {
            ApiResponse::success($this->profiles->findByUserId($userId));
        }

        if ($method === 'PUT') {
            $body = Request::jsonBody();
            Csrf::verify($body);
            unset($body['_csrf']);
            $profile = $this->validate($body);
            $this->profiles->save($userId, $profile);
            ApiResponse::success($this->profiles->findByUserId($userId));
        }

        if ($method === 'DELETE') {
            $body = Request::jsonBody();
            Csrf::verify($body);
            $this->profiles->delete($userId);
            ApiResponse::success(['profile_deleted' => true]);
        }

        header('Allow: GET, PUT, DELETE');
        ApiResponse::error('method_not_allowed', 'The request method is not allowed.', 405);
    }

    private function validate(array $body): array
    {
        $allowedFields = [
            'ssc_gpa',
            'ssc_group',
            'ssc_passing_year',
            'hsc_gpa',
            'hsc_group',
            'hsc_passing_year',
            'equivalent_qualification',
            'preferred_subject',
            'preferred_location',
            'maximum_budget',
            'scholarship_preference',
            'campus_preference',
            'admission_test_preference',
        ];

        foreach ($body as $field => $value) {
            if (!in_array($field, $allowedFields, true)) {
                ApiResponse::error('validation_failed', 'The profile contains an unsupported field.', 422);
            }
        }

        return [
            'ssc_gpa' => $this->decimal($body, 'ssc_gpa', 0, 5),
            'ssc_group' => $this->optionalString($body, 'ssc_group', 60),
            'ssc_passing_year' => $this->year($body, 'ssc_passing_year'),
            'hsc_gpa' => $this->decimal($body, 'hsc_gpa', 0, 5),
            'hsc_group' => $this->optionalString($body, 'hsc_group', 60),
            'hsc_passing_year' => $this->year($body, 'hsc_passing_year'),
            'equivalent_qualification' => $this->qualification($body),
            'preferred_subject' => $this->optionalString($body, 'preferred_subject', 120),
            'preferred_location' => $this->optionalString($body, 'preferred_location', 100),
            'maximum_budget' => $this->decimal($body, 'maximum_budget', 0, 1000000000),
            'scholarship_preference' => $this->boolean($body, 'scholarship_preference'),
            'campus_preference' => $this->optionalString($body, 'campus_preference', 80),
            'admission_test_preference' => $this->admissionTestPreference($body),
        ];
    }

    private function optionalString(array $body, string $field, int $maximumLength): ?string
    {
        $value = $body[$field] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || strlen($value) > $maximumLength || strpos($value, "\0") !== false) {
            ApiResponse::error('validation_failed', 'A profile field has an invalid value.', 422);
        }

        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private function decimal(array $body, string $field, float $minimum, float $maximum): ?float
    {
        $value = $body[$field] ?? null;
        if ($value === null) {
            return null;
        }
        if ((!is_string($value) && !is_int($value) && !is_float($value)) || !is_numeric($value)) {
            ApiResponse::error('validation_failed', 'A numeric profile field has an invalid value.', 422);
        }

        $number = (float) $value;
        if (!is_finite($number) || $number < $minimum || $number > $maximum) {
            ApiResponse::error('validation_failed', 'A numeric profile field is outside the allowed range.', 422);
        }

        return $number;
    }

    private function year(array $body, string $field): ?int
    {
        $value = $body[$field] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_int($value) || $value < 1950 || $value > (int) date('Y') + 1) {
            ApiResponse::error('validation_failed', 'A passing year is outside the allowed range.', 422);
        }

        return $value;
    }

    private function boolean(array $body, string $field): ?bool
    {
        $value = $body[$field] ?? null;
        if ($value !== null && !is_bool($value)) {
            ApiResponse::error('validation_failed', 'A preference field must be true, false, or null.', 422);
        }

        return $value;
    }

    private function qualification(array $body): ?string
    {
        $value = $body['equivalent_qualification'] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_array($value)) {
            ApiResponse::error('validation_failed', 'Equivalent qualification details must be an object.', 422);
        }

        return json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }

    private function admissionTestPreference(array $body): string
    {
        $value = $body['admission_test_preference'] ?? 'unknown';
        $allowed = ['unknown', 'required', 'preferred', 'not_required'];
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            ApiResponse::error('validation_failed', 'Admission-test preference is invalid.', 422);
        }

        return $value;
    }
}
