<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

$fail=0;
function c10(bool $ok,string $label,mixed $actual=null):void{
    global $fail;
    echo ($ok?'OK   ':'FAIL ').$label;
    if(!$ok&&$actual!==null) echo ' [actual='.(is_scalar($actual)?(string)$actual:json_encode($actual)).']';
    echo PHP_EOL;
    if(!$ok)$fail++;
}

$g=new \LittyWatch\Market\Phase7E10ResidualGuard(db());

$tests=[
    [
        ['item'=>'Bone','item_key'=>'bone','raw_segment'=>'bones stack','quality_status'=>'review','quality_reason'=>'low_confidence','confidence'=>0.6],
        'bone','catalog_match'
    ],
    [
        ['item'=>'Coffers','item_key'=>'coffers','raw_segment'=>'Coffers 4e ea','quality_status'=>'review','quality_reason'=>'catalog_first_unresolved','confidence'=>0.6],
        'coffer-of-whispers','catalog_match'
    ],
    [
        ['item'=>'Mysterious Stones','item_key'=>'mysterious-stones','raw_segment'=>'25 Mysterious Stones 5e','quality_status'=>'review','quality_reason'=>'catalog_first_unresolved','confidence'=>0.6],
        'mysterious-summoning-stone','catalog_match'
    ],
    [
        ['item'=>'"Aptitude not Attitude"','item_key'=>'aptitude-not-attitude','raw_segment'=>'AnA','quality_status'=>'review','quality_reason'=>'low_confidence','confidence'=>0.6],
        'aptitude-not-attitude','catalog_match'
    ],
];

foreach($tests as [$row,$key,$reason]){
    $out=$g->repair($row);
    c10(($out['item_key']??'')===$key,'canonical key '.$key,$out['item_key']??null);
    c10(($out['quality_reason']??'')===$reason,'quality '.$reason,$out['quality_reason']??null);
}

$dragon=$g->repair([
    'item'=>'Miniature Celestial Dragon',
    'item_key'=>'miniature-celestial-dragon',
    'raw_segment'=>'Staves 20/20 Dragon Channeling Smite',
    'quality_status'=>'review',
    'quality_reason'=>'miniature_variant_unresolved',
    'confidence'=>0.6
]);
c10(($dragon['quality_reason']??'')==='strict_catalog_generic','staff Dragon false-mini rejected',$dragon['quality_reason']??null);

$set=$g->repair([
    'item'=>'40/40 heal *2',
    'item_key'=>'40-40-heal-2',
    'raw_segment'=>'40/40 heal 10e/ea*2',
    'quality_status'=>'review',
    'quality_reason'=>'catalog_first_unresolved',
    'confidence'=>0.5
]);
c10(($set['quality_reason']??'')==='collection_or_market_request','40/40 set description rejected',$set['quality_reason']??null);

$sem=file_get_contents($root.'/app/Parser/SemanticNormalizer.php');
c10(str_contains((string)$sem,'LITTYWATCH_PHASE7E10_CELESTAL_STAFF'),'Celestal targeted marker aanwezig');

$writer=file_get_contents($root.'/app/Market/StructuredOfferWriter.php');
c10(str_contains((string)$writer,'LITTYWATCH_PHASE7E10_PREINSERT_RESIDUAL'),'writer residual guard marker aanwezig');

echo PHP_EOL;
if($fail){
    echo "Phase 7E.10 smoke-test: {$fail} fout(en).\n";
    exit(1);
}
echo "Phase 7E.10 smoke-test volledig OK.\n";
echo "Daarna: reset live market voor zuivere meting; geen reparse-all.\n";
