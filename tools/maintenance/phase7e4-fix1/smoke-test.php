<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

$catalog=new \LittyWatch\Parser\Catalog($root.'/app/Data',db());
$parser=new \LittyWatch\Parser\ParserEngine($catalog);

$tests=[
 ['WTS teas 200: 6a','Battle Iced Tea'],
 ['WTS beacons 4 = 8e','Party Beacon'],
 ['WTS 10x Frostfire Fangs','Frostfire Fang'],
 ['WTS 1000 Margo','Margonite Gemstone'],
 ['WTS 6 wd grab bags 10a','Wintersday Grab Bag'],
 ['WTS little john','Little John'],
];

$fail=0;
echo "Phase 7E.4 FIX1 smoke-test\n";
foreach($tests as [$msg,$want]){
    $found=null;
    foreach($parser->parse($msg) as $o){
        if(strcasecmp($o->item,$want)===0){$found=$o;break;}
    }
    printf(
        "%-29s => %-28s %s\n",
        $msg,
        $found ? ($found->item.' ['.$found->status.'/'.$found->reason.']') : 'NIET GEVONDEN',
        $found ? 'OK' : 'FAIL'
    );
    if(!$found)$fail++;
}

if($fail){
    fwrite(STDERR,"Phase 7E.4 FIX1 smoke-test: FAIL ({$fail})\n");
    exit(1);
}
echo "Phase 7E.4 FIX1 smoke-test: OK\n";
