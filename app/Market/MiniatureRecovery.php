<?php
declare(strict_types=1);
namespace LittyWatch\Market;

use LittyWatch\Knowledge\KnowledgeBase;
use PDO;

/**
 * Phase 7E: conservative miniature recovery.
 *
 * Rules:
 * - dedication context is mandatory (ded/unded);
 * - only one uniquely identified miniature may be promoted from a clause;
 * - multi-mini bundles remain unresolved unless a later parser stage can bind
 *   price/quantity safely per concrete miniature;
 * - tonic/potion contexts are never promoted as miniatures.
 */
final class MiniatureRecovery
{
    /** @var array<int,array{key:string,name:string,aliases:array<int,string>}>|null */
    private static ?array $catalog = null;

    /** @var array<string,string> */
    private const SAFE_SHORTHAND = [
        'wfr beetle' => 'Miniature World-Famous Racing Beetle',
        'world famous racing beetle' => 'Miniature World-Famous Racing Beetle',
        'world-famous racing beetle' => 'Miniature World-Famous Racing Beetle',
        'racing beetle' => 'Miniature World-Famous Racing Beetle',
        'rift war' => 'Miniature Rift Warden',
        'rift warden' => 'Miniature Rift Warden',
        'cave spider' => 'Miniature Cave Spider',
        'celestial dragon' => 'Miniature Celestial Dragon',
        'unded mox' => 'Miniature M.O.X.',
        'ded mox' => 'Miniature M.O.X.',
        'mini mox' => 'Miniature M.O.X.',
        'miniature mox' => 'Miniature M.O.X.',
        'grawl' => 'Miniature Grawl',
        'ghost of althea' => 'Miniature Ghost of Althea',
        'althea' => 'Miniature Ghost of Althea',
        'high priest zhang' => 'Miniature High Priest Zhang',
        'zhang' => 'Miniature High Priest Zhang',
        'thul za' => 'Miniature Thul Za Dhuum',
        'shiroken assassin' => "Miniature Shiro'ken Assassin",
        'shiro ken assassin' => "Miniature Shiro'ken Assassin",
        "shiro'ken assassin" => "Miniature Shiro'ken Assassin",
    ];

    public function __construct(private readonly PDO $pdo) {}

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>|null
     */
    public function resolve(array $row,string $message,string $state): ?array
    {
        $segment=trim((string)($row['raw_segment']??''));
        $text=trim($segment.' '.$message);
        if($text==='')return null;

        if(preg_match('/\b(?:potion|tonic)\b/iu',$segment) && !preg_match('/\bmini(?:ature|pet)?s?\b|\b(?:unded(?:icated)?|ded(?:icated)?)\b/iu',$segment)){
            return null;
        }

        $normalized=$this->normalize($text);
        $matches=[];

        foreach(self::SAFE_SHORTHAND as $needle=>$name){
            if(!$this->containsTokenPhrase($normalized,$this->normalize($needle)))continue;
            $exact=$this->exactMiniature($name);
            if($exact!==null)$matches[$exact['key']]=$exact;
        }

        foreach($this->miniatureCatalog() as $entry){
            foreach($entry['aliases'] as $alias){
                $aliasNorm=$this->normalize($alias);
                if($aliasNorm===''||mb_strlen($aliasNorm)<4)continue;
                if(in_array($aliasNorm,['mini','minis','miniature','miniatures','unded','ded'],true))continue;
                if($this->containsTokenPhrase($normalized,$aliasNorm)){
                    $matches[$entry['key']]=['key'=>$entry['key'],'name'=>$entry['name']];
                    break;
                }
            }
        }

        // Never guess inside a multi-mini bundle. Price association is not safe
        // at this layer and the original row must stay visible for review.
        if(count($matches)!==1)return null;

        $match=array_values($matches)[0];
        $row['item']=$match['name'];
        $row['item_key']=$match['key'];
        $row['market_key']=$match['key'];
        $row['variant']=$state;
        $row['relevant_json']=$this->withDedication($row['relevant_json']??null,$state);
        $row['quality_status']='accepted';
        $row['quality_reason']='catalog_match';
        $row['confidence']=max(0.92,(float)($row['confidence']??0));
        return $row;
    }

    /** @return array<int,array{key:string,name:string,aliases:array<int,string>}> */
    private function miniatureCatalog(): array
    {
        if(self::$catalog!==null)return self::$catalog;
        $sql="SELECT i.key,i.name,a.alias FROM kb_items i LEFT JOIN kb_aliases a ON a.item_key=i.key WHERE i.active=1 AND lower(i.name) LIKE 'miniature %' ORDER BY i.key";
        $rows=$this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $by=[];
        foreach($rows as $r){
            $key=(string)$r['key'];
            if(!isset($by[$key]))$by[$key]=['key'=>$key,'name'=>(string)$r['name'],'aliases'=>[(string)$r['name']]];
            $alias=trim((string)($r['alias']??''));
            if($alias!=='')$by[$key]['aliases'][]=$alias;
        }
        foreach($by as &$entry)$entry['aliases']=array_values(array_unique($entry['aliases']));
        unset($entry);
        return self::$catalog=array_values($by);
    }

    /** @return array{key:string,name:string}|null */
    private function exactMiniature(string $name): ?array
    {
        $st=$this->pdo->prepare("SELECT key,name FROM kb_items WHERE active=1 AND lower(trim(name))=lower(trim(:n)) LIMIT 1");
        $st->execute([':n'=>$name]);
        $r=$st->fetch(PDO::FETCH_ASSOC);
        if(!$r)return null;
        return ['key'=>(string)$r['key'],'name'=>CanonicalMarketIdentity::nameFor((string)$r['name'],(string)$r['key'])];
    }

    private function normalize(string $value): string
    {
        $value=str_replace(['’','´','`'],"'",mb_strtolower($value));
        $value=preg_replace('/[^a-z0-9]+/u',' ',$value)??$value;
        return trim(preg_replace('/\s+/u',' ',$value)??$value);
    }

    private function containsTokenPhrase(string $haystack,string $needle): bool
    {
        if($needle==='')return false;
        return preg_match('/(?:^| )'.preg_quote($needle,'/').'(?: |$)/u',$haystack)===1;
    }

    private function withDedication(mixed $json,string $state): string
    {
        $data=[];
        if(is_string($json)&&trim($json)!==''){
            $decoded=json_decode($json,true);
            if(is_array($decoded))$data=$decoded;
        } elseif(is_array($json)) {
            $data=$json;
        }
        $data['dedication']=$state==='unded'?'undedicated':'dedicated';
        return json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'{}';
    }
}
