<?php
declare(strict_types=1);
if (!function_exists('mb_strtolower')) { function mb_strtolower(string $v, ?string $e=null): string { return strtolower($v); } }
require dirname(__DIR__) . '/app/Support/Autoloader.php';
\LittyWatch\Support\Autoloader::register(dirname(__DIR__) . '/app');

$n=new \LittyWatch\Parser\SemanticNormalizer();
$failed=[];
$cases=[
 ['WTS All ToT Bags','WTS Trick-or-Treat Bag'],
 ['WTS Clockwork Scy','WTS Clockwork Scythe'],
 ['WTB Deld Hero armor','WTB Deldrimor Armor Remnant'],
 ['WTB cloth hero armor','WTB Cloth of Brotherhood'],
 ['WTB mysterious hero armor','WTB Mysterious Armor Piece'],
 ['WTB primeval hero armor','WTB Primeval Armor Remnant'],
 ['WTB sunspear hero armor','WTB Stolen Sunspear Armor'],
 ['WTS EShortbow Q9/13','WTS Eternal Bow Q9/13'],
];
foreach($cases as [$in,$expected]){
 $got=$n->normalize($in);
 if($got!==$expected)$failed[]=['input'=>$in,'expected'=>$expected,'got'=>$got];
}
$c=new \LittyWatch\Parser\ReviewCandidateClassifier();
foreach(['dom set','Domination set','of the elementalist for scy'] as $value){
 $got=$c->classify($value,'WTB '.$value);
 if($got['kind']==='item')$failed[]=['classifier'=>$value,'got'=>$got];
}
echo json_encode(['ok'=>$failed===[],'failed'=>$failed],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($failed===[]?0:1);
