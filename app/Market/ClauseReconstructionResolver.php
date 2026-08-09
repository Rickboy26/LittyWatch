<?php
declare(strict_types=1);

namespace LittyWatch\Market;

use LittyWatch\Knowledge\KnowledgeBase;
use PDO;

/**
 * Phase 4B: reconstruct a concrete market identity and structured weapon
 * properties from the complete trade clause before the strict catalogue gate.
 *
 * Safety rule: this class never creates an identity. Every recovered identity
 * must resolve to exactly one active kb_item (by canonical name or unique alias).
 */
final class ClauseReconstructionResolver
{
    /** @var array<string,string> */
    private const ATTRIBUTE_ALIASES = [
        'com'=>'Command','command'=>'Command',
        'mot'=>'Motivation','motivation'=>'Motivation',
        'lead'=>'Leadership','leadership'=>'Leadership',
        'str'=>'Strength','strength'=>'Strength',
        'tac'=>'Tactics','tact'=>'Tactics','tactics'=>'Tactics',
        'div'=>'Divine Favor','divine'=>'Divine Favor','divine favor'=>'Divine Favor',
        'heal'=>'Healing Prayers','healing'=>'Healing Prayers','healing prayers'=>'Healing Prayers',
        'prot'=>'Protection Prayers','protection'=>'Protection Prayers','protection prayers'=>'Protection Prayers',
        'smit'=>'Smiting Prayers','smiting'=>'Smiting Prayers','smiting prayers'=>'Smiting Prayers',
        'chan'=>'Channeling Magic','channel'=>'Channeling Magic','channeling'=>'Channeling Magic','channeling magic'=>'Channeling Magic',
        'comm'=>'Communing','communing'=>'Communing',
        'rest'=>'Restoration Magic','resto'=>'Restoration Magic','restoration'=>'Restoration Magic','restoration magic'=>'Restoration Magic',
        'death'=>'Death Magic','death magic'=>'Death Magic',
        'blood'=>'Blood Magic','blood magic'=>'Blood Magic',
        'curs'=>'Curses','curse'=>'Curses','curses'=>'Curses',
        'sr'=>'Soul Reaping','soul reaping'=>'Soul Reaping',
        'dom'=>'Domination Magic','domination'=>'Domination Magic','domination magic'=>'Domination Magic',
        'illu'=>'Illusion Magic','illus'=>'Illusion Magic','illusion'=>'Illusion Magic','illusion magic'=>'Illusion Magic',
        'insp'=>'Inspiration Magic','inspiration'=>'Inspiration Magic','inspiration magic'=>'Inspiration Magic',
        'fc'=>'Fast Casting','fast casting'=>'Fast Casting',
        'fire'=>'Fire Magic','fire magic'=>'Fire Magic',
        'water'=>'Water Magic','water magic'=>'Water Magic',
        'air'=>'Air Magic','air magic'=>'Air Magic',
        'earth'=>'Earth Magic','earth magic'=>'Earth Magic',
        'es'=>'Energy Storage','energy storage'=>'Energy Storage',
        'mark'=>'Marksmanship','marks'=>'Marksmanship','marksmanship'=>'Marksmanship',
        'beast'=>'Beast Mastery','beast mastery'=>'Beast Mastery',
        'exp'=>'Expertise','expertise'=>'Expertise',
        'dag'=>'Dagger Mastery','dagger'=>'Dagger Mastery','dagger mastery'=>'Dagger Mastery',
        'crit'=>'Critical Strikes','critical'=>'Critical Strikes','critical strikes'=>'Critical Strikes',
        'scy'=>'Scythe Mastery','scythe'=>'Scythe Mastery','scythe mastery'=>'Scythe Mastery',
        'wind'=>'Wind Prayers','wind prayers'=>'Wind Prayers',
        'earthp'=>'Earth Prayers','earth prayers'=>'Earth Prayers',
        'spear'=>'Spear Mastery','spear mastery'=>'Spear Mastery',
    ];

    /** @var array<string,string> */
    private const CANONICAL_SHORTHAND = [
        'eshield'=>'Eternal Shield',
        'e shield'=>'Eternal Shield',
        'artifact flame'=>'Flame Artifact',
        'flame artifact'=>'Flame Artifact',
        'crystalline'=>'Crystalline Sword',
        'crystalline sword'=>'Crystalline Sword',
        'amethys aegis'=>'Amethyst Aegis',
        'amethyst aegis'=>'Amethyst Aegis',
        'eaglecrest'=>'Eaglecrest Axe',
        'eagle crest'=>'Eaglecrest Axe',
        'ghozers key'=>"Ghozer's Key",
        'ghozer s key'=>"Ghozer's Key",
        'ghozer key'=>"Ghozer's Key",
        'zaishen key'=>'Zaishen Key',
        'zkey'=>'Zaishen Key',
        'zkeys'=>'Zaishen Key',
        'honey'=>'Jar of Honey',
    ];

    public function __construct(private readonly PDO $pdo) {}

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>|null
     */
    public function reconstruct(array $row,string $message): ?array
    {
        $item=trim((string)($row['item']??''));
        $raw=trim((string)($row['raw_segment']??''));
        $clause=$raw!==''?$raw:$message;
        if($clause==='')return null;

        $identity=$this->recoverIdentity($item,$clause);
        if($identity===null)return null;

        $row['item']=$identity['name'];
        $row['item_key']=$identity['key'];
        $row['market_key']=$identity['key'];

        $this->applyRequirement($row,$clause);
        $this->applyAttribute($row,$clause);
        $this->applyWeaponFlags($row,$clause);
        $this->applyClauseSemantics($row,$clause);
        return $row;
    }

    /** @return array{key:string,name:string}|null */
    private function recoverIdentity(string $item,string $clause): ?array
    {
        $normalizedClause=KnowledgeBase::normalize(str_replace(['’','´','`'],"'",$clause));
        $normalizedItem=KnowledgeBase::normalize(str_replace(['’','´','`'],"'",$item));

        // Explicit deterministic trader shorthand. The target still must exist
        // uniquely in kb_items/aliases, so deployments with a different catalog
        // simply remain unresolved instead of inventing an item.
        foreach(self::CANONICAL_SHORTHAND as $needle=>$canonical){
            if($this->containsPhrase($normalizedClause,$needle)||$this->containsPhrase($normalizedItem,$needle)){
                $hit=$this->uniqueExactOrAlias($canonical);
                if($hit!==null)return $hit;
            }
        }

        // Search full-clause labels. This recovers a concrete skin when the
        // parser emitted only a generic family. Longest unique label wins.
        $family=$this->family($item);
        $rows=$this->pdo->query("SELECT i.key,i.name,i.category_key,a.alias FROM kb_items i LEFT JOIN kb_aliases a ON a.item_key=i.key WHERE i.active=1")->fetchAll();
        /** @var array<string,array{key:string,name:string,len:int}> $hits */
        $hits=[];
        foreach($rows as $r){
            $key=(string)$r['key'];$name=CanonicalMarketIdentity::nameFor((string)$r['name'],$key);
            if($this->isGeneric($name)||CanonicalMarketIdentity::isWikiDisambiguator($name))continue;
            if($family!==null&&!$this->familyCompatible($family,$name,(string)($r['category_key']??'')))continue;
            foreach([(string)$r['name'],(string)($r['alias']??'')] as $label){
                $needle=KnowledgeBase::normalize($label);
                if(mb_strlen($needle)<4||$this->isGeneric($needle))continue;
                if(!$this->containsPhrase($normalizedClause,$needle))continue;
                $len=mb_strlen($needle);
                if(!isset($hits[$key])||$len>$hits[$key]['len'])$hits[$key]=['key'=>$key,'name'=>$name,'len'=>$len];
            }
        }
        if(!$hits)return null;
        usort($hits,static fn(array $a,array $b):int=>$b['len']<=>$a['len']);
        if(isset($hits[1])&&$hits[1]['len']===$hits[0]['len'])return null;
        return ['key'=>$hits[0]['key'],'name'=>$hits[0]['name']];
    }

    /** @param array<string,mixed> $row */
    private function applyRequirement(array &$row,string $clause): void
    {
        if(($row['requirement']??null)!==null)return;
        if(preg_match('/\b(?:q|r|req)\s*([7-9]|1[0-3])\b/iu',$clause,$m))$row['requirement']=(int)$m[1];
    }

    /** @param array<string,mixed> $row */
    private function applyAttribute(array &$row,string $clause): void
    {
        if(trim((string)($row['attribute_name']??''))!=='')return;
        $n=' '.KnowledgeBase::normalize($clause).' ';
        $matches=[];
        foreach(self::ATTRIBUTE_ALIASES as $token=>$canonical){
            if(preg_match('/(?:^|\s)'.preg_quote($token,'/').'(?:$|\s)/u',$n))$matches[$canonical]=mb_strlen($token);
        }
        if(count($matches)!==1)return;
        $name=(string)array_key_first($matches);
        $row['attribute_name']=$name;
        $row['attribute_key']=$this->key($name);
    }

    /** @param array<string,mixed> $row */
    private function applyWeaponFlags(array &$row,string $clause): void
    {
        $n=KnowledgeBase::normalize($clause);
        if(preg_match('/\b(?:os|oldschool|old school|pre nerf|prenerf)\b/u',$n))$row['is_oldschool']=1;
        if(preg_match('/\b(?:insc|inscribable|inscribable)\b/u',$n))$row['is_inscribable']=1;
    }

    /** @param array<string,mixed> $row */
    private function applyClauseSemantics(array &$row,string $clause): void
    {
        $relevant=$this->decode((string)($row['relevant_json']??'{}'));
        $mods=$this->decode((string)($row['mods_json']??'{}'));

        if(preg_match('/\+\s*(\d{1,2})\s*(?:%\s*)?(?:with|while)\s+ench(?:anted|ant(?:ed)?)?/iu',$clause,$m)){
            $mods['damage_while_enchanted']=(int)$m[1];
        }
        if(preg_match('/\+\s*(\d{1,2})\s*(?:energy|ene)\b/iu',$clause,$m)){
            $relevant['energy_bonus']=(int)$m[1];
        }
        if(preg_match('/\b(blue|purple|gold|white)\b/iu',$clause,$m)){
            $relevant['rarity']=mb_strtolower($m[1]);
        }
        if(isset($row['requirement'])&&$row['requirement']!==null)$relevant['requirement']=(int)$row['requirement'];
        if(trim((string)($row['attribute_name']??''))!==''){
            $relevant['attribute']=(string)$row['attribute_name'];
            $relevant['attribute_key']=(string)$row['attribute_key'];
        }
        if((int)($row['is_oldschool']??0)===1)$relevant['oldschool']=true;
        if((int)($row['is_inscribable']??0)===1)$relevant['inscribable']=true;

        $row['relevant_json']=$this->encode($relevant);
        $row['mods_json']=$this->encode($mods);
    }

    /** @return array{key:string,name:string}|null */
    private function uniqueExactOrAlias(string $value): ?array
    {
        $norm=KnowledgeBase::normalize($value);
        $st=$this->pdo->prepare("SELECT i.key,i.name FROM kb_items i LEFT JOIN kb_aliases a ON a.item_key=i.key WHERE i.active=1 AND (lower(trim(i.name))=lower(trim(:raw)) OR a.normalized_alias=:norm) GROUP BY i.key,i.name LIMIT 2");
        $st->execute([':raw'=>trim($value),':norm'=>$norm]);$rows=$st->fetchAll();
        return count($rows)===1?['key'=>(string)$rows[0]['key'],'name'=>CanonicalMarketIdentity::nameFor((string)$rows[0]['name'],(string)$rows[0]['key'])]:null;
    }

    private function containsPhrase(string $haystack,string $needle): bool
    {
        return preg_match('/(?:^|\s)'.preg_quote($needle,'/').'(?:$|\s)/u',' '.$haystack.' ')===1;
    }

    private function family(string $value): ?string
    {
        $n=KnowledgeBase::normalize($value);
        foreach(['axe','shield','staff','scythe','sword','hammer','spear','wand','bow','daggers','dagger','focus','focus item'] as $f){
            if($n===$f)return $f==='dagger'?'daggers':($f==='focus item'?'focus':$f);
        }
        return null;
    }

    private function familyCompatible(string $family,string $name,string $category): bool
    {
        $n=KnowledgeBase::normalize($name.' '.$category);
        $tests=[
            'axe'=>'axe','shield'=>'shield','staff'=>'staff','scythe'=>'scythe','sword'=>'sword','hammer'=>'hammer',
            'spear'=>'spear','wand'=>'wand','bow'=>'bow','daggers'=>'dagger','focus'=>'focus|artifact|offhand|off hand',
        ];
        return preg_match('/\b(?:'.$tests[$family].')\b/u',$n)===1;
    }

    private function isGeneric(string $name): bool
    {
        return in_array(KnowledgeBase::normalize($name),[
            'axe','shield','staff','scythe','sword','hammer','spear','wand','bow','dagger','daggers','focus','focus item','weapon','weapons','weapon mods','mods'
        ],true);
    }

    /** @return array<string,mixed> */
    private function decode(string $json): array
    {
        $v=json_decode($json,true);return is_array($v)?$v:[];
    }
    /** @param array<string,mixed> $v */
    private function encode(array $v): string{return json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'{}';}
    private function key(string $v): string{return trim(preg_replace('/[^a-z0-9]+/','_',mb_strtolower($v))??'','_');}
}
