<?php
declare(strict_types=1);
namespace LittyWatch\Repositories; use PDO;
final class StructuredOfferRepository{
 public function __construct(private readonly PDO $pdo){}
 public function summary():array{$a=$this->pdo->query("SELECT COUNT(*) total,SUM(quality_status='accepted') accepted,SUM(quality_status='review') review,COUNT(DISTINCT market_key) markets FROM structured_offers")->fetch()?:[];$b=$this->pdo->query("SELECT COUNT(DISTINCT message_id) parsed_messages FROM structured_offers")->fetch()?:[];return array_merge($a,$b);}
 public function latest(int $limit=100):array{$limit=max(1,min(500,$limit));return$this->pdo->query("SELECT so.*,m.player,m.message,m.posted_at FROM structured_offers so JOIN messages m ON m.id=so.message_id ORDER BY so.id DESC LIMIT $limit")->fetchAll();}
 public function markets(int $limit=100):array{$limit=max(1,min(500,$limit));return$this->pdo->query("SELECT market_key,item,requirement,attribute_name,is_oldschool,is_inscribable,COUNT(*) samples,SUM(trade_type='buy') buys,SUM(trade_type='sell') sells FROM structured_offers WHERE quality_status='accepted' GROUP BY market_key ORDER BY samples DESC LIMIT $limit")->fetchAll();}
 public function comparison(int $limit=100):array{$limit=max(1,min(500,$limit));$sql="SELECT m.id,m.player,m.posted_at,m.message,COUNT(DISTINCT o.id) legacy_count,COUNT(DISTINCT so.id) v2_count,GROUP_CONCAT(DISTINCT o.item) legacy_items,GROUP_CONCAT(DISTINCT so.item || CASE WHEN so.requirement IS NOT NULL THEN ' q'||so.requirement ELSE '' END || CASE WHEN so.attribute_name IS NOT NULL THEN ' '||so.attribute_name ELSE '' END) v2_items FROM messages m LEFT JOIN offers o ON o.message_id=m.id LEFT JOIN structured_offers so ON so.message_id=m.id GROUP BY m.id HAVING legacy_count!=v2_count OR COALESCE(legacy_items,'')!=COALESCE(v2_items,'') ORDER BY m.id DESC LIMIT $limit";return$this->pdo->query($sql)->fetchAll();}
}
