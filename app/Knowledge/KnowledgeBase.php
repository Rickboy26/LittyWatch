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
  public function profiles(): array {
    $rows=$this->db->query('SELECT * FROM kb_profiles ORDER BY name')->fetchAll();
    foreach($rows as &$r){$r['track']=json_decode($r['track_json'],true)?:[];$r['ignore']=json_decode($r['ignore_json'],true)?:[];$r['market_key']=json_decode($r['market_key_json'],true)?:[];}
    return $rows;
  }
  public function attributes(): array {
    $rows=$this->db->query('SELECT * FROM kb_attributes ORDER BY profession,name')->fetchAll();
    foreach($rows as &$r)$r['aliases']=json_decode($r['aliases_json'],true)?:[];
    return $rows;
  }
  public function matchAttribute(string $value): ?array {
    $needle=self::normalize($value); if($needle==='')return null;
    foreach($this->attributes() as $attribute){
      $aliases=array_merge([$attribute['key'],$attribute['name']],$attribute['aliases']);
      foreach($aliases as $alias){if(self::normalize((string)$alias)===$needle)return $attribute;}
    }
    return null;
  }
  public function profileForItem(string $itemKey,string $categoryKey='unknown'): array {
    $s=$this->db->prepare('SELECT p.* FROM kb_item_profiles ip JOIN kb_profiles p ON p.key=ip.profile_key WHERE ip.item_key=? LIMIT 1');$s->execute([$itemKey]);$row=$s->fetch();
    if(!$row){$s=$this->db->prepare('SELECT p.* FROM kb_category_profiles cp JOIN kb_profiles p ON p.key=cp.profile_key WHERE cp.category_key=? LIMIT 1');$s->execute([$categoryKey]);$row=$s->fetch();}
    if(!$row){$s=$this->db->prepare('SELECT * FROM kb_profiles WHERE key=? LIMIT 1');$s->execute(['generic']);$row=$s->fetch();}
    if(!$row)return ['key'=>'generic','name'=>'Generic','description'=>'','track'=>[],'ignore'=>[],'market_key'=>['item']];
    $row['track']=json_decode($row['track_json'],true)?:[];$row['ignore']=json_decode($row['ignore_json'],true)?:[];$row['market_key']=json_decode($row['market_key_json'],true)?:[];
    return $row;
  }
  public function profileAssignments(): array {
    return $this->db->query('SELECT i.name AS item_name,i.category_key,ip.item_key,ip.profile_key,p.name AS profile_name FROM kb_item_profiles ip JOIN kb_items i ON i.key=ip.item_key JOIN kb_profiles p ON p.key=ip.profile_key ORDER BY p.name,i.name')->fetchAll();
  }
  public function stats(): array {
    return [
      'items'=>(int)$this->db->query('SELECT COUNT(*) FROM kb_items')->fetchColumn(),
      'aliases'=>(int)$this->db->query('SELECT COUNT(*) FROM kb_aliases')->fetchColumn(),
      'groups'=>(int)$this->db->query('SELECT COUNT(*) FROM kb_groups')->fetchColumn(),
      'attributes'=>(int)$this->db->query('SELECT COUNT(*) FROM kb_attributes')->fetchColumn(),
      'profiles'=>(int)$this->db->query('SELECT COUNT(*) FROM kb_profiles')->fetchColumn(),
      'profile_assignments'=>(int)$this->db->query('SELECT COUNT(*) FROM kb_item_profiles')->fetchColumn()
    ];
  }
}
