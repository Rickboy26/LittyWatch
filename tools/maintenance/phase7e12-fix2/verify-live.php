<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);
require $root.'/bootstrap.php';
$good=(int)db()->query("SELECT COUNT(*) FROM structured_offers WHERE item_key='market-points-alcohol' AND quality_reason='catalog_match'")->fetchColumn();
$legacy=(int)db()->query("SELECT COUNT(*) FROM structured_offers WHERE item_key='alcohol-point'")->fetchColumn();
$bad=(int)db()->query("SELECT COUNT(*) FROM structured_offers WHERE (lower(COALESCE(raw_segment,'')) LIKE '%alc stack%' OR lower(COALESCE(raw_segment,'')) LIKE '%1pt alc%' OR lower(COALESCE(raw_segment,'')) LIKE '%1point alch%') AND NOT (item_key='market-points-alcohol' AND quality_reason='catalog_match')")->fetchColumn();
echo "=== Phase 7E.12 FIX2 Alcohol Market Metric verification ===\n";
echo "Alcohol metric catalog_match: {$good}\n";
echo "Legacy alcohol-point rows: {$legacy}\n";
echo "Alcohol metric bad rows: {$bad}\n";
exit(($legacy===0&&$bad===0)?0:1);
