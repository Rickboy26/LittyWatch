<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);require $root.'/bootstrap.php';
$p=new \LittyWatch\Parser\ParserEngine(new \LittyWatch\Parser\Catalog($root.'/app/Data',db()));
$f=0;echo "Phase 7E.4 FIX2b smoke-test\n";
foreach([['WTS 1000 Margo','Margonite Gemstone'],['WTS 250 Margos','Margonite Gemstone']] as [$m,$w]){
 $x=null;foreach($p->parse($m) as $o)if(strcasecmp($o->item,$w)===0){$x=$o;break;}
 $ok=$x&&$x->status==='accepted'&&$x->reason==='catalog_match';
 printf("%-22s => %-24s status=%-8s reason=%-22s %s\n",$m,$x?$x->item:'NIET GEVONDEN',$x?$x->status:'-',$x?$x->reason:'-',$ok?'OK':'FAIL');
 if(!$ok)$f++;
}
$bad=false;foreach($p->parse('WTS El margo 5e') as $o)if(strcasecmp($o->item,'Margonite Gemstone')===0&&$o->status==='accepted')$bad=true;
printf("%-22s => gemstone collision %s\n",'WTS El margo 5e',$bad?'FAIL':'OK');if($bad)$f++;
if($f){fwrite(STDERR,"FAIL ($f)\n");exit(1);}echo "Phase 7E.4 FIX2b smoke-test: OK\n";
