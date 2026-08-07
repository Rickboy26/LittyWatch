<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';

$parser = new \LittyWatch\Parser\ParserEngine(new \LittyWatch\Parser\Catalog(dirname(__DIR__).'/app/Data')); 
$neg = $parser->parse('WTS BDS q9 any(no chann/curses)');
if (!$neg) { fwrite(STDERR,"No negation parse\n"); exit(1); }
foreach ($neg as $o) {
    if ($o->item === 'Bone Dragon Staff' && isset($o->modifiers['attribute'])) {
        fwrite(STDERR,"Negated attribute leaked: ".$o->modifiers['attribute']."\n"); exit(1);
    }
}
$multi = $parser->parse('WTS BDS q11 dom/air/FC/ES/comm');
$attrs=[];
foreach($multi as $o) if($o->item==='Bone Dragon Staff' && isset($o->modifiers['attribute'])) $attrs[$o->modifiers['attribute']]=true;
if(count($attrs)<3){fwrite(STDERR,"Expected multi attribute expansion, got ".json_encode(array_keys($attrs))."\n");exit(1);}
if(strpos(lw_market_price(810.0),'a')===false){fwrite(STDERR,"810e should prefer armbraces\n");exit(1);}
if(strpos(lw_market_price(135.0),'e')===false){fwrite(STDERR,"135e should remain ecto primary\n");exit(1);}
$dt=lw_local_datetime('2026-08-07T18:33:08+00:00');
if($dt!=='07-08-2026 20:33'){fwrite(STDERR,"Timezone format mismatch: $dt\n");exit(1);}
echo "Phase 3C market presentation OK\n";
