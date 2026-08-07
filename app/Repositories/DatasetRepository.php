<?php
declare(strict_types=1);
namespace LittyWatch\Repositories;
use PDO;
final class DatasetRepository {
 public function __construct(private readonly PDO $pdo) {}
 public function summary(): array {
  $row=$this->pdo->query("SELECT COUNT(*) messages,COUNT(DISTINCT player) players,MIN(posted_at) first_seen,MAX(posted_at) last_seen,SUM(CASE WHEN parser_status='parsed' THEN 1 ELSE 0 END) parsed,SUM(CASE WHEN parser_status='review' OR parser_status IS NULL THEN 1 ELSE 0 END) review,SUM(CASE WHEN parser_status='excluded' THEN 1 ELSE 0 END) excluded FROM messages")->fetch()?:[];
  $offers=(int)$this->pdo->query('SELECT COUNT(*) FROM structured_offers')->fetchColumn();
  $unique=(int)$this->pdo->query("SELECT COUNT(*) FROM (SELECT 1 FROM messages GROUP BY lower(trim(player)),lower(trim(message)))")->fetchColumn();
  $messages=(int)($row['messages']??0); $parsed=(int)($row['parsed']??0);
  return ['messages'=>$messages,'unique_texts'=>$unique,'players'=>(int)($row['players']??0),'first_seen'=>$row['first_seen']??null,'last_seen'=>$row['last_seen']??null,'parsed'=>$parsed,'review'=>(int)($row['review']??0),'excluded'=>(int)($row['excluded']??0),'structured_offers'=>$offers,'coverage'=>$messages?round($parsed/$messages*100,1):0.0];
 }
 public function patterns(int $limit=25): array {
  $limit=max(1,min(100,$limit));
  return $this->pdo->query("SELECT message,COUNT(*) occurrences,COUNT(DISTINCT player) players,MIN(posted_at) first_seen,MAX(posted_at) last_seen FROM messages GROUP BY lower(trim(message)) ORDER BY occurrences DESC,last_seen DESC LIMIT $limit")->fetchAll();
 }
 public function reviewReasons(int $limit=20): array {
  $limit=max(1,min(100,$limit));
  return $this->pdo->query("SELECT COALESCE(NULLIF(trim(quality_reason),''),'(geen reden)') reason,COUNT(*) total FROM structured_offers WHERE quality_status='review' GROUP BY reason ORDER BY total DESC LIMIT $limit")->fetchAll();
 }
 public function exportRows(): array {
  return $this->pdo->query("SELECT m.id,m.source,m.source_key,m.player,m.message,m.posted_at,m.collected_at,m.parser_status,m.parser_summary,m.parser_offer_count,so.id offer_id,so.trade_type,so.item,so.item_key,so.market_key,so.quantity,so.price_amount,so.price_currency,so.price_ecto,so.unit_price_ecto,so.price_basis,so.confidence,so.quality_status,so.quality_reason,so.price_quality_status,so.price_quality_reason,so.raw_segment,so.parser_version FROM messages m LEFT JOIN structured_offers so ON so.message_id=m.id ORDER BY m.id,so.id")->fetchAll();
 }
}
