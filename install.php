<?php
require __DIR__.'/bootstrap.php';
try { installSchema(); $ok=true; $msg='Installatie gelukt. SQLite-database en tabellen zijn aangemaakt.'; }
catch(Throwable $e){$ok=false;$msg=$e->getMessage();}
?><!doctype html><html lang="nl"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Installatie</title><style>body{font-family:system-ui;background:#111827;color:#e5e7eb;max-width:800px;margin:50px auto;padding:20px}.box{background:#1f2937;padding:25px;border-radius:14px}.ok{color:#6ee7b7}.bad{color:#fca5a5}a{color:#93c5fd}</style><div class="box"><h1>GW1 Market Scanner</h1><p class="<?= $ok?'ok':'bad' ?>"><?= h($msg) ?></p><?php if($ok):?><p><a href="collect.php">Nu testdata ophalen</a> · <a href="index.php">Dashboard openen</a></p><?php else:?><p>Controleer of <code>pdo_sqlite</code> actief is en de map <code>data/</code> schrijfbaar is.</p><?php endif;?></div>
