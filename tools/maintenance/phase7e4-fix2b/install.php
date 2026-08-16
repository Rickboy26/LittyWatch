<?php
declare(strict_types=1);
$root=dirname(__DIR__,3);
$f=$root.'/app/Parser/ParserEngine.php';
if(!is_file($f)){fwrite(STDERR,"ERROR: ParserEngine.php ontbreekt.\n");exit(1);}
$c=file_get_contents($f);
if(str_contains($c,'LITTYWATCH_PHASE7E4_FIX2B_MARGO_PRE_GUARD')){echo "FIX2b staat al geïnstalleerd.\n";exit;}
$b=$root.'/storage/backups/phase7e4-fix2b-'.date('Ymd-His');@mkdir($b,0775,true);copy($f,$b.'/ParserEngine.php');
$a="            if (\$offer->reason !== 'catalog_match' || \$offer->confidence >= 0.85) return \$offer;";
$p=strpos($c,$a);
if($p===false){fwrite(STDERR,"ERROR: guard-anchor niet gevonden.\n");exit(1);}
$i=<<<'PHP'
            // LITTYWATCH_PHASE7E4_FIX2B_MARGO_PRE_GUARD
            $margoItem=mb_strtolower(trim($offer->item));
            $margoContext=mb_strtolower(trim($offer->segment.' '.$fullMessage));
            if($margoItem==='margonite gemstone'
                && preg_match('/(?:^|[\s|,;])margos?(?:$|[\s|,;0-9])/iu',$margoContext)
                && !preg_match('/\bel\s+margo\b/iu',$margoContext)){
                return new ParsedOffer(
                    $offer->tradeType,$offer->item,$offer->itemKey,$offer->modifiers,$offer->price,
                    max(0.90,$offer->confidence),'accepted','catalog_match',$offer->segment,
                    $offer->tokens,$offer->profile,$offer->relevantProperties,$offer->marketKey,$offer->exchange
                );
            }

PHP;
$c=substr($c,0,$p).$i.substr($c,$p);file_put_contents($f,$c);
exec('/usr/bin/php -l '.escapeshellarg($f),$o,$rc);
if($rc){copy($b.'/ParserEngine.php',$f);fwrite(STDERR,"ERROR: syntax; rollback.\n".implode("\n",$o)."\n");exit(1);}
echo "OK: Phase 7E.4 FIX2b geïnstalleerd.\nBackup: $b\n";
