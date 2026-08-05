<?php
declare(strict_types=1);
$config = require __DIR__ . '/config.php';
date_default_timezone_set($config['timezone']);
ini_set('display_errors', '1');
error_reporting(E_ALL);

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
 UNIQUE(message_id, trade_type, item_key, details, price_amount, price_currency),
 FOREIGN KEY(message_id) REFERENCES messages(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_offers_item ON offers(item_key);
CREATE INDEX IF NOT EXISTS idx_offers_type ON offers(trade_type);
CREATE INDEX IF NOT EXISTS idx_offers_price ON offers(unit_price_ecto);
CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT);
SQL);
}

function h(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
function norm(string $s): string { return trim(preg_replace('/\s+/u',' ',str_replace(['_','^'], ' ', $s)) ?? $s); }
function itemKey(string $s): string { return trim(preg_replace('/[^a-z0-9]+/',' ',mb_strtolower($s)) ?? ''); }

function itemCatalog(): array {
    return [
        'Gift of the Traveler'=>['gott','gift of the traveler','gifts of the traveler'],
        'Nicholas Set'=>['nick set','nickset'], 'Armbrace of Truth'=>['armbrace','armbraces','ambrace','ambraces','ambr'],
        'Glob of Ectoplasm'=>['ecto','ectos'], 'Zaishen Key'=>['zkey','zkeys','zaishen key','zaishen keys'],
        'Lockpick'=>['lockpick','lockpicks','picks'], 'Conset'=>['conset','consets'], 'Essence of Celerity'=>['essence of celerity','essences'],
        'Grail of Might'=>['grail of might','grails'], 'Armor of Salvation'=>['armor of salvation','armors of salvation'],
        'Cupcake'=>['cupcake','cupcakes'], "Stalker's Ration"=>["stalker's ration",'stalkers ration'], 'Black Dye'=>['black dye'],
        'Elite Tome'=>['elite tome','elite tomes'], 'Warrior Tome'=>['warri tome','warrior tome','warri tomes','warrior tomes'],
        'Ranger Tome'=>['ranger tome','ranger tomes'], 'Unidentified Gold'=>['unid gold','unid golds','unids','unid. golds'],
        'Bone Dragon Staff'=>['bone dragon staff','bds'], 'Eternal Blade'=>['eternal blade','eternalblade','eblade'],
        'Obsidian Edge'=>['obsidian edge','obsiedge'], 'Voltaic Spear'=>['voltaic spear'], 'Chaos Axe'=>['chaos axe'],
        'Colossal Scimitar'=>['colossal scimitar'], 'Eternal Bow'=>['eternal flatbow','eternal rec bow','eternal recurve bow'],
        'Eternal Shield'=>['eternal shield','eternal shields'], 'Rift Warden'=>['rift warden'], 'Mad King’s Guard'=>['mkg','mad king guard',"mad king's guard"],
        'Ghostly Hero'=>['ghostly hero'], 'Mallyx'=>['mallyx'], 'Miniature Undead Prince Rurik'=>['mini undead prince','undead prince'],
        'Celestial Horse'=>['cele horse','celestial horse'], 'Rin Relic Set'=>['rin set'], 'Raging Menzies'=>['raging menzies'],
        'Summoning Stone'=>['summon stone','summon stones','summoning stone'], 'Cracked Ascalonian War Horn'=>['cracked ascalonian war horn'],
        'Ruby'=>['ruby','rubies'], 'Sapphire'=>['sapphire','sapphires'], 'Char Carving'=>['char carving','char carvings'],
        'Diessa Chalice'=>['diessa chalice','diessa chalices'], 'War Supplies'=>['war supp','war supplies'],
        'Alcohol Points'=>['drunk points','alcohol points'], 'Mystical Summoning Stone (Gaki)'=>['mystical summon stone gaki','mystical summoning stone gaki'],
        'Mysterious Armor'=>['mysterious armor'], 'Envoy Staff'=>['envoy staff'], 'Padraic'=>['padraic'], 'Kerrsh’s Staff'=>['kerrsh staff'],
        'Hero Box'=>['herobox','hero box'], 'Gold Zaishen Coin'=>['gold zcoin','gold zaishen coin'], 'Tengu Support Flare'=>['tengu','tengus'],
        'Seal of the Dragon Empire'=>['guards-seals','guard seals','seals'], 'Soup'=>['soup'], 'Elixir of Valor'=>['elixir of valor','elixirs of valor'],
    ];
}

function detectType(string $text): ?string {
    if (preg_match('/(?:^|\W)wtb(?:\W|$)|\bbuying\b/i',$text)) return 'buy';
    if (preg_match('/(?:^|\W)wts(?:\W|$)|\bselling\b/i',$text)) return 'sell';
    if (preg_match('/(?:^|\W)wtt(?:\W|$)/i',$text)) return 'trade';
    return null;
}

function currencyToEcto(float $amount, string $currency): float {
    return match($currency) { 'a'=>$amount*27.0, 'e'=>$amount, 'k'=>$amount/15.0, default=>$amount };
}

function extractPrice(string $segment): ?array {
    $patterns = [
        '/(?<![a-z0-9])([0-9]+(?:[.,][0-9]+)?)\s*(a|ambr(?:ace)?s?|armbraces?|e|ectos?|k|plat(?:inum)?)(?:\b|\/|$)/i',
        '/(?<![a-z0-9])([0-9]+(?:[.,][0-9]+)?)\s*:\s*1e\b/i',
    ];
    foreach ($patterns as $i=>$pattern) if (preg_match($pattern,$segment,$m,PREG_OFFSET_CAPTURE)) {
        $amount=(float)str_replace(',','.',$m[1][0]);
        if ($i===1) return ['amount'=>1.0,'currency'=>'e','ecto'=>1.0,'offset'=>$m[0][1],'raw'=>$m[0][0],'ratio'=>(float)$m[1][0]];
        $u=mb_strtolower($m[2][0]); $currency=str_starts_with($u,'a')?'a':(str_starts_with($u,'e')?'e':'k');
        return ['amount'=>$amount,'currency'=>$currency,'ecto'=>currencyToEcto($amount,$currency),'offset'=>$m[0][1],'raw'=>$m[0][0],'ratio'=>null];
    }
    return null;
}

function detectQuantity(string $segment, ?array $price): ?float {
    if (preg_match('/\b(?:stack|stk|st)\b/i',$segment)) return 250.0;
    if (preg_match('/\[x\s*([0-9]+)\]/i',$segment,$m)) return (float)$m[1];
    if (preg_match('/\b([0-9]+)\s+(?:gott|gifts?|tomes?|unids?|rubies|sapphires|char carvings?|diessa chalices?)\b/i',$segment,$m)) return (float)$m[1];
    if ($price && $price['ratio']) return $price['ratio'];
    return null;
}

function canonicalItem(string $segment): array {
    $clean=norm($segment); $lower=mb_strtolower($clean);
    $best=null; $bestLen=0;
    foreach(itemCatalog() as $name=>$aliases) foreach($aliases as $alias) {
        if (str_contains($lower,mb_strtolower($alias)) && mb_strlen($alias)>$bestLen) { $best=$name; $bestLen=mb_strlen($alias); }
    }
    $details=[];
    if (preg_match('/\bq\s*([0-9]{1,2})\b/i',$clean,$m)) $details[]='q'.$m[1];
    if (preg_match('/\b(unded|ded)\b/i',$clean,$m)) $details[]=mb_strtolower($m[1]);
    if (preg_match('/\b(os|oldschool|old school)\b/i',$clean)) $details[]='OS';
    if (preg_match('/\b(insc|inscb|inscribable)\b/i',$clean)) $details[]='insc';
    if ($best!==null) return [$best,implode(' ',array_unique($details)),0.95];

    $fallback=preg_replace('/\b(wtb|wts|wtt|buying|selling)\b/i','',$clean);
    $fallback=preg_replace('/(?<![a-z0-9])[0-9]+(?:[.,][0-9]+)?\s*(?:a|ambr(?:ace)?s?|armbraces?|e|ectos?|k|plat(?:inum)?)(?:\b|\/|$)/i','',$fallback??'');
    $fallback=trim(preg_replace('/\b(pm|offer|offers|each|ea|per|stack|stk|st)\b.*$/i','',$fallback??'')??'');
    $fallback=trim($fallback," \t\n\r\0\x0B-:;,/|<>");
    return [$fallback!==''?mb_substr($fallback,0,100):'Onbekend',implode(' ',array_unique($details)),0.45];
}

function splitTradeSegments(string $message): array {
    $s=norm($message);
    $s=preg_replace('/\bWT([BST])\b/i','|WT$1 ',$s)??$s;
    $parts=preg_split('/\s*[|;]\s*|\s+\/\s+(?=[A-Za-z0-9+])/u',$s)?:[];
    $out=[]; $currentType=detectType($s);
    foreach($parts as $part){
        $part=trim($part," |\t\n\r"); if($part==='')continue;
        $t=detectType($part)??$currentType; if($t!==null)$currentType=$t;
        $part=preg_replace('/^WT[BST]\s*/i','',$part)??$part;
        // comma-separated offers are split only when each side appears to carry its own price/item.
        $sub=preg_split('/\s*,\s*(?=(?:q\d+|[A-Za-z][A-Za-z +\'’-]{2,})\s+[0-9]+(?:[.,][0-9]+)?\s*(?:a|e|k)\b)/iu',$part)?:[$part];
        foreach($sub as $x) if(trim($x)!=='') $out[]=['type'=>$t,'text'=>trim($x)];
    }
    return $out;
}

function parseOffers(string $message): array {
    $offers=[];
    foreach(splitTradeSegments($message) as $seg){
        if(!$seg['type']) continue;
        $text=$seg['text']; $price=extractPrice($text); [$item,$details,$confidence]=canonicalItem($text);
        if($item==='Onbekend' && !$price) continue;
        $quantity=detectQuantity($text,$price);
        $unit=$price['ecto']??null;
        if($unit!==null && $quantity && $quantity>1 && !preg_match('/\b(?:per|each|ea)\b/i',$text)) $unit/=$quantity;
        $offers[]=['type'=>$seg['type'],'item'=>$item,'item_key'=>itemKey($item.($details?' '.$details:'')),'details'=>$details,'quantity'=>$quantity,
            'amount'=>$price['amount']??null,'currency'=>$price['currency']??null,'ecto'=>$price['ecto']??null,'unit_ecto'=>$unit,'confidence'=>$confidence,'segment'=>$text];
    }
    return $offers;
}

function parseTrade(string $message): array {
    $offers=parseOffers($message); $first=$offers[0]??null;
    return ['type'=>$first['type']??detectType($message),'item'=>$first['item']??null,'amount'=>$first['amount']??null,'currency'=>$first['currency']??null,'ecto'=>$first['ecto']??null];
}

function saveOffers(int $messageId,string $message): int {
    $del=db()->prepare('DELETE FROM offers WHERE message_id=?'); $del->execute([$messageId]);
    $ins=db()->prepare('INSERT OR IGNORE INTO offers(message_id,trade_type,item,item_key,details,quantity,price_amount,price_currency,price_ecto,unit_price_ecto,confidence,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
    $n=0; foreach(parseOffers($message) as $o){$ins->execute([$messageId,$o['type'],$o['item'],$o['item_key'],$o['details'],$o['quantity'],$o['amount'],$o['currency'],$o['ecto'],$o['unit_ecto'],$o['confidence'],date(DATE_ATOM)]);$n+=$ins->rowCount();} return $n;
}

function httpGet(string $url): array { global $config; $headers=['User-Agent: LittyWatch/0.3 (+personal project)']; $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>$config['request_timeout'],CURLOPT_HTTPHEADER=>$headers,CURLOPT_ENCODING=>'']); $body=curl_exec($ch);$err=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$type=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE);curl_close($ch);if($body===false)throw new RuntimeException('cURL-fout: '.$err);return[$code,$type,$body]; }
function normalizeKamadanPayload(string $body): array { $json=json_decode($body,true);if(!is_array($json))return[];$rows=$json['messages']??$json['results']??$json;if(!is_array($rows))return[];$out=[];foreach($rows as $row){if(!is_array($row))continue;$message=(string)($row['m']??$row['message']??'');if($message==='')continue;$player=(string)($row['s']??$row['player']??'Unknown');$time=$row['t']??date(DATE_ATOM);if(is_numeric($time))$time=date(DATE_ATOM,(int)$time);$id=(string)($row['h']??hash('sha256',$player.'|'.$message.'|'.$time));$out[]=['key'=>'kamadan:'.$id,'player'=>$player,'message'=>$message,'posted_at'=>(string)$time,'source'=>'kamadan.gwtoolbox.com'];}return$out; }
function normalizeDecltypeHtml(string $html): array { return []; }

function collectMessages(): array {
    global $config; installSchema();$messages=[];$used='';$error='';
    try{[$code,$type,$body]=httpGet($config['kamadan_endpoint']);if($code>=200&&$code<300)$messages=normalizeKamadanPayload($body);if(!$messages)throw new RuntimeException('Kamadan endpoint gaf geen herkenbare JSON terug.');$used=$config['kamadan_endpoint'];}
    catch(Throwable $e){$error=$e->getMessage();}
    $insert=db()->prepare('INSERT OR IGNORE INTO messages(source,source_key,player,message,trade_type,item,price_amount,price_currency,price_ecto,posted_at,collected_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
    $added=0;$offerCount=0;
    foreach(array_slice($messages,0,$config['max_messages_per_run']) as $row){$p=parseTrade($row['message']);$insert->execute([$row['source'],$row['key'],$row['player'],$row['message'],$p['type'],$p['item'],$p['amount'],$p['currency'],$p['ecto'],$row['posted_at'],date(DATE_ATOM)]);if($insert->rowCount()){ $added++;$id=(int)db()->lastInsertId();$offerCount+=saveOffers($id,$row['message']); }}
    return ['fetched'=>count($messages),'added'=>$added,'offers_added'=>$offerCount,'source'=>$used,'warning'=>$error];
}
