<?php
declare(strict_types=1);
namespace LittyWatch\Knowledge;
use PDO;
final class KnowledgeBase {
  public function __construct(private readonly PDO $db) {}
  public static function normalize(string $value): string {
    $value=mb_strtolower(trim(str_replace(['’','_'],["'",' '],$value)));
    return trim(preg_replace('/[^\p{L}\p{N}]+/u',' ',$value)??'');
  }
  public function upsertItem(string $key,string $name,string $category='unknown',string $source='local',?string $sourceId=null,array $metadata=[]): void {
    $s=$this->db->prepare('INSERT INTO kb_items(key,name,category_key,source,source_id,metadata_json,updated_at) VALUES(?,?,?,?,?,?,?) ON CONFLICT(key) DO UPDATE SET name=excluded.name,category_key=excluded.category_key,source=excluded.source,source_id=COALESCE(excluded.source_id,kb_items.source_id),metadata_json=excluded.metadata_json,updated_at=excluded.updated_at');
    $s->execute([$key,$name,$category,$source,$sourceId,json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),gmdate('c')]);
  }
  public function addAlias(string $itemKey,string $alias,string $source='local'): void {
    $n=self::normalize($alias); if($n==='')return;
    $s=$this->db->prepare('INSERT OR IGNORE INTO kb_aliases(item_key,alias,normalized_alias,source) VALUES(?,?,?,?)');
    $s->execute([$itemKey,$alias,$n,$source]);
  }
  public function allItems(): array {
    $rows=$this->db->query('SELECT key,name,category_key FROM kb_items WHERE active=1 ORDER BY name')->fetchAll();
    $a=$this->db->query('SELECT item_key,alias FROM kb_aliases ORDER BY LENGTH(alias) DESC')->fetchAll();
    $by=[]; foreach($a as $r)$by[$r['item_key']][]=$r['alias'];
    foreach($rows as &$r)$r['aliases']=array_values(array_unique(array_merge([$r['name']],$by[$r['key']]??[])));
    return $rows;
  }
  public function groups(): array {
    $rows=$this->db->query('SELECT * FROM kb_groups ORDER BY name')->fetchAll();
    foreach($rows as &$r){$r['aliases']=json_decode($r['aliases_json'],true)?:[];$r['item_keys']=json_decode($r['item_keys_json'],true)?:[];}
    return $rows;
  }
  public function stats(): array {
    return ['items'=>(int)$this->db->query('SELECT COUNT(*) FROM kb_items')->fetchColumn(),'aliases'=>(int)$this->db->query('SELECT COUNT(*) FROM kb_aliases')->fetchColumn(),'groups'=>(int)$this->db->query('SELECT COUNT(*) FROM kb_groups')->fetchColumn()];
  }
}
