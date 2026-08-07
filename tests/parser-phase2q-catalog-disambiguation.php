<?php
declare(strict_types=1);
if (!function_exists('mb_strtolower')) { function mb_strtolower(string $v, ?string $e=null): string { return strtolower($v); } }
if (!function_exists('mb_strlen')) { function mb_strlen(string $v, ?string $e=null): int { return strlen($v); } }
if (!function_exists('mb_stripos')) { function mb_stripos(string $h,string $n,int $o=0,?string $e=null): int|false { return stripos($h,$n,$o); } }
if (!function_exists('mb_substr')) { function mb_substr(string $v,int $s,?int $l=null,?string $e=null): string { return $l===null?substr($v,$s):substr($v,$s,$l); } }
require dirname(__DIR__) . '/app/Support/Autoloader.php';
\LittyWatch\Support\Autoloader::register(dirname(__DIR__) . '/app');
$c=new \LittyWatch\Parser\Catalog(dirname(__DIR__) . '/app/Data');
$e=new \LittyWatch\Parser\ParserEngine($c);
$failed=[];

function off(string $item,float $conf,string $status,string $reason,string $segment): \LittyWatch\Parser\ParsedOffer {
 return new \LittyWatch\Parser\ParsedOffer('buy',$item,strtolower(str_replace(' ','-',$item)),[],new \LittyWatch\Parser\ParsedPrice(null,null,null),$conf,$status,$reason,$segment);
}
$ref=new ReflectionClass($e);
$suppress=$ref->getMethod('suppressGenericCatalogShadows'); $suppress->setAccessible(true);
$promote=$ref->getMethod('promoteExplicitGenericMarketSearches'); $promote->setAccessible(true);

// Learned/dynamic generic rows must be removed from mod context at message level.
$rows=[off('Bow',.76,'review','low_confidence','Bow/'),off('Axe',.80,'review','low_confidence','Axe/')];
$out=$suppress->invoke($e,$rows,'WTS Insightful Staff Head, Bow/Axe/Spear Grip of Defense');
if(array_filter($out,fn($o)=>in_array($o->item,['Bow','Axe'],true))) $failed[]=['mod_list_generic_shadow_survived'];

$rows=[off('Staff',.84,'accepted','catalog_match','Staff')];
$out=$suppress->invoke($e,$rows,'WTB +5 SR for Staff');
if($out!==[]) $failed[]=['staff_mod_target_survived'];

$rows=[off('Spear',.84,'accepted','catalog_match','Spear')];
$out=$suppress->invoke($e,$rows,'WTB Zealous for Spear');
if($out!==[]) $failed[]=['spear_mod_target_survived'];

// Concrete identity beats generic category/family shadow.
$rows=[off('Staff',.84,'accepted','catalog_match','Plagueborn Staff'),off('Plagueborn Staff',.92,'accepted','catalog_match','OS Plagueborn Staff - q9 Air Magic')];
$out=$suppress->invoke($e,$rows,'WTS OS Plagueborn Staff - q9 Air Magic|20/10');
if(array_filter($out,fn($o)=>$o->item==='Staff')) $failed[]=['staff_specificity_shadow_survived'];

$rows=[off('Focus item',.84,'accepted','catalog_match','Bogroot Focus'),off('Bogroot Focus',.92,'accepted','catalog_match','Bogroot Focus')];
$out=$suppress->invoke($e,$rows,'WTS Bogroot Focus (20/20 Channeling Green)');
if(array_filter($out,fn($o)=>$o->item==='Focus item')) $failed[]=['focus_specificity_shadow_survived'];

$rows=[off('Miniature',.84,'accepted','catalog_match','unded kuuna'),off('Kuuna',.92,'accepted','catalog_match','unded kuuna')];
$out=$suppress->invoke($e,$rows,'WTB unded kuuna pm offers');
if(array_filter($out,fn($o)=>$o->item==='Miniature')) $failed[]=['mini_specificity_shadow_survived'];

// True generic searches remain and are promoted over the review seed threshold.
foreach([
 ['Bow','Bow','WTB Q8 Bows / Q7 Bows'],
 ['Wand','Wand','WTB Q9 Wands'],
 ['Sword','Sword','WTB need many swords for my collection'],
 ['Miniature','Miniature','WTB Unded White Minis 2k/ea'],
] as [$item,$segment,$msg]){
 $out=$promote->invoke($e,[off($item,.82,'accepted','catalog_match',$segment)],$msg);
 if(count($out)!==1 || $out[0]->confidence < .85) $failed[]=['generic_not_promoted'=>$msg,'got'=>$out[0]->toArray()??null];
}

// Unsafe bare `cons` must not become Conset anymore.
$offers=$e->parse('WTB Rubies Sapphires or for Tengu Guard Cons 3:1');
if(array_filter($offers,fn($o)=>$o->item==='Conset')) $failed[]=['bare_cons_became_conset'];

// Exact canonical catalog names should be strong identities.
foreach(['Cane','Tall Shield','Plagueborn Staff'] as $name){
 $offers=$e->parse('WTS '.$name.' q9 5e');
 foreach($offers as $o) if($o->item===$name && $o->confidence<.85) $failed[]=['canonical_not_strong'=>$name,'got'=>$o->toArray()];
}

echo json_encode(['ok'=>$failed===[],'failed'=>$failed],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($failed===[]?0:1);
