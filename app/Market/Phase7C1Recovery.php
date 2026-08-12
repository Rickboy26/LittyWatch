<?php
declare(strict_types=1);

namespace LittyWatch\Market;

use LittyWatch\Knowledge\KnowledgeBase;
use PDO;

/**
 * LITTYWATCH_PHASE7C1_UNIQUE_CONCRETE_RECOVERY
 *
 * Second conservative recovery pass for residual Kamadan shorthand. It only
 * returns a result when the intended concrete catalogue identity is explicit
 * enough and resolves to exactly one active KB item. Ambiguous families such
 * as bare Scepters, Talon Daggers, generic bows or unspecified miniatures are
 * deliberately left unresolved.
 */
final class Phase7C1Recovery
{
    /** @var array<string,list<string>> */
    private const SAFE_RULES = [
        '/\blockpiok\b/iu' => ['Lockpick'],
        '/\bzaishen\s+keys?\b/iu' => ['Zaishen Key'],
        '/\bassassin\s+tomes?\b/iu' => ['Assassin Tome'],
        '/\bsilver\s+dyes?\b/iu' => ['Silver Dye'],
        '/\bz\s*-?\s*coin\s+gold\b/iu' => ['Gold Zaishen Coin'],
        '/\bgold\s+z\s*-?\s*coins?\b/iu' => ['Gold Zaishen Coin'],
        '/\bplatinum\s+staff\b/iu' => ['Platinum Staff'],
        '/\bshadow\s+staff\b/iu' => ['Shadow Staff'],
        '/\bbutterfly\s+mirrors?\b/iu' => ['Butterfly Mirror'],
    ];

    public function __construct(private readonly PDO $pdo) {}

    /** @param array<string,mixed> $row @return array{key:string,name:string,reason:string}|null */
    public function resolve(array $row,string $message): ?array
    {
        $item=trim((string)($row['item']??''));
        $segment=trim((string)($row['raw_segment']??''));
        $context=trim($item.' '.($segment!==''?$segment:$message));
        if($context==='')return null;

        // Miniatures keep their dedicated state/variant resolver. This pass must
        // never infer a miniature from an underspecified nickname.
        if(preg_match('/\b(?:mini|miniature|minis|unded|undedicated|ded|dedicated)\b/iu',$context))return null;

        foreach(self::SAFE_RULES as $pattern=>$targets){
            if(preg_match($pattern,$context)!==1)continue;
            foreach($targets as $target){
                $hit=$this->uniqueExactOrAlias($target);
                if($hit!==null)return $hit+['reason'=>'phase7c1_unique_concrete'];
            }
        }

        // Last-resort exact-name salvage: useful for parser output that already
        // contains a concrete catalogue name but carries a legacy/non-canonical
        // key. This is exact normalized equality only, never fuzzy matching.
        $norm=KnowledgeBase::normalize($item);
        if($norm===''||mb_strlen($norm)<5)return null;
        $hit=$this->uniqueNormalizedName($norm);
        if($hit===null)return null;
        if($this->isGeneric($hit['name']))return null;
        return $hit+['reason'=>'phase7c1_exact_name'];
    }

    /** @return array{key:string,name:string}|null */
    private function uniqueExactOrAlias(string $value): ?array
    {
        $norm=KnowledgeBase::normalize($value);
        if($norm==='')return null;

        $hit=$this->uniqueNormalizedName($norm);
        if($hit!==null)return $hit;

        $st=$this->pdo->prepare(
            "SELECT i.key,i.name FROM kb_aliases a JOIN kb_items i ON i.key=a.item_key " .
            "WHERE i.active=1 AND a.normalized_alias=:a GROUP BY i.key,i.name LIMIT 2"
        );
        $st->execute([':a'=>$norm]);
        $rows=$st->fetchAll();
        if(count($rows)!==1)return null;
        return [
            'key'=>(string)$rows[0]['key'],
            'name'=>CanonicalMarketIdentity::nameFor((string)$rows[0]['name'],(string)$rows[0]['key']),
        ];
    }

    /** @return array{key:string,name:string}|null */
    private function uniqueNormalizedName(string $norm): ?array
    {
        $rows=$this->pdo->query("SELECT key,name FROM kb_items WHERE active=1")->fetchAll();
        $hits=[];
        foreach($rows as $r){
            if(KnowledgeBase::normalize((string)$r['name'])!==$norm)continue;
            $key=(string)$r['key'];
            $hits[$key]=[
                'key'=>$key,
                'name'=>CanonicalMarketIdentity::nameFor((string)$r['name'],$key),
            ];
            if(count($hits)>1)return null;
        }
        return count($hits)===1?array_values($hits)[0]:null;
    }

    private function isGeneric(string $value): bool
    {
        return in_array(KnowledgeBase::normalize($value),[
            'axe','axes','shield','shields','staff','staves','staffs','scythe','scythes',
            'sword','swords','hammer','hammers','spear','spears','wand','wands','scepter',
            'scepters','dagger','daggers','focus','focus item','bow','bows','flatbow',
            'hornbow','longbow','recurve bow','recurvebow','shortbow','weapon','weapons',
            'tonic','tonics','miniature','miniatures','elite tome','normal tome',
        ],true);
    }
}
