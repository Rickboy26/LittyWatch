<?php
declare(strict_types=1);
$service=(string)file_get_contents(dirname(__DIR__).'/app/Services/ParserBatchReviewService.php');
$view=(string)file_get_contents(dirname(__DIR__).'/app/Views/reviews/index.php');
$release=json_decode((string)file_get_contents(dirname(__DIR__).'/app/Data/parser-release.json'),true);
$failed=[];
if(!str_contains($service,'new ParserEngine(new Catalog($dataDir, $this->pdo))')) $failed[]='batch review does not construct fresh parser';
if(str_contains($service,"parserV2(),\n            new VariantNormalizer()")) $failed[]='batch review still routes writer through parserV2 singleton';
if(trim((string)($release['release']??''))==='') $failed[]='release marker missing';
if(!str_contains($view,'Parser release:')) $failed[]='review UI does not show parser release';
echo json_encode(['ok'=>$failed===[],'failed'=>$failed],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($failed===[]?0:1);
