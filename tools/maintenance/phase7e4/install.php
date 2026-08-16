<?php
declare(strict_types=1);
$root=dirname(__DIR__,3); $f=$root.'/app/Parser/Catalog.php';
if(!is_file($f)){fwrite(STDERR,"ERROR: Catalog.php ontbreekt.\n");exit(1);}
$b=$root.'/storage/backups/phase7e4-'.date('Ymd-His'); @mkdir($b,0775,true); copy($f,$b.'/Catalog.php');
$c=file_get_contents($f);
if(str_contains($c,'LITTYWATCH_PHASE7E4_RESIDUAL_SHORTHAND')){echo "Phase 7E.4 staat al geïnstalleerd.\n";exit;}
$n='private array $learnedAliases = ['; $p=strpos($c,$n);
if($p===false){fwrite(STDERR,"ERROR: learnedAliases anchor niet gevonden.\n");exit(1);}
$p=strpos($c,'[',$p)+1;
$a=<<<'PHP'

        // LITTYWATCH_PHASE7E4_RESIDUAL_SHORTHAND
        'battle iced tea' => ['tea','teas','battle tea','iced tea'],
        'party beacon' => ['beacon','beacons','party beacons'],
        'frostfire fang' => ['frostfire fang','frostfire fangs','frostfire f'],
        'margonite gemstone' => ['margo','margos','margonite gem','margonite gems'],
        'wintersday grab bag' => ['wd grab bag','wd grab bags','wintersday grab bags'],
        'little john' => ['little john'],

PHP;
$c=substr($c,0,$p).$a.substr($c,$p); file_put_contents($f,$c);
exec('/usr/bin/php -l '.escapeshellarg($f),$o,$rc);
if($rc){copy($b.'/Catalog.php',$f);fwrite(STDERR,"ERROR: syntaxfout; rollback.\n".implode("\n",$o)."\n");exit(1);}
echo "OK: LittyWatch V5.2 Phase 7E.4 geïnstalleerd.\nBackup: $b\n";
echo "GHOTI en Celestal blijven bewust unresolved.\n";
echo "ALC wordt nog niet blind gemapt: betekenis is bekend (250 alcohol points/stack), canonical item nog niet.\n";
