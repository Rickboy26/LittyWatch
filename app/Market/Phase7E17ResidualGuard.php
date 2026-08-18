<?php
declare(strict_types=1);
namespace LittyWatch\Market;
final class Phase7E17ResidualGuard
{
 public function __construct(private readonly \PDO $pdo) {}
 public function repair(array $row): array
 {
  $key=str_replace('_','-',mb_strtolower(trim((string)($row['item_key']??''))));
  $segment=trim((string)($row['raw_segment']??''));
  $message=(string)($row['_message']??'');
  if($key==='alc-250pt'||preg_match('/^\s*alc\s+(\d+)\s*pt\b/iu',$segment,$m)){if(isset($m[1]))$row['quantity']=(int)$m[1];return $this->accept($row,'Alcohol Points','market-points-alcohol');}
  if(str_starts_with($key,'goldies-')||$key==='unidents'||preg_match('/\bunid(?:ent(?:ified)?)?\s+gold(?:ies)?\b/iu',$segment))return $this->accept($row,'Unidentified Gold','unidentified-gold');
  if($key==='z-keys'||preg_match('/^\s*z[-\s]?keys?\b/iu',$segment))return $this->accept($row,'Zaishen Key','zaishen-key');
  if(preg_match('/^\s*(\d+)\s*gold\s+z\s*coins?\b/iu',$segment,$m)||str_contains($key,'gold-z-coins')){if(isset($m[1]))$row['quantity']=(int)$m[1];return $this->accept($row,'Gold Zaishen Coin','gold-zaishen-coin');}
  if($key==='meas4meas-500g'||preg_match('/\bmeas4meas\b/iu',$segment))return $this->acceptResolved($row,'Measure for Measure');
  if($key==='bird'||preg_match('/^\s*bird[\'’]?\s*$/iu',$segment))return $this->accept($row,'Birdseye','birdseye');
  if(str_starts_with($key,'el-transmogrifier')||preg_match('/\bEL\s+Transmogrifi\w*Tonic\b/iu',$segment))return $this->acceptResolved($row,'Everlasting Transmogrifier Tonic');
  if(str_starts_with($key,'gladboxes')||preg_match('/^\s*gladboxes?\b/iu',$segment))return $this->acceptResolved($row,"Gladiator's Zaishen Strongbox");
  if($key==='kebap'||$key==='lindworm-kebap'||preg_match('/\b(?:lindworm\s+)?kebap\b/iu',$segment))return $this->acceptResolved($row,'Drake Kabob');
  if($key==='salad'||preg_match('/^\s*salad\b/iu',$segment))return $this->acceptResolved($row,'Pahnai Salad');
  if(str_starts_with($key,'ghost-stuff')||preg_match('/\bghost\s+stuff\b/iu',$segment))return $this->acceptResolved($row,'Ghostly Staff');
  if($key==='seeds'&&preg_match('/\b(?:gott|gift\s+of\s+the\s+traveler)\b/iu',$message))return $this->accept($row,'Unnatural Seed','unnatural-seed');
  if($key==='eli'&&preg_match('/\belite\s+war(?:rior)?\s+tome\b/iu',$message))return $this->acceptResolved($row,'Elementalist Elite Tome');
  if($key==='longbow'&&preg_match('/\bWG\s+Longbow\b/iu',$message))return $this->acceptResolved($row,'Wintergreen Longbow');
  if($key==='longbow'&&preg_match('/\bUrgoz\s+Longbow\b/iu',$message))return $this->acceptResolved($row,"Urgoz's Longbow");
  if($key==='set-of'&&preg_match('/\bset\s+of\s+5\s+bowstrings\b/iu',$message)){ $row['quality_status']='rejected';$row['quality_reason']='strict_catalog_generic';$row['confidence']=min((float)($row['confidence']??0),0.20);return $row; }
  if(preg_match('/^\s*\{\{*\s*q9\s*$/iu',$segment)||preg_match('/\b400\s+gold\s+value\b/iu',$segment)){ $row['quality_status']='rejected';$row['quality_reason']='modifier_fragment_unresolved';$row['confidence']=min((float)($row['confidence']??0),0.20);return $row; }
  if(preg_match('/\bany\s+inscribable\s+channeling\b/iu',$segment)||preg_match('/\b40\/40\s+dom\s+sets?\b/iu',$segment)){ $row['quality_status']='rejected';$row['quality_reason']='collection_or_market_request';$row['confidence']=min((float)($row['confidence']??0),0.25);return $row; }
  if($key==='miniature-high-priest-zhang'&&(preg_match('/\bEL\s+PRIEST\s+OF\s+BALTH\b/iu',$segment)||preg_match('/\bEl\s+priest\b/iu',$segment)))return $this->acceptResolved($row,'Everlasting Priest of Balthazar Tonic');
  if($key==='miniature-celestial-dragon'&&preg_match('/\bdragon\s+roots?\b/iu',$segment))return $this->acceptResolved($row,'Dragon Root');
  if($key==='mini-undead-prince-rurik'&&preg_match('/\+\s*10\s*vs\s*undead\b/iu',$segment)){ $row['quality_status']='rejected';$row['quality_reason']='modifier_fragment_unresolved';$row['confidence']=min((float)($row['confidence']??0),0.20);return $row; }
  return $row;
 }
 private function accept(array $row,string $name,string $key):array{$row['item']=$name;$row['item_key']=$key;$row['market_key']=$key;$row['quality_status']='accepted';$row['quality_reason']='catalog_match';$row['confidence']=max((float)($row['confidence']??0),0.94);return $row;}
 private function acceptResolved(array $row,string $name):array{return $this->accept($row,$name,$this->resolveKey($name));}
 private function resolveKey(string $name):string{$st=$this->pdo->prepare("SELECT key FROM kb_items WHERE active=1 AND lower(trim(name))=lower(trim(?)) LIMIT 1");$st->execute([$name]);$k=$st->fetchColumn();if($k!==false)return(string)$k;$norm=mb_strtolower(trim(preg_replace('/[^a-z0-9]+/u',' ',$name)??$name));$st=$this->pdo->prepare("SELECT item_key FROM kb_aliases WHERE normalized_alias=? LIMIT 1");$st->execute([$norm]);$k=$st->fetchColumn();if($k!==false)return(string)$k;return trim((string)preg_replace('/[^a-z0-9]+/','-',mb_strtolower($name)),'-');}
}
