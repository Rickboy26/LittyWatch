<?php
declare(strict_types=1);
namespace LittyWatch\Knowledge;
final class GwMarketDiscovery {
  public function inspect(string $url='https://gwmarket.net/'): array {
    $html=$this->fetch($url); $scripts=[];
    if(preg_match_all('/<script[^>]+src=["\']([^"\']+)["\']/i',$html,$m)){
      foreach($m[1] as $src)$scripts[]=$this->absolute($url,$src);
    }
    $candidates=[];
    foreach(array_slice(array_unique($scripts),0,20) as $script){
      try{$body=$this->fetch($script);foreach($this->endpointCandidates($body) as $c)$candidates[]=$c;}catch(\Throwable){}
    }
    return ['url'=>$url,'html_bytes'=>strlen($html),'scripts'=>array_values(array_unique($scripts)),'endpoint_candidates'=>array_values(array_unique($candidates))];
  }
  private function fetch(string $url): string {
    $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>25,CURLOPT_USERAGENT=>'LittyWatch/0.7 (+https://hollandseglory.nl)']);
    $body=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_error($ch);curl_close($ch);
    if(!is_string($body)||$code>=400)throw new \RuntimeException("Fetch failed ($code): $err");return $body;
  }
  private function absolute(string $base,string $src): string {if(preg_match('#^https?://#i',$src))return $src;$p=parse_url($base);return ($p['scheme']??'https').'://'.($p['host']??'gwmarket.net').'/'.ltrim($src,'/');}
  private function endpointCandidates(string $js): array {
    $out=[];preg_match_all('#(?:https?:)?//[^"\'\s]+|/[A-Za-z0-9_./-]*(?:api|items|market|catalog|graphql)[A-Za-z0-9_?&=./-]*#i',$js,$m);
    foreach($m[0]??[] as $v){$v=rtrim($v,'),;]}');if(strlen($v)<220)$out[]=$v;}return $out;
  }
}
