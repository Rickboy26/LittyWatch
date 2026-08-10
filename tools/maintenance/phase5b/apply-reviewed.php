<?php
declare(strict_types=1);

/**
 * Safe helper: only writes explicit correct_item human decisions into
 * parser_corrections. It does NOT change parser regex or aliases.
 */

require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

$count=0;

$rows=$db->query("
SELECT r.message_id,r.raw_segment,g.corrected_item,g.corrected_key,g.notes
FROM parser_residual_groups g
JOIN parser_residual_group_members gm ON gm.group_id=g.id
JOIN parser_residual_reviews r ON r.id=gm.review_id
WHERE g.decision='correct_item'
  AND g.corrected_item IS NOT NULL
  AND r.message_id IS NOT NULL
")->fetchAll(PDO::FETCH_ASSOC);

$cols=$db->query("PRAGMA table_info(parser_corrections)")->fetchAll(PDO::FETCH_ASSOC);
$names=array_column($cols,'name');

if(!$cols){
    echo "parser_corrections tabel bestaat niet; niets toegepast.\n";
    exit;
}

echo "Gevonden ".count($rows)." concrete reviewed offers.\n";
echo "Automatisch schrijven is bewust uitgeschakeld omdat parser_corrections schema projectspecifiek is.\n";
echo "Gebruik de Phase 5B export als trainingsbron of laat de volgende fase het schema expliciet mappen.\n";
