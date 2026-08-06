<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
$fail=[];

function names(array $offers): array { return array_values(array_column($offers,'item')); }
function need(string $msg,array $expected,array &$fail): array {
    $offers=parseOffers($msg);$n=names($offers);
    foreach($expected as$e)if(!in_array($e,$n,true))$fail[]=['message'=>$msg,'missing'=>$e,'actual'=>$offers];
    return$offers;
}

need('WTS | Ecto 6 = 100k | Tengu 2e/ea|Conset 10a/stk | Tormented Sword|Pm me|',
 ['Glob of Ectoplasm','Tengu Support Flare','Conset','Tormented Sword'],$fail);

if(parseOffers('WTS Mission 3a^FoW Armor 3a^any Quest 3a^Vanquish 5a^Dungeon 5a^Furnace^Duncan^Mallyx 10a^Deep 20a^FoW Urgoz 30a')!==[])
 $fail[]=['service'=>'not excluded'];

if(parseOffers('WTS Names:^Sxy^Jnx^Bricked^Wunderkind^Nrg 1750a/ea')!==[])
 $fail[]=['names'=>'not excluded'];

$u=need("WTS Unicorns Wrath, 10 soul reaping, +5 energy. pm me",["Unicorn's Wrath"],$fail);
if(count($u)!==1||!str_contains((string)($u[0]['details']??''),'q10')||!str_contains((string)($u[0]['details']??''),'+5 energy'))
 $fail[]=['unicorn'=>$u];

$r=need('WTS Rin Relics set 5a',['Rin Relic'],$fail);
if((float)($r[0]['quantity']??0)!==25.0||($r[0]['basis']??'')!=='set')$fail[]=['rin'=>$r];

need('WTS Unded Dhuum, Ded Destroyer + EL Destroyer Tonic',
 ['Miniature Dhuum','Miniature Destroyer of Flesh','Everlasting Destroyer Tonic'],$fail);

need('WTS Warrior Tome 2e | Elite Rit Tome 5e',['Warrior Tome','Elite Ritualist Tome'],$fail);

echo json_encode(['ok'=>$fail===[],'failures'=>$fail],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($fail===[]?0:1);
