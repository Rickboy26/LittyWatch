<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/Support/Autoloader.php';
\LittyWatch\Support\Autoloader::register(dirname(__DIR__) . '/app');

$root = sys_get_temp_dir() . '/littywatch-kp-' . bin2hex(random_bytes(4));
$pack = $root . '/pack';
$stage = $root . '/stage';
mkdir($pack,0777,true);
mkdir($stage,0777,true);

file_put_contents($pack . '/sources.json','{}');
file_put_contents($pack . '/items.json','[]');
file_put_contents($pack . '/aliases.json',json_encode([
    ['alias'=>'eblade','item'=>'Eternal Blade','source'=>'community']
]));
file_put_contents($pack . '/metadata.json','{}');

$repo = new \LittyWatch\Repositories\KnowledgePackRepository($pack,$stage);
$service = new \LittyWatch\Services\KnowledgePackService($repo);

$service->stage('unique-items','unique-item',[
    [
        'title'=>"Madruk's Prophecy",
        'pageid'=>123,
        'fullurl'=>'https://wiki.guildwars.com/wiki/Madruk%27s_Prophecy',
        'extract'=>'Madruk’s Prophecy is a unique item and a staff.',
        'categories'=>[['title'=>'Category:Unique items']],
        'redirects'=>[['title'=>'Madruks Prophecy']],
    ]
]);

$result = $service->compile();
$items = $repo->items();
$aliases = $repo->aliases();
$failed = [];

if ($result['items'] !== 1) $failed[] = 'item count';
if (($items[0]['name'] ?? '') !== "Madruk's Prophecy") $failed[] = 'item title';
if (!in_array('Madruks Prophecy', $items[0]['aliases'] ?? [], true)) $failed[] = 'redirect alias';
if (!in_array('eblade', array_column($aliases,'alias'), true)) $failed[] = 'seed alias retained';

echo json_encode(['ok'=>$failed===[],'failed'=>$failed],JSON_PRETTY_PRINT).PHP_EOL;
exit($failed===[] ? 0 : 1);
