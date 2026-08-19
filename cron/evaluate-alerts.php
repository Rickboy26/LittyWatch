<?php
declare(strict_types=1);

use LittyWatch\Repositories\AlertRepository;
use LittyWatch\Services\AlertService;
use LittyWatch\Support\RuntimeStatus;

if(PHP_SAPI!=='cli'){http_response_code(403);exit("Alleen via CLI/cron.\n");}
$root=dirname(__DIR__);
require_once $root.'/bootstrap.php';
try{$result=(new AlertService(new AlertRepository(db())))->evaluate();RuntimeStatus::write($root,'alerts',['ok'=>true,'message'=>'Alertcontrole voltooid']+$result);echo json_encode(['ok'=>true,'evaluated_at'=>date(DATE_ATOM)]+$result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;}catch(Throwable $e){RuntimeStatus::write($root,'alerts',['ok'=>false,'message'=>$e->getMessage()]);fwrite(STDERR,json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE).PHP_EOL);exit(1);}
