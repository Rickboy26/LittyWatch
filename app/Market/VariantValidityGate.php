<?php
declare(strict_types=1);

namespace LittyWatch\Market;

/**
 * Rejects market variants that cannot exist in Guild Wars.
 * Keep this deliberately conservative: only hard impossibilities belong here.
 */
final class VariantValidityGate
{
    /** @var list<string> */
    private const BDS_ATTRIBUTES = [
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
        $attribute = $this->key((string)($row['attribute_key'] ?? $row['attribute_name'] ?? ''));
        $requirement = isset($row['requirement']) && $row['requirement'] !== '' ? (int)$row['requirement'] : null;
        $oldschool = !empty($row['is_oldschool']);
        $inscribable = !empty($row['is_inscribable']);

        // A weapon cannot simultaneously be old-school and inscribable.
        if ($oldschool && $inscribable) {
            return ['allowed'=>false,'reason'=>'impossible_variant_os_and_inscribable'];
        }

        if ($itemKey === 'bone_dragon_staff') {
            // BDS is an Eye of the North dungeon-chest skin and exists as an
            // inscribable max staff. q7/q8 and old-school BDS variants are impossible.
            if ($requirement !== null && ($requirement < 9 || $requirement > 13)) {
                return ['allowed'=>false,'reason'=>'impossible_bds_requirement'];
            }
            if ($oldschool) {
                return ['allowed'=>false,'reason'=>'impossible_bds_oldschool'];
            }
            if ($attribute !== '' && !in_array($attribute, self::BDS_ATTRIBUTES, true)) {
                return ['allowed'=>false,'reason'=>'impossible_bds_attribute'];
            }
        }

        return ['allowed'=>true,'reason'=>null];
    }

    private function key(string $value): string
    {
        return trim((string)preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($value)), '_');
    }
}
