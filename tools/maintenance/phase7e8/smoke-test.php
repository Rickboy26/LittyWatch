<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/bootstrap.php';

use LittyWatch\Market\Phase7E8ClauseRepair;

$fail = 0;
function ok8(bool $ok, string $label, mixed $actual=null): void {
    global $fail;
    echo ($ok ? 'OK   ' : 'FAIL ') . $label;
    if (!$ok && $actual !== null) echo ' [actual='.(is_scalar($actual)?(string)$actual:json_encode($actual)).']';
    echo PHP_EOL;
    if (!$ok) $fail++;
}

$r = new Phase7E8ClauseRepair();

$tests = [
    [
        ['item'=>'Bone Dragon Staff','item_key'=>'bone-dragon-staff','requirement'=>8,'attribute_key'=>'domination_magic','raw_segment'=>'WTS Eternal Bows q9horn q10 shortbow/ q11 Smite BDS/gold Q8 Com Scarabshell/ Q12 Dom Froggy'],
        11, 'smiting_prayers'
    ],
    [
        ['item'=>'Bone Dragon Staff','item_key'=>'bone-dragon-staff','requirement'=>8,'attribute_key'=>'communing','raw_segment'=>'WTS Q9 FIRE BDS - 55a'],
        9, 'fire_magic'
    ],
    [
        ['item'=>'Bone Dragon Staff','item_key'=>'bone-dragon-staff','requirement'=>8,'attribute_key'=>'tactics','raw_segment'=>'WTS BDS q13 dom / Q8 Com Scarabshell'],
        13, 'domination_magic'
    ],
];

foreach ($tests as [$row,$q,$attr]) {
    $out=$r->repair($row);
    ok8((int)($out['requirement']??0)===$q, 'BDS local requirement => q'.$q, $out['requirement']??null);
    ok8(($out['attribute_key']??null)===$attr, 'BDS local attribute => '.$attr, $out['attribute_key']??null);
}

$phase7e = file_get_contents($root.'/app/Market/Phase7ERecovery.php');
ok8(str_contains((string)$phase7e,'LITTYWATCH_PHASE7E8_SEGMENT_ISOLATION'),'miniature segment isolation marker aanwezig');
ok8(str_contains((string)$phase7e,'fortune|prophecy'),'fortune/prophecy mini guard aanwezig');

$semantic = file_get_contents($root.'/app/Parser/SemanticNormalizer.php');
ok8(str_contains((string)$semantic,'LITTYWATCH_PHASE7E8_LIVE_ALIASES'),'live alias marker aanwezig');
ok8(str_contains((string)$semantic,'Party Beacon'),'Pbeacons => Party Beacon mapping aanwezig');
ok8(str_contains((string)$semantic,'Gift of the Traveler'),'gift trav mapping aanwezig');

$writer = file_get_contents($root.'/app/Market/StructuredOfferWriter.php');
ok8(str_contains((string)$writer,'LITTYWATCH_PHASE7E8_LOCAL_CLAUSE_REPAIR'),'StructuredOfferWriter local repair marker aanwezig');

echo PHP_EOL;
if($fail){
    echo "Phase 7E.8 smoke-test: {$fail} fout(en).\n";
    exit(1);
}
echo "Phase 7E.8 smoke-test volledig OK.\n";
echo "Laat daarna de live collector draaien; geen reparse nodig voor de fresh-data test.\n";
