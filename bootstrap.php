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
    $pdo->exec('PRAGMA journal_mode=WAL; PRAGMA busy_timeout=30000; PRAGMA foreign_keys=ON; PRAGMA synchronous=NORMAL;');
    return $pdo;
}

function installSchema(): void {
    \LittyWatch\Knowledge\Schema::install(db());
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
CREATE TABLE IF NOT EXISTS structured_offers (id INTEGER PRIMARY KEY AUTOINCREMENT,message_id INTEGER NOT NULL,trade_type TEXT NOT NULL,item TEXT NOT NULL,item_key TEXT NOT NULL,market_key TEXT NOT NULL,requirement INTEGER,attribute_key TEXT,attribute_name TEXT,is_oldschool INTEGER NOT NULL DEFAULT 0,is_inscribable INTEGER NOT NULL DEFAULT 0,mods_json TEXT NOT NULL DEFAULT '{}',relevant_json TEXT NOT NULL DEFAULT '{}',profile_json TEXT NOT NULL DEFAULT '{}',quantity REAL,price_amount REAL,price_currency TEXT,price_ecto REAL,unit_price_ecto REAL,price_basis TEXT,confidence REAL NOT NULL DEFAULT 0.5,quality_status TEXT NOT NULL DEFAULT 'review',quality_reason TEXT,raw_segment TEXT,parser_version TEXT NOT NULL,parsed_at TEXT NOT NULL,UNIQUE(message_id,trade_type,market_key,price_amount,price_currency,raw_segment),FOREIGN KEY(message_id) REFERENCES messages(id) ON DELETE CASCADE);
CREATE INDEX IF NOT EXISTS idx_structured_market ON structured_offers(market_key);
CREATE INDEX IF NOT EXISTS idx_structured_item ON structured_offers(item_key);
CREATE INDEX IF NOT EXISTS idx_structured_message ON structured_offers(message_id);
CREATE TABLE IF NOT EXISTS parser_reviews (id INTEGER PRIMARY KEY AUTOINCREMENT,structured_offer_id INTEGER NOT NULL UNIQUE,review_status TEXT NOT NULL DEFAULT 'pending',expected_json TEXT,notes TEXT,reviewed_at TEXT,created_at TEXT NOT NULL,updated_at TEXT NOT NULL,FOREIGN KEY(structured_offer_id) REFERENCES structured_offers(id) ON DELETE CASCADE);
CREATE INDEX IF NOT EXISTS idx_parser_reviews_status ON parser_reviews(review_status);
CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT);
SQL);
    ensureColumn('messages','parser_status','TEXT');
    ensureColumn('messages','parser_summary','TEXT');
    ensureColumn('messages','parser_offer_count','INTEGER');
    ensureColumn('messages','raw_payload','TEXT');
    ensureColumn('messages','collector_version','TEXT');
    ensureColumn('structured_offers','exchange_item','TEXT');
    ensureColumn('structured_offers','exchange_item_key','TEXT');
    ensureColumn('structured_offers','exchange_give_quantity','REAL');
    ensureColumn('structured_offers','exchange_receive_quantity','REAL');
    ensureColumn('structured_offers','normalized_market_key','TEXT');
    ensureColumn('structured_offers','lifecycle_status',"TEXT NOT NULL DEFAULT 'active'");
    ensureColumn('structured_offers','superseded_by','INTEGER');
    ensureColumn('structured_offers','lifecycle_updated_at','TEXT');
    ensureColumn('structured_offers','price_quality_status',"TEXT NOT NULL DEFAULT 'trusted'");
    ensureColumn('structured_offers','price_quality_reason','TEXT');
    ensureColumn('structured_offers','price_outlier_score','REAL');
    ensureColumn('structured_offers','price_baseline_ecto','REAL');
    db()->exec('CREATE INDEX IF NOT EXISTS idx_structured_normalized_market ON structured_offers(normalized_market_key)');
    db()->exec('CREATE INDEX IF NOT EXISTS idx_structured_lifecycle ON structured_offers(lifecycle_status)');
    db()->exec('CREATE INDEX IF NOT EXISTS idx_structured_price_quality ON structured_offers(price_quality_status)');
}

function ensureColumn(string $table,string $column,string $type): void {
    $cols=db()->query("PRAGMA table_info($table)")->fetchAll();
    foreach($cols as $col) if(($col['name']??'')===$column) return;
    db()->exec("ALTER TABLE $table ADD COLUMN $column $type");
}

function h(mixed $value): string {
    if ($value === null) return '';
    if (is_bool($value)) $value = $value ? '1' : '0';
    if (is_scalar($value) || $value instanceof Stringable) {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    return '';
}
function norm(string $s): string {
    $s=str_replace('_',' ',$s);
    $s=preg_replace('/\^{2,}/',' | ',$s)??$s;
    $s=preg_replace('/(?<=\D)\^(?=\D)/',' ',$s)??$s;
    return trim(preg_replace('/\s+/u',' ',$s)??$s);
}
function itemKey(string $s): string { return trim(preg_replace('/[^a-z0-9]+/',' ',mb_strtolower($s)) ?? ''); }

/** Phase 3C: present all stored timestamps consistently in Europe/Amsterdam. */
function lw_local_datetime(mixed $value, bool $relative = false): string {
    $raw = trim((string)$value);
    if ($raw === '') return '—';
    try {
        $dt = new DateTimeImmutable($raw);
        $dt = $dt->setTimezone(new DateTimeZone('Europe/Amsterdam'));
        if ($relative) {
            $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Amsterdam'));
            $seconds = $now->getTimestamp() - $dt->getTimestamp();
            if ($seconds >= 0 && $seconds < 3600) return max(1, (int)floor($seconds / 60)).' min geleden';
            if ($seconds >= 3600 && $seconds < 86400) return (int)floor($seconds / 3600).' uur geleden';
        }
        return $dt->format('d-m-Y H:i');
    } catch (Throwable) {
        return $raw;
    }
}

function lw_ecto_per_armbrace(): float {
    static $rate = null;
    if ($rate !== null) return $rate;
    $rate = 25.0;
    $cfgPath = __DIR__.'/config/exchange-rates.php';
    $cfg = is_file($cfgPath) ? require $cfgPath : [];
    $r = $cfg['rates']['ecto_to_armbrace'] ?? null;
    if (is_array($r)) {
        $left=(float)($r['left_amount']??0); $right=(float)($r['right_amount']??0);
        if ($left>0 && $right>0) $rate=$left/$right;
    }
    return $rate;
}

/** Phase 3C: GW1-native market display. Above 500e prefer armbraces. */
function lw_market_price(mixed $ecto, bool $equivalent = true): string {
    if ($ecto === null || $ecto === '') return '—';
    $e = (float)$ecto;
    $abs = abs($e);
    $num = static fn(float $v, int $d=2): string => rtrim(rtrim(number_format($v,$d,',','.'),'0'),',');
    if ($abs >= 500.0) {
        $a = lw_ecto_per_armbrace() > 0 ? $e / lw_ecto_per_armbrace() : 0.0;
        return $num($a).'a'.($equivalent ? ' (~'.$num($e).'e)' : '');
    }
    return $num($e).'e'.($equivalent && $abs >= lw_ecto_per_armbrace()*5 ? ' (~'.$num($e/lw_ecto_per_armbrace()).'a)' : '');
}

/** Phase 3D: item-aware display. Armbrace of Truth is itself the exchange unit,
 * so pricing it primarily in armbraces is circular; keep ecto primary. */
function lw_market_price_for_item(string $item, mixed $ecto, bool $equivalent = true): string {
    if (mb_strtolower(trim($item)) === 'armbrace of truth') {
        if ($ecto === null || $ecto === '') return '—';
        $e=(float)$ecto;
        $num=static fn(float $v,int $d=2):string=>rtrim(rtrim(number_format($v,$d,',','.'),'0'),',');
        return $num($e).'e';
    }
    return lw_market_price($ecto,$equivalent);
}

/**
 * Compatibility adapter for older diagnostics/tests.
 * Parsing has one source of truth: ParserEngine.
 * @return list<array<string,mixed>>
 */
function parseOffers(string $message): array {
    $out=[];
    foreach(parserV2()->parse($message) as $offer){
        $price=$offer->price;
        $out[]=[
            'type'=>$offer->tradeType,
            'item'=>$offer->item,
            'item_key'=>$offer->itemKey,
            'details'=>'',
            'quantity'=>$price->quantity,
            'amount'=>$price->amount,
            'currency'=>$price->currency,
            'ecto'=>$price->ectoValue,
            'unit_ecto'=>$price->unitEcto,
            'confidence'=>$offer->confidence,
            'basis'=>$price->basis,
            'segment'=>$offer->segment,
            'quality_status'=>$offer->status,
            'quality_reason'=>$offer->reason,
            'exchange_item'=>$offer->exchange['target_item']??null,
            'exchange_item_key'=>$offer->exchange['target_item_key']??null,
            'exchange_give_quantity'=>$offer->exchange['give_quantity']??null,
            'exchange_receive_quantity'=>$offer->exchange['receive_quantity']??null,
        ];
    }
    return $out;
}

function parseTrade(string $message): array {
    $first=parseOffers($message)[0]??null;
    return [
        'type'=>$first['type']??null,
        'item'=>$first['item']??null,
        'amount'=>$first['amount']??null,
        'currency'=>$first['currency']??null,
        'ecto'=>$first['ecto']??null,
    ];
}

function httpGet(string $url): array {global$config;$headers=['User-Agent: LittyWatch/0.5 (+personal project)'];$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>$config['request_timeout'],CURLOPT_HTTPHEADER=>$headers,CURLOPT_ENCODING=>'']);$body=curl_exec($ch);$err=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$type=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE);curl_close($ch);if($body===false)throw new RuntimeException('cURL-fout: '.$err);return[$code,$type,$body];}
function saneMessageTimestamp(mixed $value, ?string $fallback=null): string {$fallback=$fallback?:date(DATE_ATOM);if(is_numeric($value)){$timestamp=(float)$value;while($timestamp>20000000000){$timestamp/=1000;}if($timestamp>946684800&&$timestamp<4102444800)return date(DATE_ATOM,(int)$timestamp);return$fallback;}$raw=trim((string)$value);if($raw==='')return$fallback;if(preg_match('/^(\d{4,})-/', $raw,$m)){ $year=(int)$m[1];$maxYear=(int)date('Y')+2;if($year<2000||$year>$maxYear)return$fallback; }$ts=strtotime($raw);if($ts===false||$ts<946684800||$ts>4102444800)return$fallback;return date(DATE_ATOM,$ts);}
function normalizeKamadanPayload(string $body): array {
    $json=json_decode($body,true);if(!is_array($json))return[];
    $rows=$json['messages']??$json['results']??$json;if(!is_array($rows))return[];
    $out=[];
    foreach($rows as $row){
        if(!is_array($row))continue;
        $message=(string)($row['m']??$row['message']??'');if($message==='')continue;
        $player=(string)($row['s']??$row['player']??'Unknown');
        $time=saneMessageTimestamp($row['t']??null);
        $id=(string)($row['h']??hash('sha256',$player.'|'.$message.'|'.$time));
        $raw=json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $out[]=['key'=>'kamadan:'.$id,'player'=>$player,'message'=>$message,'posted_at'=>$time,'source'=>'kamadan.gwtoolbox.com','raw_payload'=>$raw===false?null:$raw];
    }
    return$out;
}

/** Phase 3M.1: one idempotent collector pass. Safe to call from cron every minute. */
function collectMessages(): array {
    global$config;installSchema();$messages=[];$used='';$error='';$body='';
    try{
        [$code,$type,$body]=httpGet($config['kamadan_endpoint']);
        if($code<200||$code>=300)throw new RuntimeException('Kamadan HTTP '.$code);
        $messages=normalizeKamadanPayload($body);
        if(!$messages)throw new RuntimeException('Kamadan endpoint gaf geen herkenbare JSON terug.');
        $used=$config['kamadan_endpoint'];
    }catch(Throwable$e){$error=$e->getMessage();}

    $insert=db()->prepare('INSERT OR IGNORE INTO messages(source,source_key,player,message,trade_type,item,price_amount,price_currency,price_ecto,posted_at,collected_at,raw_payload,collector_version) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $added=0;$offerCount=0;
    foreach(array_slice($messages,0,$config['max_messages_per_run'])as$row){
        $p=parseTrade($row['message']);
        $insert->execute([$row['source'],$row['key'],$row['player'],$row['message'],$p['type'],$p['item'],$p['amount'],$p['currency'],$p['ecto'],$row['posted_at'],date(DATE_ATOM),$row['raw_payload']??null,'v5.2-structured']);
        if($insert->rowCount()){
            $added++;$id=(int)db()->lastInsertId();
            try {
                $created=(new \LittyWatch\Market\StructuredOfferWriter(
                    db(), parserV2(), new \LittyWatch\Market\VariantNormalizer(),
                    new \LittyWatch\Market\OfferLifecycleService(db())
                ))->parseMessage($id,$row['message'],true);
                $offerCount += $created;
                $status=$created>0?'parsed':'review';
                $summary=$created>0?($created.' aanbieding'.($created===1?'':'en').' herkend'):'Niet betrouwbaar herkend · controle nodig';
                db()->prepare('UPDATE messages SET parser_status=?,parser_summary=?,parser_offer_count=? WHERE id=?')->execute([$status,$summary,$created,$id]);
            } catch (Throwable $shadowError) {
                // LITTYWATCH_PHASE7D4_COLLECTOR_LIFECYCLE_RETRY
                // Never silently leave a freshly inserted accepted offer active
                // without lifecycle reconciliation. The targeted lifecycle pass
                // has its own SQLite busy retries; one final repair attempt here
                // makes collector failures visible instead of accumulating dupes.
                try {
                    (new \LittyWatch\Market\OfferLifecycleService(db()))->rebuild($id);
                } catch (Throwable $repairError) {
                    $msg = 'Parser v2/lifecycle write failed: '.$shadowError->getMessage().' | repair: '.$repairError->getMessage();
                    error_log($msg);
                    $error = trim($error === '' ? $msg : $error.'; '.$msg);
                }
            }
        }
    }
    // LITTYWATCH_PHASE7D6_UNCONDITIONAL_BATCH_DUPLICATE_HEAL
    // Run the idempotent active-dedup sweep on every collector pass, even when
    // Kamadan returned no newly inserted messages. This lets cron self-heal any
    // duplicates left by an earlier interrupted lifecycle call or another write
    // path instead of waiting for a future batch with $added > 0.
    try {
        (new \LittyWatch\Market\OfferLifecycleService(db()))->healActiveDuplicates();
    } catch (Throwable $healError) {
        $msg = 'Active duplicate heal failed: '.$healError->getMessage();
        error_log($msg);
        $error = trim($error === '' ? $msg : $error.'; '.$msg);
    }

    // Phase 6D: active offers expire automatically after the configured age,
    // even on collector passes where Kamadan returned no new rows.
    try {
        (new \LittyWatch\Market\OfferLifecycleService(db()))->expireStaleOffers();
    } catch (Throwable $expiryError) {
        error_log('Offer expiry failed: '.$expiryError->getMessage());
    }

    try {
        (new \LittyWatch\Market\MarketDataRetentionService(db()))->pruneIfDue();
    } catch (Throwable $retentionError) {
        error_log('Market retention failed: '.$retentionError->getMessage());
    }
    return['fetched'=>count($messages),'added'=>$added,'offers_added'=>$offerCount,'source'=>$used,'warning'=>$error,'collector_version'=>'v5.2-structured'];
}

function parserV2(): \LittyWatch\Parser\ParserEngine {
    static $engine = null;
    if ($engine instanceof \LittyWatch\Parser\ParserEngine) return $engine;
    $catalog = new \LittyWatch\Parser\Catalog(__DIR__ . '/app/Data', db());
    $engine = new \LittyWatch\Parser\ParserEngine($catalog);
    return $engine;
}
