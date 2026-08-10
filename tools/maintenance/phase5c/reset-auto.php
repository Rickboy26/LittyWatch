<?php
declare(strict_types=1);

/**
 * Resets only Phase-5C automatic decisions.
 * Human decisions without the Phase 5C note are preserved.
 */

require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

$groups=$db->query("
SELECT id
FROM parser_residual_groups
WHERE notes LIKE 'Phase 5C auto:%'
")->fetchAll(PDO::FETCH_COLUMN);

$clearGroup=$db->prepare("
UPDATE parser_residual_groups
SET decision=NULL,corrected_item=NULL,corrected_key=NULL,notes=NULL,reviewed_at=NULL,updated_at=?
WHERE id=?");

$clearMembers=$db->prepare("
UPDATE parser_residual_reviews
SET decision=NULL,corrected_item=NULL,corrected_key=NULL,notes=NULL,reviewed_at=NULL,updated_at=?
WHERE id IN (
 SELECT review_id FROM parser_residual_group_members WHERE group_id=?
)
AND notes LIKE 'Phase 5C auto:%'");

$db->beginTransaction();
try{
    foreach($groups as $gid){
        $now=gmdate('c');
        $clearGroup->execute([$now,$gid]);
        $clearMembers->execute([$now,$gid]);
    }
    $db->commit();
}catch(Throwable $e){
    if($db->inTransaction())$db->rollBack();
    throw $e;
}

echo "Phase 5C auto decisions gereset: ".count($groups)." groups.\n";
