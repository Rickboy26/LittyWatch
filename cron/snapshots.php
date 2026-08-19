<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';


use LittyWatch\Infrastructure\Database;
use LittyWatch\Snapshots\Schema;
use LittyWatch\Snapshots\MarketStats;
use LittyWatch\Snapshots\SnapshotService;
use LittyWatch\Support\RuntimeStatus;

$root = dirname(__DIR__);
$pdo = Database::connect($root);
Schema::ensure($pdo);
$count = (new SnapshotService($pdo, new MarketStats($pdo)))->captureAll(250);
RuntimeStatus::write($root, 'snapshots', ['ok'=>true,'message'=>'Snapshots opgeslagen','count'=>$count]);
fwrite(STDOUT, sprintf("[%s] snapshots=%d\n", date(DATE_ATOM), $count));
