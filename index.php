<?php
declare(strict_types=1);

// Compatibele front controller voor de huidige Apache DocumentRoot.
require __DIR__ . '/app/Support/Autoloader.php';
\LittyWatch\Support\Autoloader::register(__DIR__ . '/app');
$app = require __DIR__ . '/app/bootstrap.php';
$app->run(\LittyWatch\Core\Request::fromGlobals());
