<?php
declare(strict_types=1);

use UniGuide\App\Controllers\UniversityAdminController;
use UniGuide\App\Core\Database;

require dirname(__DIR__, 3) . '/app/bootstrap.php';

(new UniversityAdminController(Database::connection()))->handle();
