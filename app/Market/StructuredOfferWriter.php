<?php
declare(strict_types=1);
namespace LittyWatch\Market;
use LittyWatch\Parser\ParsedOffer; use LittyWatch\Parser\ParserEngine; use PDO;
final class StructuredOfferWriter {
 public function __construct(private readonly PDO $pdo,private readonly ParserEngine $parser,private readonly ?VariantNormalizer $normalizer=null,private readonly ?OfferLifecycleService $lifecycle=null){}
 public function parseMessage(int $messageId,string $message,bool $replace=true):int{
  if($replace)$this->pdo->prepare('DELETE FROM structured_offers WHERE message_id=?')->execute([$messageId]);
  $sql="INSERT OR IGNORE INTO structured_offers(message_id,trade_type,item,item_key,market_key,normalized_market_key,requirement,attribute_key,attribute_name,is_oldschool,is_inscribable,mods_json,relevant_json,profile_json,quantity,price_amount,price_currency,price_ecto,unit_price_ecto,price_basis,confidence,quality_status,quality_reason,raw_segment,parser_version,parsed_at,lifecycle_status,lifecycle_updated_at,exchange_item,exchange_item_key,exchange_give_quantity,exchange_receive_quantity) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
  $ins=$this->pdo->prepare($sql);$n=0;$itemKeys=[];
  foreach($this->parser->parse($message) as $offer){$mapped=$this->map($offer);
  $resolved=(new CatalogFirstResolver($this->pdo))->resolve($mapped,$message);
  if($resolved===[]){
    $noise=(new NoiseFragmentGate())->inspect((string)$mapped['item'],(string)$mapped['raw_segment']);
    if($noise['drop'])continue;
    // LITTYWATCH_PHASE4E_INSUFFICIENT_IDENTITY
    // LITTYWATCH_PHASE4G_RESIDUAL_CLASSIFIER
    $mapped['quality_status']='review';

    $__lwItem=mb_strtolower(trim((string)($mapped['item']??'')));
    $__lwSegment=mb_strtolower(trim((string)($mapped['raw_segment']??$mapped['segment']??$mapped['item']??'')));
    $__lwText=trim($__lwItem.' '.$__lwSegment);

    $__lwInsufficient=(bool)preg_match(
        '/^(?:axe|axes|shield|shields|staff|staves|scythe|scythes|sword|swords|hammer|hammers|spear|spears|wand|wands|dagger|daggers|focus|focus item|bow|bows|flatbow|flatbows|hornbow|hornbows|longbow|longbows|recurve bow|recurvebow|shortbow|shortbows|elite tome|elite tomes|normal tome|normal tomes)$/u',
        $__lwItem
    );

    $__lwCollection = !$__lwInsufficient && (
        preg_match('/\b(?:q|req)\s*[0-9]+(?:\s*\/\s*(?:q|req)?\s*[0-9]+)?\s+(?:bows?|weapons?|items?|shields?|staves?|staffs?)\b/iu',$__lwText)
        || preg_match('/\b(?:gold|white|purple|green)\s+(?:items?|weapons?|minis?|miniatures?)\b/iu',$__lwText)
        || preg_match('/\b(?:os|old\s*school|pre[- ]?nerf|prenerf)\b.*\b(?:items?|mods?|weapons?|gold)\b/iu',$__lwText)
        || preg_match('/\b(?:all|any|many|package|collection)\s+(?:tomes?|minis?|miniatures?|tonics?|weapons?|items?)\b/iu',$__lwText)
        || preg_match('/\b(?:white\s+minis?|el\s+tonics?|minipet\s+package|gold\s+value\s+q[0-9]+)\b/iu',$__lwText)
        || preg_match('/\b(?:large\s+or\s+medium)\s+(?:eqbag|equipment\s+pack)\b/iu',$__lwText)
    );

    $__lwServiceNoise = !$__lwInsufficient && !$__lwCollection && (
        preg_match('/\b(?:running|run)\s+[a-z0-9 .\'-]+\s*(?:->|to)\s*[a-z0-9 .\'-]+/iu',$__lwText)
        || preg_match('/\b(?:trade\s+me|wsp\s+me|whisper\s+me|pm\s+me)\s*@?\s*(?:chest|here)?\b/iu',$__lwText)
        || preg_match('/\b(?:snowman\s+summoners?|runner|running\s+service|service|taxi|ferry)\b/iu',$__lwText)
        // LITTYWATCH_PHASE4H_CLASSIFIER_FIX: removed hard-coded item names from service/noise.
);

    // LITTYWATCH_PHASE4H_CLASSIFIER_FIX
    $__lwModifierFragment = !$__lwInsufficient && !$__lwCollection && !$__lwServiceNoise && (
        preg_match('/\b(?:[345]\s*(?:sr|soul\s*reaping|leadership|energy\s*storage)\s+for\s+(?:bow|staff|scepter|wand)|sr\s*\+\s*[345]\s+for\s+(?:bow|staff|scepter|wand))\b/iu',$__lwText)
        ||         preg_match('/^(?:\+?\s*30\s*hp|45\s*hp\s+w\s+ench|each\s*:\s*\+?\s*10\s+armor\s+vs|armor\s+\+?\s*[0-9]+\s+vs|40\/40\s+[a-z ]+\s+set)$/iu',$__lwItem)
        || (
            preg_match('/\b(?:staffhead|bowgrip|inscription)\b/iu',$__lwText)
            && !preg_match('/\b(?:of\s+the|of\s+fortitude|of\s+enchanting|of\s+shelter|of\s+warding)\b/iu',$__lwText)
        )
    );

    if ($__lwInsufficient) {
        $mapped['quality_reason']='insufficient_item_identity';
    } elseif ($__lwCollection) {
        $mapped['quality_reason']='collection_or_market_request';
    } elseif ($__lwServiceNoise) {
        $mapped['quality_reason']='service_or_noise';
    } elseif ($__lwModifierFragment) {
        $mapped['quality_reason']='modifier_fragment_unresolved';
    } else {
        $mapped['quality_reason']='catalog_first_unresolved';
    }

    $resolved=[$mapped];
  }
  foreach($resolved as $r){
   if($r['quality_status']==='accepted'){
    $gate=(new StrictCatalogGate($this->pdo))->inspect((string)$r['item'],(string)$r['item_key']);
    if(!$gate['allowed']){$r['quality_status']='review';$r['quality_reason']=$gate['reason'];}
    else{
     $r['item']=$gate['canonical_name'];$r['item_key']=$gate['canonical_key'];$r['market_key']=$gate['canonical_key'];
     // LITTYWATCH_PHASE7D2_CANONICAL_MARKET_IDENTITY
     // CatalogFirstResolver/StrictCatalogGate may replace a generic parser key
     // (for example elite_tome) with a concrete catalog item. Rebuild the
     // normalized market identity from that final canonical item so different
     // concrete items never collapse into the same market bucket.
     $normalizer=$this->normalizer??new VariantNormalizer();
     $relevant=$this->decodeJsonArray($r['relevant_json']??null);
     $profile=$this->decodeJsonArray($r['profile_json']??null);
     $normalized=$normalizer->normalize(
      (string)$r['item_key'],
      $r['requirement']===null?null:(int)$r['requirement'],
      isset($r['attribute_key'])?(string)$r['attribute_key']:null,
      isset($r['attribute_name'])?(string)$r['attribute_name']:null,
      ((int)($r['is_oldschool']??0))===1,
      ((int)($r['is_inscribable']??0))===1,
      $relevant,
      $profile
     );
     $r['item_key']=$normalized['item_key'];
     $r['normalized_market_key']=$normalized['market_key'];
     $variantGate=(new VariantValidityGate())->inspect($r);
     if(!$variantGate['allowed']){$r['quality_status']='rejected';$r['quality_reason']=$variantGate['reason'];}
    }
   }
   $itemKeys[]=$r['item_key'];$ins->execute([$messageId,$r['trade_type'],$r['item'],$r['item_key'],$r['market_key'],$r['normalized_market_key'],$r['requirement'],$r['attribute_key'],$r['attribute_name'],$r['is_oldschool'],$r['is_inscribable'],$r['mods_json'],$r['relevant_json'],$r['profile_json'],$r['quantity'],$r['price_amount'],$r['price_currency'],$r['price_ecto'],$r['unit_price_ecto'],$r['price_basis'],$r['confidence'],$r['quality_status'],$r['quality_reason'],$r['raw_segment'],'v5.2-phase7d2-canonical-live-dedup',date(DATE_ATOM),$r['quality_status']==='accepted'?'active':'rejected',date(DATE_ATOM),$r['exchange_item'],$r['exchange_item_key'],$r['exchange_give_quantity'],$r['exchange_receive_quantity']]);$n+=$ins->rowCount();}}
  if($this->lifecycle!==null){$this->lifecycle->rebuild($messageId);(new MarketQualityService($this->pdo))->rebuildForItemKeys($itemKeys);}
  return $n;
 }
 private function map(ParsedOffer $o):array{$p=$o->relevantProperties;$m=$o->modifiers;$req=$this->requirement($p['requirement']??$m['requirement']??null);$an=$p['attribute']??$m['attribute']??null;$ak=$p['attribute_key']??($an?$this->key((string)$an):null);$os=$this->truthy($p['oldschool']??$m['oldschool']??false);$insc=$this->truthy($p['inscribable']??$m['inscribable']??false);$profile=$o->profile;$normalizer=$this->normalizer??new VariantNormalizer();$normalized=$normalizer->normalize($o->itemKey,$req,$ak,$an,$os,$insc,$p,$profile);return['trade_type'=>$o->tradeType,'item'=>$o->item,'item_key'=>$normalized['item_key'],'market_key'=>$o->marketKey!==''?$o->marketKey:$o->itemKey,'normalized_market_key'=>$normalized['market_key'],'requirement'=>$req,'attribute_key'=>$normalized['attribute_key'],'attribute_name'=>$an,'is_oldschool'=>$os?1:0,'is_inscribable'=>$insc?1:0,'mods_json'=>$this->json($m),'relevant_json'=>$this->json($p),'profile_json'=>$this->json($profile),'quantity'=>$o->price->quantity,'price_amount'=>$o->price->amount,'price_currency'=>$o->price->currency,'price_ecto'=>$o->price->ectoValue,'unit_price_ecto'=>$o->price->unitEcto,'price_basis'=>$o->price->basis,'confidence'=>$o->confidence,'quality_status'=>$o->status,'quality_reason'=>$o->reason,'raw_segment'=>$o->segment,'exchange_item'=>$o->exchange['target_item']??null,'exchange_item_key'=>$o->exchange['target_item_key']??null,'exchange_give_quantity'=>$o->exchange['give_quantity']??null,'exchange_receive_quantity'=>$o->exchange['receive_quantity']??null];}
 private function requirement(mixed $v):?int{if(is_int($v))return$v;if(is_float($v))return(int)$v;if(is_string($v)&&preg_match('/(?:q|r|req)?\s*([0-9]{1,2})/i',$v,$m))return(int)$m[1];return null;}
 private function truthy(mixed $v):bool{if(is_bool($v))return$v;if(is_numeric($v))return(int)$v===1;return in_array(mb_strtolower((string)$v),['1','true','yes','ja','os','insc','inscribable'],true);}
 private function key(string $v):string{return trim(preg_replace('/[^a-z0-9]+/','_',mb_strtolower($v))??'');}
 private function json(array $v):string{return json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'{}';}
 private function decodeJsonArray(mixed $v):array{if(is_array($v))return$v;if(!is_string($v)||trim($v)==='')return[];$d=json_decode($v,true);return is_array($d)?$d:[];}
}
