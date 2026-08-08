<?php
declare(strict_types=1);

namespace LittyWatch\Market;

use LittyWatch\Knowledge\KnowledgeBase;
use PDO;

/**
 * Phase 3W: safe catalog recovery before StrictCatalogGate.
 *
 * This resolver never invents market identities. A recovery is returned only
 * when one active concrete kb_item wins unambiguously. It is intentionally
 * conservative: unresolved/ambiguous input remains review material.
 */
final class ControlledCatalogResolver
{
    /** @var list<string> */
    private const GENERIC_NAMES = [
        'wand','staff','bow','shield','tonic','focus','focus item','weapon','upgrade','mod','mods',
        'spear','sword','axe','hammer','scythe','dagger','daggers','offhand','off hand',
    ];

    /** @var array<string,string> */
    private const TOKEN_NORMALIZATION = [
        'wrap' => 'wrapping',
        'wraps' => 'wrapping',
        'vamp' => 'vampiric',
        'vampy' => 'vampiric',
        'ench' => 'enchanting',
        'enchant' => 'enchanting',
        'enchanting20' => 'enchanting',
        'crit' => 'critical',
        'def' => 'defense',
        'fort' => 'fortitude',
        'swift' => 'swiftness',
        'inscr' => 'inscription',
        'insc' => 'inscription',
        'spearhead' => 'spear',
        'bowstring' => 'bow',
    ];

    /** @var list<string> */
    private const UPGRADE_HINTS = [
        'mod','mods','upgrade','upgrades','wrapping','wrap','grip','haft','head','spearhead','snathe',
        'hilt','pommel','handle','core','bowstring','string','vamp','vampiric','zealous','enchanting',
        'fortitude','defense','shelter','mastery','inscription','insc',
    ];

    public function __construct(private readonly PDO $pdo) {}

    /** @return array{key:string,name:string,reason:string,score:float}|null */
    public function resolve(string $item, string $itemKey, string $context): ?array
    {
        $item = trim(str_replace(['’','´','`'], "'", $item));
        $context = trim(str_replace(['’','´','`'], "'", $context));
        if ($item === '' && $context === '') return null;

        // First recover compact/common spellings using the complete local context.
        foreach ($this->candidatePhrases($item, $context) as $phrase) {
            $exact = $this->uniqueExactOrAlias($phrase);
            if ($exact !== null && !$this->looksGeneric($exact['name'])) {
                return $exact + ['reason'=>'controlled_exact_alias','score'=>1.0];
            }
        }

        // Phase 4A pass 1: deterministic known-entity recovery. These are
        // spelling/market-shorthand rewrites only; the rewritten phrase must
        // still exist uniquely in the active catalogue before it is accepted.
        $known=$this->recoverKnownEntity($item,$context);
        if($known!==null)return $known + ['reason'=>'known_entity_recovery','score'=>1.0];

        // Phase 4A pass 2: when the parser only produced a generic weapon family,
        // inspect the same clause for an embedded concrete catalogue name/alias.
        // The longest unique family-compatible match wins. This lets "Eshield"
        // recover Eternal Shield without ever guessing from just "Shield".
        if($this->looksGenericWeapon($item)){
            $weapon=$this->embeddedConcreteWeapon($item,$context);
            if($weapon!==null)return $weapon + ['reason'=>'weapon_context_embedded_alias','score'=>1.0];
        }

        // Upgrade shorthand gets a category-restricted semantic search. This is
        // deliberately done before general fuzzy matching so "vamp spear" can
        // never become an arbitrary spear skin.
        if ($this->hasUpgradeContext($item.' '.$context)) {
            $upgrade = $this->bestUniqueSemantic($item, $context, true);
            if ($upgrade !== null) return $upgrade + ['reason'=>'controlled_upgrade_context'];
        }

        // General typo/compact resolver. Only a single high-confidence winner
        // with a meaningful margin is accepted.
        return $this->bestUniqueSemantic($item, $context, false);
    }

    /** @return list<string> */
    private function candidatePhrases(string $item, string $context): array
    {
        $phrases = [$item];
        $segment = $this->stripTradeNoise($context);
        if ($segment !== '') $phrases[] = $segment;

        foreach ([$item, $segment] as $value) {
            $norm = $this->normalizeShorthand($value);
            if ($norm !== '') $phrases[] = $norm;
            $expandedCoin=preg_replace('/\bgold\s+zc\b/iu','Gold Zaishen Coin',$value)??$value;
            if($expandedCoin!==$value)$phrases[]=$expandedCoin;

            // Common GW trade omission: "staff wrap enchanting" ->
            // "staff wrapping of enchanting". Exact catalog lookup still decides.
            $expanded = preg_replace('/\b(staff|wand|focus|spear|shield|bow)\s+wrapping\s+(?!of\b)([a-z][a-z -]{2,})$/iu', '$1 wrapping of $2', $norm) ?? $norm;
            if ($expanded !== $norm) $phrases[] = $expanded;
        }

        $out=[];
        foreach ($phrases as $phrase) {
            $n=KnowledgeBase::normalize($phrase);
            if ($n!=='' && !isset($out[$n])) $out[$n]=trim($phrase);
        }
        return array_values($out);
    }


    /** @return array{key:string,name:string}|null */
    private function recoverKnownEntity(string $item,string $context): ?array
    {
        $values=[];
        foreach([$item,$this->stripTradeNoise($context)] as $raw){
            $raw=trim($raw);
            if($raw==='')continue;
            $clean=preg_replace('/\s*\/\s*\d+(?:[.,]\d+)?\s*(?:a|e|k)\s*$/iu','',$raw)??$raw;
            $clean=preg_replace('/\s+\d+(?:[.,]\d+)?\s*(?:a|e|k)\s*(?:\/\s*ea|ea|each)?\s*$/iu','',$clean)??$clean;
            $clean=trim($clean," \t\n\r\0\x0B,;|/-");
            $values[]=$clean;

            // Common trader vocabulary. Every expansion remains catalogue-proof:
            // no row is returned unless the target is a unique exact name/alias.
            $n=KnowledgeBase::normalize($clean);
            $map=[
                'gold zc'=>'Gold Zaishen Coin','gold z coin'=>'Gold Zaishen Coin','gold z coins'=>'Gold Zaishen Coin',
                'golden z coin'=>'Gold Zaishen Coin','golden z coins'=>'Gold Zaishen Coin',
                'zaishen stones'=>'Zaishen Summoning Stone','zaishen stone'=>'Zaishen Summoning Stone',
                'powerstones'=>'Powerstone of Courage','powerstone'=>'Powerstone of Courage',
                'stygian gemstones'=>'Stygian Gem','stygian gemstone'=>'Stygian Gem','stygian gemstones'=>'Stygian Gem',
                'tanned hide'=>'Tanned Hide Square','tanned hides'=>'Tanned Hide Square',
                'scales'=>'Scale','celerity'=>'Essence of Celerity',
                'ghozers key'=>"Ghozer's Key",'ghozer s key'=>"Ghozer's Key",'ghozer key'=>"Ghozer's Key",
                'shiro potion'=>"Shiro's Tonic",'el frosty'=>'Everlasting Frosty Tonic',
            ];
            if(isset($map[$n]))$values[]=$map[$n];

            // Safe singular/plural recovery, again only accepted by exact/alias.
            if(preg_match('/^(.{4,})s$/u',$clean,$m))$values[]=$m[1];
            if(preg_match('/^(.+?)\s+gemstones?$/iu',$clean,$m))$values[]=$m[1].' Gem';
            if(preg_match('/^(.+?)\s+potion$/iu',$clean,$m)){
                $values[]=$m[1].' Tonic';
                $values[]='Everlasting '.$m[1].' Tonic';
            }
        }
        $seen=[];
        foreach($values as $value){
            $norm=KnowledgeBase::normalize($value);if($norm===''||isset($seen[$norm]))continue;$seen[$norm]=true;
            $r=$this->uniqueExactOrAlias($value);
            if($r!==null&&!$this->looksGeneric($r['name']))return $r;
        }
        return null;
    }

    /** @return array{key:string,name:string}|null */
    private function embeddedConcreteWeapon(string $generic,string $context): ?array
    {
        $ctx=KnowledgeBase::normalize($this->stripTradeNoise($context));
        if($ctx==='')return null;
        $family=$this->weaponFamily($generic);
        if($family===null)return null;

        $rows=$this->pdo->query("SELECT i.key,i.name,i.category_key,a.alias FROM kb_items i LEFT JOIN kb_aliases a ON a.item_key=i.key WHERE i.active=1")->fetchAll();
        /** @var array<string,array{key:string,name:string,len:int}> $hits */
        $hits=[];
        foreach($rows as $row){
            $key=(string)$row['key'];$name=CanonicalMarketIdentity::nameFor((string)$row['name'],$key);
            if($this->looksGeneric($name)||CanonicalMarketIdentity::isWikiDisambiguator($name))continue;
            if(!$this->weaponFamilyCompatible($family,$name,(string)($row['category_key']??'')))continue;
            foreach([(string)$row['name'],(string)($row['alias']??'')] as $label){
                $needle=KnowledgeBase::normalize($label);
                if(strlen($needle)<4||$this->looksGeneric($needle))continue;
                // Normalized boundaries prevent "bow" from matching "rainbow".
                if(!preg_match('/(?:^|\s)'.preg_quote($needle,'/').'(?:$|\s)/u',$ctx))continue;
                $len=mb_strlen($needle);
                if(!isset($hits[$key])||$len>$hits[$key]['len'])$hits[$key]=['key'=>$key,'name'=>$name,'len'=>$len];
            }
        }
        if(!$hits)return null;
        usort($hits,static fn(array $a,array $b):int=>$b['len']<=>$a['len']);
        $winner=$hits[0];
        // Equal-length hits for different items are ambiguous and stay review.
        if(isset($hits[1])&&$hits[1]['len']===$winner['len'])return null;
        return ['key'=>$winner['key'],'name'=>$winner['name']];
    }

    private function looksGenericWeapon(string $name): bool
    {
        return $this->weaponFamily($name)!==null && in_array(KnowledgeBase::normalize($name),self::GENERIC_NAMES,true);
    }

    private function weaponFamily(string $name): ?string
    {
        $n=KnowledgeBase::normalize($name);
        if(in_array($n,['axe','sword','hammer','scythe','spear','staff','wand','shield','focus','focus item','dagger','daggers'],true))return $n==='dagger'?'daggers':($n==='focus item'?'focus':$n);
        if(in_array($n,['bow','flatbow','hornbow','longbow','recurve bow','recurvebow','shortbow'],true))return 'bow';
        return null;
    }

    private function weaponFamilyCompatible(string $family,string $name,string $category): bool
    {
        $n=KnowledgeBase::normalize($name);$c=KnowledgeBase::normalize($category);
        if($family==='bow')return str_contains($n,'bow')||str_contains($c,'bow');
        if($family==='focus')return str_contains($c,'focus')||str_contains($c,'offhand')||preg_match('/\b(?:focus|artifact|chalice|idol|icon)\b/u',$n)===1;
        if($family==='daggers')return str_contains($n,'dagger')||str_contains($c,'dagger');
        return str_contains($n,$family)||str_contains($c,$family);
    }

    private function stripTradeNoise(string $value): string
    {
        $value = preg_replace('/\b(?:wts|wtb|wtt|buying|selling|sell|buy)\b/iu',' ',$value) ?? $value;
        $value = preg_replace('/\[(?:x\s*)?\d+\]|\b(?:x\s*)?\d+\b/iu',' ',$value) ?? $value;
        $value = preg_replace('/\b\d+(?:[.,]\d+)?\s*(?:a|e|k)\s*(?:\/\s*ea|each|ea)?\b/iu',' ',$value) ?? $value;
        $value = preg_replace('/\b(?:ea|each|pm|offer|offers|obo)\b/iu',' ',$value) ?? $value;
        return trim(preg_replace('/\s+/u',' ',$value) ?? $value," \t\n\r\0\x0B,;|+/-");
    }

    private function normalizeShorthand(string $value): string
    {
        $value = KnowledgeBase::normalize($value);
        if ($value==='') return '';
        $tokens=preg_split('/\s+/u',$value)?:[];
        foreach($tokens as &$token){$token=self::TOKEN_NORMALIZATION[$token]??$token;}
        unset($token);
        return implode(' ',array_values(array_filter($tokens,static fn(string $v):bool=>$v!=='')));
    }

    private function hasUpgradeContext(string $value): bool
    {
        $n=' '.$this->normalizeShorthand($value).' ';
        foreach(self::UPGRADE_HINTS as $hint){if(str_contains($n,' '.$hint.' '))return true;}
        // Attribute/energy shorthand such as "+5 SR Staff Wrapping" is only
        // treated as an upgrade when an explicit component family is present.
        return preg_match('/\b(?:staff|wand|focus|spear|shield|bow)\s+(?:wrapping|grip|head|handle|core|string|mod)\b/u',$n)===1;
    }

    /** @return array{key:string,name:string}|null */
    private function uniqueExactOrAlias(string $value): ?array
    {
        $norm=KnowledgeBase::normalize($value);
        if($norm==='')return null;
        $st=$this->pdo->prepare("SELECT i.key,i.name FROM kb_items i LEFT JOIN kb_aliases a ON a.item_key=i.key WHERE i.active=1 AND (lower(trim(i.name))=lower(trim(:raw)) OR a.normalized_alias=:norm) GROUP BY i.key,i.name LIMIT 3");
        $st->execute([':raw'=>trim($value),':norm'=>$norm]);$rows=$st->fetchAll();
        if(count($rows)!==1)return null;
        return ['key'=>(string)$rows[0]['key'],'name'=>CanonicalMarketIdentity::nameFor((string)$rows[0]['name'],(string)$rows[0]['key'])];
    }

    /** @return array{key:string,name:string,score:float}|null */
    private function bestUniqueSemantic(string $item,string $context,bool $upgradesOnly): ?array
    {
        $sql="SELECT i.key,i.name,i.category_key,a.alias FROM kb_items i LEFT JOIN kb_aliases a ON a.item_key=i.key WHERE i.active=1";
        if($upgradesOnly)$sql.=" AND (lower(i.category_key) LIKE '%upgrade%' OR lower(i.category_key) LIKE '%inscription%' OR lower(i.category_key) LIKE '%mod%')";
        $rows=$this->pdo->query($sql)->fetchAll();
        if(!$rows)return null;

        $needle=$this->semanticNeedle($item,$context,$upgradesOnly);
        if($needle==='')return null;
        $needleTokens=$this->tokens($needle);
        if(!$needleTokens)return null;

        /** @var array<string,array{key:string,name:string,score:float}> $best */
        $best=[];
        foreach($rows as $row){
            $key=(string)$row['key'];$name=CanonicalMarketIdentity::nameFor((string)$row['name'],$key);
            if($this->looksGeneric($name)||CanonicalMarketIdentity::isWikiDisambiguator($name))continue;
            foreach(array_filter([(string)$row['name'],(string)($row['alias']??'')]) as $label){
                $score=$this->similarity($needle,$needleTokens,$this->normalizeShorthand($label));
                if(!isset($best[$key])||$score>$best[$key]['score'])$best[$key]=['key'=>$key,'name'=>$name,'score'=>$score];
            }
        }
        if(!$best)return null;
        usort($best,static fn(array $a,array $b):int=>$b['score']<=>$a['score']);
        $winner=$best[0];$runner=$best[1]['score']??0.0;

        // Upgrade shorthand can be somewhat compact, but still needs strong token
        // agreement and an unambiguous lead. General fuzzy is stricter.
        $threshold=$upgradesOnly?0.76:0.88;
        $margin=$upgradesOnly?0.12:0.08;
        if($winner['score']<$threshold||($winner['score']-$runner)<$margin)return null;
        return $winner;
    }

    private function semanticNeedle(string $item,string $context,bool $upgradesOnly): string
    {
        $primary=$this->normalizeShorthand($item);
        $ctx=$this->normalizeShorthand($this->stripTradeNoise($context));
        if($upgradesOnly){
            // Prefer the segment because parser item may be the generic weapon family.
            return $ctx!==''?$ctx:$primary;
        }
        if($primary!==''&&!$this->looksGeneric($primary))return $primary;
        return $ctx;
    }

    /** @param list<string> $needleTokens */
    private function similarity(string $needle,array $needleTokens,string $candidate): float
    {
        if($candidate==='')return 0.0;
        $candidateTokens=$this->tokens($candidate);
        if(!$candidateTokens)return 0.0;

        $matchedNeedle=[];$matchedCandidate=[];
        foreach($needleTokens as $ni=>$nt){
            $bestJ=null;$best=0.0;
            foreach($candidateTokens as $cj=>$ct){
                if(isset($matchedCandidate[$cj]))continue;
                $sim=$this->tokenSimilarity($nt,$ct);
                if($sim>$best){$best=$sim;$bestJ=$cj;}
            }
            if($bestJ!==null&&$best>=0.72){$matchedNeedle[$ni]=$best;$matchedCandidate[$bestJ]=true;}
        }
        $matchWeight=array_sum($matchedNeedle);
        $tokenRecall=$matchWeight/max(1,count($candidateTokens));
        $tokenPrecision=$matchWeight/max(1,count($needleTokens));
        $tokenScore=(2*$tokenRecall*$tokenPrecision)/max(0.0001,$tokenRecall+$tokenPrecision);

        $a=preg_replace('/\s+/u','',$needle)??$needle;$b=preg_replace('/\s+/u','',$candidate)??$candidate;
        $max=max(strlen($a),strlen($b));$edit=$max===0?0.0:1-(levenshtein($a,$b)/$max);

        // Candidate catalog tokens are more important than unrelated price/context
        // words left in a live trade segment.
        return max(0.0,min(1.0,0.72*$tokenScore+0.28*$edit));
    }


    private function tokenSimilarity(string $a,string $b): float
    {
        if($a===$b)return 1.0;
        // Trader plurals should not prevent an otherwise exact identity.
        $as=preg_replace('/(?:es|s)$/u','',$a)??$a;$bs=preg_replace('/(?:es|s)$/u','',$b)??$b;
        if(strlen($as)>=4&&$as===$bs)return 0.98;
        $max=max(strlen($a),strlen($b));
        if($max===0)return 0.0;
        return max(0.0,1-(levenshtein($a,$b)/$max));
    }

    /** @return list<string> */
    private function tokens(string $value): array
    {
        $stop=['wts','wtb','wtt','ea','each','the','of','for','and','with','plus','x','sr','s'];
        $tokens=preg_split('/\s+/u',KnowledgeBase::normalize($value))?:[];$out=[];
        foreach($tokens as $token){
            if($token===''||in_array($token,$stop,true)||preg_match('/^\d+$/',$token))continue;
            $token=self::TOKEN_NORMALIZATION[$token]??$token;
            if(strlen($token)>=2)$out[$token]=$token;
        }
        return array_values($out);
    }

    private function looksGeneric(string $name): bool
    {
        return in_array(KnowledgeBase::normalize($name),self::GENERIC_NAMES,true);
    }
}
