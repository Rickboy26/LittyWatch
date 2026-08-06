<?php
declare(strict_types=1);
namespace LittyWatch\Repositories;
use PDO;
final class ParserReviewRepository {
 public function __construct(private readonly PDO $pdo){}
 public function summary():array{return $this->pdo->query("SELECT COUNT(*) total,SUM(review_status='pending') pending,SUM(review_status='approved') approved,SUM(review_status='rejected') rejected,SUM(review_status='corrected') corrected FROM parser_reviews")->fetch()?:[];}
 public function queue(string $status='pending',string $quality='',string $query='',int $limit=100):array{
  $where=[];$p=[];
  if(in_array($status,['pending','approved','rejected','corrected'],true)){$where[]='pr.review_status=:status';$p[':status']=$status;}
  if(in_array($quality,['accepted','review'],true)){$where[]='so.quality_status=:quality';$p[':quality']=$quality;}
  if($query!==''){$where[]='(so.item LIKE :q OR so.raw_segment LIKE :q OR m.player LIKE :q OR m.message LIKE :q OR so.market_key LIKE :q)';$p[':q']='%'.$query.'%';}
  $sql="SELECT pr.*,so.trade_type,so.item,so.market_key,so.requirement,so.attribute_name,so.is_oldschool,so.is_inscribable,so.mods_json,so.relevant_json,so.profile_json,so.confidence,so.quality_status,so.quality_reason,so.raw_segment,so.price_amount,so.price_currency,m.player,m.message,m.posted_at FROM parser_reviews pr JOIN structured_offers so ON so.id=pr.structured_offer_id JOIN messages m ON m.id=so.message_id".($where?' WHERE '.implode(' AND ',$where):'')." ORDER BY CASE pr.review_status WHEN 'pending' THEN 0 ELSE 1 END,so.confidence ASC,pr.id DESC LIMIT ".max(1,min(500,$limit));
  $s=$this->pdo->prepare($sql);$s->execute($p);return$s->fetchAll();
 }
 public function seedPending():int{$sql="INSERT OR IGNORE INTO parser_reviews(structured_offer_id,review_status,created_at,updated_at) SELECT id,'pending',datetime('now'),datetime('now') FROM structured_offers WHERE quality_status='review' OR confidence<0.85";return$this->pdo->exec($sql);}
 public function save(int $id,string $status,string $expectedItem,?int $requirement,string $attribute,string $marketKey,string $notes):void{
  if(!in_array($status,['pending','approved','rejected','corrected'],true))$status='pending';
  $expected=['item'=>trim($expectedItem),'requirement'=>$requirement,'attribute'=>trim($attribute),'market_key'=>trim($marketKey)];
  $s=$this->pdo->prepare("UPDATE parser_reviews SET review_status=:status,expected_json=:expected,notes=:notes,reviewed_at=CASE WHEN :status='pending' THEN NULL ELSE datetime('now') END,updated_at=datetime('now') WHERE id=:id");
  $s->execute([':status'=>$status,':expected'=>json_encode($expected,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),':notes'=>trim($notes),':id'=>$id]);
 }
 public function export():array{return$this->pdo->query("SELECT pr.review_status,pr.expected_json,pr.notes,so.parser_version,so.trade_type,so.item,so.market_key,so.requirement,so.attribute_name,so.mods_json,so.raw_segment,m.message,m.player FROM parser_reviews pr JOIN structured_offers so ON so.id=pr.structured_offer_id JOIN messages m ON m.id=so.message_id WHERE pr.review_status IN ('approved','rejected','corrected') ORDER BY pr.id")->fetchAll();}
}
