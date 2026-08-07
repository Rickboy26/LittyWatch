<?php
declare(strict_types=1);
if (!function_exists('mb_strtolower')) { function mb_strtolower(string $v, ?string $e=null): string { return strtolower($v); } }
if (!function_exists('mb_strtoupper')) { function mb_strtoupper(string $v, ?string $e=null): string { return strtoupper($v); } }
if (!function_exists('mb_strlen')) { function mb_strlen(string $v, ?string $e=null): int { return strlen($v); } }
if (!function_exists('mb_stripos')) { function mb_stripos(string $h,string $n,int $o=0,?string $e=null): int|false { return stripos($h,$n,$o); } }
if (!function_exists('mb_substr')) { function mb_substr(string $v,int $s,?int $l=null,?string $e=null): string { return $l===null?substr($v,$s):substr($v,$s,$l); } }
require dirname(__DIR__) . '/app/Support/Autoloader.php';
\LittyWatch\Support\Autoloader::register(dirname(__DIR__) . '/app');
$src=dirname(__DIR__).'/app/Data';
$tmp=sys_get_temp_dir().'/litty-family-'.bin2hex(random_bytes(4)); mkdir($tmp);
foreach(['items.json','modifiers.json','reject-patterns.json'] as $f) copy($src.'/'.$f,$tmp.'/'.$f);
$items=json_decode(file_get_contents($tmp.'/items.json'),true);
foreach(['Zodiac Staff','Embercrest Staff','Ghostly Staff','Goldhorn Staff','Icicle Staff','Insectoid Staff','Turquoise Staff','Suntouched Staff'] as $name){
 $key=strtolower(preg_replace('/[^a-z0-9]+/i','-',trim($name)));
 $items[]=['key'=>$key,'name'=>$name,'category'=>'weapon','aliases'=>[strtolower($name)]];
}
file_put_contents($tmp.'/items.json',json_encode($items));
$c=new \LittyWatch\Parser\Catalog($tmp); $e=new \LittyWatch\Parser\ParserEngine($c);
$offers=$e->parse('WTB Q9 Staves | Zodiac (OS) | Embercrest | Ghostly (Inspi) | Goldhorn | Icicle | Insectoid | Turquoise | Suntouched');
$failed=[];$got=[];
foreach($offers as $o){$d=$o->toArray();$got[$o->item]=$d['modifiers'];}
foreach(['Zodiac Staff','Embercrest Staff','Ghostly Staff','Goldhorn Staff','Icicle Staff','Insectoid Staff','Turquoise Staff','Suntouched Staff'] as $name){
 if(!isset($got[$name]) || ($got[$name]['requirement']??null)!=='q9')$failed[]=['item'=>$name,'mods'=>$got[$name]??null];
}
if(($got['Ghostly Staff']['attribute']??null)!=='inspiration magic')$failed[]=['ghostly-attribute'=>$got['Ghostly Staff']??null];
foreach(glob($tmp.'/*')?:[] as $f) unlink($f); rmdir($tmp);
echo json_encode(['ok'=>$failed===[],'offers'=>count($offers),'failed'=>$failed],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($failed===[]?0:1);
