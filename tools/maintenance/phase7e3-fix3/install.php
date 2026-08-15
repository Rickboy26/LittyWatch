<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);
$files=[
 $root.'/app/Parser/ParserEngine.php',
 $root.'/app/Market/StructuredOfferWriter.php'
];
foreach($files as $f){if(!is_file($f)){fwrite(STDERR,"ERROR: ontbreekt: $f\n");exit(1);}}
$backup=$root.'/storage/backups/phase7e3-fix3-'.date('Ymd-His');
@mkdir($backup,0775,true);
foreach($files as $f)copy($f,$backup.'/'.basename($f));

$f=$files[0]; $c=file_get_contents($f);
if(!str_contains($c,'LITTYWATCH_PHASE7E3_FIX3_MINIATURE_STATE_CARRY')){
 $anchor='$results = $this->propagateMiniatureHeaderDedication($results, $normalized);';
 if(!str_contains($c,$anchor)){fwrite(STDERR,"ERROR: parser pipeline anchor ontbreekt.\n");exit(1);}
 $c=str_replace($anchor,$anchor."\n        // LITTYWATCH_PHASE7E3_FIX3_MINIATURE_STATE_CARRY\n        \$results = \$this->carryMiniatureStateAcrossList(\$results, \$normalized);",$c);

 $methodAnchor='    /** @param list<ParsedOffer> $offers @return list<ParsedOffer> */'."\n".'    private function restoreMiniatureDedication';
 $pos=strpos($c,$methodAnchor);
 if($pos===false){fwrite(STDERR,"ERROR: parser method anchor ontbreekt.\n");exit(1);}
 $method=<<<'PHP'
    /** @param list<ParsedOffer> $offers @return list<ParsedOffer> */
    private function carryMiniatureStateAcrossList(array $offers,string $source):array
    {
        $states=[];
        if(preg_match_all('/\b(unded(?:icated)?|ded(?:icated)?)\b/iu',$source,$m)){
            foreach($m[1] as $token){
                $states[]=str_starts_with(mb_strtolower((string)$token),'unded')?'undedicated':'dedicated';
            }
        }
        $states=array_values(array_unique($states));
        if(count($states)!==1)return $offers;

        $dedication=$states[0];
        $hasMiniContext=
            preg_match('/\b(?:mini|mins?|minis?|miniatures?)\b/iu',$source)===1
            || preg_match('/\b(?:ghost of althea|althea|zhang|moa chick|wfr beetle|prince rurik|undead prince|dagnar|black beast|lich|varesh|destroyer|kuuna|rift warden|candysmith marley|freezie)\b/iu',$source)===1;
        if(!$hasMiniContext)return $offers;

        $out=[];
        foreach($offers as $offer){
            $segment=mb_strtolower(trim($offer->segment));
            $item=mb_strtolower(trim($offer->item));

            if($item==='miniature' && preg_match('/^(?:unded(?:icated)?|ded(?:icated)?)(?:\/)?(?:\s+\d+(?:\.\d+)?\s*(?:a|e|k))?$/iu',$segment)){
                continue;
            }

            if(!$this->isMiniatureOffer($offer)){
                $out[]=$offer; continue;
            }

            $existing=$offer->modifiers['dedication']??$offer->relevantProperties['dedication']??null;
            if(in_array($existing,['dedicated','undedicated'],true)){
                $out[]=$offer; continue;
            }

            $modifiers=$offer->modifiers;
            $modifiers['dedication']=$dedication;
            $relevant=$offer->relevantProperties;
            $relevant['dedication']=$dedication;

            $marketKey=preg_replace('/\|dedication:[^|]+/iu','',$offer->marketKey)??$offer->marketKey;
            if($marketKey==='')$marketKey=$offer->itemKey;
            $marketKey.='|dedication:'.$dedication;

            $status=$offer->status;
            $reason=$offer->reason;
            $confidence=$offer->confidence;
            if($reason==='miniature_variant_unresolved'){
                $status='accepted';
                $reason='catalog_match';
                $confidence=max(0.90,(float)$confidence);
            }

            $out[]=new ParsedOffer(
                $offer->tradeType,$offer->item,$offer->itemKey,$modifiers,$offer->price,
                $confidence,$status,$reason,$offer->segment,$offer->tokens,$offer->profile,
                $relevant,$marketKey,$offer->exchange
            );
        }
        return $out;
    }

PHP;
 $c=substr($c,0,$pos).$method.substr($c,$pos);
 file_put_contents($f,$c);
}

$f=$files[1]; $c=file_get_contents($f);
if(!str_contains($c,'LITTYWATCH_PHASE7E3_FIX3_ORPHAN_MINIATURE_HEADER_CLEANUP')){
 $anchor='foreach($resolved as $r){';
 $pos=strpos($c,$anchor);
 if($pos===false){fwrite(STDERR,"ERROR: writer loop anchor ontbreekt.\n");exit(1);}
 $insert=$pos+strlen($anchor);
 $inject=<<<'PHP'

    // LITTYWATCH_PHASE7E3_FIX3_ORPHAN_MINIATURE_HEADER_CLEANUP
    $__lwMiniItem=mb_strtolower(trim((string)($r['item']??'')));
    $__lwMiniSeg=mb_strtolower(trim((string)($r['raw_segment']??'')));
    if($__lwMiniItem==='miniature'
      && preg_match('/^(?:unded(?:icated)?|ded(?:icated)?)(?:\/)?(?:\s+\d+(?:\.\d+)?\s*(?:a|e|k))?$/iu',$__lwMiniSeg)){
        continue;
    }
PHP;
 $c=substr($c,0,$insert).$inject.substr($c,$insert);
 file_put_contents($f,$c);
}

foreach($files as $f){
 $o=[];$rc=0; exec('/usr/bin/php -l '.escapeshellarg($f),$o,$rc);
 if($rc!==0){
  foreach($files as $r){$b=$backup.'/'.basename($r);if(is_file($b))copy($b,$r);}
  fwrite(STDERR,"ERROR: syntaxfout; FIX3 teruggedraaid.\n".implode("\n",$o)."\n");exit(1);
 }
}
echo "OK: LittyWatch V5.2 Phase 7E.3 FIX3 geïnstalleerd.\n";
echo "Backup: $backup\n";
