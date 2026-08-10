<?php
declare(strict_types=1);

namespace LittyWatch\Market;

/**
 * Rejects only market variants that are physically impossible with high certainty.
 *
 * This gate is intentionally conservative. Parser context leakage can temporarily
 * attach weapon modifiers to unrelated catalogue items (coins/materials/etc.); such
 * rows must not be rejected merely because two leaked flags contradict each other.
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

        // Phase 6E.1: only hard catalogue-specific impossibilities belong here.
        // Do NOT apply a generic "OS + inscribable" rejection. When context from a
        // later item leaks onto a commodity/catalogue row, both flags can be present
        // even though the item itself has no weapon variant at all.
        if ($itemKey === 'bone_dragon_staff') {
            // Bone Dragon Staff exists as a max inscribable staff. q7/q8, OS and
            // non-caster requirements such as Tactics/Scythe Mastery are impossible.
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
