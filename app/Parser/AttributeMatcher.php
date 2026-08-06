<?php
declare(strict_types=1);

namespace LittyWatch\Parser;

use LittyWatch\Knowledge\KnowledgeBase;

final class AttributeMatcher
{
    public function __construct(private readonly KnowledgeBase $kb) {}

    public function match(string $text): ?array
    {
        $normalized = ' ' . KnowledgeBase::normalize($text) . ' ';
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
