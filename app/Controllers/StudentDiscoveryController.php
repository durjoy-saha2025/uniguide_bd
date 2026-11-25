<?php
declare(strict_types=1);

namespace UniGuide\App\Controllers;

use PDO;
use UniGuide\App\Core\ApiResponse;
use UniGuide\App\Core\Csrf;
use UniGuide\App\Core\Request;
use UniGuide\App\Middleware\AuthMiddleware;
use UniGuide\App\Repositories\StudentDiscoveryRepository;

final class StudentDiscoveryController
{
    private StudentDiscoveryRepository $discovery;
    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
        $this->discovery = new StudentDiscoveryRepository($database);
    }

    public function handle(): void
    {
        $userId = AuthMiddleware::requireRole(['student'], $this->database);
        $resource = $_GET['resource'] ?? '';
        if (!is_string($resource) || !in_array($resource, ['saved', 'comparison'], true)) {
            ApiResponse::error('invalid_resource', 'Choose saved or comparison.', 422);
        }
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'GET') {
            ApiResponse::success(
                $resource === 'saved'
                    ? $this->discovery->listSaved($userId)
                    : $this->discovery->listComparison($userId)
            );
        }
        if ($method === 'POST' || $method === 'DELETE') {
            $body = Request::jsonBody();
            Csrf::verify($body);
            $universityId = $this->universityId($body);
            if ($method === 'DELETE') {
                $removed = $resource === 'saved'
                    ? $this->discovery->removeSaved($userId, $universityId)
                    : $this->discovery->removeComparison($userId, $universityId);
                ApiResponse::success(['removed' => $removed]);
            }
            if ($resource === 'saved') {
                if (!$this->discovery->addSaved($userId, $universityId)) {
                    ApiResponse::error('not_found', 'The published university was not found.', 404);
                }
                ApiResponse::success(['saved' => true], null, 201);
            }
            $result = $this->discovery->addComparison($userId, $universityId);
            if ($result === 'not_found') {
                ApiResponse::error('not_found', 'The published university was not found.', 404);
            }
            if ($result === 'limit_reached') {
                ApiResponse::error('comparison_limit', 'A comparison can contain at most four universities.', 422);
            }
            ApiResponse::success(['added' => $result === 'added', 'already_added' => $result === 'already_added']);
        }
        header('Allow: GET, POST, DELETE');
        ApiResponse::error('method_not_allowed', 'The request method is not allowed.', 405);
    }

    private function universityId(array $body): int
    {
        $id = $body['university_id'] ?? null;
        if (!is_int($id) || $id < 1) {
            ApiResponse::error('validation_failed', 'A valid university_id is required.', 422);
        }

        return $id;
    }
}
