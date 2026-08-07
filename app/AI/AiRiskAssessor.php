<?php
declare(strict_types=1);

namespace LittyWatch\AI;

final class AiRiskAssessor
{
    /** @param array<string,mixed> $offer @param array<string,mixed> $context */
    public function assess(array $offer, array $context = []): array
    {
        $score = 0;
        $reasons = [];
        $confidence = (float)($offer['confidence'] ?? 0.0);
        $status = (string)($offer['quality_status'] ?? 'review');
        $raw = mb_strtolower((string)($offer['raw_segment'] ?? ''));
        $unit = $offer['unit_price_ecto'] ?? null;
        $amount = $offer['price_amount'] ?? null;
        $basis = (string)($offer['price_basis'] ?? 'unknown');

        if ($status !== 'accepted') { $score += 45; $reasons[] = 'parser_review'; }
        if ($confidence < 0.90) { $score += 25; $reasons[] = 'low_confidence'; }
        elseif ($confidence < 0.95) { $score += 10; $reasons[] = 'medium_confidence'; }
        if ($amount !== null && $unit === null) { $score += 35; $reasons[] = 'price_without_unit_price'; }
        if (in_array($basis, ['unknown', 'total'], true) && $amount !== null) { $score += 15; $reasons[] = 'ambiguous_price_basis'; }
        if (preg_match('/\b(?:stk|stack|stacks|ea|each|per)\b|\/|\bx\s*\d+\b|\d+\s*=\s*\d+/i', $raw)) { $score += 8; $reasons[] = 'complex_price_notation'; }
        if ((int)($context['sibling_count'] ?? 1) > 1) { $score += 12; $reasons[] = 'multi_item_message'; }

        $median = isset($context['median_unit_ecto']) ? (float)$context['median_unit_ecto'] : 0.0;
        $samples = (int)($context['market_samples'] ?? 0);
        if ($unit !== null && $median > 0 && $samples >= 3) {
            $ratio = (float)$unit / $median;
            if ($ratio >= 4.0 || $ratio <= 0.25) { $score += 45; $reasons[] = 'extreme_market_outlier'; }
            elseif ($ratio >= 2.0 || $ratio <= 0.5) { $score += 20; $reasons[] = 'market_outlier'; }
        }

        return [
            'score' => min(100, $score),
            'risky' => $score >= 20,
            'reasons' => array_values(array_unique($reasons)),
        ];
    }
}
