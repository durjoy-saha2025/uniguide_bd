<?php
declare(strict_types=1);

use UniGuide\App\Controllers\StudentPlanningController;
use UniGuide\App\Core\Database;
use UniGuide\App\Core\Request;

require dirname(__DIR__, 3) . '/app/bootstrap.php';

Request::requireMethod('GET');
(new StudentPlanningController(Database::connection()))->handle();
