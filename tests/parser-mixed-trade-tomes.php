<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$failures = [];

$offers = parseOffers(
    'WTS Q9 Volta (Voltaic Spear) INSC | WTB unded Gpriest (Miniature Ghostly Priest)'
);

$map = [];
foreach ($offers as $offer) $map[$offer['item']] = $offer;

if (($map['Voltaic Spear']['type'] ?? null) !== 'sell') {
    $failures[] = ['mixed_trade'=>'Voltaic Spear should be sell','offers'=>$offers];
}
if (($map['Miniature Ghostly Priest']['type'] ?? null) !== 'buy') {
    $failures[] = ['mixed_trade'=>'Ghostly Priest should be buy','offers'=>$offers];
}
if (!str_contains(mb_strtolower((string)($map['Voltaic Spear']['details'] ?? '')), 'q9')) {
    $failures[] = ['requirement'=>'q9 missing','offers'=>$offers];
}
if (!str_contains(mb_strtolower((string)($map['Voltaic Spear']['details'] ?? '')), 'inscribable')) {
    $failures[] = ['inscribable'=>'missing','offers'=>$offers];
}

$wand = parseOffers('WTB PLATINUM WAND REQ CHANNELING');
if (
    !$wand
    || ($wand[0]['item'] ?? '') !== 'Platinum Wand'
    || !str_contains(mb_strtolower((string)($wand[0]['details'] ?? '')), 'channeling')
) {
    $failures[] = ['attribute_only_requirement'=>$wand];
}

$rq = parseOffers('WTS RQ9 VOLTA INSC');
if (
    !$rq
    || ($rq[0]['item'] ?? '') !== 'Voltaic Spear'
    || !str_contains(mb_strtolower((string)($rq[0]['details'] ?? '')), 'q9')
    || !str_contains(mb_strtolower((string)($rq[0]['details'] ?? '')), 'inscribable')
) {
    $failures[] = ['rq9_caps'=>$rq];
}

$tomes = parseOffers('WTS TOMES: WAR (WARRIOR), MES (MESMER), ELE (ELEMENTALIST)');
$tomeNames = array_column($tomes, 'item');

foreach (['Warrior Tome','Mesmer Tome','Elementalist Tome'] as $expected) {
    if (!in_array($expected, $tomeNames, true)) {
        $failures[] = ['tome_missing'=>$expected,'offers'=>$tomes];
    }
}

echo json_encode([
    'ok'=>$failures===[],
    'failures'=>$failures,
], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit($failures===[] ? 0 : 1);
