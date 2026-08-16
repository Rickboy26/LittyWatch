<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);require $root.'/bootstrap.php';
$p=new \LittyWatch\Parser\ParserEngine(new \LittyWatch\Parser\Catalog($root.'/app/Data',db()));
$tests=[['WTS stacks of alc','Alcohol Points'],['WTS 2 stacks of alc','Alcohol Points'],['WTS unded/Livia','Miniature Livia']];
$f=0;echo "Phase 7E.5 smoke-test\n";
foreach($tests as [$m,$w]){
 $x=null;foreach($p->parse($m) as $o)if(strcasecmp($o->item,$w)===0){$x=$o;break;}
 $ok=$x!==null;
 if($m==='WTS unded/Livia'&&$x){$d=$x->modifiers['dedication']??$x->relevantProperties['dedication']??null;$ok=$ok&&$d==='undedicated'&&$x->status==='accepted';}
 printf("%-24s => %-28s status=%-8s reason=%-26s %s\n",$m,$x?$x->item:'NIET GEVONDEN',$x?$x->status:'-',$x?$x->reason:'-',$ok?'OK':'FAIL');if(!$ok)$f++;
}
$bad=false;$m="WTS GHOSTLY Hero's Strongbox 5A|DESTROYER 35E|MARGONITE 30E";
foreach($p->parse($m) as $o)if(strcasecmp($o->item,'Miniature Ghostly Hero')===0)$bad=true;
printf("%-24s => miniature collision %s\n",'Ghostly Hero Strongbox',$bad?'FAIL':'OK');if($bad)$f++;
if($f){fwrite(STDERR,"FAIL ($f)\n");exit(1);}echo "Phase 7E.5 smoke-test: OK\n";
