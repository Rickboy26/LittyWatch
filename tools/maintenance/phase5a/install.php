<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';
$db=db();

$db->exec("CREATE TABLE IF NOT EXISTS parser_residual_reviews (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 structured_offer_id INTEGER NOT NULL UNIQUE,
 message_id INTEGER,
 item TEXT NOT NULL,
 raw_segment TEXT,
 raw_message TEXT,
 current_reason TEXT NOT NULL,
 suggested_json TEXT,
 decision TEXT,
 corrected_item TEXT,
 corrected_key TEXT,
 notes TEXT,
 reviewed_at TEXT,
 created_at TEXT NOT NULL,
 updated_at TEXT NOT NULL
)");
$db->exec("CREATE INDEX IF NOT EXISTS idx_parser_residual_reviews_decision ON parser_residual_reviews(decision)");
$db->exec("CREATE INDEX IF NOT EXISTS idx_parser_residual_reviews_reason ON parser_residual_reviews(current_reason)");

$db->exec("CREATE TABLE IF NOT EXISTS parser_review_labels (
 key TEXT PRIMARY KEY,
 label TEXT NOT NULL,
 description TEXT NOT NULL,
 sort_order INTEGER NOT NULL DEFAULT 100
)");

$labels=[
 ['correct_item','Correct item','Concrete catalogusitem; voer juiste item/key in.',10],
 ['noise','Noise','Geen bruikbare marktobservatie.',20],
 ['service','Service','Dienst/run/ferry; geen itemoffer.',30],
 ['bundle','Bundle/list','Meerdere items of package; parser moet segmenteren.',40],
 ['insufficient','Insufficient identity','Bron bevat te weinig concrete identiteit.',50],
 ['miniature_variant','Miniature variant missing','Geen betrouwbare ded/unded-context.',60],
 ['modifier','Modifier fragment','Upgrade/modifier zonder zelfstandig item.',70],
 ['keep_unresolved','Keep unresolved','Nog niet veilig genoeg om te mappen.',80]
];

$stmt=$db->prepare("INSERT INTO parser_review_labels(key,label,description,sort_order)
VALUES(?,?,?,?)
ON CONFLICT(key) DO UPDATE SET label=excluded.label,description=excluded.description,sort_order=excluded.sort_order");
foreach($labels as $r)$stmt->execute($r);

echo "OK: Phase 5A schema geïnstalleerd.\n";
echo "Volgende stap: php tools/maintenance/phase5a/build-queue.php\n";
