<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/bootstrap.php';

echo "=== Phase 7E.21 FIX3 shield requirement verification ===" . PHP_EOL;

$rows = db()->query("
    SELECT item, item_key, requirement, attribute_key, raw_segment
    FROM structured_offers
    WHERE quality_status='accepted'
      AND (
          lower(COALESCE(item_key,'')) LIKE '%shield%'
          OR lower(COALESCE(item,'')) LIKE '%shield%'
      )
");

$checked = 0;
$mismatch = 0;

foreach ($rows as $r) {
    $seg = mb_strtolower((string)($r['raw_segment'] ?? ''));
    $actual = mb_strtolower((string)($r['attribute_key'] ?? ''));

    if (!preg_match(
        '/\b(?:q|r|req)\s*\d{1,2}\s*(tac(?:t(?:ics)?)?|str(?:ength)?|command|comm|mot(?:ivation)?)\b/iu',
        $seg,
        $m
    )) {
        continue;
    }

    $token = mb_strtolower($m[1]);

    $expected =
        str_starts_with($token, 'tac') ? 'tactics' :
        (str_starts_with($token, 'str') ? 'strength' :
        (($token === 'command' || $token === 'comm') ? 'command' :
        (str_starts_with($token, 'mot') ? 'motivation' : '')));

    if ($expected === '') {
        continue;
    }

    $checked++;

    if ($actual !== $expected) {
        $mismatch++;
    }
}

echo "Accepted shields met expliciete requirement: {$checked}" . PHP_EOL;
echo "Requirement mismatches: {$mismatch}" . PHP_EOL;
