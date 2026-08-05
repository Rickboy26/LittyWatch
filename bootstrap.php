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
    if (!extension_loaded('pdo_sqlite')) {
        throw new RuntimeException('PHP-extensie pdo_sqlite ontbreekt. Installeer/activeer php-sqlite3.');
    }
    $dir = dirname($config['db_path']);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Kan datamap niet aanmaken: ' . $dir);
    }
    $pdo = new PDO('sqlite:' . $config['db_path'], null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode=WAL;');
    $pdo->exec('PRAGMA busy_timeout=5000;');
    return $pdo;
}

function installSchema(): void {
    $sql = <<<'SQL'
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
CREATE INDEX IF NOT EXISTS idx_messages_item ON messages(item);
CREATE INDEX IF NOT EXISTS idx_messages_type ON messages(trade_type);
CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT);
SQL;
    db()->exec($sql);
}

function h(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }

function parseTrade(string $message): array {
    $clean = trim(preg_replace('/\s+/', ' ', $message) ?? $message);
    $lower = mb_strtolower($clean);
    $type = null;
    if (preg_match('/\bwtb\b|\bbuying\b/', $lower)) $type = 'buy';
    elseif (preg_match('/\bwts\b|\bselling\b/', $lower)) $type = 'sell';
    elseif (preg_match('/\bwtt\b/', $lower)) $type = 'trade';

    $currency = null; $amount = null; $ecto = null;
    if (preg_match('/(?:^|[\s\-\/|])([0-9]+(?:[.,][0-9]+)?)\s*(a|armbrace(?:s)?|e|ecto(?:s)?|k|plat(?:inum)?)(?:\b|\/)/i', $clean, $m)) {
        $amount = (float)str_replace(',', '.', $m[1]);
        $unit = strtolower($m[2]);
        if ($unit[0] === 'a') { $currency = 'a'; $ecto = $amount * 27.0; }
        elseif ($unit[0] === 'e') { $currency = 'e'; $ecto = $amount; }
        else { $currency = 'k'; $ecto = $amount / 15.0; }
    }

    $aliases = [
        'Unded MKG' => ['unded mkg','unded mad king','unded mad king\'s guard','mkg unded'],
        'MKG' => ['mkg','mad king\'s guard','mad king guard'],
        'Armbrace' => ['armbrace','armbraces','arms'],
        'Ectoplasm' => ['ecto','ectos','glob of ectoplasm'],
        'Zaishen Key' => ['zkey','zkeys','zaishen key','zaishen keys'],
        'Gift of the Traveler' => ['gott','gift of the traveler','gifts of the traveler','nick gift'],
        'Rift Warden (unded)' => ['unded rift warden','rift warden unded'],
        'Miniature Panda' => ['mini panda','unded panda','panda unded'],
        'Kanaxai' => ['kanaxai'],
        'Bone Dragon Staff' => ['bds','bone dragon staff'],
        'Eternal Blade' => ['eternal blade','eblade'],
        'Obsidian Edge' => ['obsidian edge'],
    ];
    $item = null;
    foreach ($aliases as $canonical => $terms) {
        foreach ($terms as $term) {
            if (str_contains($lower, $term)) { $item = $canonical; break 2; }
        }
    }
    if ($item === null && $type !== null) {
        $tmp = preg_replace('/\b(wtb|wts|wtt|buying|selling)\b/i', '', $clean);
        $tmp = preg_replace('/\b[0-9]+(?:[.,][0-9]+)?\s*(a|armbraces?|e|ectos?|k|plat(?:inum)?)\b/i', '', $tmp ?? '');
        $tmp = trim(preg_split('/[|\/]/', $tmp ?? '')[0] ?? '');
        if ($tmp !== '') $item = mb_substr($tmp, 0, 80);
    }
    return compact('type','item','amount','currency','ecto');
}

function httpGet(string $url): array {
    global $config;
    $headers = ['User-Agent: GW1MarketScanner/0.1 (+personal project)'];
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>$config['request_timeout'],CURLOPT_HTTPHEADER=>$headers,CURLOPT_ENCODING=>'']);
        $body = curl_exec($ch); $err = curl_error($ch); $code = (int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE); $type=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE); curl_close($ch);
        if ($body === false) throw new RuntimeException('cURL-fout: '.$err);
        return [$code,$type,$body];
    }
    $ctx = stream_context_create(['http'=>['timeout'=>$config['request_timeout'],'header'=>implode("\r\n",$headers)]]);
    $body = @file_get_contents($url,false,$ctx);
    if ($body === false) throw new RuntimeException('HTTP-ophalen mislukt. Activeer cURL of allow_url_fopen.');
    return [200,'',$body];
}

function normalizeKamadanPayload(string $body): array {
    $json = json_decode($body, true);
    if (!is_array($json)) return [];
    $rows = $json['messages'] ?? $json['results'] ?? $json;
    if (!is_array($rows)) return [];
    $out=[];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $message=(string)($row['message']??$row['text']??$row['msg']??'');
        if ($message==='') continue;
        $player=(string)($row['name']??$row['player']??$row['sender']??'Unknown');
        $time=$row['timestamp']??$row['time']??$row['created_at']??date(DATE_ATOM);
        if (is_numeric($time)) $time=date(DATE_ATOM,(int)$time);
        $id=(string)($row['id']??hash('sha256',$player.'|'.$message.'|'.$time));
        $out[]=['key'=>'kamadan:'.$id,'player'=>$player,'message'=>$message,'posted_at'=>(string)$time,'source'=>'kamadan.gwtoolbox.com'];
    }
    return $out;
}

function normalizeDecltypeHtml(string $html): array {
    libxml_use_internal_errors(true);
    $dom=new DOMDocument();
    if (!$dom->loadHTML($html)) return [];
    $xp=new DOMXPath($dom); $out=[];
    $nodes=$xp->query('//tr|//article|//li');
    foreach ($nodes as $node) {
        $text=trim(preg_replace('/\s+/', ' ', $node->textContent) ?? '');
        if (!preg_match('/\bWT[BS]\b/i',$text)) continue;
        $parts=preg_split('/\s+\|\s+/', $text);
        if (count($parts)>=2) { $player=trim($parts[0]); $message=trim($parts[1]); }
        else { $player='Unknown'; $message=$text; }
        $key='decltype:'.hash('sha256',$text);
        $out[]=['key'=>$key,'player'=>$player,'message'=>$message,'posted_at'=>date(DATE_ATOM),'source'=>'kamadan.decltype.org'];
        if (count($out)>=250) break;
    }
    return $out;
}

function collectMessages(): array {
    global $config;
    installSchema(); $messages=[]; $used=''; $error='';
    try {
        [$code,$type,$body]=httpGet($config['kamadan_endpoint']);
        if ($code>=200 && $code<300) $messages=normalizeKamadanPayload($body);
        if (!$messages) throw new RuntimeException('Kamadan endpoint gaf geen herkenbare JSON terug.');
        $used=$config['kamadan_endpoint'];
    } catch (Throwable $e) {
        $error=$e->getMessage();
        try {
            [$code,$type,$body]=httpGet($config['fallback_endpoint']);
            $messages=normalizeDecltypeHtml($body); $used=$config['fallback_endpoint'];
        } catch (Throwable $e2) { $error .= ' | Fallback: '.$e2->getMessage(); }
    }
    $insert=db()->prepare('INSERT OR IGNORE INTO messages(source,source_key,player,message,trade_type,item,price_amount,price_currency,price_ecto,posted_at,collected_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
    $added=0;
    foreach (array_slice($messages,0,$config['max_messages_per_run']) as $row) {
        $p=parseTrade($row['message']);
        $insert->execute([$row['source'],$row['key'],$row['player'],$row['message'],$p['type'],$p['item'],$p['amount'],$p['currency'],$p['ecto'],$row['posted_at'],date(DATE_ATOM)]);
        $added += $insert->rowCount();
    }
    return ['fetched'=>count($messages),'added'=>$added,'source'=>$used,'warning'=>$error];
}
