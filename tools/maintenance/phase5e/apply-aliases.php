<?php
declare(strict_types=1);

require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

$files=glob(dirname(__DIR__,3).'/data/exports/littywatch-phase5e-alias-dryrun-*.json');
if(!$files){fwrite(STDERR,"Geen 5E dry-run gevonden.\n");exit(1);}
rsort($files);$path=$files[0];

$data=json_decode((string)file_get_contents($path),true);
if(!is_array($data)){fwrite(STDERR,"Ongeldig dry-run bestand.\n");exit(1);}

$ins=$db->prepare("
INSERT INTO parser_learned_aliases(
 alias,normalized_alias,item_key,item_name,source,source_group_id,confidence,active,notes,created_at,updated_at
)VALUES(?,?,?,?,?,?,?,?,?,?,?)
ON CONFLICT(normalized_alias,item_key) DO UPDATE SET
 alias=excluded.alias,item_name=excluded.item_name,source=excluded.source,
 source_group_id=excluded.source_group_id,confidence=excluded.confidence,
 active=1,notes=excluded.notes,updated_at=excluded.updated_at");

$count=0;
foreach($data as $r){
    if((float)($r['confidence']??0)<0.99)continue;
    $now=gmdate('c');
    $ins->execute([
        $r['alias'],$r['normalized_alias'],$r['item_key'],$r['item_name'],$r['source'],
        $r['source_group_id']??null,$r['confidence'],1,$r['notes']??null,$now,$now
    ]);
    $count++;
}
echo "Phase 5E aliases geactiveerd: {$count}\n";
echo "Volgende stap: php tools/maintenance/phase5e/resolve-groups.php\n";
