<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);
$files=[
 $root.'/app/Parser/MarketBundleExpander.php',
 $root.'/app/Market/ContextAwareCandidatePipeline.php',
 $root.'/app/Parser/SemanticNormalizer.php'
];
foreach($files as $f){if(!is_file($f)){fwrite(STDERR,"ERROR: ontbreekt: $f\n");exit(1);}}
$b=$root.'/storage/backups/phase7e5-'.date('Ymd-His');@mkdir($b,0775,true);
foreach($files as $f)copy($f,$b.'/'.basename($f));

$f=$files[0];$c=file_get_contents($f);
if(!str_contains($c,'LITTYWATCH_PHASE7E5_ALC_STACKS')){
 $a="        \$map = ['party'=>'Party Points','sweet'=>'Sweet Points','alc'=>'Alcohol Points','alcohol'=>'Alcohol Points'];";
 $p=strpos($c,$a);if($p===false){fwrite(STDERR,"ERROR: points-map anchor ontbreekt.\n");exit(1);}
 $i=<<<'PHP'
        // LITTYWATCH_PHASE7E5_ALC_STACKS
        if(preg_match('/\b(?:(\d+)\s+)?stacks?\s+of\s+alc(?:ohol)?\b/iu',$text,$m)){
            $stacks=isset($m[1])&&$m[1]!==''?max(1,(int)$m[1]):1;
            return [['text'=>'Alcohol Points','item'=>'Alcohol Points','quantity'=>$stacks*250]];
        }

PHP;
 $c=substr($c,0,$p).$i.substr($c,$p);file_put_contents($f,$c);
}

$f=$files[1];$c=file_get_contents($f);
if(!str_contains($c,'LITTYWATCH_PHASE7E5_UNDED_SLASH_LIVIA')){
 $a='        // Bare slash lists such as Zhed/Livia are treated as miniature lists only';
 $p=strpos($c,$a);if($p===false){fwrite(STDERR,"ERROR: slash-list anchor ontbreekt.\n");exit(1);}
 $i=<<<'PHP'
        // LITTYWATCH_PHASE7E5_UNDED_SLASH_LIVIA
        if(preg_match('/^\s*(unded(?:icated)?|ded(?:icated)?)\s*\/\s*livia\s*$/iu',$text,$m)){
            $state=str_starts_with(mb_strtolower((string)$m[1]),'unded')?'unded':'ded';
            return [['candidate'=>'Miniature Livia','context'=>$state.' Miniature Livia','source'=>$text]];
        }

PHP;
 $c=substr($c,0,$p).$i.substr($c,$p);file_put_contents($f,$c);
}

$f=$files[2];$c=file_get_contents($f);
if(!str_contains($c,'LITTYWATCH_PHASE7E5_GHOSTLY_HERO_STRONGBOX_GUARD')){
 $old="            'ghostly\\s+hero' => 'Miniature Ghostly Hero',";
 if(!str_contains($c,$old)){fwrite(STDERR,"ERROR: Ghostly Hero mapping anchor ontbreekt.\n");exit(1);}
 $new="            // LITTYWATCH_PHASE7E5_GHOSTLY_HERO_STRONGBOX_GUARD\n            'ghostly\\s+hero(?![\\'’]s?\\s+strongbox)' => 'Miniature Ghostly Hero',";
 $c=str_replace($old,$new,$c);
 $a='        // Strongbox community shorthand.';$p=strpos($c,$a);
 if($p===false){fwrite(STDERR,"ERROR: strongbox anchor ontbreekt.\n");exit(1);}
 $i=<<<'PHP'
        // LITTYWATCH_PHASE7E5_GHOSTLY_HERO_STRONGBOX_CANONICAL
        $text=preg_replace('/\bGhostly\s+Hero[\'’]s\s+Strongboxes?\b/iu',"Hero's Strongbox",$text)??$text;

PHP;
 $c=substr($c,0,$p).$i.substr($c,$p);file_put_contents($f,$c);
}

foreach($files as $f){
 $o=[];$rc=0;exec('/usr/bin/php -l '.escapeshellarg($f),$o,$rc);
 if($rc){foreach($files as $r){$bf=$b.'/'.basename($r);if(is_file($bf))copy($bf,$r);}
 fwrite(STDERR,"ERROR: syntax; rollback.\n".implode("\n",$o)."\n");exit(1);}
}
echo "OK: LittyWatch V5.2 Phase 7E.5 geïnstalleerd.\nBackup: $b\n";
