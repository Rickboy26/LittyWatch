<?php
declare(strict_types=1);

namespace LittyWatch\Market;

/**
 * Rejects only market variants that are physically impossible with high certainty.
 *
 * Phase 6F extends the BDS-only guard with conservative weapon-family rules.
 * Unknown/ambiguous catalogue items are intentionally left untouched.
 */
final class VariantValidityGate
{
    /** @var list<string> */
    private const CASTER_ATTRIBUTES = [
        'domination_magic','illusion_magic','inspiration_magic','fast_casting',
        'divine_favor','healing_prayers','protection_prayers','smiting_prayers',
        'soul_reaping','blood_magic','curses','death_magic',
        'energy_storage','air_magic','earth_magic','fire_magic','water_magic',
        'spawning_power','channeling_magic','communing','restoration_magic',
    ];

    /** @var array<string,list<string>> */
    private const FAMILY_ATTRIBUTES = [
        'staff'   => self::CASTER_ATTRIBUTES,
        'wand'    => self::CASTER_ATTRIBUTES,
        'focus'   => self::CASTER_ATTRIBUTES,
        'shield'  => ['strength','tactics','leadership','command','motivation'],
        'sword'   => ['swordsmanship'],
        'axe'     => ['axe_mastery'],
        'hammer'  => ['hammer_mastery'],
        'bow'     => ['marksmanship'],
        'spear'   => ['spear_mastery'],
        'scythe'  => ['scythe_mastery'],
        'daggers' => ['dagger_mastery'],
    ];

    /**
     * Catalogue names whose weapon family is not safely derivable from the final
     * noun in the canonical key. Keep this list deliberately small and certain.
     *
     * @var array<string,string>
     */
    private const SPECIAL_FAMILIES = [
        'eternal_blade'       => 'sword',
        'obsidian_edge'       => 'sword',
        'colossal_scimitar'   => 'sword',
        'golden_machete'      => 'sword',
        'fellblade'           => 'sword',
        'igneous_blade'       => 'sword',
        'platinum_blade'      => 'sword',
        'crested_machete'     => 'sword',
        'padraic'             => 'sword',
        'unicorns_wrath'      => 'wand',
        'hogs_gluttony'       => 'shield',
        'dhuums_soul_reaper'  => 'scythe',
        'urkal_s_kamas'       => 'daggers',
    ];

    /** @return array{allowed:bool,reason:?string} */
    public function inspect(array $row): array
    {
        $itemKey = $this->key((string)($row['item_key'] ?? ''));
        $attribute = $this->key((string)($row['attribute_key'] ?? $row['attribute_name'] ?? ''));
        $requirement = isset($row['requirement']) && $row['requirement'] !== '' ? (int)$row['requirement'] : null;
        $oldschool = !empty($row['is_oldschool']);

        // Catalogue-specific hard rule: Bone Dragon Staff is an inscribable max
        // staff skin with q9-q13 caster requirements. q7/q8 and OS are impossible.
        if ($itemKey === 'bone_dragon_staff') {
            if ($requirement !== null && ($requirement < 9 || $requirement > 13)) {
                return ['allowed'=>false,'reason'=>'impossible_bds_requirement'];
            }
            if ($oldschool) {
                return ['allowed'=>false,'reason'=>'impossible_bds_oldschool'];
            }
        }

        // Generic family validation only runs when the canonical item can be
        // classified with high confidence. It intentionally does not infer a
        // family from arbitrary words in raw chat, preventing upgrade/commodity
        // false positives.
        $family = $this->familyForItem($itemKey);
        if ($family !== null && $attribute !== '') {
            $allowed = self::FAMILY_ATTRIBUTES[$family] ?? [];
            if ($allowed !== [] && !in_array($attribute, $allowed, true)) {
                return ['allowed'=>false,'reason'=>'impossible_'.$family.'_attribute'];
            }
        }

        return ['allowed'=>true,'reason'=>null];
    }

    private function familyForItem(string $itemKey): ?string
    {
        if (isset(self::SPECIAL_FAMILIES[$itemKey])) {
            return self::SPECIAL_FAMILIES[$itemKey];
        }

        // Safe canonical suffixes only. Example: staff_wrapping_of_enchanting
        // is not classified as a staff because its key does not end in _staff.
        $patterns = [
            'staff'   => '/(?:^|_)staff$/',
            'wand'    => '/(?:^|_)wand$/',
            'focus'   => '/(?:^|_)focus$/',
            'shield'  => '/(?:^|_)shield$/',
            'bow'     => '/(?:^|_)(?:bow|longbow)$/',
            'sword'   => '/(?:^|_)sword$/',
            'axe'     => '/(?:^|_)axe$/',
            'hammer'  => '/(?:^|_)hammer$/',
            'spear'   => '/(?:^|_)spear$/',
            'scythe'  => '/(?:^|_)scythe$/',
            'daggers' => '/(?:^|_)daggers$/',
            // In Guild Wars, scepters are caster one-handed weapons (wand family).
            'wand'    => '/(?:^|_)(?:wand|scepter)$/',
        ];

        foreach ($patterns as $family => $pattern) {
            if (preg_match($pattern, $itemKey)) return $family;
        }
        return null;
    }

    private function key(string $value): string
    {
        return trim((string)preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($value)), '_');
    }
}
