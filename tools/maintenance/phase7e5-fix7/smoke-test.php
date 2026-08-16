<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

$p=new \LittyWatch\Parser\ParserEngine(
    new \LittyWatch\Parser\Catalog($root.'/app/Data',db())
);

$fail=0;
echo "Phase 7E.5 FIX7 smoke-test\n";

$tests=[
    ['WTS stacks of alc','Alcohol Points',null],
    ['WTS 2 stacks of alc','Alcohol Points',null],
    ['WTS unded/Livia','Miniature Livia','undedicated'],
    ['WTS ded/Livia','Miniature Livia','dedicated'],
    ['WTS unded Ghostly Hero 25a','Miniature Ghostly Hero','undedicated'],
    ['WTS mini Ghostly Hero 25a','Miniature Ghostly Hero',null],
];

foreach($tests as [$msg,$want,$wantDed]){
    $found=null;
    foreach($p->parse($msg) as $o){
        if(strcasecmp(trim($o->item),$want)===0){$found=$o;break;}
    }
    $ok=$found!==null;
    $ded='-';
    if($found && $wantDed!==null){
        $ded=$found->modifiers['dedication']??$found->relevantProperties['dedication']??'-';
        $ok=$ok && $ded===$wantDed;
    }
    printf("%-32s => %-26s ded=%-12s status=%-8s reason=%-26s %s\n",
        $msg,
        $found?$found->item:'NIET GEVONDEN',
        $ded,
        $found?$found->status:'-',
        $found?$found->reason:'-',
        $ok?'OK':'FAIL'
    );
    if(!$ok)$fail++;
}

foreach([
    "WTS GHOSTLY Hero's Strongbox 5A|DESTROYER 35E|MARGONITE 30E",
    "WTS Ghostly Heros Strongbox 5a",
    "WTS Ghostly Hero Strongbox 5a",
    "WTS Ghostly Hero Strongboxes 5a",
] as $msg){
    $collision=false;$items=[];
    foreach($p->parse($msg) as $o){
        $items[]=$o->item.'{'.$o->itemKey.'}';
        if(
            preg_match('/^miniature\s+ghostly\s+hero$/iu',trim($o->item))
            || in_array(mb_strtolower(trim($o->itemKey)),[
                'ghostly_hero','ghostly-hero',
                'miniature_ghostly_hero','miniature-ghostly-hero'
            ],true)
        ) $collision=true;
    }
    printf("%-52s => collision=%-3s parsed=[%s] %s\n",
        $msg,
        $collision?'YES':'NO',
        implode(', ',$items),
        !$collision?'OK':'FAIL'
    );
    if($collision)$fail++;
}

if($fail){
    fwrite(STDERR,"\nPhase 7E.5 FIX7 smoke-test: FAIL ({$fail})\n");
    exit(1);
}
echo "\nPhase 7E.5 FIX7 smoke-test: OK\n";
