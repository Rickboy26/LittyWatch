<?php
declare(strict_types=1);require __DIR__.'/bootstrap.php';
use LittyWatch\Knowledge\Seeder;
installSchema();$stats=(new Seeder(db(),__DIR__.'/app/Data/items.json'))->run();
?><!doctype html><html lang="nl"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Knowledge Base</title><style>body{font-family:system-ui;background:#0b1220;color:#e5e7eb;max-width:900px;margin:40px auto;padding:20px}.box{background:#151f32;padding:24px;border-radius:14px}a{color:#7db5ff}code{background:#08101f;padding:2px 5px}</style><div class="box"><h1>LittyWatch Knowledge Base</h1><p>Basiskennis geïnstalleerd.</p><p><strong><?=h((string)$stats['items'])?></strong> items · <strong><?=h((string)$stats['aliases'])?></strong> aliassen · <strong><?=h((string)$stats['groups'])?></strong> groepen</p><p><a href="gwmarket-discover.php">GW Market-bron onderzoeken</a> · <a href="parser-v2-test.php">Parser v2 testen</a></p></div>
