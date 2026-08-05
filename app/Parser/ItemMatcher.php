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
                $aliasLower = mb_strtolower($alias);
                $offset = 0;
                while (($pos = mb_stripos($lower, $aliasLower, $offset)) !== false) {
                    if (!$this->hasBoundaries($lower, $pos, mb_strlen($aliasLower))) {
                        $offset = $pos + 1;
                        continue;
                    }
                    $matches[] = [
                        'item' => $item['name'],
                        'key' => $item['key'],
                        'category' => $item['category'] ?? 'unknown',
                        'start' => $pos,
                        'length' => mb_strlen($aliasLower),
                        'alias' => $alias,
                        'score' => min(0.99, 0.72 + min(0.24, mb_strlen($aliasLower) / 50)),
                    ];
                    $offset = $pos + max(1, mb_strlen($aliasLower));
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

    private function hasBoundaries(string $text, int $start, int $length): bool
    {
        $before = $start > 0 ? mb_substr($text, $start - 1, 1) : '';
        $after = mb_substr($text, $start + $length, 1);
        return ($before === '' || !preg_match('/[\p{L}\p{N}]/u', $before))
            && ($after === '' || !preg_match('/[\p{L}\p{N}]/u', $after));
    }
}
