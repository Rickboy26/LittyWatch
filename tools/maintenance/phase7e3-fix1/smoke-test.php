<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

use LittyWatch\Parser\Catalog;
use LittyWatch\Parser\ParserEngine;

$engine=new ParserEngine(new Catalog($root.'/app/Data',db()));

$tests=[
 [
  'WTS Undead Prince 150A, unded Ghost of Althea 150A, unded Varesh 30A',
  [
   ['Miniature Ghost of Althea','undedicated'],
   ['Miniature Varesh','undedicated'],
  ]
 ],
 [
  'WTS UNDED Minis : Lich 10k | Prince Rurik 10k | Candysmith Marley 5k | Freezie 1k',
  [
   ['Miniature Lich','undedicated'],
   ['Miniature Undead Prince','undedicated'],
   ['Miniature Candysmith Marley','undedicated'],
   ['Miniature Freezie','undedicated'],
  ]
 ],
 [
  'WTS unded minis: Dagnar | Black Beast of Aaaargh | White rabbit | Lich | Prince Rurik',
  [
   ['Miniature Dagnar Stonepate','undedicated'],
   ['Miniature Black Beast of Aaaaarrrrrrggghhh','undedicated'],
   ['Miniature White Rabbit','undedicated'],
   ['Miniature Lich','undedicated'],
   ['Miniature Undead Prince','undedicated'],
  ]
 ],
];

$fail=0;
echo "Phase 7E.3 FIX1 smoke-test\n";

foreach($tests as [$message,$expected]){
    $offers=$engine->parse($message);

    foreach($expected as [$item,$ded]){
        $found=null;
        foreach($offers as $offer){
            if($offer->item===$item){$found=$offer;break;}
        }

        if($found===null){
            printf("%-43s FAIL (niet gevonden)\n",$item);
            $fail++;
            continue;
        }

        $actual=$found->modifiers['dedication']
            ?? $found->relevantProperties['dedication']
            ?? null;

        $ok=$actual===$ded
            && $found->status==='accepted'
            && $found->reason==='catalog_match';

        printf(
            "%-43s ded=%-12s status=%-8s reason=%-28s %s\n",
            $item,
            $actual??'-',
            $found->status,
            $found->reason,
            $ok?'OK':'FAIL'
        );
        if(!$ok)$fail++;
    }

    foreach($offers as $offer){
        if($offer->item==='Miniature' && $offer->status==='accepted'){
            echo "Generic Miniature header row             FAIL\n";
            $fail++;
        }
    }
}

if($fail){
    fwrite(STDERR,"\nPhase 7E.3 FIX1 smoke-test: FAIL ({$fail})\n");
    exit(1);
}

echo "\nPhase 7E.3 FIX1 smoke-test: OK\n";
