<?php
declare(strict_types=1);
namespace LittyWatch\Market;

use LittyWatch\Knowledge\KnowledgeBase;
use PDO;

final class CatalogFirstResolver
{
    private const PROFESSIONS=[
        'warrior'=>'Warrior','ranger'=>'Ranger','monk'=>'Monk','necromancer'=>'Necromancer',
        'mesmer'=>'Mesmer','elementalist'=>'Elementalist','assassin'=>'Assassin',
        'ritualist'=>'Ritualist','paragon'=>'Paragon','dervish'=>'Dervish',
        'war'=>'Warrior','rng'=>'Ranger','monk'=>'Monk','nec'=>'Necromancer','mes'=>'Mesmer',
        'ele'=>'Elementalist','sin'=>'Assassin','assa'=>'Assassin','rit'=>'Ritualist',
        'para'=>'Paragon','derv'=>'Dervish'
    ];

    public function __construct(private readonly PDO $pdo) {}

    /**
     * Expand one parser offer into zero, one or multiple concrete catalogue offers.
     * @param array<string,mixed> $row
     * @return array<int,array<string,mixed>>
     */
    public function resolve(array $row,string $message): array
    {
        $item=CanonicalMarketIdentity::nameFor(trim((string)($row['item']??'')),trim((string)($row['item_key']??'')));
        $row['item']=$item;
        $lower=mb_strtolower($item.' '.$message);

        if($this->isGenericEliteTome($item)){
            return $this->expandProfessionTomes($row,$message,true);
        }
        if($this->isGenericNormalTome($item)){
            return $this->expandProfessionTomes($row,$message,false);
        }

        $mini=$this->resolveMiniature($row,$message);
        if($mini!==null)return $mini;

        $exact=$this->catalogueExact($item,(string)($row['item_key']??''));
        if($exact===null)return [];
        $row['item']=$exact['name'];$row['item_key']=$exact['key'];$row['market_key']=$exact['key'];
        return [$row];
    }

    /** @return array<int,array<string,mixed>> */
    private function expandProfessionTomes(array $row,string $message,bool $elite): array
    {
        $out=[];$m=mb_strtolower($message);
        foreach(self::PROFESSIONS as $token=>$profession){
            // require profession evidence in the actual message, not only the generic parsed label
            if(!preg_match('/(?<![a-z])'.preg_quote($token,'/').'(?![a-z])/u',$m))continue;
            $name=($elite?'Elite ':'').$profession.' Tome';
            $exact=$this->catalogueExact($name,'');
            if($exact===null)continue;
            $copy=$row;$copy['item']=$exact['name'];$copy['item_key']=$exact['key'];$copy['market_key']=$exact['key'];
            // capture nearby quantity: "5 mes", "mes x5", "3 monk"
            if(preg_match('/(?:\b(\d+)\s*(?:x\s*)?'.preg_quote($token,'/').'\b|\b'.preg_quote($token,'/').'\s*(?:x\s*)?(\d+)\b)/u',$m,$q)){
                $copy['quantity']=(int)($q[1]!==''?$q[1]:$q[2]);
            }
            $out[$exact['key']]=$copy;
        }
        return array_values($out);
    }

    /** @return array<int,array<string,mixed>>|null */
    private function resolveMiniature(array $row,string $message): ?array
    {
        $item=trim((string)($row['item']??''));
        $candidate=CanonicalMarketIdentity::nameFor($item,(string)($row['item_key']??''));
        $isMini=preg_match('/\bmini(?:ature)?\b/i',$item.' '.$message)===1;
        if(!$isMini){
            // A bare name is miniature semantics only if "Miniature <name>" exists exactly.
            $pref=$this->catalogueExact('Miniature '.$candidate,'');
            if($pref===null)return null;
            $isMini=true;
        }
        if(!$isMini)return null;

        $state=$this->miniState($message.' '.$item);
        if($state===null)return []; // no ded/unded = review, never player market

        if(!str_starts_with(mb_strtolower($candidate),'miniature '))$candidate='Miniature '.$candidate;
        $exact=$this->catalogueExact($candidate,(string)($row['item_key']??''));
        if($exact===null){
            // Resolve common shorthand/aliases to a concrete miniature only.
            $exact=$this->uniqueAlias($candidate) ?? $this->uniqueAlias(preg_replace('/^Miniature\s+/i','',$candidate)??$candidate);
        }
        if($exact===null||!str_starts_with(mb_strtolower($exact['name']),'miniature '))return [];

        $row['item']=$exact['name'];$row['item_key']=$exact['key'];$row['market_key']=$exact['key'];
        $row['variant']=$state;
        return [$row];
    }

    private function miniState(string $text): ?string
    {
        $t=mb_strtolower($text);
        if(preg_match('/\b(?:unded|undedi|undedicated|un[- ]?ded)\b/u',$t))return 'unded';
        if(preg_match('/\b(?:ded|dedicated)\b/u',$t))return 'ded';
        return null;
    }

    private function isGenericEliteTome(string $item): bool
    {
        return preg_match('/^(?:elite\s+)?tomes?$/i',$item)===1 || mb_strtolower(trim($item))==='elite tome';
    }
    private function isGenericNormalTome(string $item): bool
    {
        return preg_match('/^(?:normal\s+)?tomes?$/i',$item)===1;
    }

    /** @return array{key:string,name:string}|null */
    private function catalogueExact(string $name,string $key): ?array
    {
        $st=$this->pdo->prepare("SELECT key,name FROM kb_items WHERE active=1 AND (key=:k OR lower(trim(name))=lower(trim(:n))) ORDER BY CASE WHEN key=:k2 THEN 0 ELSE 1 END LIMIT 1");
        $st->execute([':k'=>$key,':k2'=>$key,':n'=>trim($name)]);$r=$st->fetch();
        return $r?['key'=>(string)$r['key'],'name'=>CanonicalMarketIdentity::nameFor((string)$r['name'],(string)$r['key'])]:null;
    }
    /** @return array{key:string,name:string}|null */
    private function uniqueAlias(string $alias): ?array
    {
        $norm=KnowledgeBase::normalize($alias);
        $st=$this->pdo->prepare("SELECT i.key,i.name FROM kb_aliases a JOIN kb_items i ON i.key=a.item_key WHERE i.active=1 AND a.normalized_alias=:a GROUP BY i.key,i.name LIMIT 2");
        $st->execute([':a'=>$norm]);$r=$st->fetchAll();
        return count($r)===1?['key'=>(string)$r[0]['key'],'name'=>CanonicalMarketIdentity::nameFor((string)$r[0]['name'],(string)$r[0]['key'])]:null;
    }
}
