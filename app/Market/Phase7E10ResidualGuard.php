<?php
declare(strict_types=1);

namespace LittyWatch\Market;

use PDO;

/**
 * Phase 7E.10 - residual live cleanup after 7E.9.
 *
 * Conservative rules only, based on fresh live examples:
 * - bones stack => Bone
 * - Coffers => Coffer of Whispers (only coffer item in KB)
 * - Mysterious Stones => Mysterious Summoning Stone
 * - AnA already resolved to aptitude-not-attitude => promote catalog_match
 * - reject prose/price fragments and 40/40 set descriptions
 * - block false Miniature Celestial Dragon from staff-description context
 */
final class Phase7E10ResidualGuard
{
    public function __construct(private readonly PDO $pdo) {}

    public function repair(array $row): array
    {
        $item = trim((string)($row['item'] ?? ''));
        $key = str_replace('_','-',mb_strtolower(trim((string)($row['item_key'] ?? ''))));
        $segment = trim((string)($row['raw_segment'] ?? ''));
        $seg = mb_strtolower($segment);
        $reason = (string)($row['quality_reason'] ?? '');

        // Exact safe live aliases.
        if ($key === 'bone' && preg_match('/\bbones?\s+(?:stack|stk)\b/iu', $segment)) {
            return $this->accept($row, 'Bone', 'bone');
        }

        if ($key === 'coffers' || mb_strtolower($item) === 'coffers') {
            return $this->accept($row, 'Coffer of Whispers', 'coffer-of-whispers');
        }

        if ($key === 'mysterious-stones' || mb_strtolower($item) === 'mysterious stones') {
            return $this->accept($row, 'Mysterious Summoning Stone', 'mysterious-summoning-stone');
        }

        // Current parser already selected the intended catalog identity.
        // Avoid guessing between duplicate KB aliases when the row key itself is exact.
        if ($key === 'aptitude-not-attitude'
            && preg_match('/^\s*(?:ana|"??aptitude\s+not\s+attitude"??)\s*$/iu', $segment)) {
            return $this->accept($row, '"Aptitude not Attitude"', 'aptitude-not-attitude');
        }

        // False miniature: "Dragon" occurs as a staff skin/description in a weapon list.
        if ($key === 'miniature-celestial-dragon'
            && preg_match('/\bst(?:aff|aves)\b|\b20\/20\b|\bchannel(?:ing)?\b|\bsmite\b/iu', $segment)
            && !preg_match('/\bmini(?:ature|pet)?\b|\b(?:ded|unded)(?:icated)?\b/iu', $segment)) {
            $row['quality_status'] = 'rejected';
            $row['quality_reason'] = 'strict_catalog_generic';
            $row['confidence'] = min((float)($row['confidence'] ?? 0), 0.35);
            return $row;
        }

        // 40/40 + attribute is a set/build description, not an item identity.
        if (preg_match('/^\s*40\/40\s+[a-z]+(?:\s+\d+(?:[.,]\d+)?\s*(?:e|a|k))?(?:\/ea)?(?:\*\d+)?\s*$/iu', $segment)) {
            $row['quality_status'] = 'rejected';
            $row['quality_reason'] = 'collection_or_market_request';
            $row['confidence'] = min((float)($row['confidence'] ?? 0), 0.35);
            return $row;
        }

        // Price-only / prose fragments observed in trade chat.
        if (
            preg_match('/^\s*\d+(?:[.,]\d+)?\s*e\s+ea\b/iu', $segment)
            || preg_match('/\b(?:love\s*,?\s*loc|yours\s+in\s+love|eternal\s+gratitude|trade\s+chat\s+gamers)\b/iu', $segment)
            || preg_match('/^\s*(?:attention|put\s+the|my\s+heart\s+longs\s+for|well\s+well\s+well|fellow\s+trade\s+chat)\b/iu', $segment)
            || preg_match('/^\s*\d+[—,-]?\s*count\s+[\'’]?em\b/iu', $segment)
        ) {
            $row['quality_status'] = 'rejected';
            $row['quality_reason'] = 'service_or_noise';
            $row['confidence'] = min((float)($row['confidence'] ?? 0), 0.25);
            return $row;
        }

        return $row;
    }

    private function accept(array $row, string $name, string $key): array
    {
        $row['item'] = $name;
        $row['item_key'] = $key;
        $row['market_key'] = $key;
        $row['quality_status'] = 'accepted';
        $row['quality_reason'] = 'catalog_match';
        $row['confidence'] = max((float)($row['confidence'] ?? 0), 0.92);
        return $row;
    }
}
