<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);
$files=[
 $root.'/app/Parser/MarketBundleExpander.php',
 $root.'/app/Parser/ContextualSegmentExpander.php'
];
foreach($files as $f){if(!is_file($f)){fwrite(STDERR,"ERROR: ontbreekt: $f\n");exit(1);}}
$backup=$root.'/storage/backups/phase7e3-'.date('Ymd-His');
@mkdir($backup,0775,true);
foreach($files as $f)copy($f,$backup.'/'.basename($f));

/* MarketBundleExpander */
$f=$files[0]; $c=file_get_contents($f);
if(!str_contains($c,'LITTYWATCH_PHASE7E3_MINIATURE_LIST_CONTEXT_RECOVERY')){
 $anchor="        'forest griffon'=>'Miniature Forest Griffon',";
 if(!str_contains($c,$anchor)){fwrite(STDERR,"ERROR: alias anchor ontbreekt.\n");exit(1);}
 $extra=<<<'PHP'
        // LITTYWATCH_PHASE7E3_MINIATURE_LIST_CONTEXT_RECOVERY
        'ghost of althea'=>'Miniature Ghost of Althea',
        'althea'=>'Miniature Ghost of Althea',
        'destroyer'=>'Miniature Destroyer of Flesh',
        'dagnar'=>'Miniature Dagnar Stonepate',
        'black beast of aaaargh'=>'Miniature Black Beast of Aaaaarrrrrrggghhh',
        'black beast'=>'Miniature Black Beast of Aaaaarrrrrrggghhh',
        'prince rurik'=>'Miniature Prince Rurik',
        'candysmith marley'=>'Miniature Candysmith Marley',
        'freezie'=>'Miniature Freezie',
        'zhang'=>'Miniature Zhang',
        'moa chick'=>'Miniature Moa Chick',
        'wfr beetle'=>'Miniature World-Famous Racing Beetle',
        'world-famous racing beetle'=>'Miniature World-Famous Racing Beetle',
        'varesh'=>'Miniature Varesh',
PHP;
 $c=str_replace($anchor,$anchor."\n".$extra,$c);
 file_put_contents($f,$c);
}

/* ContextualSegmentExpander: preserve explicit miniature state headers */
$f=$files[1]; $c=file_get_contents($f);
if(!str_contains($c,'LITTYWATCH_PHASE7E3_HEADER_PROPAGATION')){
 $anchor='            $miniatureHeader = $this->miniatureHeader($segment);';
 if(!str_contains($c,$anchor)){fwrite(STDERR,"ERROR: context anchor ontbreekt.\n");exit(1);}
 $inject=<<<'PHP'
            // LITTYWATCH_PHASE7E3_HEADER_PROPAGATION
            if (preg_match('/^(unded(?:icated)?|ded(?:icated)?)\s+minis?(?:atures?)?\s*:?\s*$/iu', trim($segment), $m7e3)) {
                $activeMiniatureState = str_starts_with(mb_strtolower((string)$m7e3[1]), 'unded') ? 'unded' : 'ded';
                $activeFamily = 'Miniature';
                continue;
            }
            if (preg_match('/^(?:unded(?:icated)?|ded(?:icated)?)\s+(?:b-?day|birthday|white)\s+minis?(?:atures?)?\s*$/iu', trim($segment))) {
                $activeMiniatureState = null;
                $activeFamily = 'Miniature';
                continue;
            }

PHP;
 $c=str_replace($anchor,$inject.$anchor,$c);
 file_put_contents($f,$c);
}

foreach($files as $f){
 exec('/usr/bin/php -l '.escapeshellarg($f),$out,$rc);
 if($rc!==0){
  foreach($files as $r){$b=$backup.'/'.basename($r);if(is_file($b))copy($b,$r);}
  fwrite(STDERR,"ERROR: syntaxfout; backup teruggezet.\n".implode("\n",$out)."\n");exit(1);
 }
}
echo "OK: LittyWatch V5.2 Phase 7E.3 geïnstalleerd.\n";
echo "Backup: $backup\n";
echo "Miniature aliases + Unded/Ded Minis header propagation actief.\n";
