<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

$expander=new \LittyWatch\Parser\MarketBundleExpander();

echo "Phase 7E.6 FIX2 direct expander checks\n";

$direct=[
    "Miniature Zhed Shadowhoof unded/Livia"=>[
        "Miniature Zhed Shadowhoof unded",
        "Miniature Livia unded",
    ],
    "Miniature Zhed Shadowhoof ded/Livia"=>[
        "Miniature Zhed Shadowhoof ded",
        "Miniature Livia ded",
    ],
    "Miniature Zhed Shadowhoof unded/Princess Salma/Livia"=>[
        "Miniature Zhed Shadowhoof unded",
        "Miniature Princess Salma unded",
        "Miniature Livia unded",
    ],
    "Miniature Zhed Shadowhoof unded/Salma/Livia"=>[
        "Miniature Zhed Shadowhoof unded",
        "Miniature Princess Salma unded",
        "Miniature Livia unded",
    ],
];

$fail=0;
foreach($direct as $input=>$expected){
    $actual=$expander->expand($input);
    $ok=$actual===$expected;
    echo PHP_EOL."INPUT: ".$input.PHP_EOL;
    echo "ACTUAL:   ".json_encode($actual,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL;
    echo "EXPECTED: ".json_encode($expected,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL;
    echo $ok?"OK\n":"FAIL\n";
    if(!$ok)$fail++;
}

echo PHP_EOL."Phase 7E.6 FIX2 ParserEngine checks\n";

$parser=new \LittyWatch\Parser\ParserEngine(
    new \LittyWatch\Parser\Catalog($root.'/app/Data',db())
);

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
];

foreach($tests as $test){
    $offers=$parser->parse($test['msg']);
    echo PHP_EOL.$test['msg'].PHP_EOL;

    foreach($test['expected'] as $item=>$wantDed){
        $found=null;
        foreach($offers as $o){
            if($o->item===$item){$found=$o;break;}
        }

        $ded=$found?($found->modifiers['dedication']??$found->relevantProperties['dedication']??null):null;
        $ok=$found!==null
            && $ded===$wantDed
            && $found->status==='accepted'
            && $found->reason==='catalog_match'
            && str_contains($found->marketKey,'dedication:'.$wantDed);

        printf(
            "  %-30s ded=%-12s status=%-8s reason=%-28s market=%-50s %s\n",
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
        if(
            mb_strtolower(trim($o->item))==='miniature'
            || (
                in_array($o->item,['Miniature Zhed Shadowhoof','Miniature Princess Salma','Miniature Livia'],true)
                && $o->reason==='miniature_variant_unresolved'
            )
        ){
            echo "  RESIDUAL: {$o->item} | {$o->status} | {$o->reason} | {$o->segment}\n";
            $fail++;
        }
    }
}

if($fail){
    fwrite(STDERR,"\nPhase 7E.6 FIX2 smoke-test: FAIL ({$fail})\n");
    exit(1);
}
echo "\nPhase 7E.6 FIX2 smoke-test: OK\n";
