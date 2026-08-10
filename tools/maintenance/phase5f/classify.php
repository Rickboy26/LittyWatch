<?php
declare(strict_types=1);

require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

function has5f(string $t,string $p):bool{return (bool)preg_match($p,$t);}

$groups=$db->query("
SELECT * FROM parser_residual_groups
WHERE decision='keep_unresolved'
ORDER BY offer_count DESC,id
")->fetchAll(PDO::FETCH_ASSOC);

$saveGroup=$db->prepare("
UPDATE parser_residual_groups
SET decision=?,notes=?,reviewed_at=?,updated_at=?
WHERE id=? AND decision='keep_unresolved'");

$saveMembers=$db->prepare("
UPDATE parser_residual_reviews
SET decision=?,notes=?,reviewed_at=?,updated_at=?
WHERE id IN (
 SELECT review_id FROM parser_residual_group_members WHERE group_id=?
)
AND decision='keep_unresolved'");

$counts=[];$offers=[];

foreach($groups as $g){
    $item=mb_strtolower(trim((string)$g['item_sample']));
    $seg=mb_strtolower(trim((string)$g['segment_sample']));
    $text=trim($item.' '.$seg);

    $decision=null;$reason=null;

    // Bundles/lists: multiple currencies/items/professions/slashes.
    if(
        has5f($text,'/\b(?:x\d+\s+(?:mes|war|nec|derv|sin|rit|ele|para|rang))\b/iu')
        || has5f($text,'/\b(?:rurik|thack|miku|keiran)\b.*\b(?:rurik|thack|miku|keiran)\b/iu')
        || has5f($text,'/\b(?:tanned hide|wood|cloth|dust)\b.*[,\/].*\b(?:tanned hide|wood|cloth|dust)\b/iu')
        || has5f($text,'/\b(?:aureate|cerulean|violet)\b.*\/.*\b(?:aureate|cerulean|violet)\b/iu')
        || has5f($text,'/\b(?:fire set|channelling set)\b.*\//iu')
        || has5f($text,'/\b(?:green necromancer).*(?:elementalist|warrior)\b/iu')
    ){
        $decision='bundle';
        $reason='Phase 5F auto: clear multi-item/list structure.';
    }

    // Modifier fragments.
    elseif(
        has5f($text,'/\b(?:vs fire|vs cold|while enchanted|soul reaping|spawning power|leadership)\b/iu')
        || has5f($text,'/\b(?:health\s*\+\d+|\+\d+\s*energy|halves casting time)\b/iu')
        || has5f($text,'/\b(?:fmn|hah)\s*\d+/iu')
        || has5f($text,'/\bmods?\s+of\b/iu')
    ){
        $decision='modifier';
        $reason='Phase 5F auto: modifier/stat fragment.';
    }

    // Clearly broad/insufficient market families.
    elseif(
        has5f($text,'/\b(?:green staffs?|oppressor weapons?|bag spaces?|ton tonics?|perfect kit)\b/iu')
        || has5f($text,'/\b(?:margo|torment|unified)\b/iu')
        || has5f($text,'/\b(?:elite sin|reg tomes?|tome elite)\b/iu')
        || has5f($text,'/\b(?:high q vs|q9 vs)\b/iu')
        || has5f($text,'/\b(?:little john|kath 30|for 1)\b/iu')
    ){
        $decision='insufficient';
        $reason='Phase 5F auto: market shorthand too broad for one concrete item.';
    }

    // Service/noise-like phrasing.
    elseif(
        has5f($text,'/\b(?:offer|whisp|whisper|pm|running|ferry|service)\b/iu')
        && !has5f($text,'/\b(?:miniature|tonic|staff|shield|bow|axe|sword|dagger|dye|gift|scroll)\b/iu')
    ){
        $decision='service';
        $reason='Phase 5F auto: non-item/service/chat residual.';
    }

    if($decision===null)continue;

    $now=gmdate('c');
    $db->beginTransaction();
    try{
        $saveGroup->execute([$decision,$reason,$now,$now,$g['id']]);
        $saveMembers->execute([$decision,$reason,$now,$now,$g['id']]);
        $db->commit();
    }catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        throw $e;
    }

    $counts[$decision]=($counts[$decision]??0)+1;
    $offers[$decision]=($offers[$decision]??0)+(int)$g['offer_count'];
}

echo "Phase 5F second-pass classifier klaar.\n";
foreach($counts as $k=>$v){
    echo sprintf("%-16s groups=%d offers=%d\n",$k,$v,$offers[$k]??0);
}
echo "Volgende stap: php tools/maintenance/phase5f/mine-aliases.php\n";
