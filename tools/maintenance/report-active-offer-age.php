<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Alleen CLI.\n");
    exit(2);
}

installSchema();
$pdo = db();
$lifecycle = new \LittyWatch\Market\OfferLifecycleService($pdo);
$hours = $lifecycle->expiryHours();
$now = new DateTimeImmutable();

$rows = $pdo->query(<<<'SQL'
SELECT so.id, so.lifecycle_status, m.posted_at
FROM structured_offers so
JOIN messages m ON m.id=so.message_id
WHERE so.quality_status='accepted'
SQL)->fetchAll();

$buckets = [
    '0-6 uur' => 0,
    '6-12 uur' => 0,
    '12-24 uur' => 0,
    '24-48 uur' => 0,
    '>48 uur' => 0,
];
$lifecycleCounts = ['active' => 0, 'superseded' => 0, 'expired' => 0, 'rejected' => 0, 'other' => 0];
$staleActive = 0;
$activeTotal = 0;

foreach ($rows as $row) {
    $status = (string)($row['lifecycle_status'] ?? 'active');
    if (array_key_exists($status, $lifecycleCounts)) {
        $lifecycleCounts[$status]++;
    } else {
        $lifecycleCounts['other']++;
    }

    if ($status !== 'active') {
        continue;
    }
    $activeTotal++;

    try {
        $posted = new DateTimeImmutable((string)$row['posted_at']);
        $age = max(0.0, ($now->getTimestamp() - $posted->getTimestamp()) / 3600);
    } catch (Throwable) {
        continue;
    }

    if ($age < 6) {
        $buckets['0-6 uur']++;
    } elseif ($age < 12) {
        $buckets['6-12 uur']++;
    } elseif ($age < 24) {
        $buckets['12-24 uur']++;
    } elseif ($age < 48) {
        $buckets['24-48 uur']++;
    } else {
        $buckets['>48 uur']++;
    }

    if ($age >= $hours) {
        $staleActive++;
    }
}

printf("=== ACTIVE OFFER AGE ===\n");
printf("Expiry-grens:                    %d uur\n", $hours);
printf("Actieve offers totaal:           %d\n\n", $activeTotal);
foreach ($buckets as $label => $count) {
    printf("%-32s %d\n", $label . ':', $count);
}
printf("\nActief ouder dan expiry-grens:   %d\n", $staleActive);
printf("\n=== LIFECYCLE ===\n");
printf("Active:                           %d\n", $lifecycleCounts['active']);
printf("Superseded:                       %d\n", $lifecycleCounts['superseded']);
printf("Expired:                          %d\n", $lifecycleCounts['expired']);
if ($lifecycleCounts['other'] > 0) {
    printf("Overig:                            %d\n", $lifecycleCounts['other']);
}
printf("\nDit rapport wijzigt niets.\n");
