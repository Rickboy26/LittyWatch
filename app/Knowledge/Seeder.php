<?php
declare(strict_types=1);
namespace LittyWatch\Knowledge;
use PDO;
final class Seeder {
  public function __construct(private readonly PDO $db,private readonly string $itemsJson,private readonly ?string $attributesJson=null,private readonly ?string $profilesJson=null) {}
  public function run(): array {
    Schema::install($this->db); $kb=new KnowledgeBase($this->db);
    $items=json_decode((string)file_get_contents($this->itemsJson),true,flags:JSON_THROW_ON_ERROR);
    $written=0;
    foreach($items as $i){$kb->upsertItem($i['key'],$i['name'],$i['category']??'unknown','parser-v2-seed');foreach($i['aliases']??[] as $a)$kb->addAlias($i['key'],$a,'parser-v2-seed');$written++;}
    $extra=[
      ['eternal-staff','Eternal Staff','weapon',['eternal staff','eternal staves']],
      ['eternal-sword','Eternal Blade','weapon',['eternal sword','eternal swords']],
      ['bronze-guardian','Bronze Guardian','shield',['bronze guardian']],
      ['crude-shield','Crude Shield','shield',['crude shield']],
      ['zodiac-staff','Zodiac Staff','staff',['zodiac staff']],
      ['ghostly-staff','Ghostly Staff','staff',['ghostly staff']],
    ];
    foreach($extra as [$k,$n,$c,$aliases]){$kb->upsertItem($k,$n,$c,'knowledge-seed');foreach($aliases as $a)$kb->addAlias($k,$a,'knowledge-seed');}
    $stmt=$this->db->prepare('INSERT INTO kb_groups(key,name,aliases_json,item_keys_json,source) VALUES(?,?,?,?,?) ON CONFLICT(key) DO UPDATE SET name=excluded.name,aliases_json=excluded.aliases_json,item_keys_json=excluded.item_keys_json');
    $stmt->execute(['eternal-skins','Eternal skins',json_encode(['eternal skins','eternal skin','eternal weapons']),json_encode(['eternal-bow','eternal-sword','eternal-staff','eternal-shield','chaos-axe']),'knowledge-seed']);
    $this->seedAttributes();
    $this->seedProfiles();
    $this->db->prepare('INSERT INTO kb_import_runs(source,status,items_seen,items_written,notes,created_at) VALUES(?,?,?,?,?,?)')->execute(['local-seed','ok',count($items),$written+count($extra),'Seeded item catalog, attributes and item profiles',gmdate('c')]);
    return $kb->stats();
  }
  private function seedAttributes(): void {
    if(!$this->attributesJson||!is_file($this->attributesJson))return;
    $rows=json_decode((string)file_get_contents($this->attributesJson),true,flags:JSON_THROW_ON_ERROR);
    $s=$this->db->prepare('INSERT INTO kb_attributes(key,name,profession,aliases_json) VALUES(?,?,?,?) ON CONFLICT(key) DO UPDATE SET name=excluded.name,profession=excluded.profession,aliases_json=excluded.aliases_json');
    foreach($rows as $row)$s->execute([$row['key'],$row['name'],$row['profession']??null,json_encode($row['aliases']??[],JSON_UNESCAPED_UNICODE)]);
  }
  private function seedProfiles(): void {
    if(!$this->profilesJson||!is_file($this->profilesJson))return;
    $data=json_decode((string)file_get_contents($this->profilesJson),true,flags:JSON_THROW_ON_ERROR);
    $s=$this->db->prepare('INSERT INTO kb_profiles(key,name,description,track_json,ignore_json,market_key_json) VALUES(?,?,?,?,?,?) ON CONFLICT(key) DO UPDATE SET name=excluded.name,description=excluded.description,track_json=excluded.track_json,ignore_json=excluded.ignore_json,market_key_json=excluded.market_key_json');
    foreach($data['profiles']??[] as $row)$s->execute([$row['key'],$row['name'],$row['description']??'',json_encode($row['track']??[]),json_encode($row['ignore']??[]),json_encode($row['market_key']??[])]);
    $ip=$this->db->prepare('INSERT INTO kb_item_profiles(item_key,profile_key,source) VALUES(?,?,?) ON CONFLICT(item_key) DO UPDATE SET profile_key=excluded.profile_key,source=excluded.source');
    foreach($data['item_profiles']??[] as $item=>$profile)$ip->execute([$item,$profile,'profile-seed']);
    $cp=$this->db->prepare('INSERT INTO kb_category_profiles(category_key,profile_key,source) VALUES(?,?,?) ON CONFLICT(category_key) DO UPDATE SET profile_key=excluded.profile_key,source=excluded.source');
    foreach($data['category_profiles']??[] as $category=>$profile)$cp->execute([$category,$profile,'profile-seed']);
  }
}
