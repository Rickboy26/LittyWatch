<?php
declare(strict_types=1);

require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

$files=glob(dirname(__DIR__,3).'/data/exports/littywatch-phase5d-dryrun-*.json');
if(!$files){fwrite(STDERR,"Geen Phase 5D dry-run bestand gevonden.\n");exit(1);}
rsort($files);
$path=$files[0];

$data=json_decode((string)file_get_contents($path),true);
if(!is_array($data)){fwrite(STDERR,"Ongeldig dry-run bestand.\n");exit(1);}

$saveGroup=$db->prepare("
UPDATE parser_residual_groups
SET decision='correct_item',corrected_item=?,corrected_key=?,notes=?,reviewed_at=?,updated_at=?
WHERE id=? AND decision='keep_unresolved'");

$saveMembers=$db->prepare("
UPDATE parser_residual_reviews
SET decision='correct_item',corrected_item=?,corrected_key=?,notes=?,reviewed_at=?,updated_at=?
WHERE id IN (
 SELECT review_id FROM parser_residual_group_members WHERE group_id=?
)
AND decision='keep_unresolved'");

$count=0;$offers=0;
foreach($data as $r){
    if(($r['confidence']??'')!=='HIGH' || empty($r['apply']) || empty($r['top']['key']))continue;
    $gid=(int)$r['group_id'];
    $item=(string)$r['top']['name'];
    $key=(string)$r['top']['key'];
    $note='Phase 5D auto: HIGH unique catalog-assisted candidate from dry-run '.$path;
    $now=gmdate('c');

    $db->beginTransaction();
    try{
        $saveGroup->execute([$item,$key,$note,$now,$now,$gid]);
        $saveMembers->execute([$item,$key,$note,$now,$now,$gid]);
        $db->commit();
    }catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }
    $count++;
    $offers+=(int)($r['offer_count']??0);
}

echo "Phase 5D HIGH candidates toegepast.\n";
echo "Groups: {$count}\n";
echo "Offers: {$offers}\n";
echo "Bron: {$path}\n";
