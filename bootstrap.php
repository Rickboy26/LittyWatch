<?php
declare(strict_types=1);
$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/app/Support/Autoloader.php';
\LittyWatch\Support\Autoloader::register(__DIR__ . '/app');

date_default_timezone_set($config['timezone']);
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Kleine polyfills voor servers zonder ext-mbstring.
if (!function_exists('mb_strtolower')) { function mb_strtolower(string $s): string { return strtolower($s); } }
if (!function_exists('mb_strlen')) { function mb_strlen(string $s): int { return strlen($s); } }
if (!function_exists('mb_substr')) { function mb_substr(string $s,int $start,?int $length=null): string { return $length===null?substr($s,$start):substr($s,$start,$length); } }
if (!function_exists('mb_stripos')) { function mb_stripos(string $haystack,string $needle,int $offset=0): int|false { return stripos($haystack,$needle,$offset); } }

function db(): PDO {
    static $pdo = null;
    global $config;
    if ($pdo instanceof PDO) return $pdo;
    if (!extension_loaded('pdo_sqlite')) throw new RuntimeException('PHP-extensie pdo_sqlite ontbreekt.');
    $dir = dirname($config['db_path']);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('Kan datamap niet aanmaken: '.$dir);
    $pdo = new PDO('sqlite:' . $config['db_path'], null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $pdo->exec('PRAGMA journal_mode=WAL; PRAGMA busy_timeout=5000; PRAGMA foreign_keys=ON;');
    return $pdo;
}

function installSchema(): void {
    db()->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS messages (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 source TEXT NOT NULL,
 source_key TEXT NOT NULL UNIQUE,
 player TEXT NOT NULL,
 message TEXT NOT NULL,
 trade_type TEXT,
 item TEXT,
 price_amount REAL,
 price_currency TEXT,
 price_ecto REAL,
 posted_at TEXT NOT NULL,
 collected_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_messages_posted ON messages(posted_at DESC);
CREATE TABLE IF NOT EXISTS offers (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 message_id INTEGER NOT NULL,
 trade_type TEXT NOT NULL,
 item TEXT NOT NULL,
 item_key TEXT NOT NULL,
 details TEXT,
 quantity REAL,
 price_amount REAL,
 price_currency TEXT,
 price_ecto REAL,
 unit_price_ecto REAL,
 confidence REAL NOT NULL DEFAULT 0.5,
 created_at TEXT NOT NULL,
 price_basis TEXT,
 raw_segment TEXT,
 UNIQUE(message_id, trade_type, item_key, details, price_amount, price_currency, raw_segment),
 FOREIGN KEY(message_id) REFERENCES messages(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_offers_item ON offers(item_key);
CREATE INDEX IF NOT EXISTS idx_offers_type ON offers(trade_type);
CREATE INDEX IF NOT EXISTS idx_offers_price ON offers(unit_price_ecto);
CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT);
SQL);
    ensureColumn('offers','price_basis','TEXT');
    ensureColumn('offers','raw_segment','TEXT');
    ensureColumn('offers','quality_status',"TEXT NOT NULL DEFAULT 'review'");
    ensureColumn('offers','quality_reason','TEXT');
}

function ensureColumn(string $table,string $column,string $type): void {
    $cols=db()->query("PRAGMA table_info($table)")->fetchAll();
    foreach($cols as $col) if(($col['name']??'')===$column) return;
    db()->exec("ALTER TABLE $table ADD COLUMN $column $type");
}

function h(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
function norm(string $s): string {
    $s=str_replace('_',' ',$s);
    $s=preg_replace('/\^{2,}/',' | ',$s)??$s;
    $s=preg_replace('/(?<=\D)\^(?=\D)/',' ',$s)??$s;
    return trim(preg_replace('/\s+/u',' ',$s)??$s);
}
function itemKey(string $s): string { return trim(preg_replace('/[^a-z0-9]+/',' ',mb_strtolower($s)) ?? ''); }

function itemCatalog(): array {
    return [
        'Gift of the Traveler'=>['gott','gotts','got','gots','gift of the traveler','gifts of the traveler','nick gift','nick gifts','nickgift','nickgifts'],
        'Nicholas Set'=>['nicholas sets','nicholas set','nick sets','nick set','nicksets','nickset','nic sets','nic set'], 'Armbrace of Truth'=>['armbrace of truth','armbrace','armbraces','ambrace','ambraces','ambr','arms'],
        'Glob of Ectoplasm'=>['glob of ectoplasm','ectoplasm','ectos','ecto','ektos'], 'Zaishen Key'=>['zaishen keys','zaishen key','zkeys','zkey'],
        'Lockpick'=>['lockpicks','lockpick','picks'], 'Conset'=>['consets','conset'], 'Essence of Celerity'=>['essence of celerity','essences'],
        'Grail of Might'=>['grail of might','grails'], 'Armor of Salvation'=>['armor of salvation','armors of salvation'],
        'Cupcake'=>['cupcakes','cupcake'], "Stalker's Ration"=>["stalker's rations","stalker's ration",'stalkers rations','stalkers ration'], 'Black Dye'=>['black dyes','black dye'],
        'Elite Tome'=>['elite tomes','elite tome'], 'Warrior Tome'=>['warrior tomes','warri tomes','warrior tome','warri tome'],
        'Ranger Tome'=>['regular ranger tomes','reg ranger tomes','ranger tomes','ranger tome'], 'Elementalist Tome'=>['elementalist tomes','ele tomes'],
        'Unidentified Gold'=>['unidentified golds','unidentified gold','unid. golds','unid golds','unid gold','unids'],
        'Bone Dragon Staff'=>['bone dragon staff','bds'], 'Eternal Blade'=>['eternal blade','eternalblade','eblade'],
        'Obsidian Edge'=>['obsidian edge','obsiedge'], 'Voltaic Spear'=>['voltaic spear','voltaicspear','vs'], 'Chaos Axe'=>['chaos axe'],
        'Colossal Scimitar'=>['colossal scimitar'], 'Eternal Bow'=>['eternal bows','eternal bow','eternal flatbow','eternal longbow','eternal shortbow','eternal hornbow','eternal rec bow','eternal recurve bow'],
        'Eternal Shield'=>['eternal shields','eternal shield'], 'Rift Warden'=>['rift warden'], 'Mad King’s Guard'=>['mad king’s guard',"mad king's guard",'mad king guard','mkg'],
        'Ghostly Hero'=>['ghostly hero'], 'Mallyx'=>['mallyx'], 'Miniature Undead Prince Rurik'=>['miniature undead prince rurik','mini undead prince','undead prince'],
        'Celestial Horse'=>['celestial horse','cele horse'], 'Rin Relic Set'=>['rin relic set','rin set'], 'Raging Menzies'=>['raging menzies'],
        'Summoning Stone'=>['summoning stones','summoning stone','summon stones','summon stone'], 'Cracked Ascalonian War Horn'=>['cracked ascalonian war horns','cracked ascalonian war horn'],
        'Obsidian Shard'=>['obsidian shards','obsidian shard','obsi shards','obsi shard'], 'Royal Gift'=>['royal gifts','royal gift'], 'Silver Zaishen Coin'=>['silver zaishen coins','silver zaishen coin','silver z coin','silver zcoin'],
        'Primeval Armor Remnant'=>['primeval armor remnants','primeval armor remnant'], 'Flame Sentinel Tonic'=>['flame sentinel tonic','el flame sentinel tonic'],
        'Ruby'=>['rubies','ruby'], 'Sapphire'=>['sapphires','sapphire'], 'Char Carving'=>['char carvings','char carving'],
        'Diessa Chalice'=>['diessa chalices','diessa chalice'], 'War Supplies'=>['war supplies','war supp'],
        'Alcohol Points'=>['alcohol points','drunk points'], 'Sweet Points'=>['sweet points'],
        'Mystical Summoning Stone (Gaki)'=>['mystical summoning stone gaki','mystical summon stone gaki','gaki'],
        'Mysterious Armor'=>['mysterious armor'], 'Envoy Staff'=>['envoy staff'], 'Padraic'=>['padraic'], 'Kerrsh’s Staff'=>["kerrsh's staff",'kerrsh staff'],
        'Hero Box'=>['hero boxes','herobox','hero box'], 'Gold Zaishen Coin'=>['gold zaishen coin','gold zcoin'], 'Tengu Support Flare'=>['tengu support flare','tengus','tengu'],
        'Seal of the Dragon Empire'=>['seal of the dragon empire','guards-seals','guard seals'], 'Soup'=>['soup'], 'Elixir of Valor'=>['elixirs of valor','elixir of valor'],
        'Droknar’s Key'=>["droknar's key",'droknars key'], 'Kathandrax Hammer'=>['kath hammer','kathandrax hammer'], 'Compass'=>['compasses','compass'], 'Asterius Scythe'=>['asterius scythe'], 'Warrior Rune of Superior Vigor'=>['sup rune vigor','superior vigor'],
    ];
}

function detectType(string $text): ?string {
    if (preg_match('/(?:^|\W)wtb(?:\W|$)|\bbuying\b/i',$text)) return 'buy';
    if (preg_match('/(?:^|\W)wts(?:\W|$)|\bselling\b/i',$text)) return 'sell';
    if (preg_match('/(?:^|\W)wtt(?:\W|$)/i',$text)) return 'trade';
    return null;
}
function currencyToEcto(float $amount,string $currency): float { return match($currency){'a'=>$amount*27.0,'e'=>$amount,'k'=>$amount/15.0,default=>$amount}; }

function extractPrice(string $segment): ?array {
    // 5:1e = five items for one ecto.
    if(preg_match('/(?<!\d)([0-9]+(?:[.,][0-9]+)?)\s*:\s*([0-9]+(?:[.,][0-9]+)?)\s*(a|e|k)\b/i',$segment,$m,PREG_OFFSET_CAPTURE)){
        $qty=(float)str_replace(',','.',$m[1][0]); $amount=(float)str_replace(',','.',$m[2][0]); $cur=strtolower($m[3][0]);
        return ['amount'=>$amount,'currency'=>$cur,'ecto'=>currencyToEcto($amount,$cur),'offset'=>$m[0][1],'raw'=>$m[0][0],'ratio_qty'=>$qty,'basis'=>'ratio'];
    }
    // 250 = 125e or 7e = 100k.
    if(preg_match('/(?<!\d)([0-9]+(?:[.,][0-9]+)?)\s*=\s*([0-9]+(?:[.,][0-9]+)?)\s*(a|e|k)\b/i',$segment,$m,PREG_OFFSET_CAPTURE)){
        $qty=(float)str_replace(',','.',$m[1][0]); $amount=(float)str_replace(',','.',$m[2][0]); $cur=strtolower($m[3][0]);
        return ['amount'=>$amount,'currency'=>$cur,'ecto'=>currencyToEcto($amount,$cur),'offset'=>$m[0][1],'raw'=>$m[0][0],'ratio_qty'=>$qty,'basis'=>'exchange'];
    }
    if(preg_match('/(?<![a-z0-9])([0-9]+(?:[.,][0-9]+)?)\s*(a|ambr(?:ace)?s?|armbraces?|e|ectos?|k|plat(?:inum)?)(?=\b|\/|$)/i',$segment,$m,PREG_OFFSET_CAPTURE)){
        $amount=(float)str_replace(',','.',$m[1][0]); $u=mb_strtolower($m[2][0]); $cur=str_starts_with($u,'a')?'a':(str_starts_with($u,'e')?'e':'k');
        $tail=mb_substr($segment,$m[0][1]+mb_strlen($m[0][0]),12);
        $basis=preg_match('/\/\s*(?:st|stk|stack)\b/i',$tail)?'stack':(preg_match('/\/\s*(?:ea|each)\b/i',$tail)?'each':'total');
        return ['amount'=>$amount,'currency'=>$cur,'ecto'=>currencyToEcto($amount,$cur),'offset'=>$m[0][1],'raw'=>$m[0][0],'ratio_qty'=>null,'basis'=>$basis];
    }
    return null;
}

function detectQuantity(string $segment,?array $price): array {
    if($price && $price['ratio_qty']) return [(float)$price['ratio_qty'],$price['basis']];
    if(preg_match('/\[x\s*([0-9]+)\]/i',$segment,$m)) return [(float)$m[1],'inventory'];
    if(preg_match('/\bx\s*([0-9]+)\b/i',$segment,$m)) return [(float)$m[1],'inventory'];
    if(preg_match('/\b([0-9]+)\s+(?:gott|gotts|gots?|nickgifts?|nick\s*sets?|nicholas\s*sets?|gifts?|tomes?|unids?|rubies|sapphires|char carvings?|diessa chalices?|gaki|war horns?)\b/i',$segment,$m)) return [(float)$m[1],($price && $price['basis']==='each')?'inventory':'total'];
    if(preg_match('/\b([0-9]+)\s+stacks?\b/i',$segment,$m)) return [(float)$m[1],'inventory_stacks'];
    if($price && $price['basis']==='stack') return [250.0,'stack'];
    if(preg_match('/\b(?:stack|stk)\b/i',$segment)) return [250.0,'stack'];
    return [null,$price['basis']??'unknown'];
}

function extractDetails(string $clean): string {
    $d=[];
    if(preg_match('/\bq\s*([0-9]{1,2})(?:\s*[-–]\s*([0-9]{1,2}))?\b/i',$clean,$m)) $d[]='q'.$m[1].(!empty($m[2])?'-'.$m[2]:'');
    if(preg_match('/\b(unded|ded)\b/i',$clean,$m)) $d[]=strtolower($m[1]);
    if(preg_match('/\b(os|oldschool|old school)\b/i',$clean)) $d[]='OS';
    if(preg_match('/\b(insc|inscb|inscr|inscribable)\b/i',$clean)) $d[]='insc';
    if(preg_match('/\b(fc|fast cast|inspa?|prot|comm|communing|motivation|tact|tactics|str|strength)\b/i',$clean,$m)) $d[]=strtolower($m[1]);
    return implode(' ',array_unique($d));
}

function catalogMentions(string $text): array {
    $lower=mb_strtolower($text); $hits=[];
    foreach(itemCatalog() as $name=>$aliases) foreach($aliases as $alias){
        $offset=0;$a=mb_strtolower($alias);
        while(($p=mb_stripos($lower,$a,$offset))!==false){$hits[]=['start'=>$p,'len'=>mb_strlen($a),'item'=>$name,'alias'=>$alias];$offset=$p+max(1,mb_strlen($a));}
    }
    usort($hits,fn($x,$y)=>$x['start']<=>$y['start'] ?: $y['len']<=>$x['len']);
    $out=[];$end=-1;
    foreach($hits as $h){if($h['start']<$end)continue;$out[]=$h;$end=$h['start']+$h['len'];}
    return $out;
}

function fallbackItem(string $segment): array {
    $clean=norm($segment);$details=extractDetails($clean);
    $fallback=preg_replace('/\b(wtb|wts|wtt|buying|selling)\b/i','',$clean);
    $fallback=preg_replace('/[0-9]+(?:[.,][0-9]+)?\s*(?:a|ambr(?:ace)?s?|armbraces?|e|ectos?|k|plat(?:inum)?)(?:\b|\/|$)/i','',$fallback??'');
    $fallback=preg_replace('/\b(pm|offer|offers|each|ea|per)\b.*$/i','',$fallback??'');
    $fallback=trim($fallback," \t\n\r\0\x0B-:;,/|<>+=");
    return [$fallback!==''?mb_substr($fallback,0,100):'Onbekend',$details,0.45];
}

function splitTypeBlocks(string $message): array {
    $s=norm($message);$matches=[];
    preg_match_all('/\bWT([BST])\b/i',$s,$matches,PREG_OFFSET_CAPTURE);
    if(!$matches[0]) return [['type'=>detectType($s),'text'=>$s]];
    $blocks=[];
    foreach($matches[0] as $i=>$m){$start=$m[1];$end=$matches[0][$i+1][1]??mb_strlen($s);$marker=strtoupper($m[0]);$type=$marker==='WTB'?'buy':($marker==='WTS'?'sell':'trade');$text=trim(mb_substr($s,$start+3,$end-($start+3))," _|-/");if($text!=='')$blocks[]=['type'=>$type,'text'=>$text];}
    return $blocks;
}

function isGarbageFragment(string $piece): ?string {
    $clean=trim(norm($piece));
    if($clean==='') return 'empty';
    if(preg_match('/^(?:or|and|plus)\b/i',$clean)) return 'dangling_conjunction';
    if(preg_match('/^(?:pm|wsp|show me|trade\/pm|offer(?:s)? only)\b/i',$clean)) return 'contact_only';
    if(preg_match('/^[+_=<>!\-\s]+$/',$clean)) return 'symbols_only';
    if(mb_strlen($clean)<2) return 'too_short';
    return null;
}

function qualityForOffer(array $offer): array {
    $item=trim((string)$offer['item']);
    $confidence=(float)$offer['confidence'];
    $price=$offer['price'];
    $basis=(string)$offer['basis'];
    $segment=(string)$offer['segment'];
    $garbage=isGarbageFragment($item);
    if($garbage) return ['rejected',$garbage];
    if($item==='Onbekend') return ['rejected','unknown_item'];
    if(preg_match('/\b(?:guild cape|trim your guild|mission(?:s)?|rush|service|runs?|armor any|names?:)\b/i',$item)) return ['review','service_or_non_item'];
    if(preg_match('/^(?:q\d+|mods?\/insc|\+{2,}|\d+[,.]?\d*\s*rp)\b/i',$item)) return ['review','generic_description'];
    if($confidence>=0.8 && $price!==null && !in_array($basis,['bundle','currency_exchange'],true)) return ['accepted','catalog_price'];
    if($confidence>=0.8) return ['accepted','catalog_no_price'];
    if($price!==null && mb_strlen($item)>=3) return ['review','uncatalogued_with_price'];
    return ['review','uncatalogued_no_price'];
}

function robustMedian(array $values): ?float {
    $values=array_values(array_filter(array_map('floatval',$values),fn($v)=>is_finite($v)&&$v>0));
    if(!$values)return null;sort($values,SORT_NUMERIC);$n=count($values);$mid=intdiv($n,2);
    return $n%2?$values[$mid]:($values[$mid-1]+$values[$mid])/2;
}
function filterPriceOutliers(array $values): array {
    $values=array_values(array_filter(array_map('floatval',$values),fn($v)=>is_finite($v)&&$v>0));
    if(count($values)<4)return $values;sort($values,SORT_NUMERIC);
    $median=robustMedian($values);$dev=array_map(fn($v)=>abs($v-$median),$values);$mad=robustMedian($dev);
    if(!$mad || $mad<0.0001)return $values;
    return array_values(array_filter($values,fn($v)=>abs($v-$median)/$mad<=4.5));
}
function flipOpportunities(int $days=7,int $minTraders=2): array {
    $cutoff=(new DateTimeImmutable("-$days days"))->format(DATE_ATOM);
    $st=db()->prepare("SELECT o.item,o.item_key,o.trade_type,o.unit_price_ecto,m.player,m.posted_at,o.id FROM offers o JOIN messages m ON m.id=o.message_id WHERE o.unit_price_ecto IS NOT NULL AND o.quality_status='accepted' AND o.confidence>=0.8 AND COALESCE(o.price_basis,'') NOT IN ('bundle','currency_exchange','exchange') AND datetime(m.posted_at)>=datetime(?) ORDER BY datetime(m.posted_at) DESC,o.id DESC");
    $st->execute([$cutoff]);$group=[];
    foreach($st->fetchAll() as $r){$k=$r['item_key'];$side=$r['trade_type'];if(!in_array($side,['buy','sell'],true))continue;$player=mb_strtolower(trim($r['player']));if(isset($group[$k][$side][$player]))continue;$group[$k]['item']=$r['item'];$group[$k][$side][$player]=(float)$r['unit_price_ecto'];}
    $out=[];
    foreach($group as $k=>$g){$buys=array_values($g['buy']??[]);$sells=array_values($g['sell']??[]);if(count($buys)<$minTraders||count($sells)<$minTraders)continue;$buys=filterPriceOutliers($buys);$sells=filterPriceOutliers($sells);if(count($buys)<$minTraders||count($sells)<$minTraders)continue;$buy=robustMedian($buys);$sell=robustMedian($sells);if($buy===null||$sell===null||$buy<=$sell)continue;$out[]=['item'=>$g['item'],'item_key'=>$k,'buy_median'=>$buy,'sell_median'=>$sell,'spread'=>$buy-$sell,'buy_traders'=>count($buys),'sell_traders'=>count($sells),'samples'=>count($buys)+count($sells)];}
    usort($out,fn($a,$b)=>$b['spread']<=>$a['spread']);return array_slice($out,0,20);
}

function parsePiece(string $piece,string $type,?string $inheritedItem=null): array {
    $piece=trim($piece," \t\n\r|,;");if($piece==='')return[];
    $mentions=catalogMentions($piece);
    if(count($mentions)>1 && preg_match('/\b(package|bundle|all unidentified|all unid)\b/i',$piece) && extractPrice($piece)){
        $names=array_values(array_unique(array_column($mentions,'item')));
        $price=extractPrice($piece);[$qty,$basis]=detectQuantity($piece,$price);
        return [[ 'type'=>$type,'item'=>'Bundle: '.implode(' + ',$names),'details'=>extractDetails($piece),'confidence'=>0.9,'price'=>$price,'quantity'=>$qty,'basis'=>'bundle','segment'=>$piece ]];
    }
    if(count($mentions)>1){
        $out=[];
        foreach($mentions as $i=>$hit){$start=$i===0?0:$hit['start'];$end=$mentions[$i+1]['start']??mb_strlen($piece);$slice=trim(mb_substr($piece,$start,$end-$start)," /,;|");$out=array_merge($out,parsePiece($slice,$type,$hit['item']));}
        return $out;
    }
    $price=extractPrice($piece);[$qty,$basis]=detectQuantity($piece,$price);
    if($mentions){$item=$mentions[0]['item'];$confidence=.95;}
    elseif($inheritedItem!==null && preg_match('/^(?:q\s*\d+|\d+\s*(?:a|e|k)\b|(?:fc|inspa?|prot|comm|motivation|tact))/i',$piece)){$item=$inheritedItem;$confidence=.85;}
    else{[$item,$unused,$confidence]=fallbackItem($piece);}
    $details=extractDetails($piece);
    return [[ 'type'=>$type,'item'=>$item,'details'=>$details,'confidence'=>$confidence,'price'=>$price,'quantity'=>$qty,'basis'=>$basis,'segment'=>$piece ]];
}

function parseOffers(string $message): array {
    $offers=[];
    foreach(splitTypeBlocks($message) as $block){
        if(!$block['type'])continue;
        $parts=preg_split('/\s*[|;]\s*|\s*[,.]\s*(?=q\s*\d+\b)|\s*,\s*(?=[A-Za-z][A-Za-z +\'’.-]{2,}\s+[0-9]+(?:[.,][0-9]+)?\s*(?:a|e|k)\b)/iu',$block['text'])?:[$block['text']];
        $lastItem=null;
        foreach($parts as $part){
            $parsed=parsePiece($part,$block['type'],$lastItem);
            foreach($parsed as $p){
                if($p['item']==='Onbekend'&&!$p['price'])continue;
                if(!str_starts_with($p['item'],'Bundle:'))$lastItem=$p['item'];
                $price=$p['price'];$unit=$price['ecto']??null;$qty=$p['quantity'];$basis=$p['basis'];
                if($unit!==null&&$qty&&$qty>0&&in_array($basis,['ratio','exchange','total','stack'],true))$unit/=$qty;
                if($basis==='exchange'&&$p['item']==='Glob of Ectoplasm')$basis='currency_exchange';
                $candidate=['type'=>$p['type'],'item'=>$p['item'],'item_key'=>itemKey($p['item'].($p['details']?' '.$p['details']:'')),'details'=>$p['details'],'quantity'=>$qty,
                    'amount'=>$price['amount']??null,'currency'=>$price['currency']??null,'ecto'=>$price['ecto']??null,'unit_ecto'=>$unit,'confidence'=>$p['confidence'],'basis'=>$basis,'segment'=>$p['segment']];
                [$candidate['quality_status'],$candidate['quality_reason']]=qualityForOffer(['item'=>$p['item'],'confidence'=>$p['confidence'],'price'=>$price,'basis'=>$basis,'segment'=>$p['segment']]);
                if($candidate['quality_status']!=='rejected')$offers[]=$candidate;
            }
        }
    }
    return $offers;
}

function parseTrade(string $message): array {$offers=parseOffers($message);$f=$offers[0]??null;return['type'=>$f['type']??detectType($message),'item'=>$f['item']??null,'amount'=>$f['amount']??null,'currency'=>$f['currency']??null,'ecto'=>$f['ecto']??null];}
function saveOffers(int $messageId,string $message): int {
    db()->prepare('DELETE FROM offers WHERE message_id=?')->execute([$messageId]);
    $ins=db()->prepare('INSERT OR IGNORE INTO offers(message_id,trade_type,item,item_key,details,quantity,price_amount,price_currency,price_ecto,unit_price_ecto,confidence,created_at,price_basis,raw_segment,quality_status,quality_reason) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $n=0;foreach(parseOffers($message) as $o){$ins->execute([$messageId,$o['type'],$o['item'],$o['item_key'],$o['details'],$o['quantity'],$o['amount'],$o['currency'],$o['ecto'],$o['unit_ecto'],$o['confidence'],date(DATE_ATOM),$o['basis'],$o['segment'],$o['quality_status'],$o['quality_reason']]);$n+=$ins->rowCount();}return$n;
}

function httpGet(string $url): array {global$config;$headers=['User-Agent: LittyWatch/0.5 (+personal project)'];$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>$config['request_timeout'],CURLOPT_HTTPHEADER=>$headers,CURLOPT_ENCODING=>'']);$body=curl_exec($ch);$err=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$type=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE);curl_close($ch);if($body===false)throw new RuntimeException('cURL-fout: '.$err);return[$code,$type,$body];}
function normalizeKamadanPayload(string $body): array {$json=json_decode($body,true);if(!is_array($json))return[];$rows=$json['messages']??$json['results']??$json;if(!is_array($rows))return[];$out=[];foreach($rows as $row){if(!is_array($row))continue;$message=(string)($row['m']??$row['message']??'');if($message==='')continue;$player=(string)($row['s']??$row['player']??'Unknown');$time=$row['t']??date(DATE_ATOM);if(is_numeric($time))$time=date(DATE_ATOM,(int)$time);$id=(string)($row['h']??hash('sha256',$player.'|'.$message.'|'.$time));$out[]=['key'=>'kamadan:'.$id,'player'=>$player,'message'=>$message,'posted_at'=>(string)$time,'source'=>'kamadan.gwtoolbox.com'];}return$out;}
function collectMessages(): array {global$config;installSchema();$messages=[];$used='';$error='';try{[$code,$type,$body]=httpGet($config['kamadan_endpoint']);if($code>=200&&$code<300)$messages=normalizeKamadanPayload($body);if(!$messages)throw new RuntimeException('Kamadan endpoint gaf geen herkenbare JSON terug.');$used=$config['kamadan_endpoint'];}catch(Throwable$e){$error=$e->getMessage();}$insert=db()->prepare('INSERT OR IGNORE INTO messages(source,source_key,player,message,trade_type,item,price_amount,price_currency,price_ecto,posted_at,collected_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)');$added=0;$offerCount=0;foreach(array_slice($messages,0,$config['max_messages_per_run'])as$row){$p=parseTrade($row['message']);$insert->execute([$row['source'],$row['key'],$row['player'],$row['message'],$p['type'],$p['item'],$p['amount'],$p['currency'],$p['ecto'],$row['posted_at'],date(DATE_ATOM)]);if($insert->rowCount()){$added++;$id=(int)db()->lastInsertId();$offerCount+=saveOffers($id,$row['message']);}}return['fetched'=>count($messages),'added'=>$added,'offers_added'=>$offerCount,'source'=>$used,'warning'=>$error];}

function parserV2(): \LittyWatch\Parser\ParserEngine {
    static $engine = null;
    if ($engine instanceof \LittyWatch\Parser\ParserEngine) return $engine;
    $catalog = new \LittyWatch\Parser\Catalog(__DIR__ . '/app/Data');
    $engine = new \LittyWatch\Parser\ParserEngine($catalog);
    return $engine;
}
