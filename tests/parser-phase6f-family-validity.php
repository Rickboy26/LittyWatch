<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use LittyWatch\Market\VariantValidityGate;

$g = new VariantValidityGate();
$fail = [];

$invalid = [
    ['item_key'=>'eternal-bow','requirement'=>12,'attribute_key'=>'domination_magic'],
    ['item_key'=>'voltaic-spear','requirement'=>9,'attribute_key'=>'tactics'],
    ['item_key'=>'chaos-axe','requirement'=>9,'attribute_key'=>'swordsmanship'],
    ['item_key'=>'storm-bow','requirement'=>9,'attribute_key'=>'fast_casting'],
    ['item_key'=>'tormented-daggers','requirement'=>9,'attribute_key'=>'critical_strikes'],
    ['item_key'=>'tormented-shield','requirement'=>9,'attribute_key'=>'fire_magic'],
    ['item_key'=>'platinum-wand','requirement'=>9,'attribute_key'=>'tactics'],
    ['item_key'=>'frog-scepter','requirement'=>9,'attribute_key'=>'tactics'],
    ['item_key'=>'dhuums-soul-reaper','requirement'=>9,'attribute_key'=>'death_magic'],
    ['item_key'=>'urkal-s-kamas','requirement'=>9,'attribute_key'=>'critical_strikes'],
];
foreach ($invalid as $row) {
    $row += ['is_oldschool'=>0,'is_inscribable'=>0];
    if ($g->inspect($row)['allowed']) $fail[] = 'invalid family variant accepted: '.json_encode($row);
}

$valid = [
    ['item_key'=>'eternal-bow','requirement'=>9,'attribute_key'=>'marksmanship'],
    ['item_key'=>'voltaic-spear','requirement'=>9,'attribute_key'=>'spear_mastery'],
    ['item_key'=>'chaos-axe','requirement'=>9,'attribute_key'=>'axe_mastery'],
    ['item_key'=>'tormented-shield','requirement'=>9,'attribute_key'=>'command'],
    ['item_key'=>'platinum-wand','requirement'=>9,'attribute_key'=>'domination_magic'],
    ['item_key'=>'frog-scepter','requirement'=>9,'attribute_key'=>'death_magic'],
    ['item_key'=>'dhuums-soul-reaper','requirement'=>9,'attribute_key'=>'scythe_mastery'],
    ['item_key'=>'urkal-s-kamas','requirement'=>9,'attribute_key'=>'dagger_mastery'],
    // Unknown item family: conservatively do not reject.
    ['item_key'=>'birdseye','requirement'=>9,'attribute_key'=>'fast_casting'],
    // Non-weapon with leaked properties: conservatively do not reject here.
    ['item_key'=>'silver-zaishen-coin','requirement'=>9,'attribute_key'=>'fire_magic'],
];
foreach ($valid as $row) {
    $row += ['is_oldschool'=>0,'is_inscribable'=>0];
    if (!$g->inspect($row)['allowed']) $fail[] = 'valid/safe variant rejected: '.json_encode($row);
}

echo json_encode(['ok'=>$fail===[],'failed'=>$fail], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($fail===[] ? 0 : 1);
