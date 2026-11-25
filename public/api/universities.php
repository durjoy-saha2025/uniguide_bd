<?php
declare(strict_types=1);

use UniGuide\App\Controllers\UniversityController;
use UniGuide\App\Core\Database;
use UniGuide\App\Repositories\UniversityRepository;

require dirname(__DIR__, 2) . '/app/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    \UniGuide\App\Core\ApiResponse::error(
        'method_not_allowed',
        'Only GET requests are supported.',
        405
    );
}

$controller = new UniversityController(
    new UniversityRepository(Database::connection())
);
$controller->index();
