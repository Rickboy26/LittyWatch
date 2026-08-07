<?php
declare(strict_types=1);

/**
 * LittyWatch Phase 4A AI validator.
 *
 * Queue + validate risky offers (default):
 *   php tools/maintenance/ai-validate.php --limit=25
 *
 * Queue all offers:
 *   LITTYWATCH_AI_MODE=all php tools/maintenance/ai-validate.php --sync --limit=25
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR,"Alleen via CLI.\n"); exit(1); }
@set_time_limit(0); @ini_set('max_execution_time','0');
$root=dirname(__DIR__,2); require $root.'/bootstrap.php'; installSchema();

use LittyWatch\AI\AiMarketValidator;
use LittyWatch\AI\AiValidationRepository;
use LittyWatch\AI\OpenAiResponsesClient;

$ai=require $root.'/config/ai.php';
$limit=25;$sync=false;
foreach(array_slice($argv,1) as $arg){if($arg==='--sync')$sync=true;elseif(str_starts_with($arg,'--limit='))$limit=max(1,min(500,(int)substr($arg,8)));}
$repo=new AiValidationRepository(db());
if($sync){$queued=$repo->syncAll((string)$ai['mode']);fwrite(STDOUT,"AI queue gesynchroniseerd: {$queued} geselecteerd (mode={$ai['mode']}).\n");}
if(!(bool)$ai['enabled']){fwrite(STDERR,"AI is niet actief. Zet OPENAI_API_KEY en LITTYWATCH_AI_MODE=risky of all.\n");fwrite(STDOUT,'Queue: '.json_encode($repo->summary(),JSON_UNESCAPED_SLASHES)."\n");exit(2);}
$validator=new AiMarketValidator(new OpenAiResponsesClient($ai),$ai);$rows=$repo->pending($limit);$done=0;$errors=0;
foreach($rows as $row){$id=(int)$row['id'];$offerId=(int)$row['structured_offer_id'];try{$context=$repo->context($offerId);if(!$context)throw new RuntimeException('Structured offer niet gevonden.');$out=$validator->validate($context);$repo->saveResult($id,$out['result'],(string)$ai['model'],$out['response_id'],$out['raw_json']);$done++;fwrite(STDOUT,sprintf("[%d] %s | %s | %s (%.0f%%)\n",$offerId,$context['item'],$out['result']['decision'],100*(float)$out['result']['confidence']));}catch(Throwable $e){$repo->saveError($id,$e->getMessage());$errors++;fwrite(STDERR,"[{$offerId}] ERROR: {$e->getMessage()}\n");}}
fwrite(STDOUT,"Klaar. Gecontroleerd={$done}, fouten={$errors}.\n");fwrite(STDOUT,'Status: '.json_encode($repo->summary(),JSON_UNESCAPED_SLASHES)."\n");
