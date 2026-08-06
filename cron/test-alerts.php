<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Alleen via CLI.\n"); }
require __DIR__ . '/evaluate-alerts.php';
