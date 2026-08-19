<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

$fail=0;
function check21(bool $ok,string $label,mixed $actual=null):void{
    global $fail;
    echo ($ok?'OK   ':'FAIL ').$label;
    if(!$ok && $actual!==null){
        echo ' [actual='.(is_scalar($actual)?(string)$actual:json_encode($actual)).']';
    }
    echo PHP_EOL;
    if(!$ok)$fail++;
}

$file=$root.'/app/Market/Phase7E21AcceptedSafetyGuard.php';
check21(is_file($file),'guard file bestaat');
if(is_file($file)) require_once $file;
check21(class_exists(\LittyWatch\Market\Phase7E21AcceptedSafetyGuard::class),'guard class laadbaar');

$g=new \LittyWatch\Market\Phase7E21AcceptedSafetyGuard();

$tests=[];

$tests[]=[
    $g->repair([
        'item'=>'Spawning Staff','item_key'=>'spawning-staff','raw_segment'=>'Scepter q13 spaw',
        'quality_status'=>'accepted','quality_reason'=>'catalog_match','confidence'=>0.88
    ]),
    'accepted_weapon_type_collision',
    'Scepter mag geen Staff worden'
];

$tests[]=[
    $g->repair([
        'item'=>'of Enchanting','item_key'=>'of-enchanting','raw_segment'=>'axe 15%ench',
        'quality_status'=>'accepted','quality_reason'=>'catalog_match','confidence'=>0.86
    ]),
    'accepted_modifier_collision',
    '15% ench mag geen of Enchanting worden'
];

$tests[]=[
    $g->repair([
        'item'=>'Blessing of War','item_key'=>'blessing-of-war','raw_segment'=>'Bow 1a/ea',
        'quality_status'=>'accepted','quality_reason'=>'catalog_match','confidence'=>0.86
    ]),
    'accepted_named_item_collision',
    'bare Bow mag geen Blessing of War worden'
];

$tests[]=[
    $g->repair([
        'item'=>'Paragon Rune of Superior Leadership','item_key'=>'paragon-rune-of-superior-leadership',
        'raw_segment'=>'Q5 Strength/Leadership Shie','quality_status'=>'accepted',
        'quality_reason'=>'catalog_match','confidence'=>0.90
    ]),
    'accepted_item_family_collision',
    'Strength/Leadership Shield mag geen rune worden'
];

$fds=$g->repair([
    'item'=>'Fiery','item_key'=>'fiery','raw_segment'=>'Fiery drag sword q9 ins',
    '_message'=>'WTS Fiery drag sword q9 ins 9e',
    'quality_status'=>'accepted','quality_reason'=>'catalog_match','confidence'=>0.92
]);
check21(($fds['item_key']??'')==='fiery-dragon-sword','Fiery drag sword => Fiery Dragon Sword',$fds['item_key']??null);

$tests[]=[
    $g->repair([
        'item'=>'Split Chakrams of the Forgotten','item_key'=>'split-chakrams-of-the-forgotten',
        'raw_segment'=>'Wand q9 of Forgotten','quality_status'=>'accepted',
        'quality_reason'=>'catalog_match','confidence'=>0.86
    ]),
    'accepted_weapon_type_collision',
    'Wand mag geen Chakrams worden'
];

$tests[]=[
    $g->repair([
        'item'=>'Eternal Shield','item_key'=>'eternal-shield','raw_segment'=>'eternal shield q10 comm 30e',
        'attribute_key'=>'communing','quality_status'=>'accepted',
        'quality_reason'=>'catalog_match','confidence'=>0.92
    ]),
    'accepted_impossible_variant',
    'Communing Eternal Shield geblokkeerd'
];

foreach($tests as [$out,$expected,$label]){
    check21(($out['quality_reason']??'')===$expected,$label,$out['quality_reason']??null);
}

$writer=file_get_contents($root.'/app/Market/StructuredOfferWriter.php');
check21(str_contains((string)$writer,'LITTYWATCH_PHASE7E21_ACCEPTED_SAFETY'),'writer marker aanwezig');

echo PHP_EOL;
if($fail){
    echo "Phase 7E.21 smoke-test: {$fail} fout(en).".PHP_EOL;
    exit(1);
}
echo "Phase 7E.21 smoke-test volledig OK.".PHP_EOL;
echo "Daarna live-market reset voor zuivere accepted-audit; geen reparse-all.".PHP_EOL;
