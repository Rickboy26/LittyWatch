<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php'; installSchema();
$repo=new \LittyWatch\Repositories\DatasetRepository(db());
$out=$argv[1]??(dirname(__DIR__,2).'/data/exports/littywatch-training-dataset-'.date('Ymd-His').'.ndjson');
if(!is_dir(dirname($out)))mkdir(dirname($out),0775,true);
$fh=fopen($out,'wb'); $n=0; foreach($repo->exportRows() as $row){fwrite($fh,json_encode($row,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n");$n++;} fclose($fh);
echo "Phase 3M training dataset export: $n rows -> $out\n";
