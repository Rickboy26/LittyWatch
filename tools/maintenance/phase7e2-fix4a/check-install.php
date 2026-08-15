<?php
declare(strict_types=1);

$root=dirname(__DIR__,3);
$file=$root.'/app/Market/StructuredOfferWriter.php';
$code=file_get_contents($file);

$checks=[
 'marker'=>str_contains($code,'LITTYWATCH_PHASE7E2_FIX4_WRITER_MINIATURE_VARIANT_INVARIANT'),
 'method'=>str_contains($code,'private function reconcileMiniatureVariant(array $row):array'),
 'call'=>str_contains($code,'$r=$this->reconcileMiniatureVariant($r);'),
];

$fail=0;
foreach($checks as $name=>$ok){
 printf("%-12s %s\n",$name,$ok?'OK':'FAIL');
 if(!$ok)$fail++;
}

if($fail){
 fwrite(STDERR,"FIX4a install-check: FAIL\n");
 exit(1);
}

echo "FIX4a install-check: OK\n";
