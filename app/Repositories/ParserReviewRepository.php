<?php
declare(strict_types=1);
namespace LittyWatch\Repositories;
use PDO;
final class ParserReviewRepository{
 public function __construct(private readonly PDO$pdo,private readonly ParserKnowledgeRepository$knowledge){}
 public function summary():array{return$this->pdo->query("SELECT COUNT(*) total,SUM(review_status='pending') pending,SUM(review_status='approved') approved,SUM(review_status='rejected') rejected,SUM(review_status='corrected') corrected FROM parser_reviews")->fetch()?:[];}
 public function qualityStats():array{$r=$this->pdo->query("SELECT COUNT(*) total,SUM(parser_status='parsed') parsed,SUM(parser_status='review' OR parser_status IS NULL) review,SUM(parser_status='excluded') excluded FROM messages")->fetch()?:[];return array_map('intval',$r);}
 public function queue(string$status='pending',string$quality='',string$query='',int$limit=100):array{$where=[];$p=[];if(in_array($status,['pending','approved','rejected','corrected'],true)){$where[]='pr.review_status=:status';$p[':status']=$status;}if(in_array($quality,['accepted','review'],true)){$where[]='so.quality_status=:quality';$p[':quality']=$quality;}if($query!==''){$where[]='(so.item LIKE :q OR so.raw_segment LIKE :q OR m.player LIKE :q OR m.message LIKE :q OR so.market_key LIKE :q)';$p[':q']='%'.$query.'%';}$sql="SELECT pr.*,so.message_id,so.trade_type,so.item,so.market_key,so.requirement,so.attribute_name,so.mods_json,so.confidence,so.quality_status,so.quality_reason,so.raw_segment,so.price_amount,so.price_currency,m.player,m.message,m.posted_at,av.status ai_status,av.risk_score ai_risk_score,av.risk_reasons_json ai_risk_reasons_json,av.decision ai_decision,av.ai_confidence,av.reason ai_reason,av.correction_json ai_correction_json,av.model ai_model,av.checked_at ai_checked_at,av.last_error ai_last_error FROM parser_reviews pr JOIN structured_offers so ON so.id=pr.structured_offer_id JOIN messages m ON m.id=so.message_id LEFT JOIN ai_offer_validations av ON av.structured_offer_id=so.id".($where?' WHERE '.implode(' AND ',$where):'')." ORDER BY CASE pr.review_status WHEN 'pending' THEN 0 ELSE 1 END,so.confidence ASC,pr.id DESC LIMIT ".max(1,min(500,$limit));$s=$this->pdo->prepare($sql);$s->execute($p);return$s->fetchAll();}
 public function seedPending():int{return$this->pdo->exec("INSERT OR IGNORE INTO parser_reviews(structured_offer_id,review_status,created_at,updated_at) SELECT so.id,'pending',datetime('now'),datetime('now') FROM structured_offers so LEFT JOIN ai_offer_validations av ON av.structured_offer_id=so.id WHERE so.quality_status='review' OR so.confidence<0.85 OR av.status IN ('disagreed','review','error')");}
 public function save(int$id,string$status,string$expectedItem,?int$requirement,string$attribute,string$marketKey,string$notes,string$alias=''):void{if(!in_array($status,['pending','approved','rejected','corrected'],true))$status='pending';$expected=['item'=>trim($expectedItem),'requirement'=>$requirement,'attribute'=>trim($attribute),'market_key'=>trim($marketKey)];$s=$this->pdo->prepare("UPDATE parser_reviews SET review_status=:status,expected_json=:expected,notes=:notes,reviewed_at=CASE WHEN :status='pending' THEN NULL ELSE datetime('now') END,updated_at=datetime('now') WHERE id=:id");$s->execute([':status'=>$status,':expected'=>json_encode($expected,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),':notes'=>trim($notes),':id'=>$id]);$row=$this->pdo->prepare("SELECT pr.structured_offer_id,so.message_id,so.raw_segment FROM parser_reviews pr JOIN structured_offers so ON so.id=pr.structured_offer_id WHERE pr.id=?");$row->execute([$id]);$info=$row->fetch()?:[];if($status==='corrected'&&trim($alias)!==''&&trim($expectedItem)!=='')$this->knowledge->addAlias($alias,$expectedItem);if($status==='rejected'&&trim($notes)!=='')$this->knowledge->addExclusion($notes,'noise');$this->knowledge->log($id,(int)($info['message_id']??0),$status,$alias,$expectedItem,$notes);}
 public function export():array{return$this->pdo->query("SELECT pr.review_status,pr.expected_json,pr.notes,so.parser_version,so.trade_type,so.item,so.market_key,so.raw_segment,m.message,m.player FROM parser_reviews pr JOIN structured_offers so ON so.id=pr.structured_offer_id JOIN messages m ON m.id=so.message_id WHERE pr.review_status IN ('approved','rejected','corrected') ORDER BY pr.id")->fetchAll();}
 public function commonTerms():array{$rows=$this->pdo->query("SELECT m.message FROM parser_reviews pr JOIN structured_offers so ON so.id=pr.structured_offer_id JOIN messages m ON m.id=so.message_id WHERE pr.review_status='pending' ORDER BY pr.id DESC LIMIT 500")->fetchAll();$stop=['wts','wtb','wtt','pm','open','trade','each','stack','offers','offer'];$c=[];foreach($rows as$r){preg_match_all("/[A-Za-z][A-Za-z0-9'’+-]{2,}/u",mb_strtolower((string)$r['message']),$m);foreach($m[0]??[]as$t){if(in_array($t,$stop,true))continue;$c[$t]=($c[$t]??0)+1;}}arsort($c);$out=[];foreach(array_slice($c,0,20,true)as$t=>$n)$out[]=['term'=>$t,'count'=>$n];return$out;}
 public function knowledge():array{return['aliases'=>$this->knowledge->aliasRows(),'exclusions'=>$this->knowledge->exclusionRows(),'set_sizes'=>$this->knowledge->setSizeRows(),'corrections'=>$this->knowledge->corrections()];}
 public function knowledgeAction(string$action,array$data):void{match($action){'add_alias'=>$this->knowledge->addAlias((string)($data['alias']??''),(string)($data['item_name']??'')),'add_exclusion'=>$this->knowledge->addExclusion((string)($data['phrase']??''),(string)($data['kind']??'noise')),'add_set_size'=>$this->knowledge->addSetSize((string)($data['item_name']??''),(float)($data['set_size']??0)),'delete_alias'=>$this->knowledge->delete('parser_aliases',(int)($data['id']??0)),'delete_exclusion'=>$this->knowledge->delete('parser_exclusions',(int)($data['id']??0)),'delete_set_size'=>$this->knowledge->delete('parser_set_sizes',(int)($data['id']??0)),default=>null};}
    /** @return list<array{reason:string,total:int}> */
    public function reasonGroups(int $limit = 20): array
    {
        $statement = $this->pdo->prepare(
            "SELECT COALESCE(so.quality_reason,'unknown') reason, COUNT(*) total
             FROM parser_reviews pr
             JOIN structured_offers so ON so.id=pr.structured_offer_id
             WHERE pr.review_status='pending'
             GROUP BY COALESCE(so.quality_reason,'unknown')
             ORDER BY total DESC
             LIMIT ?"
        );
        $statement->bindValue(1, max(1,min(100,$limit)), PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

}
