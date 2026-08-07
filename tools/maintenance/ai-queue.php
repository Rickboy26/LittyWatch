<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { fwrite(STDERR,"Alleen via CLI.\n"); exit(1); }
$root=dirname(__DIR__,2);require $root.'/bootstrap.php';installSchema();
use LittyWatch\AI\AiValidationRepository;
$ai=require $root.'/config/ai.php';$repo=new AiValidationRepository(db());$n=$repo->syncAll((string)$ai['mode']);
fwrite(STDOUT,"AI queue bijgewerkt: {$n} geselecteerd (mode={$ai['mode']}).\n");fwrite(STDOUT,json_encode($repo->summary(),JSON_UNESCAPED_SLASHES)."\n");
