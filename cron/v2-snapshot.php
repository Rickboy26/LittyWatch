<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/V2/Database.php';
require dirname(__DIR__) . '/app/V2/Schema.php';
require dirname(__DIR__) . '/app/V2/MarketStats.php';
require dirname(__DIR__) . '/app/V2/SnapshotService.php';
require dirname(__DIR__) . '/app/V2/RuntimeStatus.php';

use LittyWatch\V2\Database;
use LittyWatch\V2\Schema;
use LittyWatch\V2\MarketStats;
use LittyWatch\V2\SnapshotService;
use LittyWatch\V2\RuntimeStatus;

$root = dirname(__DIR__);
$pdo = Database::connect($root);
Schema::ensure($pdo);
$count = (new SnapshotService($pdo, new MarketStats($pdo)))->captureAll(250);
RuntimeStatus::write($root, 'snapshots', ['ok'=>true,'message'=>'Snapshots opgeslagen','count'=>$count]);
fwrite(STDOUT, sprintf("[%s] snapshots=%d\n", date(DATE_ATOM), $count));
