<?php
declare(strict_types=1);

namespace LittyWatch\Market;

use LittyWatch\Knowledge\KnowledgeBase;

/**
 * Phase 3X: turn obvious multi-item trader shorthand into independent candidate
 * identities before the strict catalog gate. This class only segments text; the
 * CatalogFirstResolver remains authoritative for whether every candidate maps
 * to a real active catalog item.
 */
final class ContextualOfferListResolver
{
    /** @var list<string> */
    private const GENERIC = [
        'weapon mods','mods','weapons','minis','miniatures','items','left',
        'axe','shield','staff','scythe','sword','hammer','spear','wand','bow','daggers','tonic','focus item',
    ];

    /** @return list<string> */
    public function candidates(string $item, string $context): array
    {
        $item = trim($item);
        $context = trim($context);
        $source = $item;

        // When the parser only produced a generic umbrella, the raw segment is
        // more informative (e.g. "Weapon Mods" from "Zealous, Vamp Bow").
        if ($this->isGeneric($item) && $context !== '') {
            $source = $this->stripTradeNoise($context);
        }

        if (!$this->looksLikeList($source)) return [];

        // Never split stat notation / prices such as 20/20, 15^50 or 5e/ea.
        $source = preg_replace('/(?<=\d)\/(?=\d)|(?<=\d)\/(?=ea\b)/iu', '§SLASH§', $source) ?? $source;
        $parts = preg_split('/\s*(?:,|\||\s+and\s+|\s*&\s*|\s+\/\s+)\s*/iu', $source) ?: [];
        $parts = array_values(array_filter(array_map(fn(string $v): string => $this->cleanPart(str_replace('§SLASH§','/',$v)), $parts), static fn(string $v): bool => $v !== ''));
        if (count($parts) < 2 || count($parts) > 12) return [];

        $parts = $this->inheritMiniatureContext($parts, $source);
        $parts = $this->inheritWeaponFamily($parts);
        $parts = $this->inheritSharedPrefix($parts);

        $out=[];
        foreach($parts as $part){
            $n=KnowledgeBase::normalize($part);
            if($n==='' || in_array($n,['left','for','elite','all','set','collection','x'],true)) continue;
            $out[$n]=$part;
        }
        return count($out)>=2 ? array_values($out) : [];
    }

    private function looksLikeList(string $value): bool
    {
        return preg_match('/,|\||\s+and\s+|\s*&\s*|\s+\/\s+/iu',$value)===1;
    }

    private function isGeneric(string $item): bool
    {
        return in_array(KnowledgeBase::normalize($item), self::GENERIC, true);
    }

    private function stripTradeNoise(string $value): string
    {
        $value=preg_replace('/^\s*(?:wts|wtb|wtt|selling|buying)\b\s*/iu','',$value)??$value;
        $value=preg_replace('/\b(?:pm|trade me|obo)\b.*$/iu','',$value)??$value;
        $value=preg_replace('/\b\d+(?:[.,]\d+)?\s*(?:a|e|k)\s*(?:\/\s*ea|ea|each)?\b/iu',' ',$value)??$value;
        return trim(preg_replace('/\s+/u',' ',$value)??$value," \t\n\r\0\x0B,;|");
    }

    private function cleanPart(string $value): string
    {
        $value=preg_replace('/^\s*(?:wts|wtb|wtt)\b\s*/iu','',$value)??$value;
        $value=preg_replace('/\s+(?:obo|pm|trade me).*$/iu','',$value)??$value;
        $value=preg_replace('/\s+\d+(?:[.,]\d+)?\s*(?:a|e|k)\s*(?:\/\s*ea|ea|each)?\s*$/iu','',$value)??$value;
        return trim($value," \t\n\r\0\x0B,;|");
    }

    /** @param list<string> $parts @return list<string> */
    private function inheritMiniatureContext(array $parts,string $source): array
    {
        if(!preg_match('/\b(?:mini(?:ature)?s?|uded|unded|undedicated|un[- ]?ded|ded|dedicated)\b/iu',$source)) return $parts;
        $state='';
        if(preg_match('/\b(?:uded|unded|undedicated|un[- ]?ded)\b/iu',$source))$state=' unded';
        elseif(preg_match('/\b(?:ded|dedicated)\b/iu',$source))$state=' ded';

        // "Uded Celestial Sheep and Rat" => carry "Celestial" into Rat.
        $family='';
        $first=preg_replace('/\b(?:mini(?:ature)?s?|uded|unded|undedicated|un[- ]?ded|ded|dedicated)\b/iu',' ',$parts[0])??$parts[0];
        $tokens=preg_split('/\s+/u',trim($first))?:[];
        if(count($tokens)>=2 && in_array(mb_strtolower($tokens[0]),['celestial'],true))$family=$tokens[0].' ';

        foreach($parts as $i=>$part){
            if(!preg_match('/\bmini(?:ature)?\b/iu',$part)){
                $clean=preg_replace('/\b(?:unded|undedicated|un[- ]?ded|ded|dedicated)\b/iu',' ',$part)??$part;
                $clean=trim(preg_replace('/\s+/u',' ',$clean)??$clean);
                if($i>0 && $family!=='' && !str_starts_with(mb_strtolower($clean),mb_strtolower(trim($family))))$clean=$family.$clean;
                $parts[$i]='Miniature '.$clean.$state;
            }
        }
        return $parts;
    }

    /** @param list<string> $parts @return list<string> */
    private function inheritWeaponFamily(array $parts): array
    {
        $family=null;
        foreach(array_reverse($parts) as $part){
            if(preg_match('/\b(bow|spear|staff|wand|shield|axe|sword|hammer|scythe|dagger|daggers)\b/iu',$part,$m)){$family=$m[1];break;}
        }
        if($family===null)return $parts;
        foreach($parts as $i=>$part){
            if(!preg_match('/\b(?:bow|spear|staff|wand|shield|axe|sword|hammer|scythe|dagger|daggers)\b/iu',$part)
                && preg_match('/\b(?:vamp|vampiric|zealous|mastery|fortitude|defense|shelter|enchanting|critical|crit|30hp|es\+5|sr\+\d+)\b/iu',$part)){
                $parts[$i]=trim($part).' '.$family;
            }
        }
        return $parts;
    }

    /** @param list<string> $parts @return list<string> */
    private function inheritSharedPrefix(array $parts): array
    {
        // Conservative support for "Powerstones, Stygian Gemstones"-style lists
        // intentionally does not invent lexical prefixes. Catalog aliases handle
        // each independent token. This hook exists for future proven families.
        return $parts;
    }
}
