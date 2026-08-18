<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/bootstrap.php';

$fail = 0;
function c13(bool $ok, string $label, mixed $actual=null): void {
    global $fail;
    echo ($ok ? 'OK   ' : 'FAIL ') . $label;
    if (!$ok && $actual !== null) echo ' [actual='.(is_scalar($actual)?(string)$actual:json_encode($actual)).']';
    echo PHP_EOL;
    if (!$ok) $fail++;
}

$g = new \LittyWatch\Market\Phase7E13ResidualGuard(db());

$ghost = $g->repair(['item'=>'Miniature Ghostly Priest','item_key'=>'miniature-ghostly-priest','raw_segment'=>'Q9 earth os ghostly hct can mod it for final buyer 6a obo. PST','quality_status'=>'review','quality_reason'=>'miniature_variant_unresolved','confidence'=>0.6]);
c13(($ghost['quality_reason']??'')==='strict_catalog_generic','Ghostly weapon context rejected',$ghost['quality_reason']??null);

$necro = $g->repair(['item'=>'Of the Necro +5 sr for scyt','item_key'=>'of-the-necro-5-sr-for-scyt','raw_segment'=>'Of the Necro +5 sr for scyt','quality_status'=>'review','quality_reason'=>'catalog_first_unresolved','confidence'=>0.6]);
c13(($necro['item_key']??'')==='scythe-grip-of-the-necromancer','Necro scythe grip canonical',$necro['item_key']??null);
c13(($necro['quality_reason']??'')==='catalog_match','Necro scythe grip catalog_match',$necro['quality_reason']??null);

$icy = $g->repair(['item'=>'icedragon blade','item_key'=>'icedragon-blade','raw_segment'=>'icedragon blade','quality_status'=>'review','quality_reason'=>'catalog_first_unresolved','confidence'=>0.6]);
c13(($icy['item_key']??'')==='icy-dragon-sword','icedragon blade => Icy Dragon Sword',$icy['item_key']??null);

$cake = $g->repair(['item'=>'D-cakes :200 All','item_key'=>'d-cakes-200-all','raw_segment'=>'D-cakes 4a:200 All','quality_status'=>'review','quality_reason'=>'catalog_first_unresolved','confidence'=>0.6]);
c13(($cake['item_key']??'')==='birthday-cupcake','D-cakes => Birthday Cupcake',$cake['item_key']??null);

$gold = $g->repair(['item'=>'gold value','item_key'=>'gold-value','raw_segment'=>'400 gold value','quality_status'=>'review','quality_reason'=>'catalog_first_unresolved','confidence'=>0.5]);
c13(($gold['quality_reason']??'')==='modifier_fragment_unresolved','gold value rejected as property',$gold['quality_reason']??null);

$service = $g->repair(['item'=>'Heart Of shiverpeaks and DD in NM','item_key'=>'heart-of-shiverpeaks-and-dd-in-nm','raw_segment'=>'Heart Of shiverpeaks and DD in NM','quality_status'=>'review','quality_reason'=>'catalog_first_unresolved','confidence'=>0.5]);
c13(($service['quality_reason']??'')==='service_or_noise','mission/service request rejected',$service['quality_reason']??null);

$unchanged = $g->repair(['item'=>'40 40 d','item_key'=>'40-40-d','raw_segment'=>'40 40 d','quality_status'=>'review','quality_reason'=>'catalog_first_unresolved','confidence'=>0.5]);
c13(($unchanged['quality_reason']??'')==='catalog_first_unresolved','40 40 d deliberately unchanged',$unchanged['quality_reason']??null);

$writer = file_get_contents($root . '/app/Market/StructuredOfferWriter.php');
c13(str_contains((string)$writer,'LITTYWATCH_PHASE7E13_PREINSERT_RESIDUAL'),'writer 7E.13 marker aanwezig');

echo PHP_EOL;
if ($fail) {
    echo "Phase 7E.13 smoke-test: {$fail} fout(en).\n";
    exit(1);
}
echo "Phase 7E.13 smoke-test volledig OK.\n";
echo "Daarna live-market reset voor zuivere meting; geen reparse-all.\n";
