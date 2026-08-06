<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap-db.php';

use LittyWatch\V2\Controllers\DashboardController;
use LittyWatch\V2\Core\Response;

try {
    $controller = new DashboardController();
    $controller->index();
} catch (Throwable $e) {
    Response::error($e);
}
