<?php
declare(strict_types=1);
namespace LittyWatch\Knowledge;
use PDO;
final class Seeder {
  public function __construct(private readonly PDO $db,private readonly string $itemsJson) {}
  public function run(): array {
    Schema::install($this->db); $kb=new KnowledgeBase($this->db);
    $items=json_decode((string)file_get_contents($this->itemsJson),true,flags:JSON_THROW_ON_ERROR);
    $written=0;
    foreach($items as $i){$kb->upsertItem($i['key'],$i['name'],$i['category']??'unknown','parser-v2-seed');foreach($i['aliases']??[] as $a)$kb->addAlias($i['key'],$a,'parser-v2-seed');$written++;}
    $extra=[
      ['eternal-staff','Eternal Staff','weapon',['eternal staff','eternal staves']],
      ['eternal-sword','Eternal Blade','weapon',['eternal sword','eternal swords']],
    ];
    foreach($extra as [$k,$n,$c,$aliases]){$kb->upsertItem($k,$n,$c,'knowledge-seed');foreach($aliases as $a)$kb->addAlias($k,$a,'knowledge-seed');}
    $stmt=$this->db->prepare('INSERT INTO kb_groups(key,name,aliases_json,item_keys_json,source) VALUES(?,?,?,?,?) ON CONFLICT(key) DO UPDATE SET name=excluded.name,aliases_json=excluded.aliases_json,item_keys_json=excluded.item_keys_json');
    $stmt->execute(['eternal-skins','Eternal skins',json_encode(['eternal skins','eternal skin','eternal weapons']),json_encode(['eternal-bow','eternal-sword','eternal-staff','eternal-shield','chaos-axe']),'knowledge-seed']);
    $this->db->prepare('INSERT INTO kb_import_runs(source,status,items_seen,items_written,notes,created_at) VALUES(?,?,?,?,?,?)')->execute(['local-seed','ok',count($items),$written+count($extra),'Seeded from parser v2 catalog',gmdate('c')]);
    return $kb->stats();
  }
}
