<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){fwrite(STDERR,"Alleen CLI.\n");exit(1);}
$root=dirname(__DIR__,2);require $root.'/bootstrap.php';
use LittyWatch\Knowledge\KnowledgeBase;
use LittyWatch\Market\CanonicalMarketIdentity;
$pdo=db();installSchema();$kb=new KnowledgeBase($pdo);
$aliases=[
 'silverwing-bow'=>['Silverwing Bow','silverwing bow','silverwing'],
 'ghostly-hero'=>['Ghostly Hero','ghostly hero'],
 'rift-warden'=>['Rift Warden','rift warden'],
 'mallyx'=>['Mallyx','mallyx'],
 'kuuna'=>['Kuuna','kuuna','Kuunavang','kuunavang'],
 'mad-kings-guard'=>["Mad King's Guard",'mad kings guard','mkg'],
];
$pdo->beginTransaction();
try{
 foreach(CanonicalMarketIdentity::overrides() as $key=>$name){
  $st=$pdo->prepare('SELECT category_key,source,source_id,metadata_json FROM kb_items WHERE key=? LIMIT 1');$st->execute([$key]);$old=$st->fetch();
  if(!$old){fwrite(STDOUT,"SKIP {$key}: ontbreekt in kb_items\n");continue;}
  $meta=json_decode((string)($old['metadata_json']??'{}'),true);if(!is_array($meta))$meta=[];$meta['_phase3u_canonical_name']=true;
  $kb->upsertItem($key,$name,(string)$old['category_key'],'phase3u-canonical',(string)($old['source_id']??''),$meta);
  $kb->addAlias($key,$name,'phase3u-canonical');foreach($aliases[$key]??[] as $alias)$kb->addAlias($key,$alias,'phase3u-legacy-alias');
  fwrite(STDOUT,"OK {$key} => {$name}\n");
 }
 $pdo->commit();
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
$result=(new LittyWatch\Market\StrictCatalogGate($pdo))->quarantineExisting();
fwrite(STDOUT,'Strict catalog: '.json_encode($result,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n");
