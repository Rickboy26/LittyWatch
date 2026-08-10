<?php
declare(strict_types=1);

namespace LittyWatch\Market;

/**
 * Rejects only catalogue variants that are impossible with high certainty.
 *
 * Keep this guard deliberately conservative. Cross-item attribute/modifier
 * leakage belongs in the parser ownership layer, not in broad family rules.
 */
final class VariantValidityGate
{
    /** @var list<string> */
    private const BDS_CASTER_ATTRIBUTES = [
        'domination_magic','illusion_magic','inspiration_magic','fast_casting',
        'divine_favor','healing_prayers','protection_prayers','smiting_prayers',
        'soul_reaping','blood_magic','curses','death_magic',
        'energy_storage','air_magic','earth_magic','fire_magic','water_magic',
        'spawning_power','channeling_magic','communing','restoration_magic',
    ];

    /** @return array{allowed:bool,reason:?string} */
    public function inspect(array $row): array
    {
        $itemKey = $this->key((string)($row['item_key'] ?? ''));
        if ($itemKey !== 'bone_dragon_staff') {
            return ['allowed'=>true,'reason'=>null];
        }

        $attribute = $this->key((string)($row['attribute_key'] ?? $row['attribute_name'] ?? ''));
        $requirement = isset($row['requirement']) && $row['requirement'] !== '' ? (int)$row['requirement'] : null;
        $oldschool = !empty($row['is_oldschool']);

        if ($requirement !== null && ($requirement < 9 || $requirement > 13)) {
            return ['allowed'=>false,'reason'=>'impossible_bds_requirement'];
        }
        if ($oldschool) {
            return ['allowed'=>false,'reason'=>'impossible_bds_oldschool'];
        }
        if ($attribute !== '' && !in_array($attribute, self::BDS_CASTER_ATTRIBUTES, true)) {
            return ['allowed'=>false,'reason'=>'impossible_bds_attribute'];
        }

        return ['allowed'=>true,'reason'=>null];
    }

    private function key(string $value): string
    {
        return trim((string)preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($value)), '_');
    }
}
