<?php
declare(strict_types=1);

namespace LittyWatch\Parser;

use LittyWatch\Knowledge\KnowledgeBase;

final class AttributeMatcher
{
    public function __construct(private readonly KnowledgeBase $kb) {}

    public function match(string $text): ?array
    {
        // Phase 3C: attributes mentioned inside an explicit exclusion such as
        // \"q9 any(no chann/curses)\" are not the item's attribute. Strip the
        // negative clause before matching so variant statistics are not inverted.
        $positiveText = preg_replace('/\bno\b[^|,;)]*/iu', ' ', $text) ?? $text;
        $normalized = ' ' . KnowledgeBase::normalize($positiveText) . ' ';
        $best = null;
        $bestLength = 0;
        foreach ($this->kb->attributes() as $attribute) {
            foreach (array_merge([$attribute['key'], $attribute['name']], $attribute['aliases']) as $alias) {
                $needle = KnowledgeBase::normalize((string)$alias);
                if ($needle === '') continue;
                if (str_contains($normalized, ' ' . $needle . ' ') && mb_strlen($needle) > $bestLength) {
                    $best = $attribute;
                    $bestLength = mb_strlen($needle);
                }
            }
        }
        return $best;
    }
}
