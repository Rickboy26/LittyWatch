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
];

$fail=0;
echo "Phase 7E.6 FIX1 smoke-test\n";

foreach($tests as $test){
    $offers=$parser->parse($test['msg']);
    $seen=[];

    foreach($offers as $o){
        $ded=$o->modifiers['dedication']??$o->relevantProperties['dedication']??null;
        $seen[$o->item][]=[
            'ded'=>$ded,
            'status'=>$o->status,
            'reason'=>$o->reason,
            'market'=>$o->marketKey,
            'segment'=>$o->segment,
        ];
    }

    echo PHP_EOL.$test['msg'].PHP_EOL;

    foreach($test['expected'] as $item=>$expectedDed){
        $row=$seen[$item][0]??null;
        $ok=$row!==null
            && $row['ded']===$expectedDed
            && $row['status']==='accepted'
            && $row['reason']==='catalog_match'
            && str_contains((string)$row['market'],'dedication:'.$expectedDed);

        printf(
            "  %-30s ded=%-12s status=%-8s reason=%-28s market=%-52s %s\n",
            $item,
            $row['ded']??'-',
            $row['status']??'-',
            $row['reason']??'-',
            $row['market']??'-',
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
    fwrite(STDERR,"\nPhase 7E.6 FIX1 smoke-test: FAIL ({$fail})\n");
    exit(1);
}

echo "\nPhase 7E.6 FIX1 smoke-test: OK\n";
