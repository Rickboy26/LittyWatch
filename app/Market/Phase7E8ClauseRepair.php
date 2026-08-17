<?php
declare(strict_types=1);

namespace LittyWatch\Market;

/**
 * Phase 7E.8: conservative local clause repair.
 *
 * Repairs only attributes/requirements that can be read directly next to
 * Bone Dragon Staff/BDS in its own raw segment. This prevents q/attribute
 * leakage from a following item such as Scarabshell or Froggy.
 */
final class Phase7E8ClauseRepair
{
    private const ATTRIBUTES = [
        'fire' => ['fire_magic', 'Fire Magic'],
        'water' => ['water_magic', 'Water Magic'],
        'air' => ['air_magic', 'Air Magic'],
        'earth' => ['earth_magic', 'Earth Magic'],
        'dom' => ['domination_magic', 'Domination Magic'],
        'domination' => ['domination_magic', 'Domination Magic'],
        'inspi' => ['inspiration_magic', 'Inspiration Magic'],
        'insp' => ['inspiration_magic', 'Inspiration Magic'],
        'fc' => ['fast_casting', 'Fast Casting'],
        'smite' => ['smiting_prayers', 'Smiting Prayers'],
        'smiting' => ['smiting_prayers', 'Smiting Prayers'],
        'heal' => ['healing_prayers', 'Healing Prayers'],
        'prot' => ['protection_prayers', 'Protection Prayers'],
        'blood' => ['blood_magic', 'Blood Magic'],
        'death' => ['death_magic', 'Death Magic'],
        'curses' => ['curses', 'Curses'],
        'sr' => ['soul_reaping', 'Soul Reaping'],
        'resto' => ['restoration_magic', 'Restoration Magic'],
        'restoration' => ['restoration_magic', 'Restoration Magic'],
        'chan' => ['channeling_magic', 'Channeling Magic'],
        'channeling' => ['channeling_magic', 'Channeling Magic'],
        'spaw' => ['spawning_power', 'Spawning Power'],
        'spawning' => ['spawning_power', 'Spawning Power'],
    ];

    /** @param array<string,mixed> $row */
    public function repair(array $row): array
    {
        $item = mb_strtolower(trim((string)($row['item'] ?? '')));
        $key = mb_strtolower(trim((string)($row['item_key'] ?? '')));

        $isBds = $item === 'bone dragon staff'
            || in_array(str_replace('_', '-', $key), ['bone-dragon-staff'], true);

        if (!$isBds) {
            return $row;
        }

        $text = trim((string)($row['raw_segment'] ?? ''));
        if ($text === '') {
            return $row;
        }

        $local = $this->bdsLocalContext($text);
        if ($local === null) {
            return $row;
        }

        if ($local['requirement'] !== null) {
            $row['requirement'] = $local['requirement'];
        }

        if ($local['attribute_key'] !== null) {
            $row['attribute_key'] = $local['attribute_key'];
            $row['attribute_name'] = $local['attribute_name'];
        }

        return $row;
    }

    /** @return array{requirement:?int,attribute_key:?string,attribute_name:?string}|null */
    private function bdsLocalContext(string $text): ?array
    {
        $patterns = [
            // q11 Smite BDS / Q9 FIRE BDS
            '/\bq\s*(\d{1,2})\s+([a-z]+)\s+(?:bone\s+dragon\s+staff|bds)\b/iu',
            // Smite q11 BDS
            '/\b([a-z]+)\s+q\s*(\d{1,2})\s+(?:bone\s+dragon\s+staff|bds)\b/iu',
            // BDS q13 dom
            '/\b(?:bone\s+dragon\s+staff|bds)\s+q\s*(\d{1,2})\s+([a-z]+)\b/iu',
            // BDS dom q13
            '/\b(?:bone\s+dragon\s+staff|bds)\s+([a-z]+)\s+q\s*(\d{1,2})\b/iu',
        ];

        foreach ($patterns as $i => $pattern) {
            if (!preg_match($pattern, $text, $m)) {
                continue;
            }

            if ($i === 1 || $i === 3) {
                $attrRaw = mb_strtolower((string)$m[1]);
                $q = (int)$m[2];
            } else {
                $q = (int)$m[1];
                $attrRaw = mb_strtolower((string)$m[2]);
            }

            $attr = self::ATTRIBUTES[$attrRaw] ?? null;
            return [
                'requirement' => $q > 0 ? $q : null,
                'attribute_key' => $attr[0] ?? null,
                'attribute_name' => $attr[1] ?? null,
            ];
        }

        return null;
    }
}
