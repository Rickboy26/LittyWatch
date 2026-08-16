<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

$p=new \LittyWatch\Parser\ParserEngine(
    new \LittyWatch\Parser\Catalog($root.'/app/Data',db())
);

$fail=0;
echo "Phase 7E.5 FIX4 smoke-test\n";

$tests=[
    ['WTS stacks of alc','Alcohol Points',null,true],
    ['WTS 2 stacks of alc','Alcohol Points',null,true],
    ['WTS unded/Livia','Miniature Livia','undedicated',true],
    ['WTS ded/Livia','Miniature Livia','dedicated',true],
    ['WTS unded Ghostly Hero 25a','Miniature Ghostly Hero','undedicated',true],
    ['WTS mini Ghostly Hero 25a','Miniature Ghostly Hero',null,true],
];

foreach($tests as [$msg,$want,$wantDed,$shouldFind]){
    $found=null;
    foreach($p->parse($msg) as $o){
        if(strcasecmp($o->item,$want)===0){$found=$o;break;}
    }

    $ok=$shouldFind ? $found!==null : $found===null;
    $ded='-';

    if($found && $wantDed!==null){
        $ded=$found->modifiers['dedication']??$found->relevantProperties['dedication']??'-';
        $ok=$ok && $ded===$wantDed;
    }

    printf(
        "%-32s => %-26s ded=%-12s status=%-8s reason=%-26s %s\n",
        $msg,
        $found?$found->item:'NIET GEVONDEN',
        $ded,
        $found?$found->status:'-',
        $found?$found->reason:'-',
        $ok?'OK':'FAIL'
    );
    if(!$ok)$fail++;
}

$msg="WTS GHOSTLY Hero's Strongbox 5A|DESTROYER 35E|MARGONITE 30E|BOREAL 15E|KNIGHT 10E";
$collision=false;
$items=[];
foreach($p->parse($msg) as $o){
    $items[]=$o->item;
    if(strcasecmp($o->item,'Miniature Ghostly Hero')===0)$collision=true;
}

printf(
    "%-32s => collision=%-3s parsed=[%s] %s\n",
    "Ghostly Hero's Strongbox",
    $collision?'YES':'NO',
    implode(', ',$items),
    !$collision?'OK':'FAIL'
);
if($collision)$fail++;

if($fail){
    fwrite(STDERR,"\nPhase 7E.5 FIX4 smoke-test: FAIL ({$fail})\n");
    exit(1);
}

echo "\nPhase 7E.5 FIX4 smoke-test: OK\n";
