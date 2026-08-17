<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

$catalog=new \LittyWatch\Parser\Catalog($root.'/app/Data',db());
$parser=new \LittyWatch\Parser\ParserEngine($catalog);

$tests=[
    [
        'msg'=>'WTB unded Zhed/Livia',
        'expected'=>[
            'Miniature Zhed Shadowhoof'=>'undedicated',
            'Miniature Livia'=>'undedicated',
        ],
    ],
    [
        'msg'=>'WTB ded Zhed/Livia',
        'expected'=>[
            'Miniature Zhed Shadowhoof'=>'dedicated',
            'Miniature Livia'=>'dedicated',
        ],
    ],
    [
        'msg'=>'WTB unded Zhed/Princess Salma/Livia',
        'expected'=>[
            'Miniature Zhed Shadowhoof'=>'undedicated',
            'Miniature Princess Salma'=>'undedicated',
            'Miniature Livia'=>'undedicated',
        ],
    ],
    [
        'msg'=>'WTB unded Zhed/Salma/Livia',
        'expected'=>[
            'Miniature Zhed Shadowhoof'=>'undedicated',
            'Miniature Princess Salma'=>'undedicated',
            'Miniature Livia'=>'undedicated',
        ],
    ],
    [
        'msg'=>'WTS unded Ghostly Hero 25a',
        'expected'=>[
            'Miniature Ghostly Hero'=>'undedicated',
        ],
    ],
];

$fail=0;
echo "Phase 7E.6 FIX3 smoke-test\n";

foreach($tests as $test){
    $offers=$parser->parse($test['msg']);
    echo PHP_EOL.$test['msg'].PHP_EOL;

    foreach($test['expected'] as $item=>$wantDed){
        $found=null;
        foreach($offers as $o){
            if($o->item===$item){$found=$o;break;}
        }

        $ded=$found
            ? ($found->modifiers['dedication']??$found->relevantProperties['dedication']??null)
            : null;

        $ok=$found!==null
            && $ded===$wantDed
            && $found->status==='accepted'
            && $found->reason==='catalog_match'
            && str_contains($found->marketKey,'dedication:'.$wantDed);

        printf(
            "  %-30s ded=%-12s status=%-8s reason=%-28s market=%-52s %s\n",
            $item,
            $ded??'-',
            $found?$found->status:'-',
            $found?$found->reason:'-',
            $found?$found->marketKey:'-',
            $ok?'OK':'FAIL'
        );

        if(!$ok)$fail++;
    }

    foreach($offers as $o){
        $badGeneric =
            mb_strtolower(trim($o->item))==='miniature'
            && preg_match('/^(?:uded|unded|ded|dedicated|undedicated)$/iu',trim($o->segment));

        $badVariant =
            in_array($o->item,array_keys($test['expected']),true)
            && $o->reason==='miniature_variant_unresolved';

        if($badGeneric || $badVariant){
            echo "  RESIDUAL: {$o->item} | {$o->status} | {$o->reason} | {$o->segment}\n";
            $fail++;
        }
    }
}

/* Regression: a true bare miniature query must not disappear just because the
 * generic Miniature item exists. We do not require acceptance here, only that
 * FIX3 does not globally delete generic miniature parsing.
 */
$generic=$parser->parse('WTB unded minis');
echo PHP_EOL."WTB unded minis regression".PHP_EOL;
echo "  offers=".count($generic)." (informational; generic policy remains owned elsewhere)".PHP_EOL;

/* Strongbox regression from 7E.5 FIX7. */
$collision=false;
foreach($parser->parse("WTS Ghostly Hero's Strongbox 5a") as $o){
    if(mb_strtolower(trim($o->item))==='miniature ghostly hero')$collision=true;
}
echo PHP_EOL."Ghostly Hero Strongbox regression => ".($collision?'FAIL':'OK').PHP_EOL;
if($collision)$fail++;

if($fail){
    fwrite(STDERR,"\nPhase 7E.6 FIX3 smoke-test: FAIL ({$fail})\n");
    exit(1);
}

echo "\nPhase 7E.6 FIX3 smoke-test: OK\n";
