<?php
declare(strict_types=1);

namespace LittyWatch\Parser;

final class ModifierMatcher
{
    public function __construct(private readonly Catalog $catalog) {}

    public function match(string $text): array
    {
        $found = [];
        foreach ($this->catalog->modifiers() as $modifier) {
            if (preg_match($modifier['pattern'], $text, $match)) {
                $value = $modifier['value'];
                if (isset($modifier['capture']) && isset($match[$modifier['capture']])) {
                    $value .= $match[$modifier['capture']];
                }
                $found[$modifier['key']] = $value;
            }
        }
        return $found;
    }
}
