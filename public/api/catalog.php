<?php
declare(strict_types=1);

use UniGuide\App\Controllers\CatalogController;
use UniGuide\App\Core\Database;

require dirname(__DIR__, 2) . '/app/bootstrap.php';

(new CatalogController(Database::connection()))->handle();
