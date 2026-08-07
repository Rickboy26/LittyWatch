<?php
declare(strict_types=1);

namespace LittyWatch\Parser;

final class ItemMatcher
{
    public function __construct(private readonly Catalog $catalog) {}

    /** @return list<array{item:string,key:string,category:string,start:int,length:int,alias:string,score:float}> */
    public function matchAll(string $text): array
    {
        $lower = mb_strtolower($text);
        $matches = [];
        foreach ($this->catalog->items() as $item) {
            foreach ($item['aliases'] as $alias) {
                $variants = [$alias];
                // Kamadan traders frequently remove spaces from skin names
                // (WingedAxe, GoldenMachete, RazorclawScythe). Generate this
                // variant at match time instead of polluting the alias store.
                if (preg_match('/[\s-]/u', $alias)) {
                    $compact = preg_replace('/[\s-]+/u', '', $alias) ?? $alias;
                    if (mb_strlen($compact) >= 6 && $compact !== $alias) $variants[] = $compact;
                }

                foreach (array_values(array_unique($variants)) as $variant) {
                    $aliasLower = mb_strtolower($variant);
                    $offset = 0;
                    while (($pos = mb_stripos($lower, $aliasLower, $offset)) !== false) {
                        if (!$this->hasBoundaries($lower, $pos, mb_strlen($aliasLower))) {
                            $offset = $pos + 1;
                            continue;
                        }
                        if (!$this->isContextuallyValid($text, $item, $variant)) {
                            $offset = $pos + max(1, mb_strlen($aliasLower));
                            continue;
                        }
                        $matches[] = [
                            'item' => $item['name'],
                            'key' => $item['key'],
                            'category' => $item['category'] ?? 'unknown',
                            'start' => $pos,
                            'length' => mb_strlen($aliasLower),
                            'alias' => $variant,
                            'score' => $this->aliasScore($item, $variant),
                        ];
                        $offset = $pos + max(1, mb_strlen($aliasLower));
                    }
                }
            }
        }

        usort($matches, static fn(array $a, array $b): int => $a['start'] <=> $b['start'] ?: $b['length'] <=> $a['length']);
        $accepted = [];
        $occupiedUntil = -1;
        foreach ($matches as $match) {
            if ($match['start'] < $occupiedUntil) continue;
            $accepted[] = $match;
            $occupiedUntil = $match['start'] + $match['length'];
        }
        return $accepted;
    }

    private function aliasScore(array $item, string $alias): float
    {
        $a = mb_strtolower(trim($alias));
        $name = mb_strtolower((string)($item['name'] ?? ''));
        if (($name === 'bone dragon staff' && $a === 'bds')
            || ($name === 'gift of the traveler' && in_array($a, ['gott','gotts','nick gift','nick gifts'], true))
            || ($name === 'golden phoenix blade' && $a === 'gpb')
            || ($name === 'kuuna' && $a === 'kuuna')
            || ($name === 'ruby' && $a === 'ruby')
            || ($name === 'sapphire' && $a === 'sapphire')) {
            return 0.88;
        }
        return min(0.99, 0.72 + min(0.24, mb_strlen($a) / 50));
    }

    /** Phase 2H: prevent short/community aliases from hijacking unrelated weapon/mod text. */
    private function isContextuallyValid(string $text, array $item, string $alias): bool
    {
        $name = mb_strtolower((string)($item['name'] ?? ''));
        $a = mb_strtolower(trim($alias));
        $t = mb_strtolower($text);

        if ($name === 'voltaic spear' && $a === 'volta') {
            $conflictingWeapon = preg_match('/\b(?:shield|staff|wand|focus|bow|sword|axe|hammer|scythe|daggers?|cane)\b/iu', $t);
            $spearContext = preg_match('/\b(?:spear|voltaic\s+spear)\b/iu', $t);
            if ($conflictingWeapon && !$spearContext) return false;
        }

        // Phase 2I: context-sensitive community shorthand.
        if ($name === 'blessing of war' && $a === 'bow') return false;
        if ($name === 'armbrace of truth' && in_array($a, ['arms','ambr','ambraces'], true)) {
            if (preg_match('/\b(?:tonic|mod|mods|bow|axe|staff|wand|spear|grip|haft|wrapping|head)\b/iu', $t)) return false;
        }
        if ($name === 'mystical summoning stone (gaki)' && $a === 'gaki' && preg_match('/\bpolymock\b/iu', $t)) return false;

        // One/two-character aliases are too weak for catalog identity. They may
        // exist in imported knowledge, but should not create price observations.
        if (mb_strlen($a) <= 2) return false;

        return true;
    }

    private function hasBoundaries(string $text, int $start, int $length): bool
    {
        $before = $start > 0 ? mb_substr($text, $start - 1, 1) : '';
        $after = mb_substr($text, $start + $length, 1);
        return ($before === '' || !preg_match('/[\p{L}\p{N}]/u', $before))
            && ($after === '' || !preg_match('/[\p{L}\p{N}]/u', $after));
    }
}
