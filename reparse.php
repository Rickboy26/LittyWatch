<?php
require __DIR__.'/bootstrap.php';
installSchema();
$rows=db()->query('SELECT id,message FROM messages ORDER BY id')->fetchAll();
$count=0;
foreach($rows as $row){$count+=saveOffers((int)$row['id'],$row['message']);}
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['messages_reparsed'=>count($rows),'offers_created'=>$count],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
