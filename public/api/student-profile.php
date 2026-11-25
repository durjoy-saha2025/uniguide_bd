<?php
declare(strict_types=1);

use UniGuide\App\Controllers\StudentProfileController;
use UniGuide\App\Core\Database;

require dirname(__DIR__, 2) . '/app/bootstrap.php';

(new StudentProfileController(Database::connection()))->handle();
