<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);require $root.'/bootstrap.php';$fail=0;
function c15(bool $ok,string $label):void{global $fail;echo($ok?'OK   ':'FAIL ').$label.PHP_EOL;if(!$ok)$fail++;}
$g=new \LittyWatch\Market\Phase7E15MarketSemanticGuard(db());
$gold=$g->repair(['item'=>'Golds','item_key'=>'golds','raw_segment'=>'inscribable Golds 2k each','quality_status'=>'review','quality_reason'=>'catalog_first_unresolved']);c15(($gold['item_key']??'')==='market-inscribable-golds','Insc Golds => market-inscribable-golds');
$egg=$g->repair(['item'=>'Egg','item_key'=>'egg','raw_segment'=>'Egg 8e','quality_status'=>'review','quality_reason'=>'catalog_first_unresolved']);c15(($egg['item_key']??'')==='golden-egg','Egg => Golden Egg');
$cake=$g->repair(['item'=>'Birthday Cupcake','item_key'=>'birthday-cupcake','raw_segment'=>'D-cakes 4a:200 All','quality_status'=>'accepted','quality_reason'=>'catalog_match']);c15(($cake['item_key']??'')==='delicious-cake','D-Cakes => Delicious Cake');
$noise=$g->repair(['item'=>'how much u want','item_key'=>'how-much-u-want','raw_segment'=>'how much u want','quality_status'=>'review','quality_reason'=>'catalog_first_unresolved']);c15(($noise['quality_reason']??'')==='service_or_noise','chat noise rejected');
$sem=file_get_contents($root.'/app/Parser/SemanticNormalizer.php');c15(str_contains((string)$sem,'LITTYWATCH_PHASE7E15_DOA_GEMS'),'DoA gems marker aanwezig');c15(str_contains((string)$sem,'Margonite Gemstone | Stygian Gemstone | Torment Gemstone'),'no-titan gem expansion aanwezig');c15(str_contains((string)$sem,'LITTYWATCH_PHASE7E15_SPEAR_MOD_REORDER'),'spear reorder marker aanwezig');
$writer=file_get_contents($root.'/app/Market/StructuredOfferWriter.php');c15(str_contains((string)$writer,'LITTYWATCH_PHASE7E15_PREINSERT_MARKET_SEMANTICS'),'writer 7E.15 marker aanwezig');
echo PHP_EOL;if($fail){echo "Phase 7E.15 smoke-test: {$fail} fout(en).\n";exit(1);}echo "Phase 7E.15 smoke-test volledig OK.\nDaarna live-market reset voor zuivere meting; geen reparse-all.\n";
