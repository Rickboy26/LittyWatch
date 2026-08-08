<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use LittyWatch\Market\CanonicalMarketIdentity as C;
$cases=[
 ['Silverwing Bow','silverwing-bow','Silverwing Recurve Bow'],
 ['Ghostly Hero','ghostly-hero','Miniature Ghostly Hero'],
 ['Rift Warden','rift-warden','Miniature Rift Warden'],
 ['Mallyx','mallyx','Miniature Mallyx'],
 ['Kuuna','kuuna','Miniature Kuunavang'],
];
foreach($cases as[$name,$key,$want]){ $got=C::nameFor($name,$key); if($got!==$want)throw new RuntimeException("{$name}: {$got} != {$want}"); }
if(!C::isWikiDisambiguator('Recurve Bow (weapon)'))throw new RuntimeException('Wiki disambiguator not rejected');
echo "Phase 3U canonical identity tests OK\n";
