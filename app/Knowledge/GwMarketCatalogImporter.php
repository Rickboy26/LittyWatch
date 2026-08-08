<?php
declare(strict_types=1);

namespace LittyWatch\Knowledge;

use PDO;
use RuntimeException;

final class GwMarketCatalogImporter
{
    public function __construct(private readonly PDO $db) {}

    /** @return array<string,int|string> */
    public function import(string $category, string $json): array
    {
        $category=$this->slug($category);
        if($category===''||strlen($category)>40) throw new RuntimeException('Ongeldige GW Market categorie.');
        $decoded=json_decode($json,true,512,JSON_THROW_ON_ERROR);
        if(!is_array($decoded)) throw new RuntimeException('Ongeldige GW Market JSON.');

        Schema::install($this->db);
        $kb=new KnowledgeBase($this->db);
        $rows=$this->flatten($decoded,$category);
        $written=0;$aliases=0;

        $this->db->beginTransaction();
        try{
            foreach($rows as $row){
                $name=trim((string)($row['name']??''));
                if($name==='') continue;
                $key=$this->slug($name);
                if($key==='') continue;

                $metadata=$row;
                $metadata['_littywatch_source']='gw-market-public-catalog';
                $metadata['_littywatch_category']=$category;
                $metadata['_littywatch_imported_at']=gmdate('c');

                $kb->upsertItem($key,$name,$category,'gw-market-catalog',null,$metadata);
                $kb->addAlias($key,$name,'gw-market-catalog');
                $aliases++;

                // Useful normalized aliases for parenthetical disambiguators used
                // in the public catalogue, e.g. "Deldrimor Axe (unique)".
                $plain=trim((string)preg_replace('/\s+\((?:unique|weapon|item)\)\s*$/iu','',$name));
                if($plain!==''&&strcasecmp($plain,$name)!==0){
                    $kb->addAlias($key,$plain,'gw-market-normalized');
                    $aliases++;
                }
                $written++;
            }

            $stmt=$this->db->prepare(
                'INSERT INTO kb_import_runs(source,status,items_seen,items_written,notes,created_at) VALUES(?,?,?,?,?,?)'
            );
            $stmt->execute([
                'gw-market/'.$category,'ok',count($rows),$written,
                'Public GW1 catalogue facts imported into LittyWatch knowledge base',gmdate('c')
            ]);
            $this->db->commit();
        }catch(\Throwable $e){
            if($this->db->inTransaction())$this->db->rollBack();
            throw $e;
        }

        return ['category'=>$category,'seen'=>count($rows),'written'=>$written,'aliases'=>$aliases];
    }

    /** @return list<array<string,mixed>> */
    private function flatten(array $node,string $category,?string $group=null): array
    {
        $out=[];
        if(array_is_list($node)){
            foreach($node as $entry){
                if(!is_array($entry))continue;
                // GW Market data commonly uses {type, items:[...]} groups.
                if(isset($entry['items'])&&is_array($entry['items'])){
                    $type=trim((string)($entry['type']??$group??$category));
                    foreach($this->flatten($entry['items'],$category,$type) as $row)$out[]=$row;
                    continue;
                }
                if(isset($entry['name'])&&is_string($entry['name'])){
                    if($group!==null&&$group!=='')$entry['_group']=$group;
                    $out[]=$entry;
                    continue;
                }
                foreach($entry as $value)if(is_array($value)){
                    foreach($this->flatten($value,$category,$group) as $row)$out[]=$row;
                }
            }
            return $out;
        }
        foreach($node as $value)if(is_array($value)){
            foreach($this->flatten($value,$category,$group) as $row)$out[]=$row;
        }
        return $out;
    }

    private function slug(string $value): string
    {
        $value=KnowledgeBase::normalize($value);
        return trim(str_replace(' ','-',$value),'-');
    }
}
