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
$cases=[
 ['WTS Bogroot Focus (20/20 Channeling Green)','Bogroot Focus'],
 ['WTS Bogroot Focus (20/20 Channeling Green) / Stygian Spear','Bogroot Focus'],
 ['WTS Bogroot Focus (20/20 Channeling Green) / Stygian Spear','Stygian Spear'],
 ['WTB Spear grips of fortitude, 2','Spear Grip of Fortitude'],
];
foreach($cases as [$msg,$want]){
 $offers=$e->parse($msg);
 $hit=array_values(array_filter($offers,fn($o)=>$o->item===$want));
 if(!$hit || $hit[0]->confidence<.85) $failed[]=['missing_or_weak'=>$want,'msg'=>$msg,'offers'=>array_map(fn($o)=>$o->toArray(),$offers)];
}
foreach([
 'WTS Bogroot Focus (20/20 Channeling Green)',
 'WTS Bogroot Focus (20/20 Channeling Green) / Stygian Spear',
] as $msg){
 $offers=$e->parse($msg);
 foreach($offers as $o) if(in_array($o->item,['Unique item','Focus item','Spear'],true)) $failed[]=['generic_shadow'=>$o->item,'msg'=>$msg];
}
$offers=$e->parse('WTS Perfect Salvage Kit.+30hp for Staff. Axe. Scythe. Cloths of the Brotherhood');
foreach($offers as $o) if($o->item==='Axe') $failed[]=['axe_mod_shadow'];
$offers=$e->parse('WTS Insightful staff head +5energy | WTS +5SR Sword of the necro | Zealous bowstring, zealous hammer haft');
foreach($offers as $o) if($o->item==='Sword') $failed[]=['sword_mod_shadow'];
foreach([
 ['WTS Q9 Volta | CC Q9 Water | WTB unded Gpriest','Voltaic Spear'],
 ['WTB 5k each | Q9 Insc Tac. Tall','Tall Shield'],
 ['WTB SR wand x4 / staff x1','Staff'],
] as [$msg,$want]){
 $offers=$e->parse($msg); $hit=array_values(array_filter($offers,fn($o)=>$o->item===$want));
 if(!$hit || $hit[0]->confidence<.85) $failed[]=['not_promoted'=>$want,'msg'=>$msg,'offers'=>array_map(fn($o)=>$o->toArray(),$offers)];
}
echo json_encode(['ok'=>$failed===[],'failed'=>$failed],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($failed===[]?0:1);
