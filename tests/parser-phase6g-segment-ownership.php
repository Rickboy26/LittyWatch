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
$e=new \LittyWatch\Parser\ParserEngine($c);
$fail=[];

$cases=[
    'WTS BDS, Q8 Tac Shield',
    'WTS Q9 FC BDS, Q8 Tac Shield',
    'WTS BDS/ q8 gold inscribable scarabshell shield/ Q9 Demoncrest/Q9',
];
foreach($cases as $text){
    foreach($e->parse($text) as $o){
        $d=$o->toArray();
        if(($d['item']??'')!=='Bone Dragon Staff') continue;
        $req=$d['modifiers']['requirement']??null;
        $attr=$d['modifiers']['attribute']??null;
        if(in_array($req,['q7','q8'],true) || in_array($attr,['tactics','scythe mastery'],true)){
            $fail[]=['text'=>$text,'offer'=>$d];
        }
    }
}

// Valid shorthand ownership must remain intact.
$offers=$e->parse('WTS BDS | Air Q9/11 | Blood Q9/11');
$got=[];
foreach($offers as $o){$d=$o->toArray();if(($d['item']??'')==='Bone Dragon Staff')$got[]=[($d['modifiers']['attribute']??null),($d['modifiers']['requirement']??null)];}
foreach([['air magic','q9'],['air magic','q11'],['blood magic','q9'],['blood magic','q11']] as $pair){
    if(!in_array($pair,$got,true))$fail[]=['missing_valid_bds'=>$pair,'got'=>$got];
}

if($fail){fwrite(STDERR,json_encode($fail,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL);exit(1);}
echo "Phase 6G segment ownership: OK\n";
