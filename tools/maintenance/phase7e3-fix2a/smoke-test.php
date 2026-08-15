<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

use LittyWatch\Parser\Catalog;
use LittyWatch\Parser\ParserEngine;

$engine=new ParserEngine(new Catalog($root.'/app/Data',db()));

$tests=[
    ['WTS Unded Prince Rurik 10k','Miniature Prince Rurik','undedicated'],
    ['WTS Unded Undead Prince 150a','Miniature Undead Prince','undedicated'],
];

$fail=0;
echo "Phase 7E.3 FIX2a smoke-test\n";

foreach($tests as [$msg,$expected,$dedExpected]){
    $offers=$engine->parse($msg);
    $found=null;

    foreach($offers as $offer){
        if($offer->item===$expected){$found=$offer;break;}
    }

    if(!$found){
        printf("%-32s => %-28s FAIL (niet gevonden)\n",$msg,$expected);
        $fail++;
        continue;
    }

    $ded=$found->modifiers['dedication']
        ?? $found->relevantProperties['dedication']
        ?? null;

    $ok=$ded===$dedExpected
        && $found->status==='accepted'
        && $found->reason==='catalog_match';

    printf(
        "%-32s => %-28s ded=%-12s status=%-8s reason=%-28s %s\n",
        $msg,$found->item,$ded??'-',$found->status,$found->reason,$ok?'OK':'FAIL'
    );

    if(!$ok)$fail++;
}

/* Hard collision guard */
foreach($engine->parse('WTS Unded Prince Rurik 10k') as $offer){
    if($offer->item==='Miniature Undead Prince'){
        echo "Prince Rurik -> Undead Prince collision: FAIL\n";
        $fail++;
    }
}

if($fail){
    fwrite(STDERR,"\nPhase 7E.3 FIX2a smoke-test: FAIL ({$fail})\n");
    exit(1);
}

echo "\nPhase 7E.3 FIX2a smoke-test: OK\n";
