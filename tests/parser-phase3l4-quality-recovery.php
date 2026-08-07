<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';

use LittyWatch\Market\MarketQualityService;

$ref=new ReflectionClass(MarketQualityService::class);
$svc=$ref->newInstanceWithoutConstructor();
$method=$ref->getMethod('recoverCanonicalUnit');
$method->setAccessible(true);

$check=static function(array $row,?float $want,string $label)use($svc,$method):void{
    $got=$method->invoke($svc,$row);
    if ($want===null ? $got!==null : ($got===null || abs($got-$want)>0.00001)) {
        fwrite(STDERR,"$label: got ".var_export($got,true).", want ".var_export($want,true)."\n"); exit(1);
    }
};
$base=['price_basis'=>'uncertain','price_amount'=>2.0,'price_currency'=>'e','price_ecto'=>2.0,'quantity'=>null];
$check($base+['raw_segment'=>'Warrior Tome 2e/ea'],2.0,'each slash');
$check(['price_basis'=>'uncertain','price_amount'=>50.0,'price_currency'=>'e','price_ecto'=>50.0,'quantity'=>null,'raw_segment'=>'Stalkers 50e/stk'],0.2,'stack slash');
$check(['price_basis'=>'uncertain','price_amount'=>1.0,'price_currency'=>'e','price_ecto'=>1.0,'quantity'=>null,'raw_segment'=>'Sapphires 3:1e or for'],1/3,'ratio');
$check(['price_basis'=>'uncertain','price_amount'=>27.0,'price_currency'=>'e','price_ecto'=>27.0,'quantity'=>null,'raw_segment'=>'arms 27e/ea x4'],27.0,'each inventory');
$check(['price_basis'=>'range','price_amount'=>675.0,'price_currency'=>'e','price_ecto'=>675.0,'quantity'=>null,'raw_segment'=>'Red Rock Candy Candy 225-675e'],null,'range stays uncertain');
$check(['price_basis'=>'uncertain','price_amount'=>20.0,'price_currency'=>'e','price_ecto'=>20.0,'quantity'=>null,'raw_segment'=>'Cupcakes / Eggs / Honeycombs 20e'],null,'shared list stays uncertain');
echo "Phase 3L.4 market-quality recovery OK\n";
