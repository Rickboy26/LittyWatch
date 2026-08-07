<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/bootstrap.php';

if (PHP_SAPI !== 'cli') { fwrite(STDERR,"Alleen CLI.\n"); exit(2); }

$options=getopt('', ['loop','interval::','retries::','quiet']);
$loop=array_key_exists('loop',$options);
$interval=max(30,(int)($options['interval']??60));
$retries=max(1,min(5,(int)($options['retries']??3)));
$quiet=array_key_exists('quiet',$options);

$storage=dirname(__DIR__,2).'/storage';
$logDir=$storage.'/logs';
if(!is_dir($logDir)) @mkdir($logDir,0775,true);
$lockPath=$storage.'/kamadan-collector.lock';
$statusPath=$storage.'/kamadan-collector-status.json';
$lock=fopen($lockPath,'c+');
if(!$lock){fwrite(STDERR,"Kan collector-lock niet openen: $lockPath\n");exit(2);}
if(!flock($lock,LOCK_EX|LOCK_NB)){
    if(!$quiet)fwrite(STDOUT,"Collector draait al; deze run wordt overgeslagen.\n");
    exit(0);
}

$print=function(array $r,int $attempt=1)use($quiet,$statusPath):void{
    $now=date(DATE_ATOM);
    $ok=empty($r['warning']);
    $line=sprintf('[%s] %s attempt=%d fetched=%d added=%d offers=%d%s',
        $now,$ok?'OK':'ERROR',$attempt,(int)($r['fetched']??0),(int)($r['added']??0),(int)($r['offers_added']??0),
        $ok?'':' warning='.str_replace(["\r","\n"],' ',(string)$r['warning'])
    );
    if(!$quiet)fwrite(STDOUT,$line."\n");
    @file_put_contents($statusPath,json_encode([
        'last_run'=>$now,'ok'=>$ok,'attempt'=>$attempt,'fetched'=>(int)($r['fetched']??0),
        'added'=>(int)($r['added']??0),'offers_added'=>(int)($r['offers_added']??0),
        'source'=>$r['source']??null,'warning'=>$r['warning']??null,'collector_version'=>'phase3m1'
    ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
};

$run=function()use($retries,$print):bool{
    for($attempt=1;$attempt<=$retries;$attempt++){
        try{$r=collectMessages();}
        catch(Throwable $e){$r=['fetched'=>0,'added'=>0,'offers_added'=>0,'source'=>'','warning'=>$e->getMessage()];}
        $print($r,$attempt);
        if(empty($r['warning']))return true;
        if($attempt<$retries)sleep(min(15,2**$attempt));
    }
    return false;
};

do{
    $ok=$run();
    if(!$loop)break;
    sleep($interval);
}while(true);

flock($lock,LOCK_UN);fclose($lock);
exit(($ok??false)?0:1);
