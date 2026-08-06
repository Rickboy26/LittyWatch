<?php
declare(strict_types=1);

namespace LittyWatch\Parser;

/**
 * Recognizes valid generic GW1 market families even when a specific skin
 * is not yet in the catalog.
 */
final class GenericItemRecognizer
{
    /** @return array{item:string,key:string,category:string,start:int,end:int}|null */
    public function recognize(string $segment): ?array
    {
        $families = [
            'Flatbow' => ['flatbow','flat bow'],
            'Longbow' => ['longbow','long bow'],
            'Shortbow' => ['shortbow','short bow'],
            'Recurve Bow' => ['recurve bow'],
            'Hornbow' => ['hornbow','horn bow'],
            'Shield' => ['shield','shields'],
            'Staff' => ['staff','staves'],
            'Wand' => ['wand','wands'],
            'Focus' => ['focus','focuses'],
            'Sword' => ['sword','swords','longsword'],
            'Axe' => ['axe','axes'],
            'Hammer' => ['hammer','hammers'],
            'Spear' => ['spear','spears'],
            'Scythe' => ['scythe','scythes'],
            'Daggers' => ['daggers','dagger'],
        ];

        foreach ($families as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                if (preg_match('/\b' . preg_quote($alias,'/') . '\b/iu', $segment, $match, PREG_OFFSET_CAPTURE)) {
                    $offset = (int)$match[0][1];
                    return [
                        'item'=>$canonical,
                        'key'=>$this->key($canonical),
                        'category'=>'generic-weapon-family',
                        'score'=>0.82,
                        'alias'=>$match[0][0],
                        'start'=>$offset,
                        'end'=>$offset + strlen($match[0][0]),
                    ];
                }
            }
        }

        return null;
    }

    private function key(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/u',' ', $value) ?? $value;
        return trim($value);
    }
}
