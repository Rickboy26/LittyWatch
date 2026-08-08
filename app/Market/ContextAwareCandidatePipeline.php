<?php
declare(strict_types=1);

namespace LittyWatch\Market;

use LittyWatch\Knowledge\KnowledgeBase;
use PDO;

/**
 * Phase 3Z: context-aware candidate pipeline.
 *
 * This layer sits between parser output and catalogue resolution. It turns
 * obvious trader lists/bundles into candidate identities, carries shared
 * semantics (miniature dedication / family, tome suffixes), and suppresses
 * syntactic fragments before they can become structured offers.
 *
 * It does NOT decide that an item exists. CatalogFirstResolver / StrictCatalogGate
 * remain authoritative for that decision.
 */
final class ContextAwareCandidatePipeline
{
    /** @var list<string> */
    private const GENERIC_UMBRELLAS = [
        'weapon mods','mods','weapons','minis','miniatures','items','left',
        'axe','shield','staff','scythe','sword','hammer','spear','wand','bow','daggers',
        'tonic','focus','focus item','tome','tomes','elite tome','normal tome',
    ];

    public function __construct(private readonly PDO $pdo) {}

    /**
     * @param array<string,mixed> $row
     * @return list<array{item:string,raw_segment:string}>
     */
    public function expand(array $row, string $message): array
    {
        $item=trim((string)($row['item']??''));
        $raw=trim((string)($row['raw_segment']??''));
        $source=$this->sourceFor($item,$raw,$message);
        if($source==='' || !$this->looksLikeList($source))return [];

        $parts=$this->split($source);
        if(count($parts)<2 || count($parts)>16)return [];

        $parts=$this->inheritMiniatureContext($parts,$source);
        $parts=$this->inheritTomeContext($parts,$source);
        $parts=$this->inheritWeaponFamily($parts);
        $parts=$this->inheritSharedPrefix($parts);

        $gate=new NoiseFragmentGate();$out=[];
        foreach($parts as $part){
            $part=$this->cleanPart($part);
            if($part==='')continue;
            $noise=$gate->inspect($part,$part);
            if($noise['drop'])continue;
            $n=KnowledgeBase::normalize($part);
            if($n==='' || isset($out[$n]))continue;
            $out[$n]=['item'=>$part,'raw_segment'=>$part];
        }
        $values=array_values($out);
        if(count($values)<2)return [];

        // Phase 3Z.1 safety rail: never manufacture review rows from punctuation.
        // A list expansion is allowed only when every surviving candidate already
        // has concrete catalogue evidence (exact name, unique alias, or exact
        // miniature name after dedication cleanup). If one part is ambiguous,
        // keep the original parser row intact.
        foreach($values as $candidate){
            if(!$this->hasCatalogueEvidence($candidate['item']))return [];
        }
        return $values;
    }

    private function sourceFor(string $item,string $raw,string $message): string
    {
        $normalized=KnowledgeBase::normalize($item);

        // Prefer the parser's item candidate. It is already the narrowest piece
        // of text and avoids turning prices/attributes elsewhere in raw_segment
        // into fake items.
        if($this->looksLikeList($item))return $item;

        // Raw-segment expansion is reserved for semantic umbrella labels where
        // the parser explicitly told us that the actual identities live in the
        // surrounding text. Weapon-family umbrellas are deliberately excluded:
        // their raw text commonly contains req/mod/price punctuation.
        if($raw!=='' && in_array($normalized,[
            'minis','miniatures','tomes','elite tome','normal tome'
        ],true) && $this->looksLikeList($raw)){
            return $this->stripTradeNoise($raw);
        }

        // Phase 3Z.2: restore the proven Phase 3X upgrade-list case without
        // reopening generic weapon raw-segment splitting. Only an explicit
        // Weapon Mods umbrella plus a list containing upgrade semantics may use
        // raw context here. Every resulting part still has to pass catalogue
        // evidence below before the split is accepted.
        if($raw!=='' && in_array($normalized,['weapon mods','mods'],true)
            && $this->looksLikeList($raw) && $this->looksLikeUpgradeList($raw)){
            return $this->stripTradeNoise($raw);
        }
        return '';
    }

    private function hasCatalogueEvidence(string $item): bool
    {
        $candidate=trim($item);
        if($candidate==='')return false;

        // Dedication is a market variant, not part of the catalogue name.
        $candidate=preg_replace('/\s+(?:uded|unded|undedicated|un[- ]?ded|ded|dedicated)$/iu','',$candidate)??$candidate;
        $candidate=trim($candidate);

        $st=$this->pdo->prepare("SELECT COUNT(DISTINCT key) FROM kb_items WHERE active=1 AND lower(trim(name))=lower(trim(:n))");
        $st->execute([':n'=>$candidate]);
        if((int)$st->fetchColumn()===1)return true;

        $norm=KnowledgeBase::normalize($candidate);
        if($norm==='')return false;
        $st=$this->pdo->prepare("SELECT COUNT(DISTINCT i.key) FROM kb_aliases a JOIN kb_items i ON i.key=a.item_key WHERE i.active=1 AND a.normalized_alias=:a");
        $st->execute([':a'=>$norm]);
        if((int)$st->fetchColumn()===1)return true;

        // Bare miniature candidates may have been context-expanded with the
        // canonical prefix already, but keep this fallback for safety.
        if(!str_starts_with(mb_strtolower($candidate),'miniature ')){
            $st=$this->pdo->prepare("SELECT COUNT(DISTINCT key) FROM kb_items WHERE active=1 AND lower(trim(name))=lower(trim(:n))");
            $st->execute([':n'=>'Miniature '.$candidate]);
            if((int)$st->fetchColumn()===1)return true;
        }

        // Compact upgrade shorthand such as "Zealous Bow" is intentionally
        // not required to be a literal alias. The controlled resolver already
        // restricts upgrade searches to upgrade/mod/inscription catalogue
        // categories and only returns an unambiguous winner.
        if($this->looksLikeUpgradeCandidate($candidate)){
            $resolved=(new ControlledCatalogResolver($this->pdo))->resolve($candidate,'',$candidate);
            if($resolved!==null)return true;
        }
        return false;
    }

    private function looksLikeUpgradeList(string $value): bool
    {
        $n=KnowledgeBase::normalize($value);
        return preg_match('/\b(?:vamp|vampiric|zealous|mastery|fortitude|defense|shelter|enchanting|critical|crit|30hp|es\s*\+?5|sr\s*\+?\d+)\b/u',$n)===1
            && preg_match('/\b(?:bow|spear|staff|wand|shield|axe|sword|hammer|scythe|daggers?)\b/u',$n)===1;
    }

    private function looksLikeUpgradeCandidate(string $value): bool
    {
        $n=KnowledgeBase::normalize($value);
        return preg_match('/\b(?:vamp|vampiric|zealous|mastery|fortitude|defense|shelter|enchanting|critical|crit|30hp|es\s*\+?5|sr\s*\+?\d+)\b/u',$n)===1
            && preg_match('/\b(?:bow|spear|staff|wand|shield|axe|sword|hammer|scythe|daggers?)\b/u',$n)===1;
    }

    private function looksLikeList(string $value): bool
    {
        if(preg_match('/,|\||\s+and\s+|\s*&\s*/iu',$value))return true;
        // Alphabetic slash lists: Zhed/Livia, Party/Sweet, Naga/Oni/... .
        // Numeric/stat slashes (20/20, 5e/ea) are excluded.
        return preg_match('/(?<=[\p{L}\)])\s*\/\s*(?=[\p{L}\(])/u',$value)===1;
    }

    /** @return list<string> */
    private function split(string $source): array
    {
        // Protect semantic/stat slashes before list splitting.
        $source=preg_replace('/(?<=\d)\s*\/\s*(?=\d)|(?<=\d)\s*\/\s*(?=ea\b)|(?<=\d[ek])\s*\/\s*(?=ea\b)/iu','§SLASH§',$source)??$source;
        $parts=preg_split('/\s*(?:,|\||\s+and\s+|\s*&\s*|(?<=[\p{L}\)])\s*\/\s*(?=[\p{L}\(]))\s*/iu',$source)?:[];
        return array_values(array_filter(array_map(static fn(string $v):string=>trim(str_replace('§SLASH§','/',$v)), $parts),static fn(string $v):bool=>$v!==''));
    }

    /** @param list<string> $parts @return list<string> */
    private function inheritMiniatureContext(array $parts,string $source): array
    {
        $explicitMini=preg_match('/\bmini(?:ature)?s?\b/iu',$source)===1;
        $state='';
        if(preg_match('/\b(?:uded|unded|undedicated|un[- ]?ded)\b/iu',$source))$state=' unded';
        elseif(preg_match('/\b(?:ded|dedicated)\b/iu',$source))$state=' ded';

        // Bare slash lists such as Zhed/Livia are treated as miniature lists only
        // when every member has one and only one exact "Miniature <name>" item.
        if(!$explicitMini && $state==='' && !$this->allPartsAreMiniatures($parts))return $parts;

        $family='';
        $first=$this->stripMiniTokens($parts[0]);
        $tokens=preg_split('/\s+/u',$first)?:[];
        if(count($tokens)>=2 && in_array(mb_strtolower($tokens[0]),['celestial'],true))$family=$tokens[0].' ';

        foreach($parts as $i=>$part){
            $clean=$this->stripMiniTokens($part);
            if($i>0 && $family!=='' && !str_starts_with(mb_strtolower($clean),mb_strtolower(trim($family))))$clean=$family.$clean;
            if(!str_starts_with(mb_strtolower($clean),'miniature '))$clean='Miniature '.$clean;
            $parts[$i]=trim($clean.$state);
        }
        return $parts;
    }

    /** @param list<string> $parts */
    private function allPartsAreMiniatures(array $parts): bool
    {
        if(count($parts)<2)return false;
        foreach($parts as $part){
            $candidate=$this->cleanPart($this->stripMiniTokens($part));
            if($candidate==='')return false;
            $st=$this->pdo->prepare("SELECT COUNT(DISTINCT key) FROM kb_items WHERE active=1 AND lower(trim(name))=lower(trim(:n))");
            $st->execute([':n'=>'Miniature '.$candidate]);
            if((int)$st->fetchColumn()!==1)return false;
        }
        return true;
    }

    private function stripMiniTokens(string $value): string
    {
        $value=str_replace(['’','´','`'],"'",$value);
        $value=preg_replace("/^mini(?:ature)?s?\\s*['’]?s?\\s*[:\\-]?\\s*/iu",'',$value)??$value;
        $value=preg_replace('/^(?:uded|unded|undedicated|un[- ]?ded|ded|dedicated)\s+/iu','',$value)??$value;
        $value=preg_replace("/^mini(?:ature)?s?\\s*['’]?s?\\s*[:\\-]?\\s*/iu",'',$value)??$value;
        $value=preg_replace('/\s+(?:uded|unded|undedicated|un[- ]?ded|ded|dedicated)$/iu','',$value)??$value;
        $value=preg_replace('/\s+mini(?:ature)?s?$/iu','',$value)??$value;
        return trim(preg_replace('/\s+/u',' ',$value)??$value," \t\n\r\0\x0B:,-");
    }

    /** @param list<string> $parts @return list<string> */
    private function inheritTomeContext(array $parts,string $source): array
    {
        if(!preg_match('/\btomes?\b/iu',$source))return $parts;
        foreach($parts as $i=>$part){
            if(preg_match('/\btomes?\b/iu',$part))continue;
            $n=KnowledgeBase::normalize($part);
            if(in_array($n,['elite','elites'],true))$parts[$i]='Elite Tome';
            elseif($n==='normal')$parts[$i]='Normal Tome';
            elseif(preg_match('/^(war|warrior|ranger|rng|monk|nec|necromancer|mes|mesmer|ele|elementalist|sin|assa|assassin|rit|ritualist|para|paragon|derv|dervish)$/u',$n))$parts[$i]=$part.' Tome';
        }
        return $parts;
    }

    /** @param list<string> $parts @return list<string> */
    private function inheritWeaponFamily(array $parts): array
    {
        $families=['bow','spear','staff','wand','shield','axe','sword','hammer','scythe','daggers'];
        $family='';
        foreach($parts as $part){
            foreach($families as $f){if(preg_match('/\b'.preg_quote($f,'/').'s?\b/iu',$part)){$family=$f;break 2;}}
        }
        if($family==='')return $parts;
        foreach($parts as $i=>$part){
            $n=KnowledgeBase::normalize($part);
            if(!preg_match('/\b(?:vamp|vampiric|zealous|mastery|defense|fortitude|enchanting|shelter|swift|swiftness|30hp|es\s*\+?5|sr\s*\+?4)\b/u',$n))continue;
            if(!preg_match('/\b(?:bow|spear|staff|wand|shield|axe|sword|hammer|scythe|daggers?)\b/u',$n))$parts[$i]=trim($part.' '.$family);
        }
        return $parts;
    }

    /** @param list<string> $parts @return list<string> */
    private function inheritSharedPrefix(array $parts): array
    {
        if(count($parts)<2)return $parts;
        $first=trim($parts[0]);
        if(!preg_match('/^(celestial)\s+(.+)$/iu',$first,$m))return $parts;
        foreach($parts as $i=>$part){
            if($i===0)continue;
            if(!preg_match('/^'.preg_quote($m[1],'/').'\b/iu',$part))$parts[$i]=$m[1].' '.$part;
        }
        return $parts;
    }

    private function stripTradeNoise(string $value): string
    {
        $value=preg_replace('/^\s*(?:wts|wtb|wtt|selling|buying|sell|buy)\b\s*/iu','',$value)??$value;
        $value=preg_replace('/\b(?:pm|trade me|obo)\b.*$/iu','',$value)??$value;
        return trim(preg_replace('/\s+/u',' ',$value)??$value," \t\n\r\0\x0B,;|");
    }

    private function cleanPart(string $value): string
    {
        $value=preg_replace('/^\s*(?:wts|wtb|wtt)\b\s*/iu','',$value)??$value;
        $value=preg_replace('/\s+(?:obo|pm|trade me).*$/iu','',$value)??$value;
        $value=preg_replace('/^\s*(?:\d+[.,]?\d*\s*[x×]?\s*)/u','',$value)??$value;
        $value=preg_replace('/\s+\d+(?:[.,]\d+)?\s*(?:a|e|k)\s*(?:\/\s*ea|ea|each)?\s*$/iu','',$value)??$value;
        return trim(preg_replace('/\s+/u',' ',$value)??$value," \t\n\r\0\x0B,;|");
    }
}
