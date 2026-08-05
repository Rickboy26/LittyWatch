<?php
declare(strict_types=1);
namespace LittyWatch\Knowledge;
use PDO;
final class JsonImporter {
  public function __construct(private readonly PDO $db) {}
  public function import(string $json,string $source='gwmarket-json'): array {
    Schema::install($this->db);$data=json_decode($json,true,flags:JSON_THROW_ON_ERROR);$rows=$this->findRows($data);$kb=new KnowledgeBase($this->db);$written=0;
    foreach($rows as $row){$name=$row['name']??$row['title']??$row['item_name']??null;if(!is_string($name)||trim($name)==='')continue;$id=(string)($row['id']??$row['_id']??'');$key=$this->slug($name).($id!==''?'-'.preg_replace('/[^a-z0-9]+/i','',$id):'');$category=(string)($row['category']??$row['type']??'unknown');$kb->upsertItem($key,trim($name),$this->slug($category),$source,$id,$row);$kb->addAlias($key,trim($name),$source);foreach(($row['aliases']??[]) as $a)if(is_string($a))$kb->addAlias($key,$a,$source);$written++;}
    $this->db->prepare('INSERT INTO kb_import_runs(source,status,items_seen,items_written,notes,created_at) VALUES(?,?,?,?,?,?)')->execute([$source,'ok',count($rows),$written,'Generic JSON import',gmdate('c')]);
    return ['seen'=>count($rows),'written'=>$written]+$kb->stats();
  }
  private function findRows(array $data): array {if(array_is_list($data))return $data;foreach(['items','data','results','catalog'] as $k)if(isset($data[$k])&&is_array($data[$k]))return $this->findRows($data[$k]);return [];}
  private function slug(string $v): string {$v=KnowledgeBase::normalize($v);return trim(str_replace(' ','-',$v),'-')?:'unknown';}
}
