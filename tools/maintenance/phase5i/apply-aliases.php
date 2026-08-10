<?php
declare(strict_types=1);

require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

$files=glob(dirname(__DIR__,3).'/data/exports/littywatch-phase5i-items-dryrun-*.json');
if(!$files){fwrite(STDERR,"Geen 5I item dry-run gevonden.\n");exit(1);}
rsort($files);$path=$files[0];
$data=json_decode((string)file_get_contents($path),true);
if(!is_array($data)){fwrite(STDERR,"Ongeldig 5I rapport.\n");exit(1);}

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
    $now=gmdate('c');
    $ins->execute([
        $r['alias'],$r['normalized_alias'],$r['item_key'],$r['item_name'],
        'phase5i_trusted_pattern',$r['group_id'],0.99,1,
        'Phase 5I trusted currency/material/typo alias',$now,$now
    ]);
    $count++;
}
echo "Phase 5I aliases geactiveerd: {$count}\n";
echo "Draai nu: php tools/maintenance/phase5e/resolve-groups.php\n";
