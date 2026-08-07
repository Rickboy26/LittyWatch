<?php
declare(strict_types=1);

namespace LittyWatch\AI;

use PDO;

final class AiValidationRepository
{
    public function __construct(private readonly PDO $pdo, private readonly ?AiRiskAssessor $risk = null) {}

    public function syncMessage(int $messageId, string $mode = 'risky'): int
    {
        if ($mode === 'off') return 0;
        $rows = $this->offerRows('WHERE so.message_id=:message_id', [':message_id'=>$messageId]);
        $stats = $this->marketStats();
        $count = 0;
        foreach ($rows as $row) {
            $key=(string)($row['normalized_market_key'] ?: $row['market_key']);
            $count += $this->queueRow($row, $mode, $stats[$key] ?? []);
        }
        return $count;
    }

    public function syncAll(string $mode = 'risky'): int
    {
        if ($mode === 'off') return 0;
        $rows = $this->offerRows('', []);
        $stats = $this->marketStats();
        $count = 0;
        foreach ($rows as $row) {
            $key=(string)($row['normalized_market_key'] ?: $row['market_key']);
            $count += $this->queueRow($row, $mode, $stats[$key] ?? []);
        }
        return $count;
    }

    /** @return list<array<string,mixed>> */
    public function pending(int $limit = 25): array
    {
        $s = $this->pdo->prepare("SELECT av.*,so.trade_type,so.item,so.item_key,so.market_key,so.normalized_market_key,so.quantity,so.price_amount,so.price_currency,so.price_ecto,so.unit_price_ecto,so.price_basis,so.confidence,so.quality_status,so.quality_reason,so.raw_segment,so.relevant_json,so.profile_json,m.player,m.message,m.posted_at FROM ai_offer_validations av JOIN structured_offers so ON so.id=av.structured_offer_id JOIN messages m ON m.id=so.message_id WHERE av.status='queued' ORDER BY av.risk_score DESC,av.id ASC LIMIT ?");
        $s->bindValue(1, max(1,min(500,$limit)), PDO::PARAM_INT); $s->execute(); return $s->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function context(int $structuredOfferId): ?array
    {
        $s=$this->pdo->prepare("SELECT so.*,m.player,m.message,m.posted_at FROM structured_offers so JOIN messages m ON m.id=so.message_id WHERE so.id=?");$s->execute([$structuredOfferId]);$row=$s->fetch();if(!$row)return null;
        $siblings=$this->pdo->prepare("SELECT id,trade_type,item,price_amount,price_currency,unit_price_ecto,price_basis,raw_segment FROM structured_offers WHERE message_id=? ORDER BY id");$siblings->execute([(int)$row['message_id']]);
        $marketKey=(string)($row['normalized_market_key']?:$row['market_key']);
        $hist=$this->pdo->prepare("SELECT unit_price_ecto FROM structured_offers WHERE id<>? AND COALESCE(NULLIF(normalized_market_key,''),market_key)=? AND quality_status='accepted' AND unit_price_ecto IS NOT NULL AND unit_price_ecto>0 ORDER BY unit_price_ecto LIMIT 101");$hist->execute([$structuredOfferId,$marketKey]);$values=array_map('floatval',array_column($hist->fetchAll(),'unit_price_ecto'));sort($values);$median=null;if($values){$n=count($values);$median=$n%2?$values[intdiv($n,2)]:($values[$n/2-1]+$values[$n/2])/2;}
        $row['siblings']=$siblings->fetchAll();$row['market_samples']=count($values);$row['median_unit_ecto']=$median;return $row;
    }

    /** @param array<string,mixed> $result */
    public function saveResult(int $validationId, array $result, string $model, ?string $responseId, string $rawJson): void
    {
        $decision=(string)($result['decision']??'review');$status=in_array($decision,['accept'],true)?'validated':(in_array($decision,['reject','correct'],true)?'disagreed':'review');
        $s=$this->pdo->prepare("UPDATE ai_offer_validations SET status=:status,decision=:decision,ai_confidence=:confidence,reason=:reason,correction_json=:correction,model=:model,response_id=:response_id,raw_json=:raw_json,checked_at=datetime('now'),updated_at=datetime('now'),last_error=NULL WHERE id=:id");
        $s->execute([':status'=>$status,':decision'=>$decision,':confidence'=>(float)($result['confidence']??0),':reason'=>(string)($result['reason']??''),':correction'=>json_encode($result['correction']??new \stdClass(),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),':model'=>$model,':response_id'=>$responseId,':raw_json'=>$rawJson,':id'=>$validationId]);
    }

    public function saveError(int $id, string $error): void
    {
        $s=$this->pdo->prepare("UPDATE ai_offer_validations SET status='error',last_error=?,updated_at=datetime('now') WHERE id=?");$s->execute([mb_substr($error,0,2000),$id]);
    }

    /** @return array<string,int> */
    public function summary(): array
    {
        $out=['queued'=>0,'validated'=>0,'disagreed'=>0,'review'=>0,'error'=>0,'skipped'=>0];
        foreach($this->pdo->query("SELECT status,COUNT(*) total FROM ai_offer_validations GROUP BY status")->fetchAll() as $r)$out[(string)$r['status']]=(int)$r['total'];
        return $out;
    }

    /** @param array<string,mixed> $row @param array<string,mixed> $market */
    private function queueRow(array $row,string $mode,array $market=[]): int
    {
        $assessor=$this->risk??new AiRiskAssessor();
        $risk=$assessor->assess($row,[
            'sibling_count'=>(int)$row['sibling_count'],
            'median_unit_ecto'=>$market['reference_unit_ecto']??null,
            'market_samples'=>(int)($market['samples']??0),
        ]);
        $wanted=$mode==='all'||$risk['risky'];$status=$wanted?'queued':'skipped';
        $s=$this->pdo->prepare("INSERT INTO ai_offer_validations(structured_offer_id,status,risk_score,risk_reasons_json,created_at,updated_at) VALUES(?,?,?,?,datetime('now'),datetime('now')) ON CONFLICT(structured_offer_id) DO UPDATE SET status=CASE WHEN ai_offer_validations.status IN ('validated','disagreed','review') THEN ai_offer_validations.status ELSE excluded.status END,risk_score=excluded.risk_score,risk_reasons_json=excluded.risk_reasons_json,updated_at=datetime('now')");
        $s->execute([(int)$row['id'],$status,(int)$risk['score'],json_encode($risk['reasons'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);return $wanted?1:0;
    }

    /** @return list<array<string,mixed>> */
    private function offerRows(string $where,array $params): array
    {
        $sql="SELECT so.*, (SELECT COUNT(*) FROM structured_offers sx WHERE sx.message_id=so.message_id) sibling_count FROM structured_offers so ".$where." ORDER BY so.id";
        $s=$this->pdo->prepare($sql);$s->execute($params);return $s->fetchAll();
    }

    /** @return array<string,array{samples:int,reference_unit_ecto:float}> */
    private function marketStats(): array
    {
        $rows=$this->pdo->query("SELECT COALESCE(NULLIF(normalized_market_key,''),market_key) market_key,COUNT(*) samples,AVG(unit_price_ecto) reference_unit_ecto FROM structured_offers WHERE quality_status='accepted' AND unit_price_ecto IS NOT NULL AND unit_price_ecto>0 GROUP BY COALESCE(NULLIF(normalized_market_key,''),market_key)")->fetchAll();
        $out=[];foreach($rows as $r)$out[(string)$r['market_key']]=['samples'=>(int)$r['samples'],'reference_unit_ecto'=>(float)$r['reference_unit_ecto']];return$out;
    }

}
