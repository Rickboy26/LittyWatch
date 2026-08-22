<?php
declare(strict_types=1);

namespace LittyWatch\Parser;

/**
 * Carries compact Kamadan market context across pipe/~ separated segments.
 * Examples:
 *   BDS | Air Q9/11 | Blood Q9/11  => BDS Air Q9, BDS Air Q11, ...
 *   Q9 Staves | Zodiac (OS) | Insectoid => Q9 Zodiac Staff (OS), Q9 Insectoid Staff
 *   Q9 | War Pick | Archaic Maul => Q9 War Pick, Q9 Archaic Maul
 */
final class ContextualSegmentExpander
{
    public function __construct(private readonly ItemMatcher $items) {}

    /** @param list<string> $segments @return list<string> */
    public function expand(array $segments): array
    {
        $out = [];
        $activeItem = null;
        $activeFamily = null;
        $activeRequirement = null;
        $pendingHeader = null;
        // LITTYWATCH_PHASE4C_MINIATURE_CONTEXT
        $activeMiniatureState = null;

        foreach ($segments as $raw) {
            $segment = trim($raw, " \t\n\r\0\x0B|,;");
            if ($segment === '') continue;

            if (preg_match('/^(?:q|r|rq|req(?:uirement)?)\s*([0-9]{1,2})$/iu', $segment, $m)) {
                $activeRequirement = 'q' . $m[1];
                continue;
            }

            // Phase 4C: "unded minis | Bone Dragon, Zhed, Kuuna" is a typed
            // list. This context must win over an accidental bare-name match.
            $miniatureHeader = $this->miniatureHeader($segment);
            if ($miniatureHeader !== null) {
                if ($pendingHeader !== null) { $out[] = $pendingHeader; $pendingHeader = null; }
                $activeFamily = 'Miniature';
                $activeMiniatureState = $miniatureHeader['state'];
                $activeItem = null;
                $activeRequirement = null;
                continue;
            }
            $family = $this->familyHeader($segment);
            if ($family !== null) {
                if ($pendingHeader !== null) { $out[] = $pendingHeader; $pendingHeader = null; }
                $activeFamily = $family['family'];
                $activeMiniatureState = null;
                $activeItem = null;
                if ($family['requirement'] !== null) $activeRequirement = $family['requirement'];
                continue;
            }

            // Family headers are often followed by a comma-only name list, e.g.
            // "Everlasting Tonics | Tahlkora,Morgahn,Dunkoro". Expand each
            // child under the inherited family before catalog matching.
            if ($activeFamily !== null && str_contains($segment, ',')) {
                $parts = array_values(array_filter(array_map('trim', preg_split('/\s*,\s*/u', $segment) ?: [])));
                if (count($parts) > 1) {
                    $expandedAny = false;

                    // LITTYWATCH_PHASE8F42_LOCAL_SHARED_REQUIREMENT
                    // In a comma-separated variant list, a requirement stated on
                    // the first member owns the remaining members of that list:
                    //
                    //   q10 Heal 6e, Fire 4e, Death 4e
                    //     -> q10 Heal / q10 Fire / q10 Death
                    //
                    // Keep this local to this comma list. It must not permanently
                    // overwrite wider context unless normal parsing later does so.
                    $listRequirement = $activeRequirement;
                    if ($parts !== []) {
                        $firstRequirement = $this->requirement($parts[0]);
                        if ($firstRequirement !== null) {
                            $listRequirement = $firstRequirement;
                        }
                    }

                    foreach ($parts as $part) {
                        $candidate = $this->attachFamily($part, $activeFamily, $listRequirement, $activeMiniatureState);
                        $candidateMatches = $this->items->matchAll($candidate);
                        if ($candidateMatches === []) continue;
                        if ($pendingHeader !== null) { $pendingHeader = null; }
                        foreach ($this->expandRequirements($candidate) as $expanded) $out[] = $expanded;
                        $activeItem = (string)$candidateMatches[0]['item'];
                        $expandedAny = true;
                    }
                    if ($expandedAny) continue;
                }
            }

            // Phase 4C: while a miniature list is active, try "Miniature X"
            // before matching the bare X. This is the key fix for Ghostly Hero,
            // Zhed, Kuuna/Kuunavang, Rift Warden, Prince Rurik, etc.
            if ($activeFamily === 'Miniature') {
                $miniCandidate = $this->attachFamily($segment, 'Miniature', null, $activeMiniatureState);
                $miniMatches = $this->items->matchAll($miniCandidate);
                if ($miniMatches !== []) {
                    if ($pendingHeader !== null) { $pendingHeader = null; }
                    $out[] = $miniCandidate;
                    $activeItem = (string)$miniMatches[0]['item'];
                    continue;
                }
            }
            $matches = $this->items->matchAll($segment);
            if ($matches !== []) {
                if ($pendingHeader !== null) { $out[] = $pendingHeader; $pendingHeader = null; }
                $activeItem = (string)$matches[0]['item'];
                $inferredFamily = $this->familyFromItem($activeItem);
                if ($inferredFamily !== null) {
                    $activeFamily = $inferredFamily;
                    if ($inferredFamily !== 'Miniature') $activeMiniatureState = null;
                } elseif ($activeFamily === 'Miniature') {
                    // A concrete non-miniature item ends miniature list context.
                    $activeFamily = null;
                    $activeMiniatureState = null;
                }
                $req = $this->requirement($segment);
                if ($req !== null) $activeRequirement = $req;

                $prepared = $segment;
                if ($req === null && $activeRequirement !== null && $this->looksLikeWeapon($activeItem)) {
                    $prepared = $activeItem . ' ' . $activeRequirement . ' ' . trim($this->removeMatchedAlias($segment, $matches[0]));
                }
                if ($this->isItemHeader($segment, $matches[0])) {
                    $pendingHeader = $prepared;
                    continue;
                }

                // LITTYWATCH_PHASE8G5B_CONCRETE_SHARED_ATTRIBUTE_LIST
                // A concrete caster skin can carry several attributes under one
                // requirement:
                //
                //   Frog Scepter q9 insp SR
                //     -> Frog Scepter q9 Inspiration Magic
                //     -> Frog Scepter q9 Soul Reaping
                //
                // This branch handles segments that already contain the concrete
                // item itself; continuation-only segments are handled below.
                $sharedAttributeVariants = $this->expandSharedRequirementAttributeList(
                    $activeItem,
                    $prepared
                );

                if ($sharedAttributeVariants !== []) {
                    foreach ($sharedAttributeVariants as $expanded) {
                        $out[] = $expanded;
                    }
                    continue;
                }

                foreach ($this->expandRequirements($prepared) as $expanded) $out[] = $expanded;
                continue;
            }

            // An explicit weapon family starts a new context boundary. Without
            // this guard, "Q9 FC BDS, Q8 Tac Shield" can inherit BDS into the
            // generic Shield clause merely because Shield has no concrete skin match.
            if ($activeItem !== null) {
                $explicitFamily = $this->explicitFamilyInSegment($segment);
                $activeItemFamily = $this->familyFromItem($activeItem);
                if ($explicitFamily !== null && $activeItemFamily !== null && $explicitFamily !== $activeItemFamily) {
                    if ($pendingHeader !== null) { $out[] = $pendingHeader; $pendingHeader = null; }
                    $activeItem = null;
                    $activeFamily = $explicitFamily;
                    $req = $this->requirement($segment);
                    if ($req !== null) $activeRequirement = $req;
                    $out[] = $segment;
                    continue;
                }
            }

            if ($activeItem !== null && $this->isModifierOnlyDescriptor($segment)) {
                // A pipe-separated modifier belongs to the previous item, e.g.
                // "Q11 Eternal Blade | Insc". Merge it into the pending header
                // instead of creating a fake item called "inscribable".
                if ($pendingHeader !== null) {
                    $pendingHeader = trim($pendingHeader . ' ' . $segment);
                }
                continue;
            }

            // LITTYWATCH_PHASE8F3_BDS_ATTRIBUTE_CONTINUATION
            // Compact BDS advertisements commonly use:
            //
            //   BDS all q9 (...) // q10 dom/death/insp/ES/FC/...
            //
            // At this point older family logic can append a generic "Staff" to
            // the q10 continuation. Preserve the already-known concrete BDS skin
            // instead of allowing that generic shadow to become its own market item.
            if ($activeItem !== null
                && mb_strtolower($activeItem) === 'bone dragon staff'
                && preg_match(
                    '/^(?:q|r|rq|req(?:uirement)?)\s*\d{1,2}\b/iu',
                    trim($segment)
                )
            ) {
                $clean = preg_replace(
                    '/\s+Staff\s*$/iu',
                    '',
                    trim($segment)
                ) ?? trim($segment);

                $pendingHeader = null;
                $prepared = $activeItem . ' ' . $clean;

                $attributeVariants = $this->expandSharedRequirementAttributeList(
                    $activeItem,
                    $clean
                );

                if ($attributeVariants === []) {
                    $attributeVariants = $this->expandAttributeRequirementPairs(
                        $activeItem,
                        $clean
                    );
                }

                if ($attributeVariants !== []) {
                    foreach ($attributeVariants as $expanded) {
                        $out[] = $expanded;
                    }
                } else {
                    foreach ($this->expandRequirements($prepared) as $expanded) {
                        $out[] = $expanded;
                    }
                }

                continue;
            }

            if ($activeItem !== null && $this->isVariantDescriptor($segment)) {
                // The previous bare item was a context header, not an extra offer.
                $pendingHeader = null;
                $prepared = $activeItem . ' ' . $segment;
                if ($this->requirement($segment) === null && $activeRequirement !== null) {
                    $prepared = $activeItem . ' ' . $activeRequirement . ' ' . $segment;
                }
                $attributeVariants = $this->expandSharedRequirementAttributeList($activeItem, $segment);
                if ($attributeVariants === []) {
                    $attributeVariants = $this->expandAttributeRequirementPairs($activeItem, $segment);
                }
                if ($attributeVariants !== []) {
                    foreach ($attributeVariants as $expanded) $out[] = $expanded;
                } else {
                    foreach ($this->expandRequirements($prepared) as $expanded) $out[] = $expanded;
                }
                continue;
            }

            if ($activeFamily !== null) {
                if ($pendingHeader !== null) { $out[] = $pendingHeader; $pendingHeader = null; }
                $candidate = $this->attachFamily($segment, $activeFamily, $activeRequirement, $activeMiniatureState);
                if ($this->items->matchAll($candidate) !== []) {
                    foreach ($this->expandRequirements($candidate) as $expanded) $out[] = $expanded;
                    $candidateMatches = $this->items->matchAll($candidate);
                    if ($candidateMatches !== []) $activeItem = (string)$candidateMatches[0]['item'];
                    continue;
                }
            }

            if ($pendingHeader !== null) { $out[] = $pendingHeader; $pendingHeader = null; }
            $out[] = $segment;
        }

        if ($pendingHeader !== null) $out[] = $pendingHeader;
        return array_values($out);
    }

    /** @return array{state:?string}|null */
    private function miniatureHeader(string $segment): ?array
    {
        $clean = trim($segment);
        if (!preg_match(
            '/^(?:(unded(?:icated)?|ded(?:icated)?)\s+)?(?:(?:bday|birthday|cele(?:stial)?)\s+)?(?:miniatures?|minis?|mini\s*pets?|minipets?)(?:\s+list)?$/iu',
            $clean,
            $m
        )) {
            return null;
        }
        $state = null;
        if (!empty($m[1])) $state = str_starts_with(mb_strtolower($m[1]), 'un') ? 'unded' : 'ded';
        return ['state'=>$state];
    }
    /** @return array{family:string,requirement:?string}|null */
    private function familyHeader(string $segment): ?array
    {
        if (!preg_match('/^(?:(?:q|r|rq|req(?:uirement)?)\s*([0-9]{1,2})\s+)?(staves|staffs|staff|wands?|bows?|swords?|axes?|hammers?|shields?|spears?|scythes?|daggers?|focus(?:es)?|everlasting\s+tonics?|tonics?)(?:\s+skins?)?$/iu', trim($segment), $m)) {
            return null;
        }
        return [
            'family'=>$this->singularFamily($m[2]),
            'requirement'=>!empty($m[1]) ? 'q'.$m[1] : null,
        ];
    }

    private function singularFamily(string $family): string
    {
        $f = mb_strtolower($family);
        return match (true) {
            in_array($f, ['staves','staffs','staff'], true) => 'Staff',
            str_starts_with($f, 'wand') => 'Wand',
            str_starts_with($f, 'bow') => 'Bow',
            str_starts_with($f, 'sword') => 'Sword',
            str_starts_with($f, 'axe') => 'Axe',
            str_starts_with($f, 'hammer') => 'Hammer',
            str_starts_with($f, 'shield') => 'Shield',
            str_starts_with($f, 'spear') => 'Spear',
            str_starts_with($f, 'scythe') => 'Scythe',
            str_starts_with($f, 'dagger') => 'Daggers',
            str_starts_with($f, 'focus') => 'Focus',
            str_contains($f, 'tonic') => 'Everlasting Tonic',
            default => ucfirst($family),
        };
    }

    private function familyFromItem(string $item): ?string
    {
        foreach (['Miniature','Staff','Wand','Scepter','Bow','Sword','Axe','Hammer','Shield','Spear','Scythe','Daggers','Dagger','Focus','Tonic'] as $family) {
            if (preg_match('/\b'.preg_quote($family,'/').'\b/iu', $item)) {
                if ($family === 'Dagger') return 'Daggers';
                if ($family === 'Tonic') return 'Everlasting Tonic';
                if ($family === 'Scepter') return 'Wand';
                return $family;
            }
        }
        return null;
    }

    private function explicitFamilyInSegment(string $segment): ?string
    {
        if (!preg_match('/\b(staves|staffs|staff|wands?|bows?|swords?|axes?|hammers?|shields?|spears?|scythes?|daggers?|focus(?:es)?)\b/iu', $segment, $m)) {
            return null;
        }
        return $this->singularFamily($m[1]);
    }

    private function looksLikeWeapon(string $item): bool
    {
        return $this->familyFromItem($item) !== null;
    }

    private function isVariantDescriptor(string $segment): bool
    {
        $attributePattern = '/\b(?:air|blood|sr|soul\s+reaping|fc|fast\s+casting|es|energy\s+storage|illu(?:sion)?|illus(?:ion)?|dom(?:ination)?|df|divine\s+favor|smite|smiting|resto(?:ration)?|com(?:muning)?|chan(?:neling)?|spaw(?:ning)?|inspi(?:ration)?|fire|water|earth|curs(?:es)?|death|heal(?:ing)?|prot(?:ection)?|strength|str|tac(?:tics)?|lead(?:ership)?)\b/iu';
        if (!preg_match($attributePattern, $segment)) return false;

        $remaining = preg_replace($attributePattern, ' ', $segment) ?? $segment;
        $remaining = preg_replace('/\b(?:q|r|rq|req(?:uirement)?)\s*\d{1,2}(?:\s*\/\s*\d{1,2})*\b/iu', ' ', $remaining) ?? $remaining;

        // LITTYWATCH_PHASE8F41_PRICED_VARIANT_DESCRIPTOR
        // Local prices do not make an attribute continuation a new item:
        //   q10 Heal 6e, Fire 4e, Death 4e
        $remaining = preg_replace(
            '/\b\d+(?:[.,]\d+)?\s*(?:a|e|k)(?:\s*\/\s*(?:ea|each))?\b/iu',
            ' ',
            $remaining
        ) ?? $remaining;

        $remaining = preg_replace('/[()\[\],+\-\/\s]+/u', '', $remaining) ?? $remaining;
        return $remaining === '';
    }

    private function isModifierOnlyDescriptor(string $segment): bool
    {
        $clean = trim($segment);
        if ($clean === '') return false;
        $clean = preg_replace('/\b(?:insc|inscr|inscribable|inscriptable|os|old\s*school|unid(?:entified)?|unded(?:icated)?|ded(?:icated)?)\b/iu', ' ', $clean) ?? $clean;
        $clean = preg_replace('/\b(?:q|r|rq|req(?:uirement)?)\s*\d{1,2}\b/iu', ' ', $clean) ?? $clean;
        $clean = preg_replace('/\b(?:pm|offer|offers|price)\b.*$/iu', ' ', $clean) ?? $clean;
        $clean = preg_replace('/[^\p{L}\p{N}]+/u', '', $clean) ?? $clean;
        return $clean === '';
    }

    private function requirement(string $segment): ?string
    {
        return preg_match('/\b(?:q|r|rq|req(?:uirement)?)\s*([0-9]{1,2})\b/iu', $segment, $m) ? 'q'.$m[1] : null;
    }

    /** @return list<string> */
    private function expandRequirements(string $segment): array
    {
        if (preg_match('/\b(?:q|r)\s*(\d{1,2})\s*-\s*(\d{1,2})\b/iu', $segment, $m)) {
            $from=(int)$m[1]; $to=(int)$m[2];
            if ($to >= $from && ($to-$from) <= 10) {
                $out=[];
                for($q=$from;$q<=$to;$q++) $out[] = preg_replace('/'.preg_quote($m[0],'/').'/u', 'q'.$q, $segment, 1) ?? $segment;
                return $out;
            }
        }
        if (!preg_match('/\b(?:q|r)\s*(\d{1,2}(?:\s*\/\s*\d{1,2})+)\b/iu', $segment, $m)) {
            return [$segment];
        }
        $raw = $m[0];
        $numbers = preg_split('/\s*\/\s*/u', $m[1]) ?: [];
        $out = [];
        foreach ($numbers as $number) {
            if ($number === '') continue;
            $out[] = preg_replace('/'.preg_quote($raw,'/').'/u', 'q'.$number, $segment, 1) ?? $segment;
        }
        return $out !== [] ? $out : [$segment];
    }

    /** @return list<string> */
    /**
     * LITTYWATCH_PHASE8G5_SHARED_Q_ATTRIBUTE_LIST
     *
     * Compact caster notation can put several attributes behind one requirement:
     *
     *   Frog Scepter q9 insp SR
     *     -> Frog Scepter q9 Inspiration Magic
     *     -> Frog Scepter q9 Soul Reaping
     *
     * Only expand when every remaining token is a known attribute abbreviation.
     *
     * @return list<string>
     */
    private function expandSharedRequirementAttributeList(string $item, string $segment): array
    {
        if (!preg_match(
            '/\b(?:q|r|rq|req(?:uirement)?)\s*(\d{1,2})\s+(.+)$/iu',
            trim($segment),
            $m
        )) {
            return [];
        }

        $q = $m[1];
        $tail = trim($m[2]);

        // Keep this deliberately strict. Prices/mods/prose mean this is not a
        // pure shared-requirement attribute list.
        if ($tail === '' || preg_match(
            '/\b\d+(?:[.,]\d+)?\s*(?:a|e|k)\b|[+%]|20\/20|15\^50|offer|pm\b/iu',
            $tail
        )) {
            return [];
        }

        $aliases = [
            'air' => 'Air Magic',
            'blood' => 'Blood Magic',
            'sr' => 'Soul Reaping',
            'soul reaping' => 'Soul Reaping',
            'fc' => 'Fast Casting',
            'fast casting' => 'Fast Casting',
            'es' => 'Energy Storage',
            'energy storage' => 'Energy Storage',
            'illu' => 'Illusion Magic',
            'illus' => 'Illusion Magic',
            'illusion' => 'Illusion Magic',
            'dom' => 'Domination Magic',
            'domination' => 'Domination Magic',
            'df' => 'Divine Favor',
            'divine' => 'Divine Favor',
            'divine favor' => 'Divine Favor',
            'smite' => 'Smiting Prayers',
            'smiting' => 'Smiting Prayers',
            'resto' => 'Restoration Magic',
            'restoration' => 'Restoration Magic',
            'comm' => 'Communing',
            'com' => 'Communing',
            'communing' => 'Communing',
            'chan' => 'Channeling Magic',
            'chann' => 'Channeling Magic',
            'channeling' => 'Channeling Magic',
            'spaw' => 'Spawning Power',
            'spawning' => 'Spawning Power',
            'insp' => 'Inspiration Magic',
            'inspi' => 'Inspiration Magic',
            'inspiration' => 'Inspiration Magic',
            'fire' => 'Fire Magic',
            'water' => 'Water Magic',
            'earth' => 'Earth Magic',
            'curs' => 'Curses',
            'curses' => 'Curses',
            'death' => 'Death Magic',
            'heal' => 'Healing Prayers',
            'healing' => 'Healing Prayers',
            'prot' => 'Protection Prayers',
            'protection' => 'Protection Prayers',
            'str' => 'Strength',
            'strength' => 'Strength',
            'tac' => 'Tactics',
            'tact' => 'Tactics',
            'tactics' => 'Tactics',
            'lead' => 'Leadership',
            'leadership' => 'Leadership',
        ];

        // Slash, comma and whitespace are all observed compact separators.
        // Multi-word canonical aliases are normalized first.
        $normalized = mb_strtolower($tail);
        $normalized = preg_replace('/\bsoul\s+reaping\b/iu', 'sr', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bfast\s+casting\b/iu', 'fc', $normalized) ?? $normalized;
        $normalized = preg_replace('/\benergy\s+storage\b/iu', 'es', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bdivine\s+favor\b/iu', 'df', $normalized) ?? $normalized;

        $tokens = array_values(array_filter(
            preg_split('/[\s,\/]+/u', trim($normalized)) ?: [],
            static fn(string $v): bool => $v !== ''
        ));

        if (count($tokens) < 2) {
            return [];
        }

        $attributes = [];

        foreach ($tokens as $token) {
            $key = mb_strtolower(trim($token));

            if (!isset($aliases[$key])) {
                return [];
            }

            $attributes[$aliases[$key]] = true;
        }

        if (count($attributes) < 2) {
            return [];
        }

        $out = [];

        foreach (array_keys($attributes) as $attribute) {
            $out[] = trim($item . ' q' . $q . ' ' . $attribute);
        }

        return $out;
    }

    private function expandAttributeRequirementPairs(string $item, string $segment): array
    {
        $attribute = '(?:es|energy\\s+storage|fc|fast\\s+casting|sr|soul\\s+reaping|spaw(?:ning)?|dom(?:ination)?|illu(?:sion)?|inspi(?:ration)?|heal(?:ing)?|smite|smiting|fire|water|air|earth|blood|death|curs(?:es)?|resto(?:ration)?|com(?:muning)?|chan(?:neling)?|df|divine\\s+favor|str(?:ength)?|tac(?:tics)?|lead(?:ership)?)';

        // LITTYWATCH_PHASE8F41_SHARED_REQUIREMENT_ATTRIBUTE_LIST
        // A requirement at the start owns all following comma-separated
        // attribute variants until another requirement is explicitly stated:
        //
        //   q10 Heal 6e, Fire 4e, Death 4e
        //     -> Item q10 Heal 6e
        //     -> Item q10 Fire 4e
        //     -> Item q10 Death 4e
        //
        // Require every comma member to look like an attribute variant so
        // ordinary market lists cannot inherit a requirement accidentally.
        if (preg_match(
            '/^(?:q|r)\\s*(\\d{1,2})\\s+(.+)$/iu',
            trim($segment),
            $shared
        ) && str_contains($shared[2], ',')) {
            $q = $shared[1];
            $parts = array_values(array_filter(array_map(
                'trim',
                preg_split('/\\s*,\\s*/u', $shared[2]) ?: []
            )));

            if (count($parts) >= 2) {
                $out = [];
                $valid = true;

                foreach ($parts as $part) {
                    if (!preg_match(
                        '/^(' . $attribute . ')(?:\\s+\\d+(?:[.,]\\d+)?\\s*(?:a|e|k)(?:\\s*\\/\\s*(?:ea|each))?)?$/iu',
                        $part
                    )) {
                        $valid = false;
                        break;
                    }

                    $out[] = trim($item . ' q' . $q . ' ' . $part);
                }

                if ($valid && count($out) >= 2) {
                    return $out;
                }
            }
        }

        // Existing explicit-pair behaviour:
        // q9 Dom, q10 Fire, q11 Blood ...
        if (!preg_match_all(
            '/\\b(?:q|r)\\s*(\\d{1,2})\\s+(' . $attribute . ')/iu',
            $segment,
            $matches,
            PREG_SET_ORDER
        )) {
            return [];
        }

        if (count($matches) < 2) return [];

        $out=[];
        foreach($matches as $m) {
            $out[] = trim($item.' q'.$m[1].' '.$m[2]);
        }
        return $out;
    }

    private function attachFamily(string $segment, string $family, ?string $requirement, ?string $miniatureState = null): string
    {
        $segment = trim($segment);
        if ($family === 'Miniature') {
            $localState = $miniatureState;
            if (preg_match('/^(unded(?:icated)?|ded(?:icated)?)\s+/iu', $segment, $sm)) {
                $localState = str_starts_with(mb_strtolower($sm[1]), 'un') ? 'unded' : 'ded';
                $segment = trim(preg_replace('/^(?:unded(?:icated)?|ded(?:icated)?)\s+/iu', '', $segment, 1) ?? $segment);
            }
            $segment = trim(preg_replace('/^mini(?:ature)?\s+/iu', '', $segment, 1) ?? $segment);
            $candidate = trim('Miniature ' . $segment . ($localState !== null ? ' ' . $localState : ''));
            return $candidate;
        }
        $suffix = '';
        if (preg_match('/^(.*?)(\s*\([^)]*\))$/u', $segment, $m)) {
            $segment = trim($m[1]);
            $suffix = $m[2];
        }
        $candidate = $family === 'Everlasting Tonic'
            ? trim('Everlasting ' . $segment . ' Tonic' . $suffix)
            : trim($segment . ' ' . $family . $suffix);
        if ($requirement !== null && $this->requirement($candidate) === null) {
            $candidate = $requirement . ' ' . $candidate;
        }
        return $candidate;
    }

    /** @param array<string,mixed> $match */
    private function isItemHeader(string $segment, array $match): bool
    {
        $rest = trim($this->removeMatchedAlias($segment, $match));
        return $rest === '' || (bool)preg_match('/^(?:q|r|rq|req(?:uirement)?)\s*\d{1,2}$/iu', $rest);
    }

    /** @param array<string,mixed> $match */
    private function removeMatchedAlias(string $segment, array $match): string
    {
        $alias = trim((string)($match['alias'] ?? ''));
        if ($alias === '') return $segment;
        return trim(preg_replace('/'.preg_quote($alias,'/').'/iu', '', $segment, 1) ?? $segment);
    }
}
