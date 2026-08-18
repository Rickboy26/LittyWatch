<?php
declare(strict_types=1);
namespace LittyWatch\Market;

final class Phase7E14ResidualGuard
{
    public function __construct(private readonly \PDO $pdo) {}

    public function repair(array $row): array
    {
        $item=trim((string)($row['item']??''));
        $key=str_replace('_','-',mb_strtolower(trim((string)($row['item_key']??''))));
        $segment=trim((string)($row['raw_segment']??''));

        if(preg_match('/\babnormal\s+seeds?\b/iu',$segment)||str_starts_with($key,'abnormal-seed')){
            return $this->accept($row,'Unnatural Seed','unnatural-seed');
        }

        if(preg_match('/\bbords?\s+eyes?\b/iu',$segment)||$key==='bords-eyes'){
            return $this->accept($row,'Birdseye','birdseye');
        }

        if(preg_match('/\bdestruction\s+depths\b.*\b(?:nm\s+)?rush\b/iu',$segment)||str_starts_with($key,'destruction-depths')){
            $row['quality_status']='rejected';
            $row['quality_reason']='service_or_noise';
            $row['confidence']=min((float)($row['confidence']??0),0.25);
            return $row;
        }

        if($key==='blessing-of-war'&&preg_match('/^\s*(?:bow|axe|hammer|sword|dagger|wand|staff|spear|scythe)\s*$/iu',$segment)){
            $row['quality_status']='rejected';
            $row['quality_reason']='strict_catalog_generic';
            $row['confidence']=min((float)($row['confidence']??0),0.25);
            return $row;
        }

        if(preg_match('/^\s*unids?\s*$/iu',$segment)||$key==='unidentified-gold'){
            return $this->accept($row,'Unidentified Gold','unidentified-gold');
        }

        if($key==='grim-cesta'||mb_strtolower($item)==='grim cesta'){
            $row['quality_status']='review';
            $row['quality_reason']='catalog_first_unresolved';
        }

        return $row;
    }

    private function accept(array $row,string $name,string $key):array
    {
        $row['item']=$name;
        $row['item_key']=$key;
        $row['market_key']=$key;
        $row['quality_status']='accepted';
        $row['quality_reason']='catalog_match';
        $row['confidence']=max((float)($row['confidence']??0),0.92);
        return $row;
    }
}
