<?php
declare(strict_types=1);
namespace LittyWatch\Market;
final class Phase7E15MarketSemanticGuard
{
    public function __construct(private readonly \PDO $pdo) {}
    public function repair(array $row): array
    {
        $key=str_replace('_','-',mb_strtolower(trim((string)($row['item_key']??''))));
        $segment=trim((string)($row['raw_segment']??''));
        if(preg_match('/\binsc(?:ribable)?\s+golds?\b/iu',$segment)||in_array($key,['golds','insc-golds','inscribable-golds'],true)){
            return $this->accept($row,'Inscribable Golds','market-inscribable-golds');
        }
        if(preg_match('/^\s*egg(?:\s+\d+(?:[.,]\d+)?\s*(?:e|a|k))?\s*$/iu',$segment)||$key==='egg'){
            return $this->accept($row,'Golden Egg','golden-egg');
        }
        if(preg_match('/^\s*beacons?\b/iu',$segment)||$key==='beacons'){
            return $this->accept($row,'Party Beacon','party-beacon');
        }
        if(preg_match('/^\s*teas?\b/iu',$segment)||$key==='teas'){
            return $this->accept($row,'Battle Isle Iced Tea','battle-isle-iced-tea');
        }
        if(preg_match('/^\s*d[-\s]?cakes?\b/iu',$segment)||str_starts_with($key,'d-cakes')||$key==='birthday-cupcake'){
            return $this->accept($row,'Delicious Cake','delicious-cake');
        }
        if(preg_match('/^\s*how\s+much\s+(?:u|you)\s+want\s*[?.!]*\s*$/iu',$segment)||$key==='how-much-u-want'){
            $row['quality_status']='rejected';
            $row['quality_reason']='service_or_noise';
            $row['confidence']=min((float)($row['confidence']??0),0.20);
        }
        return $row;
    }
    private function accept(array $row,string $name,string $key):array
    {
        $row['item']=$name;$row['item_key']=$key;$row['market_key']=$key;
        $row['quality_status']='accepted';$row['quality_reason']='catalog_match';
        $row['confidence']=max((float)($row['confidence']??0),0.92);
        return $row;
    }
}
