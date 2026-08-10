<?php
declare(strict_types=1);

require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

$files=glob(dirname(__DIR__,3).'/data/exports/littywatch-phase6b-context-greens-*.json');
if(!$files){fwrite(STDERR,"Geen 6B dry-run gevonden.\n");exit(1);}
rsort($files);$path=$files[0];
$data=json_decode((string)file_get_contents($path),true);
if(!is_array($data)){fwrite(STDERR,"Ongeldig 6B rapport.\n");exit(1);}

$learn=$db->prepare("
INSERT INTO parser_learned_aliases(
 alias,normalized_alias,item_key,item_name,source,source_group_id,confidence,active,notes,created_at,updated_at
) VALUES(?,?,?,?,?,?,?,?,?,?,?)
ON CONFLICT(normalized_alias,item_key) DO UPDATE SET
 alias=excluded.alias,item_name=excluded.item_name,source=excluded.source,
 source_group_id=excluded.source_group_id,confidence=excluded.confidence,
 active=1,notes=excluded.notes,updated_at=excluded.updated_at
");

function normApply6b(string $v):string{
    $v=mb_strtolower(trim($v));
    $v=strtr($v,['’'=>"'",'‘'=>"'",'´'=>"'",'`'=>"'"]);
    $v=preg_replace('/[^a-z0-9\'+]+/iu',' ',$v)??$v;
    return trim(preg_replace('/\s+/u',' ',$v)??$v);
}

$count=0;
foreach($data as $r){
    if(($r['status']??'')!=='strong_context')continue;
    if(empty($r['top'][0]['key'])||empty($r['top'][0]['name']))continue;

    $now=gmdate('c');
    $learn->execute([
        $r['item_sample'],
        normApply6b((string)$r['item_sample']),
        $r['top'][0]['key'],
        $r['top'][0]['name'],
        'phase6b_context_green',
        $r['group_id'],
        $r['top'][0]['score'],
        1,
        'Phase 6B context-aware green mapping; family='.($r['family']??'-').'; attribute='.($r['attribute']??'-'),
        $now,$now
    ]);
    $count++;
}

echo "Phase 6B strong_context aliases geactiveerd: {$count}\n";
echo "Draai daarna: php tools/maintenance/phase5e/resolve-groups.php\n";
