<?php
declare(strict_types=1);

use UniGuide\App\Controllers\SourceAdminController;
use UniGuide\App\Core\Database;

require dirname(__DIR__, 3) . '/app/bootstrap.php';

(new SourceAdminController(Database::connection()))->handle();
