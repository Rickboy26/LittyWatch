<?php
declare(strict_types=1);

$repo=file_get_contents(__DIR__.'/../app/Repositories/MarketRepository.php');
$admin=file_get_contents(__DIR__.'/../app/Views/admin/index.php');
$view=file_get_contents(__DIR__.'/../app/Views/admin/data-quality.php');
$routes=file_get_contents(__DIR__.'/../routes/web.php');
$controller=file_get_contents(__DIR__.'/../app/Controllers/AdminController.php');

$required=['unpriced','uncertain','outlier','no_catalog_item','low_confidence','parser_review'];
foreach($required as$key){
    if(!str_contains($repo,"'".$key."'")){fwrite(STDERR,"Missing repo category $key\n");exit(1);}
    if(!str_contains($view,"'".$key."'")){fwrite(STDERR,"Missing view category $key\n");exit(1);}
}
if(!str_contains($routes,"/admin/data-quality")){fwrite(STDERR,"Missing data-quality route\n");exit(1);}
if(!str_contains($controller,'function dataQuality')){fwrite(STDERR,"Missing controller action\n");exit(1);}
if(!str_contains($admin,"issue['issue_key']")){fwrite(STDERR,"Admin problems are not linked by issue_key\n");exit(1);}
if(str_contains($repo,"market_outlier: 135.000e")){fwrite(STDERR,"Outliers should not be grouped by dynamic reason\n");exit(1);}
echo "Phase 3K data-quality workbench wiring OK\n";
