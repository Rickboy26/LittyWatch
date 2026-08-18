<?php
declare(strict_types=1);
namespace LittyWatch\Market;

final class Phase7E16MarketSemanticGuard
{
    public function __construct(private readonly \PDO $pdo) {}

    public function repair(array $row): array
    {
        $key=str_replace('_','-',mb_strtolower(trim((string)($row['item_key']??''))));
        $segment=trim((string)($row['raw_segment']??''));
        $message=(string)($row['_message']??'');

        if(preg_match('/^\s*\/?\/?\/?\s*FMN(?:\s*\(\s*\d+\s*\))?\s*$/iu',$segment)) return $this->accept($row,'Forget Me Not!');
        if($key==='aptitude-not-attitude' && preg_match('/^\s*AnA\b/iu',$segment)){ $row['quality_status']='accepted';$row['quality_reason']='catalog_match';$row['confidence']=max((float)($row['confidence']??0),.95);return $row; }
        if($key==='japan') return $this->accept($row,'Japan 1st Anniversary Shield');
        if(in_array($key,['sp-5-sycthe','spawn-5-sycthe'],true)||preg_match('/\b(?:sp|spawn|spawning\s+power)\s*\+\s*5\b.*\b(?:scyt|syct|scythe)\b/iu',$segment)) return $this->accept($row,'Scythe Grip of the Ritualist');
        if($key==='cup'||preg_match('/^\s*cup\s*$/iu',$segment)) return $this->accept($row,'Cup of the Bison');
        if($key==='luna'||preg_match('/^\s*luna\b/iu',$segment)) return $this->accept($row,'Lunar Fortune');
        if(str_starts_with($key,'goot')||preg_match('/\bgoot\b/iu',$segment)) return $this->accept($row,'Gift of the Traveler');
        if($key==='stalk'||preg_match('/^\s*\d*\s*stalk\s*$/iu',$segment)) return $this->accept($row,"Stalker's Ration");
        if(preg_match('/\bWG\s+Longbow\b/iu',$segment.' '.$message)) return $this->accept($row,'Wintergreen Longbow');
        if(str_starts_with($key,'grail-stack')||preg_match('/\bgrail\s+stacks?\b/iu',$segment)) return $this->accept($row,'Grail of Holy Might');
        if($key==='saph'||preg_match('/^\s*saph\s*$/iu',$segment)) return $this->accept($row,'Sapphire');
        if($key==='stygian-gem'&&preg_match('/^\s*styg\s*$/iu',$segment)) return $this->accept($row,'Stygian Gemstone');
        if(str_starts_with($key,'e-blade')||preg_match('/^\s*E\s+Blade\b/iu',$segment)) return $this->accept($row,'Eternal Blade');
        if($key==='warsupplys'||preg_match('/^\s*war\s*suppl(?:y|ys|ies)\s*$/iu',$segment)) return $this->accept($row,'War Supplies');
        if(str_starts_with($key,'desroyer-core')||preg_match('/\bdesroyer\s+cores?\b/iu',$segment)) return $this->accept($row,'Destroyer Core');
        if($key==='ghostly-hero'&&(preg_match('/\bEL\s+Ghostly\s+Hero\b/iu',$message)||preg_match('/\beverlasting\s+ghostly\s+hero\b/iu',$segment))) return $this->accept($row,'Everlasting Ghostly Hero Tonic');

        if($key==='miniature-greased-lightning'&&preg_match('/\b(?:earth|lightning|blunt)\b/iu',$segment)&&!preg_match('/\bmini(?:ature|pet)?\b|\b(?:ded|unded)(?:icated)?\b/iu',$segment)){
            $row['quality_status']='rejected';$row['quality_reason']='modifier_fragment_unresolved';$row['confidence']=min((float)($row['confidence']??0),.25);return $row;
        }
        if($key==='blessing-of-war'&&preg_match('/^\s*(?:bow|axe|hammer|sword|dagger|wand|staff|spear|scythe)\s*[-–—]*\s*$/iu',$segment)){
            $row['quality_status']='rejected';$row['quality_reason']='modifier_fragment_unresolved';$row['confidence']=min((float)($row['confidence']??0),.25);return $row;
        }
        if($key==='soulbreaker'&&preg_match('/\bstaff\s+20%\s+enchan/i',$segment)){
            $row['quality_status']='rejected';$row['quality_reason']='modifier_fragment_unresolved';$row['confidence']=min((float)($row['confidence']??0),.25);return $row;
        }
        if(preg_match('/^\s*(?:at\s+least\s+check\s+spelling\s+and\s+grammar|what\s+was\s+that\s+jibberish)\s*[?.!]*\s*$/iu',$segment)){
            $row['quality_status']='rejected';$row['quality_reason']='service_or_noise';$row['confidence']=min((float)($row['confidence']??0),.20);return $row;
        }
        if(preg_match('/\bdecent\s+weapons?\s+for\s+my\s+heros?\b/iu',$segment)||preg_match('/\bgold\s+trim\s+guild\b/iu',$segment)){
            $row['quality_status']='rejected';$row['quality_reason']='collection_or_market_request';$row['confidence']=min((float)($row['confidence']??0),.25);return $row;
        }
        return $row;
    }

    private function accept(array $row,string $name):array
    {
        $key=$this->resolveKey($name);
        $row['item']=$name;$row['item_key']=$key;$row['market_key']=$key;
        $row['quality_status']='accepted';$row['quality_reason']='catalog_match';$row['confidence']=max((float)($row['confidence']??0),.94);
        return $row;
    }
    private function resolveKey(string $name):string
    {
        $st=$this->pdo->prepare("SELECT key FROM kb_items WHERE active=1 AND lower(trim(name))=lower(trim(?)) LIMIT 1");$st->execute([$name]);$k=$st->fetchColumn();if($k!==false)return(string)$k;
        $n=mb_strtolower(trim((string)preg_replace('/[^a-z0-9]+/u',' ',$name)));
        $st=$this->pdo->prepare("SELECT item_key FROM kb_aliases WHERE normalized_alias=? LIMIT 1");$st->execute([$n]);$k=$st->fetchColumn();if($k!==false)return(string)$k;
        return trim((string)preg_replace('/[^a-z0-9]+/','-',mb_strtolower($name)),'-');
    }
}
