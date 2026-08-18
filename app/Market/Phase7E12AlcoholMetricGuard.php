<?php
declare(strict_types=1);
namespace LittyWatch\Market;

final class Phase7E12AlcoholMetricGuard
{
    public function __construct(private readonly \PDO $pdo) {}

    public function repair(array $row): array
    {
        $key=str_replace('_','-',mb_strtolower(trim((string)($row['item_key']??''))));
        $segment=trim((string)($row['raw_segment']??''));

        if(
            $key==='alcohol-point'
            || $key==='market-points-alcohol'
            || preg_match('/\balc(?:ohol)?\s+stacks?\b/iu',$segment)
            || preg_match('/\b1\s*(?:pt|point)\s+alch?\b/iu',$segment)
            || preg_match('/\balcohol\s+points?\b/iu',$segment)
        ){
            $row['item']='Alcohol Points';
            $row['item_key']='market-points-alcohol';
            $row['market_key']='market-points-alcohol';
            $row['quality_status']='accepted';
            $row['quality_reason']='catalog_match';
            $row['confidence']=max((float)($row['confidence']??0),0.92);
        }
        return $row;
    }
}
