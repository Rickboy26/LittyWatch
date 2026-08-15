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
 ['WTS Unded Zhang 20a','Miniature High Priest Zhang','undedicated'],
 ['WTS Unded White Rabbit 10e','White Rabbit','undedicated'],
 ['WTS White Rabbit','White Rabbit',null],
];

$fail=0;
echo "Phase 7E.3 FIX2 smoke-test\n";

foreach($tests as [$msg,$expected,$dedExpected]){
    $found=null;
    foreach($engine->parse($msg) as $offer){
        if($offer->item===$expected){$found=$offer;break;}
    }

    if(!$found){
        printf("%-32s => %-30s FAIL (niet gevonden)\n",$msg,$expected);
        $fail++;
        continue;
    }

    $ded=$found->modifiers['dedication']
        ?? $found->relevantProperties['dedication']
        ?? null;

    $ok=$ded===$dedExpected;

    if($dedExpected===null){
        $ok=$ok && $found->reason==='miniature_variant_unresolved';
    }else{
        $ok=$ok && $found->status==='accepted' && $found->reason==='catalog_match';
    }

    printf(
        "%-32s => %-30s ded=%-12s status=%-8s reason=%-30s %s\n",
        $msg,$found->item,$ded??'-',$found->status,$found->reason,$ok?'OK':'FAIL'
    );

    if(!$ok)$fail++;
}

/* Ensure Prince Rurik never resolves as Undead Prince. */
foreach($engine->parse('WTS Unded Prince Rurik 10k') as $offer){
    if($offer->item==='Miniature Undead Prince'){
        echo "Prince Rurik collision with Undead Prince: FAIL\n";
        $fail++;
    }
}

if($fail){
    fwrite(STDERR,"\nPhase 7E.3 FIX2 smoke-test: FAIL ({$fail})\n");
    exit(1);
}

echo "\nPhase 7E.3 FIX2 smoke-test: OK\n";
