<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';
$fail=0;
function c14(bool $ok,string $label):void{global $fail;echo($ok?'OK   ':'FAIL ').$label.PHP_EOL;if(!$ok)$fail++;}
$g=new \LittyWatch\Market\Phase7E14ResidualGuard(db());

$a=$g->repair(['item'=>'abnormal seeds','item_key'=>'abnormal-seeds','raw_segment'=>'5 abnormal seeds 1e','quality_status'=>'review','quality_reason'=>'catalog_first_unresolved']);
c14(($a['item_key']??'')==='unnatural-seed','abnormal seeds => Unnatural Seed');

$b=$g->repair(['item'=>'Bords Eyes','item_key'=>'bords-eyes','raw_segment'=>'250 Bords Eyes 15e','quality_status'=>'review','quality_reason'=>'catalog_first_unresolved']);
c14(($b['item_key']??'')==='birdseye','Bords Eyes => Birdseye');

$s=$g->repair(['item'=>'Destruction Depths NM rush','item_key'=>'destruction-depths-nm-rush','raw_segment'=>'Destruction Depths NM rush','quality_status'=>'review','quality_reason'=>'catalog_first_unresolved']);
c14(($s['quality_reason']??'')==='service_or_noise','DD rush rejected');

$bow=$g->repair(['item'=>'Blessing of War','item_key'=>'blessing-of-war','raw_segment'=>'bow','quality_status'=>'review','quality_reason'=>'low_confidence']);
c14(($bow['quality_reason']??'')==='strict_catalog_generic','false Blessing rejected');

$u=$g->repair(['item'=>'Unidentified Gold','item_key'=>'unidentified-gold','raw_segment'=>'unids','quality_status'=>'review','quality_reason'=>'low_confidence']);
c14(($u['quality_reason']??'')==='catalog_match','unids promoted');

$sem=file_get_contents($root.'/app/Parser/SemanticNormalizer.php');
c14(str_contains((string)$sem,'LITTYWATCH_PHASE7E14_ELITE_TOME_POSITIVE_LIST'),'Elite Tome list marker aanwezig');
$writer=file_get_contents($root.'/app/Market/StructuredOfferWriter.php');
c14(str_contains((string)$writer,'LITTYWATCH_PHASE7E14_PREINSERT_RESIDUAL'),'writer 7E.14 marker aanwezig');

echo PHP_EOL;
if($fail){echo "Phase 7E.14 smoke-test: {$fail} fout(en).\n";exit(1);}
echo "Phase 7E.14 smoke-test volledig OK.\n";
