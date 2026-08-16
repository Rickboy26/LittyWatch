<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);require $root.'/bootstrap.php';
$p=new \LittyWatch\Parser\ParserEngine(new \LittyWatch\Parser\Catalog($root.'/app/Data',db()));
$t=[['WTS teas 200: 6a','Battle Iced Tea'],['WTS beacons 4 = 8e','Party Beacon'],['WTS 10x Frostfire Fangs','Frostfire Fang'],['WTS 1000 Margo','Margonite Gemstone'],['WTS 6 wd grab bags 10a','Wintersday Grab Bag'],['WTS little john','Little John']];
$f=0;echo "Phase 7E.4 smoke-test\n";
foreach($t as [$m,$w]){$x=null;foreach($p->parse($m) as $o)if(strcasecmp($o->item,$w)===0){$x=$o;break;}printf("%-29s => %-25s %s\n",$m,$x?$x->item:'NIET GEVONDEN',$x?'OK':'FAIL');if(!$x)$f++;}
if($f){fwrite(STDERR,"Phase 7E.4 smoke-test: FAIL ($f)\n");exit(1);}echo "Phase 7E.4 smoke-test: OK\n";
