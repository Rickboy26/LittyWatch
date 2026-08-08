<?php
declare(strict_types=1);

namespace LittyWatch\Market;

use LittyWatch\Knowledge\KnowledgeBase;

/**
 * Phase 3Y: final suppression layer for parser/list fragments that are clearly
 * trade syntax, quantities or collection context rather than inventory items.
 *
 * This gate is deliberately narrow. It only drops fragments whose normalized
 * form is structurally non-item; anything ambiguous remains review material.
 */
final class NoiseFragmentGate
{
    /** @return array{drop:bool,reason:?string} */
    public function inspect(string $item, string $segment): array
    {
        $n = KnowledgeBase::normalize($item);
        $s = KnowledgeBase::normalize($segment);
        if ($n === '') return ['drop'=>true,'reason'=>'noise_empty_fragment'];

        if (in_array($n, [
            'left','for','elite','elites','normal','arm','all','set','collection',
            'for full collection','trade to see','open trade','obo','pm','sweat',
            'ran','nec','alc','sta','few mods','weapon mods',
        ], true)) {
            return ['drop'=>true,'reason'=>'noise_orphan_trade_fragment'];
        }

        if (preg_match('/^(?:x\s*)?\d+$/u', $n)) {
            return ['drop'=>true,'reason'=>'noise_quantity_fragment'];
        }
        if (preg_match('/^(?:for\s+)?(?:full\s+)?collection$/u', $n)
            || preg_match('/^(?:elite|elites|normal)\s+and$/u', $n)) {
            return ['drop'=>true,'reason'=>'noise_collection_fragment'];
        }
        if (preg_match('/^(?:os|old school)(?:\s+trade\s+to\s+see)?$/u', $n)) {
            return ['drop'=>true,'reason'=>'noise_weapon_context_fragment'];
        }
        if (preg_match('/^(?:r|b|g)\s*\d+(?:[.,]\d+)?[aekg]?$/u', $n)
            || preg_match('/^(?:rocks?)\s+r\s+b\s*\d+/u', $n)) {
            return ['drop'=>true,'reason'=>'noise_price_context_fragment'];
        }

        if (preg_match('/^(?:for\s+)?\d+(?:[.,]\d+)?\s*(?:a|e|k|plat|platinum)(?:\s*\(x?\d+\))?$/u', $n)
            || preg_match('/^\d+(?:[.,]\d+)?\s*(?:a|e|k)\s+x?\d+$/u', $n)) {
            return ['drop'=>true,'reason'=>'noise_price_only_fragment'];
        }

        // Quantity-only material left after punctuation splitting, e.g. "(x6)".
        if (preg_match('/^\(?\s*x?\s*\d+\s*\)?$/iu', trim($item))) {
            return ['drop'=>true,'reason'=>'noise_quantity_fragment'];
        }

        // Do not drop phrases merely because their surrounding segment contains
        // noise; this check is candidate-centric by design.
        return ['drop'=>false,'reason'=>null];
    }
}
