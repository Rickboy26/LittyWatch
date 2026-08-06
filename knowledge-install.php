<?php
declare(strict_types=1);require __DIR__.'/bootstrap.php';
use LittyWatch\Knowledge\Seeder;
installSchema();
$stats=(new Seeder(db(),__DIR__.'/app/Data/items.json',__DIR__.'/app/Data/attributes.json',__DIR__.'/app/Data/item-profiles.json'))->run();
?><!doctype html><html lang="nl"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Knowledge Base</title><style>body{font-family:system-ui;background:#0b1220;color:#e5e7eb;max-width:900px;margin:40px auto;padding:20px}.box{background:#151f32;padding:24px;border-radius:14px}a{color:#7db5ff}code{background:#08101f;padding:2px 5px}</style><div class="box"><h1>LittyWatch Knowledge Base v1.4</h1><p>Items, attributes en itemprofielen zijn geïnstalleerd.</p><p><strong><?=h((string)$stats['items'])?></strong> items · <strong><?=h((string)$stats['aliases'])?></strong> aliassen · <strong><?=h((string)$stats['attributes'])?></strong> attributes · <strong><?=h((string)$stats['profiles'])?></strong> profielen · <strong><?=h((string)$stats['profile_assignments'])?></strong> itemtoewijzingen</p><p><a href="knowledge">Knowledge Base bekijken</a> · <a href="parser-v2-test.php">Parser v2 testen</a></p></div>
