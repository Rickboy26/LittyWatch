<?php
declare(strict_types=1);
$root=dirname(__DIR__,3); require $root.'/bootstrap.php';
use LittyWatch\Parser\Catalog;
use LittyWatch\Parser\ParserEngine;
$engine=new ParserEngine(new Catalog($root.'/app/Data',db()));

$tests=[
 ['WTS UNDED Minis : Lich 10k | Prince Rurik 10k | Candysmith Marley 5k | Freezie 1k',
  ['Miniature Lich','Miniature Prince Rurik','Miniature Candysmith Marley','Miniature Freezie']],
 ['WTS unded minis: Dagnar | Black Beast of Aaaargh | White rabbit | Lich | Prince Rurik',
  ['Miniature Dagnar Stonepate','Miniature Black Beast of Aaaaarrrrrrggghhh','White Rabbit','Miniature Lich','Miniature Prince Rurik']],
 ['WTS Egg 7e/stk Clover 3e/stk Unded Althea 125a Zhang 25a Moa Chick 50e WFR Beetle 10e',
  ['Miniature Ghost of Althea','Miniature High Priest Zhang']]
];

$fail=0; echo "Phase 7E.3 FIX3 smoke-test\n";
foreach($tests as [$msg,$expected]){
 $offers=$engine->parse($msg);
 foreach($expected as $name){
  $found=null; foreach($offers as $o){if($o->item===$name){$found=$o;break;}}
  if(!$found){printf("%-43s FAIL (niet gevonden)\n",$name);$fail++;continue;}
  $ded=$found->modifiers['dedication']??$found->relevantProperties['dedication']??null;
  $ok=$ded==='undedicated'&&$found->status==='accepted'&&$found->reason==='catalog_match';
  printf("%-43s ded=%-12s status=%-8s reason=%-28s %s\n",$name,$ded??'-',$found->status,$found->reason,$ok?'OK':'FAIL');
  if(!$ok)$fail++;
 }
}
if($fail){fwrite(STDERR,"\nPhase 7E.3 FIX3 smoke-test: FAIL ($fail)\n");exit(1);}
echo "\nPhase 7E.3 FIX3 smoke-test: OK\n";
