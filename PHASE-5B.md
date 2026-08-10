# LittyWatch V5.2 — Phase 5B Grouped Review

Install:
php tools/maintenance/phase5b/install.php
php tools/maintenance/phase5b/build-groups.php

Report:
php tools/maintenance/phase5b/report.php

Grouped review:
php tools/maintenance/phase5b/review.php
php tools/maintenance/phase5b/review.php 50

Export reviewed patterns:
php tools/maintenance/phase5b/export.php

`apply-reviewed.php` schrijft bewust nog niets automatisch naar parser_corrections;
het controleert alleen de reviewed correct_item set. Zo blijft 5B veilig en review-first.
