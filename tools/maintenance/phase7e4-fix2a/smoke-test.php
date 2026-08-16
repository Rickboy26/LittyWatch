<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';

$parser=new \LittyWatch\Parser\ParserEngine(
    new \LittyWatch\Parser\Catalog($root.'/app/Data',db())
);

$tests=[
    ['WTS 1000 Margo','Margonite Gemstone','accepted','catalog_match'],
    ['WTS 250 Margos','Margonite Gemstone','accepted','catalog_match'],
];

$fail=0;
echo "Phase 7E.4 FIX2a smoke-test\n";

foreach($tests as [$msg,$want,$status,$reason]){
    $found=null;
    foreach($parser->parse($msg) as $o){
        if(strcasecmp($o->item,$want)===0){$found=$o;break;}
    }

    $ok=$found
        && $found->status===$status
        && $found->reason===$reason;

    printf(
        "%-24s => %-24s status=%-8s reason=%-24s %s\n",
        $msg,
        $found?$found->item:'NIET GEVONDEN',
        $found?$found->status:'-',
        $found?$found->reason:'-',
        $ok?'OK':'FAIL'
    );

    if(!$ok)$fail++;
}

/* Guard: tonic shorthand must not become gemstone. */
$bad=false;
foreach($parser->parse('WTS El margo 5e') as $o){
    if(strcasecmp($o->item,'Margonite Gemstone')===0 && $o->status==='accepted'){$bad=true;break;}
}
printf("%-24s => gemstone collision %s\n",'WTS El margo 5e',$bad?'FAIL':'OK');
if($bad)$fail++;

if($fail){
    fwrite(STDERR,"\nPhase 7E.4 FIX2a smoke-test: FAIL ({$fail})\n");
    exit(1);
}

echo "\nPhase 7E.4 FIX2a smoke-test: OK\n";
