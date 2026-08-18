<?php
declare(strict_types=1);

namespace LittyWatch\Market;

use PDO;

/**
 * Phase 7E.12 - targeted residual cleanup from fresh live data.
 *
 * Exact policies:
 * - alc stack(s), 1pt alc, 1point alch => Alcohol Point
 * - unided gold => Unidentified Gold
 * - "No idea" => service_or_noise
 * - Dragon Staff / staff-context may never become Miniature Celestial Dragon
 * - "Eggs Slice of" => reject segmentation noise
 * - generic Elite Tome remains untouched so existing insufficient identity logic wins
 */
final class Phase7E12ResidualGuard
{
    public function __construct(private readonly PDO $pdo) {}

    public function repair(array $row): array
    {
        $item = trim((string)($row['item'] ?? ''));
        $key = str_replace('_', '-', mb_strtolower(trim((string)($row['item_key'] ?? ''))));
        $segment = trim((string)($row['raw_segment'] ?? ''));
        $itemLower = mb_strtolower($item);

        // Alcohol points: exact user-requested canonical market identity.
        if (
            preg_match('/\balc(?:ohol)?\s+stacks?\b/iu', $segment)
            || preg_match('/\b1\s*(?:pt|point)\s+alch?\b/iu', $segment)
            || in_array($key, ['alc-stacks','1pt-alc','1point-alch-stck','1point-alch-stk'], true)
        ) {
            return $this->accept($row, 'Alcohol Point', 'alcohol-point');
        }

        // Unidentified Gold spelling variants.
        if (
            preg_match('/\bunided?\s+gold\b/iu', $segment)
            || in_array($key, ['unided-gold','unided-gold-ea-h'], true)
        ) {
            return $this->accept($row, 'Unidentified Gold', 'unidentified-gold');
        }

        // Pure non-market chatter.
        if ($itemLower === 'no idea' || preg_match('/^\s*no\s+idea\s*[.!?]*\s*$/iu', $segment)) {
            $row['quality_status'] = 'rejected';
            $row['quality_reason'] = 'service_or_noise';
            $row['confidence'] = min((float)($row['confidence'] ?? 0), 0.20);
            return $row;
        }

        // Broader Dragon Staff protection. We deliberately do not try to
        // reconstruct the exact staff skin here; we only block the false miniature.
        if (
            $key === 'miniature-celestial-dragon'
            && (
                preg_match('/\bdragon\s+staff\b/iu', $segment)
                || preg_match('/\bstaff(?:of|\s+of|\s|$)/iu', $segment)
                || preg_match('/\bstaves\b/iu', $segment)
            )
            && !preg_match('/\bmini(?:ature|pet)?\b|\b(?:ded|unded)(?:icated)?\b/iu', $segment)
        ) {
            $row['quality_status'] = 'rejected';
            $row['quality_reason'] = 'strict_catalog_generic';
            $row['confidence'] = min((float)($row['confidence'] ?? 0), 0.30);
            return $row;
        }

        // Broken consumable-list segmentation.
        if (
            preg_match('/^\s*eggs?\s+slice\s+of\s*$/iu', $segment)
            || ($key === 'golden-egg' && preg_match('/\beggs?\s+slice\s+of\b/iu', $segment))
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
