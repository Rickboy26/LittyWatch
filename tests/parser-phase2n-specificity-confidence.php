<?php
declare(strict_types=1);
if (!function_exists('mb_strtolower')) { function mb_strtolower(string $v, ?string $e=null): string { return strtolower($v); } }
if (!function_exists('mb_strtoupper')) { function mb_strtoupper(string $v, ?string $e=null): string { return strtoupper($v); } }
if (!function_exists('mb_strlen')) { function mb_strlen(string $v, ?string $e=null): int { return strlen($v); } }
if (!function_exists('mb_stripos')) { function mb_stripos(string $h,string $n,int $o=0,?string $e=null): int|false { return stripos($h,$n,$o); } }
if (!function_exists('mb_substr')) { function mb_substr(string $v,int $s,?int $l=null,?string $e=null): string { return $l===null?substr($v,$s):substr($v,$s,$l); } }
require dirname(__DIR__) . '/app/Support/Autoloader.php';
\LittyWatch\Support\Autoloader::register(dirname(__DIR__) . '/app');
$c=new \LittyWatch\Parser\Catalog(dirname(__DIR__) . '/app/Data');
$e=new \LittyWatch\Parser\ParserEngine($c); $f=[];

$accepted=[
 ['WTT Ecto for Arms 1250e = 50a','Glob of Ectoplasm'],
 ['WTT Ecto for Arms 1250e = 50a','Armbrace of Truth'],
 ['wts soup 2 arms per stack','Soup'],
 ['wts soup 2 arms per stack','Armbrace of Truth'],
 ['WTB Ded Polar Bear','Miniature Polar Bear'],
 ['wtb Rubys and Saphire - 2 Rubys','Ruby'],
];
foreach($accepted as [$m,$want]){
 $o=$e->parse($m); $hit=null;
 foreach($o as $x) if($x->item===$want){$hit=$x;break;}
 if($hit===null) $f[]=['missing',$m,$want,array_map(fn($x)=>$x->item,$o)];
 elseif($hit->status!=='accepted') $f[]=['not_accepted',$m,$hit->toArray()];
}


// Post-match specificity suppression also protects against generic rows learned
// through the production DB, which are not present in the static test catalog.
$ref=new ReflectionClass($e); $m=$ref->getMethod('suppressLowConfidenceGenericShadows'); $m->setAccessible(true);
$none=new \LittyWatch\Parser\ParsedPrice(null,null,null,'unknown',null,null,null);
$fake=[
 new \LittyWatch\Parser\ParsedOffer('buy','Miniature','miniature',[],$none,0.80,'review','low_confidence','Ded'),
 new \LittyWatch\Parser\ParsedOffer('buy','Miniature Polar Bear','miniature-polar-bear',[],$none,0.90,'accepted','catalog_match','Polar Bear'),
 new \LittyWatch\Parser\ParsedOffer('buy','Wand','wand',[],$none,0.78,'review','low_confidence','wand wrapping'),
];
$filtered=$m->invoke($e,$fake); $names=array_map(fn($x)=>$x->item,$filtered);
if(in_array('Miniature',$names,true) || in_array('Wand',$names,true)) $f[]=['generic_shadow_not_suppressed',$names];
if(!in_array('Miniature Polar Bear',$names,true)) $f[]=['concrete_removed',$names];

$tax=new \LittyWatch\Parser\ItemTaxonomy($c->taxonomy());
foreach(['Miniature','Unique item','Staff','Wand','Focus item','Bow','Axe'] as $n){
 if(!$tax->isGenericName($n)) $f[]=['taxonomy_generic_missing',$n];
}

echo json_encode(['ok'=>$f===[],'failed'=>$f],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($f===[]?0:1);
