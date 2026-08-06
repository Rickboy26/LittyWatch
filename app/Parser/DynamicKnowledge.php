<?php
declare(strict_types=1);
namespace LittyWatch\Parser;
use LittyWatch\Repositories\ParserKnowledgeRepository;
final class DynamicKnowledge{
 public function __construct(private readonly ParserKnowledgeRepository$repo){}
 public function aliases(string$text):string{$a=$this->repo->aliases();uksort($a,static fn($x,$y)=>mb_strlen($y)<=>mb_strlen($x));foreach($a as$alias=>$item)$text=preg_replace('/(?<![A-Za-z0-9])'.preg_quote($alias,'/').'(?![A-Za-z0-9])/iu',$item,$text)??$text;return$text;}
 public function exclusion(string$text):?array{$low=mb_strtolower($text);foreach($this->repo->exclusionRows()as$r){$p=mb_strtolower(trim((string)$r['phrase']));if($p!==''&&str_contains($low,$p))return['kind'=>(string)$r['kind'],'reason'=>'learned_exclusion'];}return null;}
 public function setSize(string$item):?float{$s=$this->repo->setSizes();return$s[mb_strtolower(trim($item))]??null;}
}
