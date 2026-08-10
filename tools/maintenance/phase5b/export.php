<?php
declare(strict_types=1);

require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

$outDir=dirname(__DIR__,3).'/data/exports';
if(!is_dir($outDir))mkdir($outDir,0775,true);
$stamp=date('Ymd-His');
$path="$outDir/littywatch-phase5b-reviewed-patterns-$stamp.ndjson";

$fh=fopen($path,'wb');
$count=0;

$sql="
SELECT
 g.id group_id,g.signature,g.normalized_item,g.normalized_segment,g.primary_reason,
 g.item_sample,g.segment_sample,g.offer_count,g.message_count,g.suggested_json,
 g.decision,g.corrected_item,g.corrected_key,g.notes,g.reviewed_at
FROM parser_residual_groups g
WHERE g.decision IS NOT NULL
ORDER BY g.offer_count DESC,g.id";

foreach($db->query($sql) as $r){
    $r['suggestions']=json_decode((string)$r['suggested_json'],true)?:[];
    unset($r['suggested_json']);

    $ex=$db->prepare("
    SELECT r.raw_message,r.raw_segment,r.item,r.message_id
    FROM parser_residual_group_members gm
    JOIN parser_residual_reviews r ON r.id=gm.review_id
    WHERE gm.group_id=?
    ORDER BY r.id
    LIMIT 5");
    $ex->execute([$r['group_id']]);
    $r['examples']=$ex->fetchAll(PDO::FETCH_ASSOC);

    fwrite($fh,json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
    $count++;
}
fclose($fh);

echo "Reviewed patterns: {$count} -> {$path}\n";
