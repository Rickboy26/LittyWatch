<?php
declare(strict_types=1);
namespace LittyWatch\Market;

use PDO;

final class StrictCatalogGate
{
    public function __construct(private readonly PDO $pdo) {}

    /** @return array{allowed:bool,reason:string,canonical_name:?string,canonical_key:?string} */
    public function inspect(string $item,string $itemKey): array
    {
        $name=CanonicalMarketIdentity::nameFor(trim($item),trim($itemKey));$key=trim($itemKey);
        if($name===''||$this->looksGeneric($name)||CanonicalMarketIdentity::isWikiDisambiguator($name)){
            return ['allowed'=>false,'reason'=>'strict_catalog_generic','canonical_name'=>null,'canonical_key'=>null];
        }

        // Exact active KB key/name is authoritative.
        $st=$this->pdo->prepare("SELECT key,name FROM kb_items WHERE active=1 AND (key=:key OR lower(trim(name))=lower(trim(:name))) ORDER BY CASE WHEN key=:key2 THEN 0 ELSE 1 END LIMIT 1");
        $st->execute([':key'=>$key,':key2'=>$key,':name'=>$name]);
        $row=$st->fetch();
        if($row){
            $canonical=CanonicalMarketIdentity::nameFor((string)$row['name'],(string)$row['key']);
            if($this->looksGeneric($canonical))return ['allowed'=>false,'reason'=>'strict_catalog_placeholder','canonical_name'=>null,'canonical_key'=>null];
            return ['allowed'=>true,'reason'=>'strict_catalog_exact','canonical_name'=>$canonical,'canonical_key'=>(string)$row['key']];
        }

        // Exact alias is allowed only when it resolves to one active concrete item.
        $norm=\LittyWatch\Knowledge\KnowledgeBase::normalize($name);
        $st=$this->pdo->prepare("SELECT i.key,i.name FROM kb_aliases a JOIN kb_items i ON i.key=a.item_key WHERE i.active=1 AND a.normalized_alias=:alias GROUP BY i.key,i.name LIMIT 2");
        $st->execute([':alias'=>$norm]);$rows=$st->fetchAll();
        if(count($rows)===1&&!$this->looksGeneric((string)$rows[0]['name'])){
            $canonical=CanonicalMarketIdentity::nameFor((string)$rows[0]['name'],(string)$rows[0]['key']);
            if(CanonicalMarketIdentity::isWikiDisambiguator($canonical))return ['allowed'=>false,'reason'=>'strict_catalog_placeholder','canonical_name'=>null,'canonical_key'=>null];
            return ['allowed'=>true,'reason'=>'strict_catalog_alias','canonical_name'=>$canonical,'canonical_key'=>(string)$rows[0]['key']];
        }
        return ['allowed'=>false,'reason'=>count($rows)>1?'strict_catalog_ambiguous':'strict_catalog_missing','canonical_name'=>null,'canonical_key'=>null];
    }

    /** @return array<string,int> */
    public function quarantineExisting(): array
    {
        $rows=$this->pdo->query("SELECT id,item,item_key FROM structured_offers WHERE quality_status='accepted' AND COALESCE(lifecycle_status,'active')='active'")->fetchAll();
        $reject=$this->pdo->prepare("UPDATE structured_offers SET quality_status='review',quality_reason=:reason,lifecycle_status='rejected',lifecycle_updated_at=:ts WHERE id=:id");
        $canonical=$this->pdo->prepare("UPDATE structured_offers SET item=:item,item_key=:item_key WHERE id=:id");
        $checked=0;$quarantined=0;$normalized=0;
        $this->pdo->beginTransaction();
        try{
            foreach($rows as$row){
                $checked++;$result=$this->inspect((string)$row['item'],(string)$row['item_key']);
                if(!$result['allowed']){
                    $reject->execute([':reason'=>$result['reason'],':ts'=>date(DATE_ATOM),':id'=>$row['id']]);$quarantined++;continue;
                }
                if($result['canonical_name']!==$row['item']||$result['canonical_key']!==$row['item_key']){
                    $canonical->execute([':item'=>$result['canonical_name'],':item_key'=>$result['canonical_key'],':id'=>$row['id']]);$normalized++;
                }
            }
            $this->pdo->commit();
        }catch(\Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;}
        return ['checked'=>$checked,'quarantined'=>$quarantined,'normalized'=>$normalized];
    }

    private function looksGeneric(string $name): bool
    {
        $n=mb_strtolower(trim($name));
        if($n==='')return true;
        if(str_contains($n,'replace'))return true;
        if(preg_match('/^(?:miniature|miniatures|weapon|weapons|upgrade|upgrades|tome|tomes|material|materials|consumable|consumables|unique|uniques|item|items|elite tome|elite tomes|wand|staff|bow|shield|tonic|focus|focus item|spear|sword|axe|hammer|scythe|dagger|daggers|offhand|off hand)$/u',$n))return true;
        if(preg_match('/^any\s+(?:rare\s+)?(?:flatbow|hornbow|longbow|recurvebow|shortbow|bow|weapon|shield|staff|wand|focus)s?$/u',$n))return true;
        if(preg_match('/^(?:rare|random|any)\s+(?:item|items|weapon|weapons|miniature|miniatures)$/u',$n))return true;
        return false;
    }
}
