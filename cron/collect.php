<?php
require dirname(__DIR__).'/bootstrap.php';
try { echo json_encode(collectMessages(), JSON_UNESCAPED_SLASHES).PHP_EOL; }
catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
