<?php
declare(strict_types=1);

namespace LittyWatch\Market;

use LittyWatch\Knowledge\KnowledgeBase;
use PDO;

/**
 * LITTYWATCH_PHASE7C_CONCRETE_CLAUSE_RECOVERY
 *
 * Conservative recovery of concrete catalogue identities hidden inside noisy,
 * abbreviated weapon clauses. This class never creates synthetic catalogue
 * items and never promotes a generic weapon family. A result is returned only
 * when one concrete active KB item wins uniquely.
 */
final class Phase7CRecovery
{
    /** @var list<string> */
    private const GENERIC = [
        'axe','axes','shield','shields','staff','staves','staffs','scythe','scythes',
        'sword','swords','hammer','hammers','spear','spears','wand','wands','scepter',
        'scepters','dagger','daggers','focus','focus item','bow','bows','flatbow',
        'flatbows','hornbow','hornbows','longbow','longbows','recurve bow','recurvebow',
        'shortbow','shortbows','weapon','weapons','tonic','miniature',
    ];

    /** @var array<string,string> */
    private const REWRITES = [
        'cagedshortbo' => 'caged shortbow',
        'greatersageb' => 'greater sage b',
        'demoncrest' => 'demon crest',
        'fellblede' => 'fellblade',
        'diamod aeg' => 'diamond aegis',
        'plagueborn' => 'plagueborn',
        'darkwing' => 'darkwing',
    ];

    /** @var list<string> */
    private const NOISE = [
        'q','req','r','insc','inscribable','os','oldschool','old','school','skin',
        'ea','each','wts','wtb','wtt','buy','sell','trade','only','high','gold',
        'value','weapons','weapon','wpns','mods','mod','for','with','and','or',
        'str','tac','tactics','strength','fc','fast','casting','es','energy','storage',
        'sr','soul','reaping','df','divine','favor','dom','domination','insp',
        'inspiration','illu','illusion','heal','healing','fire','smite','smiting',
        'chan','channeling','death','curses','earth','blood','prot','protection',
    ];

    public function __construct(private readonly PDO $pdo) {}

    /** @param array<string,mixed> $row @return array{key:string,name:string,reason:string}|null */
    public function resolve(array $row,string $message): ?array
    {
        $item=trim((string)($row['item']??''));
        $segment=trim((string)($row['raw_segment']??''));
        $context=trim($segment!==''?$segment:$message);
        if($context==='')return null;

        // Miniature identity remains owned by the dedicated miniature resolver.
        if(preg_match('/\b(?:mini|miniature|minis|unded|undedicated|ded|dedicated)\b/iu',$item.' '.$context))return null;

        $normalized=$this->rewrite(KnowledgeBase::normalize($context));
        $itemNorm=$this->rewrite(KnowledgeBase::normalize($item));

        $rows=$this->pdo->query(
            "SELECT i.key,i.name,a.alias FROM kb_items i " .
            "LEFT JOIN kb_aliases a ON a.item_key=i.key WHERE i.active=1"
        )->fetchAll();

        /** @var array<string,array{key:string,name:string,score:int,label_len:int}> $hits */
        $hits=[];
        foreach($rows as $r){
            $key=(string)$r['key'];
            $name=CanonicalMarketIdentity::nameFor((string)$r['name'],$key);
            if($this->isGeneric($name)||CanonicalMarketIdentity::isWikiDisambiguator($name))continue;

            foreach([(string)$r['name'],(string)($r['alias']??'')] as $label){
                $labelNorm=$this->rewrite(KnowledgeBase::normalize($label));
                if($labelNorm===''||mb_strlen($labelNorm)<5||$this->isGeneric($labelNorm))continue;
                $score=$this->score($normalized,$itemNorm,$labelNorm);
                if($score<80)continue;
                $len=mb_strlen($labelNorm);
                if(!isset($hits[$key])||$score>$hits[$key]['score']||($score===$hits[$key]['score']&&$len>$hits[$key]['label_len'])){
                    $hits[$key]=['key'=>$key,'name'=>$name,'score'=>$score,'label_len'=>$len];
                }
            }
        }

        if($hits===[])return null;
        usort($hits,static fn(array $a,array $b):int => [$b['score'],$b['label_len']] <=> [$a['score'],$a['label_len']]);
        $best=$hits[0];
        $second=$hits[1]??null;
        if($second!==null&&$second['score']===$best['score']&&$second['label_len']===$best['label_len'])return null;

        // For non-generic parser output demand a stronger recovery signal. This
        // avoids replacing an already-specific unknown phrase by a weak fuzzy hit.
        if(!$this->isGeneric($item)&&$best['score']<100)return null;

        return ['key'=>$best['key'],'name'=>$best['name'],'reason'=>'phase7c_concrete_clause'];
    }

    private function score(string $context,string $item,string $label): int
    {
        if($context===''||$label==='')return 0;

        // Exact catalogue phrase embedded in the clause is strongest.
        if(preg_match('/(?:^|\s)'.preg_quote($label,'/').'(?:$|\s)/u',$context)===1)return 140;
        if($item!==''&&$item===$label)return 140;

        $labelTokens=$this->meaningfulTokens($label);
        $ctxTokens=$this->meaningfulTokens($context);
        if($labelTokens===[]||$ctxTokens===[])return 0;

        // All meaningful catalogue tokens must be evidenced. Tokens may be
        // truncated in Kamadan shorthand, but only at >=4 characters.
        $matched=0;$exact=0;
        foreach($labelTokens as $lt){
            $found=false;
            foreach($ctxTokens as $ct){
                if($ct===$lt){$found=true;$exact++;break;}
                if(mb_strlen($ct)>=4&&str_starts_with($lt,$ct)){$found=true;break;}
                if(mb_strlen($lt)>=4&&str_starts_with($ct,$lt)){$found=true;break;}
                // Joined spellings such as Demoncrest and CagedShortBo.
                if(mb_strlen($ct)>=6&&str_contains(str_replace(' ', '', $label),$ct)){$found=true;break;}
            }
            if(!$found)return 0;
            $matched++;
        }

        if($matched===1){
            // One-token identities are accepted only when that token itself is
            // distinctive and reasonably long (e.g. Fellblade, Plagueborn).
            $only=$labelTokens[0];
            if(mb_strlen($only)<7)return 0;
            return $exact===1?105:90;
        }

        return 100+min(20,$exact*5)+min(10,$matched*2);
    }

    /** @return list<string> */
    private function meaningfulTokens(string $value): array
    {
        $tokens=preg_split('/\s+/u',trim($value))?:[];
        $out=[];
        foreach($tokens as $t){
            if($t===''||preg_match('/^\d+(?:\/\d+)?$/',$t))continue;
            if(preg_match('/^[qrx]?\d+$/',$t))continue;
            if(in_array($t,self::NOISE,true)||$this->isGeneric($t))continue;
            if(mb_strlen($t)<3)continue;
            $out[]=$t;
        }
        return array_values(array_unique($out));
    }

    private function rewrite(string $value): string
    {
        $value=' '.trim($value).' ';
        foreach(self::REWRITES as $from=>$to){
            $value=preg_replace('/(?<![a-z0-9])'.preg_quote($from,'/').'(?![a-z0-9])/u',' '.$to.' ',$value)??$value;
        }
        return trim(preg_replace('/\s+/u',' ',$value)??$value);
    }

    private function isGeneric(string $value): bool
    {
        return in_array(KnowledgeBase::normalize($value),self::GENERIC,true);
    }
}
