<?php
declare(strict_types=1);
$root=dirname(__DIR__);require $root.'/bootstrap.php';
use LittyWatch\AI\AiRiskAssessor;
$risk=new AiRiskAssessor();
$ambiguous=$risk->assess(['confidence'=>0.88,'quality_status'=>'review','raw_segment'=>'Arms 27e/ea x4','price_amount'=>27,'unit_price_ecto'=>27,'price_basis'=>'each'],['sibling_count'=>2,'median_unit_ecto'=>26,'market_samples'=>10]);
if(!$ambiguous['risky']||$ambiguous['score']<50)throw new RuntimeException('Ambiguous offer should be risky.');
$clean=$risk->assess(['confidence'=>0.99,'quality_status'=>'accepted','raw_segment'=>'BDS 30a','price_amount'=>30,'unit_price_ecto'=>810,'price_basis'=>'each'],['sibling_count'=>1,'median_unit_ecto'=>800,'market_samples'=>10]);
if($clean['risky'])throw new RuntimeException('Clean offer should not be risky.');
echo "Phase 4A AI risk selection OK\n";
