<?php
declare(strict_types=1);

use UniGuide\App\Core\ApiResponse;
use UniGuide\App\Core\Csrf;
use UniGuide\App\Core\Request;
use UniGuide\App\Core\SessionManager;

require dirname(__DIR__, 2) . '/app/bootstrap.php';

Request::requireMethod('GET');
SessionManager::start();
ApiResponse::success(['token' => Csrf::token()]);
