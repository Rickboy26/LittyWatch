<?php
require __DIR__.'/bootstrap.php';
header('Content-Type: text/html; charset=utf-8');
try {$r=collectMessages();$ok=true;}catch(Throwable $e){$ok=false;$r=['error'=>$e->getMessage()];}
?><!doctype html><html lang="nl"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Collector</title><style>body{font-family:system-ui;background:#111827;color:#e5e7eb;max-width:800px;margin:50px auto;padding:20px}.box{background:#1f2937;padding:25px;border-radius:14px}pre{white-space:pre-wrap;background:#0b1220;padding:15px;border-radius:8px}a{color:#93c5fd}</style><div class="box"><h1>Collector-test</h1><pre><?= h(json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)) ?></pre><p><a href="index.php">Naar dashboard</a></p></div>
