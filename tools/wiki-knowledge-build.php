<?php
declare(strict_types=1);

/**
 * Optional CLI importer. The browser importer at /knowledge-pack is preferred
 * when the hosting IP is blocked by Guild Wars Wiki.
 *
 * Usage:
 * php tools/wiki-knowledge-build.php "Category:Unique items" unique-items unique-item
 */

$root = dirname(__DIR__);
require $root . '/app/Support/Autoloader.php';
\LittyWatch\Support\Autoloader::register($root . '/app');

$category = $argv[1] ?? '';
$profile = $argv[2] ?? 'manual';
$kind = $argv[3] ?? 'unknown';

if ($category === '') {
    fwrite(STDERR, "Gebruik: php tools/wiki-knowledge-build.php \"Category:Unique items\" unique-items unique-item\n");
    exit(1);
}

$repo = new \LittyWatch\Repositories\KnowledgePackRepository(
    $root . '/app/Data/knowledge-pack',
    $root . '/data/wiki-knowledge'
);

$api = 'https://wiki.guildwars.com/api.php';
$continue = '';
$total = 0;

do {
    $params = [
        'action'=>'query',
        'list'=>'categorymembers',
        'cmtitle'=>$category,
        'cmnamespace'=>0,
        'cmlimit'=>100,
        'format'=>'json',
    ];
    if ($continue !== '') $params['cmcontinue'] = $continue;

    $categoryData = request($api . '?' . http_build_query($params));
    $members = $categoryData['query']['categorymembers'] ?? [];
    $titles = array_values(array_filter(array_column($members,'title')));

    if ($titles !== []) {
        $details = request($api . '?' . http_build_query([
            'action'=>'query',
            'titles'=>implode('|',$titles),
            'prop'=>'extracts|info|categories|redirects',
            'exintro'=>1,
            'explaintext'=>1,
            'exchars'=>1000,
            'inprop'=>'url',
            'cllimit'=>'max',
            'rdlimit'=>'max',
            'format'=>'json',
        ]));
        $pages = array_values($details['query']['pages'] ?? []);
        foreach ($pages as &$page) {
            $page['profile'] = $profile;
            $page['kind'] = $kind;
        }
        $total = $repo->appendStage($profile,$pages);
        echo "Staging: {$total} pagina's\n";
    }

    $continue = (string)($categoryData['continue']['cmcontinue'] ?? '');
    usleep(300000);
} while ($continue !== '');

function request(string $url): array
{
    $context = stream_context_create([
        'http'=>[
            'method'=>'GET',
            'timeout'=>30,
            'header'=>[
                'Accept: application/json',
                'User-Agent: LittyWatch/5.1 (Guild Wars market research; contact via site administrator)',
            ],
        ],
    ]);
    $raw = @file_get_contents($url,false,$context);
    if ($raw === false) throw new RuntimeException('Wiki-request mislukt of werd geblokkeerd.');
    $data = json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    return is_array($data) ? $data : [];
}
