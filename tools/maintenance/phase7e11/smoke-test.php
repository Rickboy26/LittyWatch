<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';
$fail=0;
function c11(bool $ok,string $label):void{global $fail;echo($ok?'OK   ':'FAIL ').$label.PHP_EOL;if(!$ok)$fail++;}
foreach([
 ['kazhad-s-fortune',"Kazhad's Fortune"],
 ['superior-rune-of-holding','Superior Rune of Holding'],
 ['rune-of-belt-holding','Rune of Belt Holding']
] as [$key,$name]){
    $st=db()->prepare("SELECT COUNT(*) FROM kb_items WHERE key=? AND name=? AND active=1");
    $st->execute([$key,$name]);
    c11((int)$st->fetchColumn()===1,'KB item '.$name);
}
$sem=file_get_contents($root.'/app/Parser/SemanticNormalizer.php');
c11(str_contains((string)$sem,'LITTYWATCH_PHASE7E11_RESIDUAL_ALIASES'),'7E.11 parser marker aanwezig');
c11(str_contains((string)$sem,'Measure for Measure'),'M4Ms mapping aanwezig');
c11(str_contains((string)$sem,'Superior Rune of Holding'),'Superior Rune mapping aanwezig');
echo PHP_EOL;
if($fail){echo "Phase 7E.11 smoke-test: {$fail} fout(en).\n";exit(1);}
echo "Phase 7E.11 smoke-test volledig OK.\n";
echo "Daarna live-market reset voor zuivere meting; geen reparse-all.\n";
