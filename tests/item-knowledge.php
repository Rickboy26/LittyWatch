<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Support/Autoloader.php';
\LittyWatch\Support\Autoloader::register(dirname(__DIR__) . '/app');

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$repo = new \LittyWatch\Repositories\ItemKnowledgeRepository($pdo);

$seed = $repo->find("Madruk's Prophecy");
$failed = [];
if (!$seed || (int)$seed['is_unique'] !== 1 || (int)$seed['fixed_stats'] !== 1 || (int)$seed['modifiable'] !== 0) {
    $failed[] = 'seed';
}

$repo->save([
    'item_name' => 'Platinum Wand',
    'rarity' => 'rare',
    'item_class' => 'wand',
    'modifiable' => 1,
    'canonical_stats' => '',
]);
$wand = $repo->find('Platinum Wand');
if (!$wand || $wand['rarity'] !== 'rare' || (int)$wand['modifiable'] !== 1) {
    $failed[] = 'save';
}

echo json_encode(['ok'=>$failed===[],'failed'=>$failed], JSON_PRETTY_PRINT) . PHP_EOL;
exit($failed===[] ? 0 : 1);
