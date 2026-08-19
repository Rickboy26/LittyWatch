<?php
declare(strict_types=1);
namespace LittyWatch\Market;

final class Phase7E19ContextGuard
{
    public function __construct(private readonly \PDO $pdo) {}

    public function repair(array $row): array
    {
        $key=str_replace('_','-',mb_strtolower(trim((string)($row['item_key']??''))));
        $segment=trim((string)($row['raw_segment']??''));
        $message=(string)($row['_message']??'');

        if(preg_match('/\bEL\s+TONICS?\b/iu',$message)){
            $map=[
                'miniature-princess-salma'=>'Everlasting Princess Salma Tonic',
                'miniature-prince-rurik'=>'Everlasting Prince Rurik Tonic',
                'miniature-kuunavang'=>'Everlasting Kuunavang Tonic',
                'kuuna'=>'Everlasting Kuunavang Tonic',
            ];
            if(isset($map[$key])) return $this->acceptResolved($row,$map[$key]);
        }

        if(in_array($key,['miniature-kuunavang','kuuna'],true)&&preg_match('/\bEL\s+kuuna\b/iu',$message)){
            return $this->acceptResolved($row,'Everlasting Kuunavang Tonic');
        }

        if(str_starts_with($key,'armbracess')||preg_match('/^\s*armbracess?\b/iu',$segment)){
            return $this->acceptResolved($row,'Armbrace of Truth');
        }

        if($key==='naga-pelts'||str_starts_with($key,'naga-pelts-')){
            return $this->acceptResolved($row,'Naga Pelt');
        }

        if($key==='flame-balthaz'||preg_match('/\bflame\s+balthaz\b/iu',$segment)){
            return $this->acceptResolved($row,'Flame of Balthazar');
        }

        if($key==='drake-ka'||preg_match('/\bdrake\s+ka\b/iu',$segment)){
            return $this->acceptResolved($row,'Drake Kabob');
        }

        if($key==='80picks'||preg_match('/^\s*(\d+)\s*picks?\b/iu',$segment,$m)){
            if(isset($m[1])) $row['quantity']=(int)$m[1];
            return $this->acceptResolved($row,'Lockpick');
        }

        if($key==='rock-stack'||preg_match('/^\s*rock\s+stack\b/iu',$segment)){
            return $this->acceptByKey($row,'Rock Candy Stack','market-rock-candy-stack');
        }

        if($key==='gems'&&preg_match('/\bgems?\b.*\(\s*no\s+titans?\s*\)/iu',$segment)){
            return $this->reject($row,'strict_catalog_generic',0.30);
        }

        if($key==='japan-1st-anniversary-shield'&&preg_match('/^\s*japan\s*$/iu',$segment)) return $this->promote($row);
        if($key==='fiery-dragon-sword'&&preg_match('/^\s*FDS\b/iu',$segment)) return $this->promote($row);
        if($key==='bone'&&preg_match('/^\s*bones?(?:\s+and)?\s*$/iu',$segment)) return $this->promote($row);

        if($key==='staff-wrapping-of-energy-storage'&&preg_match('/^\s*wand\s+wrappings?\s*:?\s*$/iu',$segment)){
            return $this->reject($row,'strict_catalog_generic',0.20);
        }

        if($key==='staff-wrapping-of-the-ranger'&&preg_match('/\bbow\s+of\s+the\s+ranger\b/iu',$segment)){
            return $this->acceptResolved($row,'Bow Grip of the Ranger');
        }

        if($key==='aptitude-not-attitude'&&preg_match('/^\s*focus\s+aptitude\b/iu',$segment)) return $this->promote($row);

        if(preg_match('/\bLF\s+someone\s+with\s+proof\s+of\s+triumph\b/iu',$segment)
          ||preg_match('/\bsanctum\s+cay\s+run\b/iu',$segment)){
            return $this->reject($row,'service_or_noise',0.20);
        }

        if(preg_match('/^\s*(?:con\s+sets?|various\s+q9\s+inscribable\s+weapons|OS\s+Skins\s+r9)\b/iu',$segment)){
            return $this->reject($row,'collection_or_market_request',0.25);
        }

        if(preg_match('/^\s*(?:pmme|w\/sit|take\s+it\s+and\s+run)\s*$/iu',$segment)){
            return $this->reject($row,'service_or_noise',0.15);
        }

        return $row;
    }

    private function promote(array $row):array{
        $row['quality_status']='accepted';
        $row['quality_reason']='catalog_match';
        $row['confidence']=max((float)($row['confidence']??0),0.94);
        return $row;
    }

    private function reject(array $row,string $reason,float $cap):array{
        $row['quality_status']='rejected';
        $row['quality_reason']=$reason;
        $row['confidence']=min((float)($row['confidence']??0),$cap);
        return $row;
    }

    private function acceptResolved(array $row,string $name):array{
        return $this->acceptByKey($row,$name,$this->resolveKey($name));
    }

    private function acceptByKey(array $row,string $name,string $key):array{
        $row['item']=$name;
        $row['item_key']=$key;
        $row['market_key']=$key;
        $row['quality_status']='accepted';
        $row['quality_reason']='catalog_match';
        $row['confidence']=max((float)($row['confidence']??0),0.94);
        return $row;
    }

    private function resolveKey(string $name):string{
        $st=$this->pdo->prepare("SELECT key FROM kb_items WHERE active=1 AND lower(trim(name))=lower(trim(?)) LIMIT 1");
        $st->execute([$name]);
        $key=$st->fetchColumn();
        if($key!==false)return (string)$key;
        $norm=mb_strtolower(trim(preg_replace('/[^a-z0-9]+/u',' ',$name)??$name));
        $st=$this->pdo->prepare("SELECT item_key FROM kb_aliases WHERE normalized_alias=? LIMIT 1");
        $st->execute([$norm]);
        $key=$st->fetchColumn();
        if($key!==false)return (string)$key;
        return trim((string)preg_replace('/[^a-z0-9]+/','-',mb_strtolower($name)),'-');
    }
}
