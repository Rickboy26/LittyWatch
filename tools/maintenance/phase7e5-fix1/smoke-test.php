<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

$p=new \LittyWatch\Parser\ParserEngine(
    new \LittyWatch\Parser\Catalog($root.'/app/Data',db())
);

$fail=0;
echo "Phase 7E.5 FIX1 smoke-test\n";

$tests=[
    ['WTS stacks of alc','Alcohol Points'],
    ['WTS 2 stacks of alc','Alcohol Points'],
    ['WTS unded/Livia','Miniature Livia'],
];

foreach($tests as [$msg,$want]){
    $found=null;
    foreach($p->parse($msg) as $o){
        if(strcasecmp($o->item,$want)===0){$found=$o;break;}
    }

    $ok=$found!==null;

    if($msg==='WTS unded/Livia' && $found){
        $ded=$found->modifiers['dedication']??$found->relevantProperties['dedication']??null;
        $ok=$ok && $ded==='undedicated'
            && $found->status==='accepted'
            && $found->reason==='catalog_match';
    }

    printf(
        "%-24s => %-28s status=%-8s reason=%-28s %s\n",
        $msg,
        $found?$found->item:'NIET GEVONDEN',
        $found?$found->status:'-',
        $found?$found->reason:'-',
        $ok?'OK':'FAIL'
    );

    if(!$ok)$fail++;
}

$strongbox="WTS GHOSTLY Hero's Strongbox 5A|DESTROYER 35E|MARGONITE 30E";
$collision=false;
$strongboxFound=false;
foreach($p->parse($strongbox) as $o){
    if(strcasecmp($o->item,'Miniature Ghostly Hero')===0)$collision=true;
    if(strcasecmp($o->item,"Hero's Strongbox")===0)$strongboxFound=true;
}

printf(
    "%-24s => mini_collision=%-4s strongbox=%-4s %s\n",
    "Ghostly Hero's Strongbox",
    $collision?'YES':'NO',
    $strongboxFound?'YES':'NO',
    (!$collision)?'OK':'FAIL'
);

if($collision)$fail++;

if($fail){
    fwrite(STDERR,"\nPhase 7E.5 FIX1 smoke-test: FAIL ({$fail})\n");
    exit(1);
}

echo "\nPhase 7E.5 FIX1 smoke-test: OK\n";
