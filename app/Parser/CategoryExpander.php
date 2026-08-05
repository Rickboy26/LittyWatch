<?php
declare(strict_types=1);
namespace LittyWatch\Parser;
use LittyWatch\Knowledge\KnowledgeBase;
final class CategoryExpander {
  public function __construct(private readonly KnowledgeBase $kb) {}
  /** @return list<array{item:string,key:string,category:string,start:int,length:int,alias:string,score:float}> */
  public function expand(string $text): array {
    $lower=mb_strtolower($text);$out=[];
    foreach($this->kb->groups() as $group){
      $matched=false;
      foreach(array_merge([$group['name']],$group['aliases']) as $alias){if(mb_stripos($lower,mb_strtolower($alias))!==false){$matched=true;break;}}
      if(!$matched)continue;
      $requested=$this->requestedTypes($lower);
      foreach($group['item_keys'] as $key){$name=$this->nameFor($key);if($name===null)continue;if($requested!==[]&&!$this->matchesRequested($name,$requested))continue;$out[]=['item'=>$name,'key'=>$key,'category'=>'group-expanded','start'=>0,'length'=>mb_strlen($text),'alias'=>$group['name'],'score'=>0.91];}
    }
    return $out;
  }
  private function requestedTypes(string $text): array {$types=[];foreach(['bow','sword','blade','staff','shield','axe','spear','wand','focus'] as $t)if(preg_match('/\b'.preg_quote($t,'/').'s?\b/i',$text))$types[]=$t;return array_values(array_unique($types));}
  private function matchesRequested(string $name,array $types): bool {$n=mb_strtolower($name);foreach($types as $t){if($t==='sword'&&str_contains($n,'blade'))return true;if(str_contains($n,$t))return true;}return false;}
  private function nameFor(string $key): ?string {foreach($this->kb->allItems() as $i)if($i['key']===$key)return $i['name'];return null;}
}
