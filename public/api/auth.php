<?php
declare(strict_types=1);

use UniGuide\App\Controllers\AuthController;
use UniGuide\App\Core\Database;

require dirname(__DIR__, 2) . '/app/bootstrap.php';

(new AuthController(Database::connection()))->handle();
