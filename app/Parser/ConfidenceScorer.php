<?php
declare(strict_types=1);

namespace LittyWatch\Parser;

final class ConfidenceScorer
{
    public function __construct(private readonly Catalog $catalog) {}

    public function score(?array $item, array $modifiers, ParsedPrice $price, string $segment): array
    {
        if ($this->isRejected($segment)) {
            return [0.05, 'rejected', 'reject_pattern'];
        }
        if ($item === null) {
            return [$price->amount !== null ? 0.35 : 0.20, 'review', 'no_catalog_item'];
        }

        // Phase 2K: an explicit requirement makes a generic weapon-family
        // request a well-defined market variant (e.g. Q8 Bow / Q5 Focus).
        if (($item['category'] ?? '') === 'generic-weapon-family'
            && (isset($modifiers['requirement']) || preg_match('/\b(?:q|r|req)\s*\d{1,2}\b/iu', $segment))) {
            return [0.86, 'accepted', 'catalog_match'];
        }

        $score = (float) $item['score'];
        if ($price->amount !== null) $score += 0.08;
        if ($modifiers !== []) $score += 0.04;
        if ($price->basis === 'unknown') $score -= 0.02;
        $score = max(0.0, min(0.99, $score));

        if ($score >= 0.82) return [$score, 'accepted', 'catalog_match'];
        return [$score, 'review', 'low_confidence'];
    }

    private function isRejected(string $segment): bool
    {
        foreach ($this->catalog->rejectPatterns() as $pattern) {
            if (preg_match($pattern, $segment)) return true;
        }
        return false;
    }
}
