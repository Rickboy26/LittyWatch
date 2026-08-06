<?php
declare(strict_types=1);

namespace LittyWatch\Market;

final class VariantNormalizer
{
    private const ATTRIBUTE_ALIASES = [
        'dom' => 'domination_magic', 'domination' => 'domination_magic', 'domination magic' => 'domination_magic',
        'insp' => 'inspiration_magic', 'inspa' => 'inspiration_magic', 'inspiration' => 'inspiration_magic', 'inspiration magic' => 'inspiration_magic',
        'fc' => 'fast_casting', 'fast casting' => 'fast_casting',
        'es' => 'energy_storage', 'energy storage' => 'energy_storage',
        'air' => 'air_magic', 'air magic' => 'air_magic',
        'earth' => 'earth_magic', 'earth magic' => 'earth_magic',
        'fire' => 'fire_magic', 'fire magic' => 'fire_magic',
        'water' => 'water_magic', 'water magic' => 'water_magic',
        'chann' => 'channeling_magic', 'channeling' => 'channeling_magic', 'channeling magic' => 'channeling_magic',
        'resto' => 'restoration_magic', 'restor' => 'restoration_magic', 'restoration' => 'restoration_magic', 'restoration magic' => 'restoration_magic',
        'spaw' => 'spawning_power', 'spawning' => 'spawning_power', 'spawning power' => 'spawning_power',
        'comm' => 'command', 'command' => 'command',
        'mot' => 'motivation', 'motivation' => 'motivation',
        'lead' => 'leadership', 'leadership' => 'leadership',
        'tac' => 'tactics', 'tact' => 'tactics', 'tactics' => 'tactics',
        'str' => 'strength', 'strength' => 'strength',
        'smiting' => 'smiting_prayers', 'smite' => 'smiting_prayers', 'smiting prayers' => 'smiting_prayers',
        'heal' => 'healing_prayers', 'healing' => 'healing_prayers', 'healing prayers' => 'healing_prayers',
        'prot' => 'protection_prayers', 'protection' => 'protection_prayers', 'protection prayers' => 'protection_prayers',
        'divine' => 'divine_favor', 'df' => 'divine_favor', 'divine favor' => 'divine_favor',
        'curses' => 'curses', 'curse' => 'curses',
        'blood' => 'blood_magic', 'blood magic' => 'blood_magic',
        'death' => 'death_magic', 'death magic' => 'death_magic',
        'sr' => 'soul_reaping', 'soul reaping' => 'soul_reaping',
        'illusion' => 'illusion_magic', 'illusion magic' => 'illusion_magic',
        'wilderness' => 'wilderness_survival', 'wilderness survival' => 'wilderness_survival',
        'marksmanship' => 'marksmanship',
        'expertise' => 'expertise',
        'dagger' => 'dagger_mastery', 'dagger mastery' => 'dagger_mastery',
        'critical' => 'critical_strikes', 'critical strikes' => 'critical_strikes',
        'scythe' => 'scythe_mastery', 'scythe mastery' => 'scythe_mastery',
        'mysticism' => 'mysticism',
    ];

    public function normalize(string $itemKey, ?int $requirement, ?string $attributeKey, ?string $attributeName, bool $oldschool, bool $inscribable, array $relevant, array $profile): array
    {
        $itemKey = $this->key($itemKey);
        $attribute = $this->normalizeAttribute($attributeKey ?: $attributeName);
        $track = array_values(array_unique(array_map('strval', $profile['market_key'] ?? $profile['track'] ?? [])));

        $parts = [$itemKey];
        if ($requirement !== null && ($this->tracks($track, 'requirement') || $track === [])) {
            $parts[] = 'q:' . $requirement;
        }
        if ($attribute !== null && ($this->tracks($track, 'attribute') || $track === [])) {
            $parts[] = 'attribute:' . $attribute;
        }
        if ($oldschool && ($this->tracks($track, 'oldschool') || $track === [])) {
            $parts[] = 'oldschool:1';
        }
        if ($inscribable && ($this->tracks($track, 'inscribable') || $track === [])) {
            $parts[] = 'inscribable:1';
        }

        foreach ($this->normalizedRelevantParts($relevant, $track) as $part) {
            if (!in_array($part, $parts, true)) $parts[] = $part;
        }

        return [
            'item_key' => $itemKey,
            'attribute_key' => $attribute,
            'market_key' => implode('|', $parts),
        ];
    }

    private function normalizedRelevantParts(array $relevant, array $track): array
    {
        $parts = [];
        foreach ($relevant as $field => $value) {
            if (in_array($field, ['requirement','attribute','attribute_key','oldschool','inscribable'], true)) continue;
            if ($track !== [] && !$this->tracks($track, (string)$field)) continue;
            if ($value === null || $value === '' || $value === false || $value === []) continue;
            if (is_array($value)) {
                $values = array_map(fn($v) => $this->key((string)$v), $value);
                sort($values, SORT_STRING);
                $parts[] = $this->key((string)$field) . ':' . implode('+', array_filter($values));
            } else {
                $parts[] = $this->key((string)$field) . ':' . $this->key((string)$value);
            }
        }
        sort($parts, SORT_STRING);
        return $parts;
    }

    private function tracks(array $track, string $field): bool
    {
        foreach ($track as $candidate) {
            if ($this->key($candidate) === $this->key($field)) return true;
        }
        return false;
    }

    private function normalizeAttribute(?string $value): ?string
    {
        if ($value === null || trim($value) === '') return null;
        $raw = trim(mb_strtolower(str_replace('_', ' ', $value)));
        return self::ATTRIBUTE_ALIASES[$raw] ?? $this->key($raw);
    }

    private function key(string $value): string
    {
        return trim((string)preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($value)), '_');
    }
}
