<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Support/Autoloader.php';
\LittyWatch\Support\Autoloader::register(dirname(__DIR__) . '/app');
$app = require dirname(__DIR__) . '/app/bootstrap.php';
$app->run(\LittyWatch\Core\Request::fromGlobals());
