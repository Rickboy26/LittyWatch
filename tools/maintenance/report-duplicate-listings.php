<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

installSchema();
$sql = <<<'SQL'
SELECT
    m.player,
    so.trade_type,
    so.item,
    COALESCE(so.requirement, -1) AS requirement,
    COALESCE(so.attribute_key, '') AS attribute_key,
    COALESCE(so.is_oldschool, 0) AS is_oldschool,
    COALESCE(so.is_inscribable, 0) AS is_inscribable,
    COUNT(*) AS active_rows,
    MAX(m.posted_at) AS newest
FROM structured_offers so
JOIN messages m ON m.id = so.message_id
WHERE so.quality_status='accepted'
  AND COALESCE(so.lifecycle_status,'active')='active'
GROUP BY
    lower(trim(m.player)),
    so.trade_type,
    lower(so.item_key),
    COALESCE(so.requirement, -1),
    COALESCE(so.attribute_key, ''),
    COALESCE(so.is_oldschool, 0),
    COALESCE(so.is_inscribable, 0)
HAVING COUNT(*) > 1
ORDER BY active_rows DESC, newest DESC
LIMIT 100
SQL;

$rows = db()->query($sql)->fetchAll();
printf("Dubbele actieve listing-groepen: %d (max 100 getoond)\n\n", count($rows));
foreach ($rows as $row) {
    $variant = [];
    if ((int)$row['requirement'] >= 0) $variant[] = 'q'.(int)$row['requirement'];
    if ((string)$row['attribute_key'] !== '') $variant[] = (string)$row['attribute_key'];
    if ((int)$row['is_oldschool'] === 1) $variant[] = 'OS';
    if ((int)$row['is_inscribable'] === 1) $variant[] = 'insc';
    printf(
        "%-24s %-5s %-34s %-18s rows=%d newest=%s\n",
        mb_substr((string)$row['player'], 0, 24),
        (string)$row['trade_type'],
        mb_substr((string)$row['item'], 0, 34),
        implode(' ', $variant) ?: 'standaard',
        (int)$row['active_rows'],
        (string)$row['newest']
    );
}
