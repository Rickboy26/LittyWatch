<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

$p=new \LittyWatch\Parser\ParserEngine(
    new \LittyWatch\Parser\Catalog($root.'/app/Data',db())
);

$fail=0;
echo "Phase 7E.5 FIX2 smoke-test\n";

foreach([
    ['WTS stacks of alc','Alcohol Points',null],
    ['WTS 2 stacks of alc','Alcohol Points',null],
    ['WTS unded/Livia','Miniature Livia','undedicated'],
    ['WTS ded/Livia','Miniature Livia','dedicated'],
] as [$msg,$want,$wantDed]){
    $found=null;
    foreach($p->parse($msg) as $o){
        if(strcasecmp($o->item,$want)===0){$found=$o;break;}
    }

    $ok=$found!==null;
    $ded='-';

    if($found && $wantDed!==null){
        $ded=$found->modifiers['dedication']??$found->relevantProperties['dedication']??'-';
        $ok=$ok
            && $ded===$wantDed
            && $found->status==='accepted'
            && $found->reason==='catalog_match';
    }

    printf(
        "%-24s => %-26s ded=%-12s status=%-8s reason=%-26s %s\n",
        $msg,
        $found?$found->item:'NIET GEVONDEN',
        $ded,
        $found?$found->status:'-',
        $found?$found->reason:'-',
        $ok?'OK':'FAIL'
    );

    if(!$ok)$fail++;
}

$strongbox="WTS GHOSTLY Hero's Strongbox 5A|DESTROYER 35E|MARGONITE 30E|BOREAL 15E|KNIGHT 10E";
$collision=false;
$items=[];
foreach($p->parse($strongbox) as $o){
    $items[]=$o->item;
    if(strcasecmp($o->item,'Miniature Ghostly Hero')===0)$collision=true;
}

printf(
    "%-24s => mini_collision=%-4s parsed=[%s] %s\n",
    "Ghostly Hero's Strongbox",
    $collision?'YES':'NO',
    implode(', ',$items),
    !$collision?'OK':'FAIL'
);
if($collision)$fail++;

if($fail){
    fwrite(STDERR,"\nPhase 7E.5 FIX2 smoke-test: FAIL ({$fail})\n");
    exit(1);
}

echo "\nPhase 7E.5 FIX2 smoke-test: OK\n";
