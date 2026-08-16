<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

$catalog=new \LittyWatch\Parser\Catalog($root.'/app/Data',db());
$parser=new \LittyWatch\Parser\ParserEngine($catalog);

$tests=[
 ['WTS teas 200: 6a','Battle Isle Iced Tea'],
 ['WTS beacons 4 = 8e','Party Beacon'],
 ['WTS 10x Frostfire Fangs','Frostfire Fang'],
 ['WTS 1000 Margo','Margonite Gemstone'],
 ['WTS 6 wd grab bags 10a','Wintersday Grab Bag'],
 ['WTS little john','Little John'],
];

$fail=0;
echo "Phase 7E.4 FIX2 smoke-test\n";

foreach($tests as [$msg,$want]){
    $found=null;
    foreach($parser->parse($msg) as $o){
        if(strcasecmp($o->item,$want)===0){$found=$o;break;}
    }

    $ok=$found!==null
        && $found->status==='accepted'
        && $found->reason==='catalog_match';

    printf(
        "%-29s => %-31s status=%-8s reason=%-24s %s\n",
        $msg,
        $found? $found->item : 'NIET GEVONDEN',
        $found? $found->status : '-',
        $found? $found->reason : '-',
        $ok?'OK':'FAIL'
    );

    if(!$ok)$fail++;
}

echo "\nKB canonical checks\n";
$stmt=db()->prepare("SELECT key,name,category_key FROM kb_items WHERE key=? LIMIT 1");
foreach([
 ['battle-isle-iced-tea','Battle Isle Iced Tea'],
 ['frostfire-fang','Frostfire Fang'],
 ['little-john','Little John'],
] as [$key,$name]){
    $stmt->execute([$key]);
    $r=$stmt->fetch(PDO::FETCH_ASSOC);
    $ok=$r && strcasecmp((string)$r['name'],$name)===0;
    printf("%-24s => %-28s %s\n",$key,$r?$r['name']:'NIET IN KB',$ok?'OK':'FAIL');
    if(!$ok)$fail++;
}

if($fail){
    fwrite(STDERR,"\nPhase 7E.4 FIX2 smoke-test: FAIL ({$fail})\n");
    exit(1);
}

echo "\nPhase 7E.4 FIX2 smoke-test: OK\n";
