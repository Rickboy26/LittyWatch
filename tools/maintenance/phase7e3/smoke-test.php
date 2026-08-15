<?php
declare(strict_types=1);
$root=dirname(__DIR__,3); require $root.'/bootstrap.php';
use LittyWatch\Parser\Catalog; use LittyWatch\Parser\ParserEngine;
$engine=new ParserEngine(new Catalog($root.'/app/Data',db()));
$tests=[
 ['WTS Undead Prince 150A, unded Ghost of Althea 150A, unded Varesh 30A',['Miniature Ghost of Althea','Miniature Varesh']],
 ['WTS mini unded Destroyer 250a',['Miniature Destroyer of Flesh']],
 ['WTS UNDED Minis : Lich 10k | Prince Rurik 10k | Candysmith Marley 5k | Freezie 1k',['Miniature Lich','Miniature Prince Rurik','Miniature Candysmith Marley','Miniature Freezie']],
 ['WTS unded minis: Dagnar | Black Beast of Aaaargh | White rabbit | Lich | Prince Rurik',['Miniature Dagnar Stonepate','Miniature Black Beast of Aaaaarrrrrrggghhh','Miniature Lich','Miniature Prince Rurik']]
];
$fail=0; echo "Phase 7E.3 smoke-test\n";
foreach($tests as [$msg,$expected]){
 $names=array_values(array_unique(array_map(fn($o)=>$o->item,$engine->parse($msg))));
 foreach($expected as $x){$ok=in_array($x,$names,true);printf("%-42s %s\n",$x,$ok?'OK':'FAIL');if(!$ok)$fail++;}
}
if($fail){fwrite(STDERR,"Phase 7E.3 smoke-test: FAIL ($fail)\n");exit(1);}
echo "Phase 7E.3 smoke-test: OK\n";
