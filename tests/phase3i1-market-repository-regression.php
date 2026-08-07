<?php
declare(strict_types=1);
$path = dirname(__DIR__).'/app/Repositories/MarketRepository.php';
$src = file_get_contents($path);
if ($src === false) { fwrite(STDERR, "Cannot read MarketRepository.php\n"); exit(1); }
$bad = [
    "AND \$this->trustedPriceExpr('o') THEN 1 ELSE 0 END) AS buy_usable_count",
    "AND \$this->trustedPriceExpr('o') THEN 1 ELSE 0 END) AS sell_usable_count",
];
foreach ($bad as $needle) {
    if (str_contains($src, $needle)) {
        fwrite(STDERR, "Phase 3I.1 regression: trustedPriceExpr is interpolated as a property.\n");
        exit(1);
    }
}
if (substr_count($src, "\$this->trustedPriceExpr('o')") < 8) {
    fwrite(STDERR, "Phase 3I.1 regression: expected trustedPriceExpr calls missing.\n");
    exit(1);
}
echo "Phase 3I.1 MarketRepository trusted price SQL regression OK\n";
