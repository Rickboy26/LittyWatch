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

        foreach ($segments as $raw) {
            $segment = trim($raw, " \t\n\r\0\x0B|,;");
            if ($segment === '') continue;

            if (preg_match('/^(?:q|r|rq|req(?:uirement)?)\s*([0-9]{1,2})$/iu', $segment, $m)) {
                $activeRequirement = 'q' . $m[1];
                continue;
            }

            $family = $this->familyHeader($segment);
            if ($family !== null) {
                if ($pendingHeader !== null) { $out[] = $pendingHeader; $pendingHeader = null; }
                $activeFamily = $family['family'];
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
                    foreach ($parts as $part) {
                        $candidate = $this->attachFamily($part, $activeFamily, $activeRequirement);
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

            $matches = $this->items->matchAll($segment);
            if ($matches !== []) {
                if ($pendingHeader !== null) { $out[] = $pendingHeader; $pendingHeader = null; }
                $activeItem = (string)$matches[0]['item'];
                $inferredFamily = $this->familyFromItem($activeItem);
                if ($inferredFamily !== null) $activeFamily = $inferredFamily;
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

            if ($activeItem !== null && $this->isVariantDescriptor($segment)) {
                // The previous bare item was a context header, not an extra offer.
                $pendingHeader = null;
                $prepared = $activeItem . ' ' . $segment;
                if ($this->requirement($segment) === null && $activeRequirement !== null) {
                    $prepared = $activeItem . ' ' . $activeRequirement . ' ' . $segment;
                }
                $attributeVariants = $this->expandAttributeRequirementPairs($activeItem, $segment);
                if ($attributeVariants !== []) {
                    foreach ($attributeVariants as $expanded) $out[] = $expanded;
                } else {
                    foreach ($this->expandRequirements($prepared) as $expanded) $out[] = $expanded;
                }
                continue;
            }

            if ($activeFamily !== null) {
                if ($pendingHeader !== null) { $out[] = $pendingHeader; $pendingHeader = null; }
                $candidate = $this->attachFamily($segment, $activeFamily, $activeRequirement);
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
        foreach (['Staff','Wand','Bow','Sword','Axe','Hammer','Shield','Spear','Scythe','Daggers','Dagger','Focus','Tonic'] as $family) {
            if (preg_match('/\b'.preg_quote($family,'/').'\b/iu', $item)) return $family === 'Dagger' ? 'Daggers' : ($family === 'Tonic' ? 'Everlasting Tonic' : $family);
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
    private function expandAttributeRequirementPairs(string $item, string $segment): array
    {
        if (!preg_match_all('/\b(?:q|r)\s*(\d{1,2})\s+(es|energy\s+storage|fc|fast\s+casting|sr|soul\s+reaping|spaw(?:ning)?|dom(?:ination)?|illu(?:sion)?|inspi(?:ration)?|heal(?:ing)?|smite|smiting|fire|water|air|earth|blood|death|curs(?:es)?|resto(?:ration)?|com(?:muning)?|chan(?:neling)?|df|divine\s+favor|str(?:ength)?|tac(?:tics)?|lead(?:ership)?)/iu', $segment, $matches, PREG_SET_ORDER)) {
            return [];
        }
        if (count($matches) < 2) return [];
        $out=[];
        foreach($matches as $m) $out[] = trim($item.' q'.$m[1].' '.$m[2]);
        return $out;
    }

    private function attachFamily(string $segment, string $family, ?string $requirement): string
    {
        $segment = trim($segment);
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
